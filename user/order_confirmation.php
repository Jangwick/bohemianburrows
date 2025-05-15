<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";
require_once "../includes/display_helpers.php"; // Ensure the correct helper file is included

// Initialize debug info
$debug_info = [];

// Check if user came from a successful checkout
if(!isset($_SESSION['order_success']) || !isset($_SESSION['order_id'])) {
    header("Location: dashboard.php");
    exit;
}

$order_id = $_SESSION['order_id'];
$order_invoice = $_SESSION['order_invoice'] ?? null;

// Get order details
$stmt = $conn->prepare("
    SELECT s.*, u.username 
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ? AND s.user_id = ?
");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if(!$order) {
    // Order not found or doesn't belong to this user
    header("Location: dashboard.php");
    exit;
}

// Get order items
$items_stmt = $conn->prepare("
    SELECT si.*, p.name, p.image_path
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result();

// Clear the session order data after retrieving it
unset($_SESSION['order_success']);
unset($_SESSION['order_id']);
unset($_SESSION['order_invoice']);
// Don't clear the cart again as it should already be cleared after successful checkout

// Add debug info to track payment method
$debug_info['payment_method_raw'] = $order['payment_method'];
$debug_info['payment_method_formatted'] = display_payment_method($order['payment_method']); // Ensure it calls display_payment_method()

// Enable this for debugging if needed
$show_debug = false;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .success-icon {
            font-size: 5rem;
            color: #28a745;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .order-confirmation {
            background-color: #f8f9fa;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 30px;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .bohemian-divider {
            height: 3px;
            width: 60%;
            margin: 1.5rem auto;
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
        
        .action-buttons .btn {
            min-width: 180px;
            margin: 0 10px;
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
                    <h1 class="h2">Order Confirmation</h1>
                </div>
                
                <!-- Display debug info if needed -->
                <?php if ($show_debug): ?>
                    <div class="alert alert-info">
                        <h5>Debug Information:</h5>
                        <pre><?php print_r($debug_info); ?></pre>
                    </div>
                <?php endif; ?>
                
                <div class="order-confirmation text-center">
                    <i class="fas fa-check-circle success-icon mb-4"></i>
                    <h2>Thank You for Your Order!</h2>
                    <p class="lead mb-1">Your order has been successfully placed.</p>
                    <p class="mb-3">Order #: <strong><?php echo $order['invoice_number']; ?></strong></p>
                    <p>A confirmation email will be sent to you shortly.</p>
                    
                    <div class="bohemian-divider"></div>
                    
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Order Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Order Date:</strong></td>
                                                <td><?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Payment Method:</strong></td>
                                                <td>
                                                    <?php echo display_payment_method($order['payment_method']); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Status:</strong></td>
                                                <td>
                                                    <?php echo display_order_status($order['payment_status']); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Shipping Address:</strong></td>
                                                <td>
                                                    <?php echo htmlspecialchars($order['shipping_address']); ?>,
                                                    <?php echo htmlspecialchars($order['shipping_city']); ?>,
                                                    <?php echo htmlspecialchars($order['shipping_postal']); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Amount:</strong></td>
                                                <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Order Items</h5>
                                </div>
                                <div class="card-body">
                                    <?php if($items->num_rows > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Price</th>
                                                        <th>Quantity</th>
                                                        <th class="text-end">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while($item = $items->fetch_assoc()): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <?php 
                                                                    $imagePath = !empty($item['image_path']) ? '../' . $item['image_path'] : '../assets/images/product-placeholder.png';
                                                                    ?>
                                                                    <img src="<?php echo $imagePath; ?>" class="item-image me-3" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                                                                </div>
                                                            </td>
                                                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                                            <td><?php echo $item['quantity']; ?></td>
                                                            <td class="text-end">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                                        <td class="text-end">₱<?php echo number_format($order['total_amount'] + $order['discount'], 2); ?></td>
                                                    </tr>
                                                    <?php if($order['discount'] > 0): ?>
                                                        <tr>
                                                            <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                                            <td class="text-end">- ₱<?php echo number_format($order['discount'], 2); ?></td>
                                                        </tr>
                                                    <?php endif; ?>
                                                    <tr>
                                                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                                        <td class="text-end">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center">No items found for this order.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="action-buttons text-center mt-4">
                                <a href="shop.php" class="btn btn-primary">
                                    <i class="fas fa-shopping-bag"></i> Continue Shopping
                                </a>
                                <a href="orders.php" class="btn btn-secondary">
                                    <i class="fas fa-list"></i> View My Orders
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
