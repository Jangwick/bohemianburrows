<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect to orders page or show an error
    header("Location: orders.php");
    exit;
}

$order_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get user's full name to match against sales.customer_name for guest checkouts linked to user ID
$user_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_details = $user_result->fetch_assoc();
$user_full_name = $user_details ? $user_details['full_name'] : null;

// Get order details, ensuring it belongs to the logged-in user
// It can belong if user_id matches OR if customer_name matches and user_id was null (guest checkout later associated)
$stmt = $conn->prepare("
    SELECT s.* 
    FROM sales s
    WHERE s.id = ? AND (s.user_id = ? OR (s.customer_name = ? AND s.user_id IS NULL))
");
$stmt->bind_param("iis", $order_id, $user_id, $user_full_name);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if(!$order) {
    // Order not found or doesn't belong to user
    $_SESSION['error_message'] = "Order not found or you do not have permission to view it.";
    header("Location: orders.php");
    exit;
}

// Get order items
$items_stmt = $conn->prepare("
    SELECT si.*, p.name as product_name, p.image_path 
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result();

// Get order status history (if table exists)
$has_status_history = false;
$status_history = [];

$checkTable = $conn->query("SHOW TABLES LIKE 'order_status_history'");
if ($checkTable->num_rows > 0) {
    $has_status_history = true;
    $history_stmt = $conn->prepare("
        SELECT * FROM order_status_history 
        WHERE order_id = ?
        ORDER BY created_at ASC
    ");
    $history_stmt->bind_param("i", $order_id);
    $history_stmt->execute();
    $status_result = $history_stmt->get_result();
    while ($status = $status_result->fetch_assoc()) {
        $status_history[] = $status;
    }
}

// Get shipping information if available
$shipping_info = null;
$checkShippingTable = $conn->query("SHOW TABLES LIKE 'order_shipping'");
if ($checkShippingTable->num_rows > 0) {
    $shipping_stmt = $conn->prepare("
        SELECT * FROM order_shipping 
        WHERE order_id = ? 
        ORDER BY created_at DESC LIMIT 1
    ");
    $shipping_stmt->bind_param("i", $order_id);
    $shipping_stmt->execute();
    $shipping_result = $shipping_stmt->get_result();
    if ($shipping_result->num_rows > 0) {
        $shipping_info = $shipping_result->fetch_assoc();
    }
}

// Get shipping details if they exist (assuming sales table has these columns)
$shipping_address = $order['shipping_address'] ?? null;
$shipping_city = $order['shipping_city'] ?? null;
$shipping_postal = $order['shipping_postal'] ?? null;
$phone = $order['phone'] ?? null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?php echo htmlspecialchars($order['invoice_number']); ?> - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .order-summary-card, .order-items-card, .shipping-details-card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .product-image-sm {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5em 0.8em;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-processing { background-color: #17a2b8; color: #fff; }
        .status-shipped { background-color: #007bff; color: #fff; }
        .status-delivered { background-color: #28a745; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
        
        /* Payment method badge styles */
        .payment-method-badge {
            min-width: 90px;
            text-align: center;
            padding: 0.4em 0.6em;
            font-weight: 500;
            border-radius: .25rem;
            display: inline-block;
            line-height: 1;
        }
        .payment-cash { background-color: #28a745; color: white; }
        .payment-card { background-color: #0d6efd; color: white; }
        .payment-gcash { background-color: #17a2b8; color: white; }
        .payment-paymaya { background-color: #fd7e14; color: white; }
        .payment-cod { background-color: #6c757d; color: white; }

        /* Timeline styles */
        .timeline-with-icons {
            border-left: 1px solid #ccc;
            position: relative;
            list-style: none;
            padding-left: 30px;
            margin-left: 10px;
        }
        .timeline-with-icons .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        .timeline-with-icons .timeline-icon {
            position: absolute;
            left: -40px;
            background-color: #fff;
            border-radius: 50%;
            height: 30px;
            width: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .timeline-icon.icon-pending { background-color: #ffc107; color: #000; }
        .timeline-icon.icon-processing { background-color: #17a2b8; }
        .timeline-icon.icon-shipped { background-color: #007bff; }
        .timeline-icon.icon-delivered { background-color: #28a745; }
        .timeline-icon.icon-cancelled { background-color: #dc3545; }
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
                    <h1 class="h2">Order Details</h1>
                    <a href="orders.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to My Orders
                    </a>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Order Items Card -->
                        <div class="card order-items-card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Items in Order #<?php echo htmlspecialchars($order['invoice_number']); ?></h5>
                            </div>
                            <div class="card-body">
                                <?php if($items->num_rows > 0): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php while($item = $items->fetch_assoc()): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo !empty($item['image_path']) ? '../' . htmlspecialchars($item['image_path']) : '../assets/images/product-placeholder.png'; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="product-image-sm me-3">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                    <small class="text-muted">Quantity: <?php echo $item['quantity']; ?></small>
                                                </div>
                                            </div>
                                            <span class="fw-bold">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                        </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">No items found for this order.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if($shipping_address): ?>
                        <!-- Shipping Details Card -->
                        <div class="card shipping-details-card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Shipping Details</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($shipping_address); ?></p>
                                <p><strong>City:</strong> <?php echo htmlspecialchars($shipping_city); ?></p>
                                <p><strong>Postal Code:</strong> <?php echo htmlspecialchars($shipping_postal); ?></p>
                                <?php if($phone): ?>
                                <p><strong>Contact Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-4">
                        <!-- Order Summary Card -->
                        <div class="card order-summary-card sticky-top" style="top: 20px;">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Order Summary</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order['invoice_number']); ?></p>
                                <p><strong>Date Placed:</strong> <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge status-badge status-<?php echo strtolower($order['payment_status']); ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </p>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal:</span>
                                    <span>₱<?php echo number_format($order['total_amount'] + $order['discount'], 2); // Assuming total_amount is after discount ?></span>
                                </div>
                                <?php if($order['discount'] > 0): ?>
                                <div class="d-flex justify-content-between">
                                    <span>Discount:</span>
                                    <span class="text-danger">- ₱<?php echo number_format($order['discount'], 2); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between fw-bold fs-5 mt-2">
                                    <span>Total:</span>
                                    <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                <hr>
                                <p><strong>Payment Method:</strong> <?php echo ucfirst(htmlspecialchars($order['payment_method'])); ?></p>
                                
                                <?php if($order['payment_status'] == 'pending' && $order['payment_method'] == 'cod'): // Example for a "Pay Now" or "Cancel" button ?>
                                <!-- <div class="d-grid gap-2 mt-3">
                                    <button class="btn btn-success">Pay Now (Placeholder)</button>
                                    <button class="btn btn-outline-danger">Cancel Order (Placeholder)</button>
                                </div> -->
                                <?php endif; ?>
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
