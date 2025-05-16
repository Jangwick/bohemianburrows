<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Initialize variables to prevent undefined variable warnings
$order_success = false;
$order_error = '';
$errors = [];

// Check if shipping columns exist 
$checkColumns = $conn->query("SHOW COLUMNS FROM sales LIKE 'shipping_address'");
$shippingColumnsExist = $checkColumns->num_rows > 0;

// If shipping columns don't exist, show a warning to the admin
if (!$shippingColumnsExist && $_SESSION['role'] == 'admin') {
    $setup_warning = "Your database needs to be updated to support shipping information. Please run <a href='../update_db.php'><strong>update_db.php</strong></a> to fix this issue.";
}

// Redirect if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// Get user information
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Calculate totals
$subtotal = 0;
$discount = 0;
$shipping = 0; // Can implement shipping calculation based on location and weight
$total = 0;

foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['subtotal'];
}

// Apply shipping fee if applicable
if ($subtotal < 1000) {
    $shipping = 100; // Example: ₱100 shipping fee for orders under ₱1000
}

$total = $subtotal - $discount + $shipping;

// Process checkout
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $customer_name    = trim($_POST['name'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $shipping_address = trim($_POST['address'] ?? '');
    $shipping_city    = trim($_POST['city'] ?? '');
    $shipping_postal  = trim($_POST['postal_code'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $payment_method   = trim($_POST['payment_method'] ?? 'cod'); // Ensures 'cod' is the default
    $notes            = trim($_POST['notes'] ?? '');
    
    // Validation
    $errors = [];
    if(empty($customer_name)) $errors[] = "Full name is required";
    if(empty($phone)) $errors[] = "Phone number is required";
    if(empty($shipping_address)) $errors[] = "Shipping address is required";
    if(empty($shipping_city)) $errors[] = "City is required";
    if(empty($shipping_postal)) $errors[] = "Postal code is required";
    
    // Check if cart is still valid
    if(empty($_SESSION['cart'])) {
        $errors[] = "Your cart is empty. Please add products before checking out.";
    }

    // If no errors, proceed with checkout
    if(empty($errors)) {
        // Begin transaction to ensure data integrity
        $conn->begin_transaction();
        
        try {
            // Generate invoice number
            $inv_query = $conn->query("SELECT IFNULL(MAX(CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED)), 0) + 1 AS next_number FROM sales WHERE invoice_number LIKE 'INV-%'");
            $next_number = $inv_query->fetch_assoc()['next_number'];
            $invoice_number = 'INV-' . str_pad($next_number, 6, '0', STR_PAD_LEFT);
            
            // Default status for online orders is "pending"
            $payment_status = 'pending';
            
            // Prepare statement based on database structure
            // First check if the email column exists
            $email_exists = $conn->query("SHOW COLUMNS FROM sales LIKE 'email'")->num_rows > 0;
            $notes_exists = $conn->query("SHOW COLUMNS FROM sales LIKE 'notes'")->num_rows > 0;
            
            if($email_exists && $notes_exists) {
                $stmt = $conn->prepare("INSERT INTO sales (
                    invoice_number, user_id, customer_name, payment_method, 
                    total_amount, discount, shipping_address, shipping_city, 
                    shipping_postal, phone, email, payment_status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("sissddsssssss", 
                    $invoice_number, $_SESSION['user_id'], $customer_name, $payment_method, 
                    $total, $discount, $shipping_address, $shipping_city, 
                    $shipping_postal, $phone, $email, $payment_status, $notes);
            } else {
                $stmt = $conn->prepare("INSERT INTO sales (
                    invoice_number, user_id, customer_name, payment_method, 
                    total_amount, discount, shipping_address, shipping_city, 
                    shipping_postal, phone, payment_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("sissddsssss", 
                    $invoice_number, $_SESSION['user_id'], $customer_name, $payment_method, 
                    $total, $discount, $shipping_address, $shipping_city, 
                    $shipping_postal, $phone, $payment_status);
            }
            
            // Execute the statement and check for errors
            if (!$stmt->execute()) {
                throw new Exception('Failed to create order: ' . $stmt->error);
            }
            
            // Get the sale ID for sale items
            $sale_id = $conn->insert_id;
            
            // Process items in cart
            foreach($_SESSION['cart'] as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $price = $item['price'];
                
                // Ensure inventory has enough stock
                $inventory_check = $conn->prepare("SELECT quantity FROM inventory WHERE product_id = ?");
                $inventory_check->bind_param("i", $product_id);
                $inventory_check->execute();
                $inventory_result = $inventory_check->get_result();
                
                if($inventory_result->num_rows > 0) {
                    $inventory = $inventory_result->fetch_assoc();
                    if($inventory['quantity'] < $quantity) {
                        throw new Exception("Not enough stock for product ID $product_id. Only {$inventory['quantity']} available.");
                    }
                }
                
                // Insert sale item
                $item_stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $item_stmt->bind_param("iiid", $sale_id, $product_id, $quantity, $price);
                
                if(!$item_stmt->execute()) {
                    throw new Exception('Failed to add item to order: ' . $item_stmt->error);
                }
                
                // Update inventory
                $inv_stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?");
                $inv_stmt->bind_param("ii", $quantity, $product_id);
                
                if(!$inv_stmt->execute()) {
                    throw new Exception('Failed to update inventory: ' . $inv_stmt->error);
                }
            }
            
            // Try to add an entry in order_status_history if the table exists
            try {
                $table_check = $conn->query("SHOW TABLES LIKE 'order_status_history'");
                if($table_check->num_rows > 0) {
                    $comments = "Order placed through website checkout";
                    $status_stmt = $conn->prepare("INSERT INTO order_status_history (order_id, status, comments) VALUES (?, ?, ?)");
                    $status_stmt->bind_param("iss", $sale_id, $payment_status, $comments);
                    $status_stmt->execute();
                }
            } catch (Exception $e) {
                // Silently continue if status history fails - it's not critical
            }
            
            // If we got this far, commit the transaction
            $conn->commit();
            
            // Clear cart
            unset($_SESSION['cart']);
            
            // Set session values for success page
            $_SESSION['order_success'] = true;
            $_SESSION['order_id'] = $sale_id;
            $_SESSION['order_invoice'] = $invoice_number;
            
            // Redirect to confirmation page
            header("Location: order_confirmation.php");
            exit;
            
        } catch(Exception $e) {
            // Roll back the transaction on error
            $conn->rollback();
            $order_error = "Sorry, there was an error processing your order: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .checkout-section {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .payment-method-option {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .payment-method-option.selected {
            border-color: #6d4c41;
            background-color: #f5f5f5;
        }
        .payment-method-option img {
            height: 30px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Checkout</h1>
                </div>
                
                <?php if(!empty($order_error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $order_error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Debug information for database setup, only shown to admins -->
                <?php if(isset($setup_warning)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $setup_warning; ?>
                    </div>
                <?php endif; ?>

                <form action="checkout.php" method="POST" id="checkout-form">
                    <div class="row">
                        <!-- Shipping Information -->
                        <div class="col-md-8">
                            <div class="checkout-section">
                                <h3>Shipping Information</h3>
                                <hr>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" required placeholder="09XX XXX XXXX">
                                    </div>
                                    <div class="col-12">
                                        <label for="address" class="form-label">Shipping Address *</label>
                                        <input type="text" class="form-control" id="address" name="address" required placeholder="House number, street name, barangay">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="city" class="form-label">City/Municipality *</label>
                                        <input type="text" class="form-control" id="city" name="city" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="postal_code" class="form-label">Postal Code *</label>
                                        <input type="text" class="form-control" id="postal_code" name="postal_code" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="notes" class="form-label">Delivery Notes (optional)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Special delivery instructions, landmark, etc."></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Methods -->
                            <div class="checkout-section">
                                <h3>Payment Method</h3>
                                <hr>
                                
                                <div class="payment-methods mb-3">
                                    <label class="form-label fw-bold">Payment Method</label>
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <div class="card payment-method-option selected" data-method="cod">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" id="payment-cod" value="cod" checked>
                                                    <label class="form-check-label d-flex align-items-center fw-bold" for="payment-cod">
                                                        <i class="fas fa-money-bill-wave me-2 text-success"></i>
                                                        Cash on Delivery (COD)
                                                    </label>
                                                </div>
                                                <p class="text-muted small mt-2 mb-0">Pay in cash when your order is delivered</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <div class="card payment-method-option h-100" data-method="gcash">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="payment_method" id="payment-gcash" value="gcash">
                                                        <label class="form-check-label d-flex align-items-center" for="payment-gcash">
                                                            <img src="../assets/images/gcash-logo.png" alt="GCash" class="payment-logo me-2" style="height:24px;">
                                                            GCash
                                                        </label>
                                                    </div>
                                                    <p class="text-muted small mt-2 mb-0">Pay using your GCash account</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="online-payment-instructions" class="mt-3 alert alert-info d-none">
                                    <p class="mb-1"><strong>Online Payment Instructions</strong></p>
                                    <p class="mb-1">1. Send your payment to 09XX-XXX-XXXX</p>
                                    <p class="mb-1">2. Take a screenshot of your payment</p>
                                    <p class="mb-1">3. Email your receipt to <a href="mailto:payments@bohemianburrows.com">payments@bohemianburrows.com</a> and include your Order #</p>
                                    <p class="mb-0">4. Your order will be processed once payment is verified</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="col-md-4">
                            <div class="checkout-section">
                                <h3>Order Summary</h3>
                                <hr>
                                
                                <div class="cart-items py-2">
                                    <?php foreach($_SESSION['cart'] as $item): ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <div>
                                                <span class="badge bg-secondary me-1"><?php echo $item['quantity']; ?>×</span>
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </div>
                                            <span>₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal</span>
                                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Shipping Fee</span>
                                    <span>₱<?php echo number_format($shipping, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Discount</span>
                                    <span>- ₱<?php echo number_format($discount, 2); ?></span>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-4">
                                    <span class="fw-bold">Total</span>
                                    <span class="fw-bold">₱<?php echo number_format($total, 2); ?></span>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-check-circle"></i> Place Order
                                    </button>
                                </div>
                                
                                <div class="mt-3 text-center">
                                    <a href="cart.php" class="text-decoration-none">
                                        <i class="fas fa-arrow-left"></i> Return to Cart
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Payment method selection
            const paymentOptions = document.querySelectorAll('.payment-method-option');
            const onlineInstructions = document.getElementById('online-payment-instructions');
            
            paymentOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Update radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    // Update styling
                    paymentOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    // Show/hide instructions
                    const method = this.dataset.method;
                    if(method === 'gcash' || method === 'paymaya') {
                        onlineInstructions.classList.remove('d-none');
                    } else {
                        onlineInstructions.classList.add('d-none');
                    }
                });
            });
            
            // Form validation
            const form = document.getElementById('checkout-form');
            
            form.addEventListener('submit', function(event) {
                // Basic form validation
                const required = ['name', 'phone', 'address', 'city', 'postal_code'];
                let valid = true;
                
                required.forEach(field => {
                    const input = document.getElementById(field);
                    if(!input.value.trim()) {
                        input.classList.add('is-invalid');
                        valid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });
                
                // Phone validation - simple pattern for Philippines
                const phone = document.getElementById('phone').value.trim();
                if(phone && !phone.match(/^09\d{9}$/)) {
                    document.getElementById('phone').classList.add('is-invalid');
                    alert('Please enter a valid Philippine mobile number (e.g., 09123456789)');
                    valid = false;
                }
                
                if(!valid) {
                    event.preventDefault();
                    window.scrollTo(0, 0);
                } else {
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Make the COD payment method more visible
            const paymentOptions = document.querySelectorAll('.payment-method-option');
            
            paymentOptions.forEach(option => {
                const radio = option.querySelector('input[type="radio"]');
                if (radio.checked) {
                    option.classList.add('selected', 'border', 'border-success', 'bg-light');
                }
                
                option.addEventListener('click', function() {
                    paymentOptions.forEach(opt => {
                        opt.classList.remove('selected', 'border', 'border-success', 'bg-light');
                        opt.querySelector('input[type="radio"]').checked = false;
                    });
                    
                    radio.checked = true;
                    this.classList.add('selected', 'border', 'border-success', 'bg-light');
                });
            });
        });
    </script>
</body>
</html>
