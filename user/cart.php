<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart updates via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update quantities
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantities'] as $index => $quantity) {
            if (isset($_SESSION['cart'][$index])) {
                // Verify quantity is valid
                $quantity = (int)$quantity;
                if ($quantity > 0) {
                    $_SESSION['cart'][$index]['quantity'] = $quantity;
                    $_SESSION['cart'][$index]['subtotal'] = $_SESSION['cart'][$index]['price'] * $quantity;
                }
            }
        }
    }
    
    // Remove item
    if (isset($_POST['remove_item']) && isset($_POST['item_index'])) {
        $index = (int)$_POST['item_index'];
        if (isset($_SESSION['cart'][$index])) {
            array_splice($_SESSION['cart'], $index, 1);
        }
    }
    
    // Clear cart
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
    }
}

// Calculate totals
$subtotal = 0;
$discount = 0; // Can be pulled from a coupon system or loyalty program
$total = 0;

foreach ($_SESSION['cart'] as &$item) {
    // Calculate subtotal if it doesn't exist
    if (!isset($item['subtotal'])) {
        $item['subtotal'] = $item['price'] * $item['quantity'];
    }
    $subtotal += $item['subtotal'];
}
unset($item); // Break the reference

$total = $subtotal - $discount;

// Get stock information for cart items
$stock_info = [];
if (!empty($_SESSION['cart'])) {
    $product_ids = array_map(function($item) {
        return $item['product_id'];
    }, $_SESSION['cart']);
    
    $ids_param = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT p.id, i.quantity as stock 
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE p.id IN ($ids_param)
    ");
    
    $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $stock_info[$row['id']] = $row['stock'] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .cart-item-image {
            max-height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        .cart-header {
            background: linear-gradient(135deg, #8d6e63, #6d4c41);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        .quantity-input {
            max-width: 80px;
        }
        .cart-summary {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .empty-cart {
            text-align: center;
            padding: 50px 0;
        }
        .empty-cart i {
            font-size: 5rem;
            color: #d7ccc8;
            margin-bottom: 20px;
        }
        .bohemian-divider {
            height: 3px;
            width: 60%;
            margin: 1rem auto;
            background: linear-gradient(90deg, 
                rgba(255,255,255,0), 
                #d7ccc8, 
                #a1887f, 
                #8d6e63, 
                #a1887f, 
                #d7ccc8, 
                rgba(255,255,255,0));
            border-radius: 10px;
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
                <!-- Cart Header -->
                <div class="cart-header text-center">
                    <h1>Your Shopping Cart</h1>
                    <p class="lead">Review your items before checkout</p>
                    <div class="bohemian-divider"></div>
                </div>
                
                <?php if (empty($_SESSION['cart'])): ?>
                    <!-- Empty Cart -->
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <p class="text-muted">Add items to your cart to see them here.</p>
                        <a href="shop.php" class="btn btn-primary mt-3">Continue Shopping</a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <!-- Cart Items -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Cart Items (<?php echo count($_SESSION['cart']); ?>)</h5>
                                    <form method="post" class="d-inline">
                                        <button type="submit" name="clear_cart" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to clear your cart?')">
                                            <i class="fas fa-trash-alt me-1"></i> Clear Cart
                                        </button>
                                    </form>
                                </div>
                                <div class="card-body">
                                    <form method="post" id="cart-form">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Product</th>
                                                        <th>Price</th>
                                                        <th>Quantity</th>
                                                        <th class="text-end">Subtotal</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($_SESSION['cart'] as $index => $item): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <?php 
                                                                    $image = !empty($item['image_path']) ? '../' . $item['image_path'] : '../assets/images/product-placeholder.png';
                                                                    ?>
                                                                    <img src="<?php echo $image; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image me-3">
                                                                    <div>
                                                                        <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                                        <?php 
                                                                        // Check stock status
                                                                        $stock = $stock_info[$item['product_id']] ?? 0;
                                                                        $out_of_stock = $stock <= 0;
                                                                        $low_stock = $stock > 0 && $stock < 5;
                                                                        
                                                                        if($out_of_stock): ?>
                                                                            <span class="badge bg-danger">Out of Stock</span>
                                                                        <?php elseif($low_stock): ?>
                                                                            <span class="badge bg-warning text-dark">Only <?php echo $stock; ?> left</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                                            <td>
                                                                <div class="input-group" style="width: 120px;">
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary quantity-btn" data-action="decrease" data-index="<?php echo $index; ?>">-</button>
                                                                    <input type="number" name="quantities[<?php echo $index; ?>]" value="<?php echo $item['quantity']; ?>" 
                                                                           min="1" max="<?php echo $stock_info[$item['product_id']] ?? 99; ?>" 
                                                                           class="form-control form-control-sm text-center quantity-input"
                                                                           <?php echo $out_of_stock ? 'disabled' : ''; ?>>
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary quantity-btn" data-action="increase" data-index="<?php echo $index; ?>">+</button>
                                                                </div>
                                                            </td>
                                                            <td class="text-end">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-outline-danger remove-item" data-index="<?php echo $index; ?>">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-end mt-3">
                                            <button type="submit" name="update_cart" class="btn btn-outline-secondary">
                                                <i class="fas fa-sync-alt me-1"></i> Update Cart
                                            </button>
                                            <a href="shop.php" class="btn btn-outline-primary ms-2">
                                                <i class="fas fa-arrow-left me-1"></i> Continue Shopping
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cart Summary -->
                        <div class="col-lg-4">
                            <div class="card cart-summary">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Order Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal</span>
                                        <span>₱<?php echo number_format($subtotal, 2); ?></span>
                                    </div>
                                    <?php if($discount > 0): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Discount</span>
                                        <span>-₱<?php echo number_format($discount, 2); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <hr>
                                    <div class="d-flex justify-content-between mb-3 fw-bold">
                                        <span>Total</span>
                                        <span>₱<?php echo number_format($total, 2); ?></span>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="checkout.php" class="btn btn-primary">
                                            <i class="fas fa-lock me-1"></i> Proceed to Checkout
                                        </a>
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <h6>Accepted Payment Methods:</h6>
                                    <div class="d-flex justify-content-between">
                                        <i class="fas fa-money-bill-wave text-success fa-2x"></i>
                                        <i class="fab fa-cc-visa text-primary fa-2x"></i>
                                        <i class="fab fa-cc-mastercard text-danger fa-2x"></i>
                                        <i class="fab fa-paypal text-info fa-2x"></i>
                                        <i class="fas fa-mobile-alt text-warning fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Shipping Info Card -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-truck me-2"></i> Shipping</h5>
                                    <p class="card-text">Free shipping on all orders within Metro Manila.</p>
                                    <p class="card-text small text-muted">Additional fees may apply for provincial deliveries.</p>
                                </div>
                            </div>
                            
                            <!-- Return Policy Card -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-undo me-2"></i> Our Return Policy</h5>
                                    <p class="card-text small">Items can be returned within 7 days of delivery. Please ensure items are in original condition with tags attached.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- You May Also Like Section -->
                <div class="mt-5">
                    <h4>You May Also Like</h4>
                    <div class="row row-cols-2 row-cols-md-4 g-4">
                        <?php
                        // Get random products to suggest
                        $stmt = $conn->prepare("
                            SELECT p.*, i.quantity AS stock 
                            FROM products p
                            LEFT JOIN inventory i ON p.id = i.product_id
                            WHERE (i.quantity > 0 OR i.quantity IS NULL)
                            ORDER BY RAND()
                            LIMIT 4
                        ");
                        $stmt->execute();
                        $suggested_products = $stmt->get_result();
                        
                        while($product = $suggested_products->fetch_assoc()):
                            $image = !empty($product['image_path']) ? '../' . $product['image_path'] : '../assets/images/product-placeholder.png';
                        ?>
                            <div class="col">
                                <div class="card h-100">
                                    <a href="product_details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                                        <img src="<?php echo $image; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 180px; object-fit: cover;">
                                        <div class="card-body">
                                            <h6 class="card-title text-dark"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <p class="card-text text-primary fw-bold">₱<?php echo number_format($product['price'], 2); ?></p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle quantity buttons (increase/decrease)
        const quantityBtns = document.querySelectorAll('.quantity-btn');
        const quantityInputs = document.querySelectorAll('.quantity-input');
        const removeButtons = document.querySelectorAll('.remove-item');
        
        // Function to update quantity via AJAX
        function updateCartItem(index, quantity) {
            // Create a form data object
            const formData = new FormData();
            formData.append('update_item_quantity', 'true');
            formData.append('item_index', index);
            formData.append('quantity', quantity);
            
            // Send AJAX request
            fetch('update_cart_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the subtotal display
                    const row = document.querySelector(`input[name="quantities[${index}]"]`).closest('tr');
                    const subtotalCell = row.querySelector('td:nth-last-child(2)');
                    subtotalCell.textContent = '₱' + parseFloat(data.subtotal).toFixed(2);
                    
                    // Update the cart summary
                    document.querySelector('.cart-summary .d-flex:first-child span:last-child').textContent = '₱' + parseFloat(data.cart_total).toFixed(2);
                    document.querySelector('.cart-summary .fw-bold span:last-child').textContent = '₱' + parseFloat(data.cart_total).toFixed(2);
                } else {
                    alert('Error updating cart: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Fall back to form submission if AJAX fails
                document.getElementById('cart-form').submit();
            });
        }
        
        // Handle quantity buttons
        quantityBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const index = this.getAttribute('data-index');
                const action = this.getAttribute('data-action');
                const input = document.querySelector(`input[name="quantities[${index}]"]`);
                let value = parseInt(input.value);
                const max = parseInt(input.getAttribute('max'));
                
                if (action === 'increase' && value < max) {
                    value++;
                } else if (action === 'decrease' && value > 1) {
                    value--;
                } else {
                    return; // Don't update if at min/max
                }
                
                input.value = value;
                updateCartItem(index, value);
            });
        });
        
        // Handle direct quantity input changes
        quantityInputs.forEach(input => {
            input.addEventListener('change', function() {
                const index = this.name.match(/\[(\d+)\]/)[1];
                let value = parseInt(this.value);
                const min = parseInt(this.getAttribute('min'));
                const max = parseInt(this.getAttribute('max'));
                
                if (isNaN(value) || value < min) {
                    value = min;
                    this.value = min;
                } else if (value > max) {
                    value = max;
                    this.value = max;
                }
                
                updateCartItem(index, value);
            });
        });
        
        // Handle remove item buttons
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const index = this.getAttribute('data-index');
                
                // Create a form data object
                const formData = new FormData();
                formData.append('remove_item', 'true');
                formData.append('item_index', index);
                
                // Send AJAX request
                fetch('update_cart_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh the page to show updated cart
                        window.location.reload();
                    } else {
                        alert('Error removing item: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Fall back to traditional form submission
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `<input type="hidden" name="remove_item" value="true"><input type="hidden" name="item_index" value="${index}">`;
                    document.body.appendChild(form);
                    form.submit();
                });
            });
        });
    });
    </script>
</body>
</html>
