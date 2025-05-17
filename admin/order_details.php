<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

// Force cache prevention
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once "../includes/db_connect.php";
require_once "../includes/functions.php";
require_once "../includes/display_helpers.php";

// Check if ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid order ID.";
    header("Location: orders.php");
    exit;
}

$order_id = (int)$_GET['id'];

// Process direct form submissions for status updates
if($_SERVER["REQUEST_METHOD"] == "POST") {
    if(isset($_POST['update_status']) && isset($_POST['new_status'])) {
        $new_status = $_POST['new_status'];
        $comments = isset($_POST['comments']) ? $_POST['comments'] : '';
        
        // Use direct query for maximum reliability
        $update_sql = "UPDATE sales SET payment_status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $order_id);
        
        if($update_stmt->execute()) {
            // Check if update was successful
            if($update_stmt->affected_rows > 0) {
                // Log in history if table exists
                $history_check = $conn->query("SHOW TABLES LIKE 'order_status_history'");
                if($history_check->num_rows > 0) {
                    $history_stmt = $conn->prepare("
                        INSERT INTO order_status_history (order_id, status, comments, created_by)
                        VALUES (?, ?, ?, ?)
                    ");
                    $history_stmt->bind_param("issi", $order_id, $new_status, $comments, $_SESSION['user_id']);
                    $history_stmt->execute();
                }
                
                $_SESSION['success_message'] = "Order status successfully updated to " . ucfirst($new_status);
            } else {
                $_SESSION['info_message'] = "Status was already set to " . ucfirst($new_status);
            }
        } else {
            $_SESSION['error_message'] = "Failed to update status: " . $conn->error;
        }
        
        // Redirect with cache-busting parameter
        header("Location: order_details.php?id=$order_id&t=" . time());
        exit;
    }
}

// Get order details - ALWAYS FETCH FRESH DATA
$order_stmt = $conn->prepare("
    SELECT s.*, u.username
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$result = $order_stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error_message'] = "Order not found.";
    header("Location: orders.php");
    exit;
}

$order = $result->fetch_assoc();

// Get order items
$items_stmt = $conn->prepare("
    SELECT si.*, p.name, p.image_path, p.barcode
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result();

// Add safety check to prevent undefined variable error
if ($items === null) {
    $items = []; // Initialize as an empty array if query failed
    $_SESSION['error_message'] = "Failed to retrieve order items.";
} else {
    // Create an array of items for easier handling
    $items_array = [];
    while ($item = $items->fetch_assoc()) {
        $items_array[] = $item;
    }
    // Reset result pointer for future use
    $items->data_seek(0);
}

// Fetch fresh order data to ensure we have latest status
$fresh_query = "SELECT s.*, u.username FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = $order_id";
$result = $conn->query($fresh_query);

if($result->num_rows == 0) {
    header("Location: orders.php");
    exit;
}

$order = $result->fetch_assoc();

// Debug current status (leave this in to help troubleshoot)
$current_status = $order['payment_status'] ?? 'unknown';
error_log("Order #$order_id status: $current_status");

// Get shipping details if available
$shipping_details = null;

// First check if order_shipping table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'order_shipping'");
if ($tableCheck->num_rows > 0) {
    $tracking_stmt = $conn->prepare("
        SELECT * FROM order_shipping 
        WHERE order_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $tracking_stmt->bind_param("i", $order_id);
    $tracking_stmt->execute();
    $tracking_result = $tracking_stmt->get_result();
    
    // Assign the result to $shipping_details if we found a record
    if ($tracking_result->num_rows > 0) {
        $shipping_details = $tracking_result->fetch_assoc();
    }
}

// Get order status history
$history_stmt = $conn->prepare("
    SELECT h.*, u.username
    FROM order_status_history h
    LEFT JOIN users u ON h.created_by = u.id
    WHERE h.order_id = ?
    ORDER BY h.created_at DESC
");
$history_stmt->bind_param("i", $order_id);
$history_stmt->execute();
$history = $history_stmt->get_result();

// DEBUG: Print the current status to verify
$current_status = $order['payment_status'] ?? 'unknown';
// Uncomment for debugging: error_log("Order #$order_id current status: $current_status");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .status-badge {
            min-width: 100px;
            text-align: center;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-processing { background-color: #17a2b8; color: #fff; }
        .status-shipped { background-color: #007bff; color: #fff; }
        .status-delivered { background-color: #28a745; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        .btn-action {
            min-width: 120px;
        }
        .timeline-with-icons {
            border-left: 1px solid #ccc;
            position: relative;
            list-style: none;
            padding-left: 30px;
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
        .timeline-icon.icon-pending { background-color: #ffc107; }
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
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="orders.php?t=<?php echo time(); ?>" class="btn btn-sm btn-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Back to Orders
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <?php if($order['shipping_address']): ?>
                            <a href="generate_shipping_label.php?id=<?php echo $order_id; ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-2">
                                <i class="fas fa-tag"></i> Print Shipping Label
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Header -->
                <div class="order-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h3>Order #<?php echo htmlspecialchars($order['invoice_number']); ?></h3>
                            <p class="mb-1">
                                <strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Cashier/Staff:</strong> <?php echo htmlspecialchars($order['username'] ?: 'Online Order'); ?>
                            </p>
                            <p class="mb-0">
                                <strong>Payment Method:</strong> <?php echo display_payment_method($order['payment_method'], true); ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="mb-3">
                                <strong>Status:</strong> 
                                <span id="currentOrderStatus">
                                    <?php echo display_order_status($order['payment_status'] ?: 'pending'); ?>
                                </span>
                            </div>

                            <!-- Status Update Dropdown -->
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-primary dropdown-toggle" type="button" id="updateStatusDropdown" 
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    Update Status
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="updateStatusDropdown">
                                    <li><button class="dropdown-item detail-update-status" data-status="pending">Pending</button></li>
                                    <li><button class="dropdown-item detail-update-status" data-status="processing">Processing</button></li>
                                    <li><button class="dropdown-item detail-update-status" data-status="shipped">Shipped</button></li>
                                    <li><button class="dropdown-item detail-update-status" data-status="delivered">Delivered</button></li>
                                    <li><button class="dropdown-item detail-update-status" data-status="completed">Completed</button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><button class="dropdown-item detail-update-status text-danger" data-status="cancelled">Cancelled</button></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="mb-0">Order #<?php echo $order['invoice_number']; ?></h5>
                            </div>
                            <div class="col-auto">
                                <span class="badge status-badge status-<?php echo $order['payment_status']; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></p>
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p><strong>Username:</strong> <?php echo $order['username'] ? '@' . htmlspecialchars($order['username']) : 'Guest Order'; ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email'] ?? 'N/A'); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Payment Method:</strong> <?php echo display_payment_method($order['payment_method']); ?></p>
                                <p><strong>Status:</strong> <?php echo display_order_status($order['payment_status']); ?></p>
                                <p><strong>Total Amount:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                                <p><strong>Discount:</strong> ₱<?php echo number_format($order['discount'], 2); ?></p>
                                <p><strong>Gross Total:</strong> ₱<?php echo number_format($order['total_amount'] + $order['discount'], 2); ?></p>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <hr>
                                <h6>Update Order Status:</h6>
                                <div class="btn-group">
                                    <?php if($order['payment_status'] == 'pending'): ?>
                                        <button type="button" class="btn btn-success btn-action update-status" data-order-id="<?php echo $order_id; ?>" data-status="processing">
                                            <i class="fas fa-check me-1"></i> Accept Order
                                        </button>
                                        <button type="button" class="btn btn-danger btn-action update-status" data-order-id="<?php echo $order_id; ?>" data-status="cancelled">
                                            <i class="fas fa-times me-1"></i> Cancel Order
                                        </button>
                                    <?php elseif($order['payment_status'] == 'processing'): ?>
                                        <button type="button" class="btn btn-primary btn-action update-status" data-order-id="<?php echo $order_id; ?>" data-status="shipped">
                                            <i class="fas fa-shipping-fast me-1"></i> Mark as Shipped
                                        </button>
                                    <?php elseif($order['payment_status'] == 'shipped'): ?>
                                        <button type="button" class="btn btn-success btn-action update-status" data-order-id="<?php echo $order_id; ?>" data-status="delivered">
                                            <i class="fas fa-check-circle me-1"></i> Mark as Delivered
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shipping Information Section -->
                <?php if(in_array($order['payment_status'], ['processing', 'shipped', 'approved'])): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Shipping Information</h5>
                        <button type="button" class="btn btn-primary" onclick="printShippingLabel()">
                            <i class="fas fa-print"></i> Print Shipping Label
                        </button>
                    </div>
                    <div class="card-body">
                        <?php 
                        // Get shipping details from order or shipping info table if available
                        $shipping_address = !empty($order['shipping_address']) ? $order['shipping_address'] : '';
                        $shipping_city = !empty($order['shipping_city']) ? $order['shipping_city'] : '';
                        $shipping_postal = !empty($order['shipping_postal']) ? $order['shipping_postal'] : '';
                        $recipient_name = !empty($order['customer_name']) ? $order['customer_name'] : 'Customer';
                        $recipient_phone = !empty($order['phone']) ? $order['phone'] : '';
                        
                        // Get tracking info if available
                        $tracking_number = isset($shipping_details['tracking_number']) ? $shipping_details['tracking_number'] : '';
                        ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Recipient Information:</h6>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($recipient_name); ?></p>
                                <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($shipping_address); ?></p>
                                <p class="mb-1"><strong>City:</strong> <?php echo htmlspecialchars($shipping_city); ?></p>
                                <p class="mb-1"><strong>Postal Code:</strong> <?php echo htmlspecialchars($shipping_postal); ?></p>
                                <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($recipient_phone); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Shipping Details:</h6>
                                <p class="mb-1"><strong>Order Number:</strong> <?php echo htmlspecialchars($order['invoice_number']); ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                                <?php if(!empty($tracking_number)): ?>
                                <p class="mb-0"><strong>Tracking Number:</strong> <?php echo htmlspecialchars($tracking_number); ?></p>
                                <?php else: ?>
                                <p class="mb-0 text-muted">No tracking number assigned yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if(empty($shipping_address)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Shipping information is incomplete. Please update before printing the label.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Order Items Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Order Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($items && $items->num_rows > 0): ?>
                                        <?php while($item = $items->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <img src="<?php echo !empty($item['image_path']) ? '../' . htmlspecialchars($item['image_path']) : '../assets/images/product-placeholder.png'; ?>" 
                                                         alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                                                </td>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No items found for this order.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3"></td>
                                        <td><strong>Subtotal:</strong></td>
                                        <td>₱<?php echo number_format($order['total_amount'] + $order['discount'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"></td>
                                        <td><strong>Discount:</strong></td>
                                        <td>₱<?php echo number_format($order['discount'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"></td>
                                        <td><strong>Total:</strong></td>
                                        <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Order Details in Tabs -->
                <ul class="nav nav-tabs" id="orderTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="items-tab" data-bs-toggle="tab" data-bs-target="#items" type="button" role="tab" aria-controls="items" aria-selected="true">Items</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button" role="tab" aria-controls="shipping" aria-selected="false">Shipping Details</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline" type="button" role="tab" aria-controls="timeline" aria-selected="false">Order Timeline</button>
                    </li>
                </ul>
                <div class="tab-content" id="orderTabsContent">
                    <!-- Items Tab -->
                    <div class="tab-pane fade show active" id="items" role="tabpanel" aria-labelledby="items-tab">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Image</th>
                                                <th>Item</th>
                                                <th>Barcode</th>
                                                <th>Price</th>
                                                <th>Quantity</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($item = $items->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <img src="<?php echo !empty($item['image_path']) ? '../' . htmlspecialchars($item['image_path']) : '../assets/images/product-placeholder.png'; ?>" 
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['barcode'] ?? 'N/A'); ?></td>
                                                    <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                                    <td><?php echo $item['quantity']; ?></td>
                                                    <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4"></td>
                                                <td><strong>Subtotal:</strong></td>
                                                <td>₱<?php echo number_format($order['total_amount'] + $order['discount'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4"></td>
                                                <td><strong>Discount:</strong></td>
                                                <td>₱<?php echo number_format($order['discount'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4"></td>
                                                <td><strong>Total:</strong></td>
                                                <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Details Tab -->
                    <div class="tab-pane fade" id="shipping" role="tabpanel" aria-labelledby="shipping-tab">
                        <div class="card">
                            <div class="card-body">
                                <?php 
                                // Check if the table has shipping address columns
                                $hasShippingColumns = false;
                                $shippingColumnsCheck = $conn->query("SHOW COLUMNS FROM sales LIKE 'shipping_address'");
                                if($shippingColumnsCheck && $shippingColumnsCheck->num_rows > 0) {
                                    $hasShippingColumns = true;
                                }
                                ?>

                                <?php if($hasShippingColumns && isset($order['shipping_address']) && !empty($order['shipping_address'])): ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5>Delivery Address</h5>
                                            <address>
                                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                                <?php echo htmlspecialchars($order['shipping_address'] ?? 'N/A'); ?><br>
                                                <?php echo htmlspecialchars($order['shipping_city'] ?? 'N/A'); ?>, 
                                                <?php echo htmlspecialchars($order['shipping_postal'] ?? 'N/A'); ?><br>
                                                Phone: <?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?>
                                            </address>
                                        </div>
                                        <div class="col-md-6">
                                            <h5>Shipping Details</h5>
                                            <?php if($shipping_details): ?>
                                                <p><strong>Status:</strong> <?php echo ucfirst($shipping_details['status']); ?></p>
                                                <p><strong>Tracking Number:</strong> <?php echo $shipping_details['tracking_number'] ? htmlspecialchars($shipping_details['tracking_number']) : 'Not available'; ?></p>
                                                <p><strong>Courier:</strong> <?php echo htmlspecialchars($shipping_details['courier']); ?></p>
                                                <p><strong>Estimated Delivery:</strong> <?php echo $shipping_details['estimated_delivery'] ? date('F j, Y', strtotime($shipping_details['estimated_delivery'])) : 'Not specified'; ?></p>
                                                <p><strong>Shipped Date:</strong> <?php echo date('F j, Y', strtotime($shipping_details['created_at'])); ?></p>
                                            <?php elseif($order['payment_status'] == 'shipped'): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    Order is marked as shipped but shipping details are not available.
                                                </div>
                                                <a href="#" class="btn btn-primary update-status" data-order-id="<?php echo $order_id; ?>" data-status="shipped">
                                                    <i class="fas fa-shipping-fast me-1"></i> Update Shipping Details
                                                </a>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    Shipping information will be available once the order is shipped.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <p>No shipping information available for this order.</p>
                                        
                                        <?php if(!$hasShippingColumns): ?>
                                        <p class="mb-0">Your database does not have shipping columns.</p>
                                        
                                        <!-- Add button to alter table and add shipping columns -->
                                        <div class="text-center mt-3">
                                            <a href="create_orders_tables.php" class="btn btn-primary">
                                                <i class="fas fa-plus-circle me-1"></i> Add Shipping Support to Database
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline Tab -->
                    <div class="tab-pane fade" id="timeline" role="tabpanel" aria-labelledby="timeline-tab">
                        <div class="card">
                            <div class="card-body">
                                <?php
                                // Check if order_status_history table exists
                                $historyTableExists = $conn->query("SHOW TABLES LIKE 'order_status_history'")->num_rows > 0;
                                
                                if($historyTableExists) {
                                    // Get order history
                                    $history_stmt = $conn->prepare("
                                        SELECT * FROM order_status_history 
                                        WHERE order_id = ?
                                        ORDER BY created_at ASC
                                    ");
                                    $history_stmt->bind_param("i", $order_id);
                                    $history_stmt->execute();
                                    $history = $history_stmt->get_result();
                                    
                                    if($history->num_rows > 0):
                                ?>
                                    <ul class="timeline-with-icons">
                                        <?php while($event = $history->fetch_assoc()): 
                                            $status_class = strtolower(htmlspecialchars($event['status']));
                                            $icon_class = 'fas fa-info-circle'; // Default icon
                                            switch($event['status']) {
                                                case 'pending': $icon_class = 'fas fa-hourglass-start'; break;
                                                case 'processing': $icon_class = 'fas fa-cogs'; break; // "Accepted" is when it moves to processing
                                                case 'shipped': $icon_class = 'fas fa-shipping-fast'; break;
                                                case 'delivered': $icon_class = 'fas fa-check-circle'; break;
                                                case 'cancelled': $icon_class = 'fas fa-times-circle'; break;
                                            }
                                        ?>
                                            <li class="timeline-item">
                                                <span class="timeline-icon icon-<?php echo $status_class; ?>">
                                                    <i class="<?php echo $icon_class; ?>"></i>
                                                </span>
                                                <h5 class="fw-bold mb-1"><?php echo ucfirst(htmlspecialchars($event['status'])); ?></h5>
                                                <p class="text-muted mb-1"><small><?php echo date('F j, Y, g:i a', strtotime($event['created_at'])); ?></small></p>
                                                <?php if(!empty($event['comments'])): ?>
                                                    <p class="text-muted small mb-0"><?php echo nl2br(htmlspecialchars($event['comments'])); ?></p>
                                                <?php endif; ?>
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php 
                                    else: 
                                        // No history entries found, show basic info based on current order status
                                ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i> 
                                        <strong>Order Timeline</strong><br>
                                        This order was placed on <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?>.
                                        <br>Current status is <strong><?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?></strong>.
                                        <?php if ($order['payment_status'] == 'processing'): ?>
                                            <br>The order has been accepted and is being processed.
                                        <?php elseif (in_array($order['payment_status'], ['shipped', 'delivered'])): ?>
                                            <br>The order was accepted and has progressed further.
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Detailed History Not Available</strong><br>
                                        No specific history events found for this order. Ensure status updates are being logged correctly or run the 
                                        <a href="create_orders_tables.php" class="alert-link">database setup script</a> if the history table is missing.
                                    </div>
                                <?php 
                                    endif;
                                } else {
                                    // order_status_history table doesn't exist at all
                                ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Setup Required for Order Timeline</strong><br>
                                        Order history tracking requires the `order_status_history` table. 
                                        Please run the <a href="create_orders_tables.php" class="alert-link">database setup script</a>.
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal for Status Update Confirmation -->
    <div class="modal fade" id="statusUpdateModal" tabindex="-1" aria-labelledby="statusUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusUpdateModalLabel">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to update this order to <strong id="new-status-text"></strong>?</p>
                    
                    <div id="shipping-details-container" style="display: none;">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> This will send a notification to the customer about their shipment.
                        </div>
                        
                        <div class="mb-3">
                            <label for="tracking-number" class="form-label">Tracking Number (optional)</label>
                            <input type="text" class="form-control" id="tracking-number">
                        </div>
                        
                        <div class="mb-3">
                            <label for="courier" class="form-label">Courier/Shipping Company</label>
                            <select class="form-select" id="courier">
                                <option value="LBC">LBC Express</option>
                                <option value="JRS">JRS Express</option>
                                <option value="J&T">J&T Express</option>
                                <option value="Grab">Grab Express</option>
                                <option value="Lalamove">Lalamove</option>
                                <option value="In-House">In-House Delivery</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div id="other-courier-container" style="display: none;">
                            <div class="mb-3">
                                <label for="other-courier" class="form-label">Specify Courier</label>
                                <input type="text" class="form-control" id="other-courier">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="estimated-delivery" class="form-label">Estimated Delivery Date</label>
                            <input type="date" class="form-control" id="estimated-delivery">
                        </div>
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">Additional Comments (optional)</label>
                            <textarea class="form-control" id="comments" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-status-update">Confirm Update</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add toast notification for status updates -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="statusUpdateToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Status Update</strong>
                <small>Just now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="statusUpdateMessage">
                Status updated successfully.
            </div>
        </div>
    </div>

    <!-- Add the shipping label template (hidden by default) -->
    <div id="shipping-label-template" style="display: none;">
        <div class="shipping-label">
            <div class="label-header">
                <div class="company-info">
                    <h2>The Bohemian Burrows</h2>
                    <p>123 Fashion Street, Makati City</p>
                    <p>Philippines, Tel: (02) 8123-4567</p>
                </div>
                <div class="postage">
                    <h3>SHIPPING LABEL</h3>
                    <p>Order: <?php echo htmlspecialchars($order['invoice_number']); ?></p>
                    <p><?php echo date('m/d/Y', strtotime($order['created_at'])); ?></p>
                </div>
            </div>
            <div class="to-address">
                <h3>SHIP TO:</h3>
                <div class="recipient-details">
                    <h4><?php echo htmlspecialchars($recipient_name); ?></h4>
                    <p><?php echo htmlspecialchars($shipping_address); ?></p>
                    <p><?php echo htmlspecialchars($shipping_city); ?>, <?php echo htmlspecialchars($shipping_postal); ?></p>
                    <p>Phone: <?php echo htmlspecialchars($recipient_phone); ?></p>
                </div>
            </div>
            <div class="barcode-section">
                <?php if(!empty($tracking_number)): ?>
                <p>Tracking #: <?php echo htmlspecialchars($tracking_number); ?></p>
                <?php endif; ?>
                <div class="barcode">
                    <!-- Barcode representing the order number -->
                    <svg id="order-barcode"></svg>
                </div>
            </div>
            <div class="order-info">
                <p><strong>Order Date:</strong> <?php echo date('m/d/Y', strtotime($order['created_at'])); ?></p>
                <p><strong>Items:</strong> <?php echo $items ? $items->num_rows : 0; ?></p>
                <p><strong>Shipping Method:</strong> Standard Shipping</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Status update handling
            const statusUpdateLinks = document.querySelectorAll('.update-status');
            const statusUpdateModal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
            const newStatusText = document.getElementById('new-status-text');
            const confirmStatusUpdateBtn = document.getElementById('confirm-status-update');
            const shippingDetailsContainer = document.getElementById('shipping-details-container');
            const courierSelect = document.getElementById('courier');
            const otherCourierContainer = document.getElementById('other-courier-container');
            
            let currentOrderId = null;
            let currentStatus = null;
            
            // Show modal when status update is clicked
            statusUpdateLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    currentOrderId = this.getAttribute('data-order-id');
                    currentStatus = this.getAttribute('data-status');
                    
                    // Show appropriate title based on status
                    switch(currentStatus) {
                        case 'processing':
                            newStatusText.textContent = 'Processing';
                            shippingDetailsContainer.style.display = 'none';
                            break;
                        case 'shipped':
                            newStatusText.textContent = 'Shipped';
                            shippingDetailsContainer.style.display = 'block';
                            // Set default estimated delivery to 3 days from now
                            const estimatedDate = new Date();
                            estimatedDate.setDate(estimatedDate.getDate() + 3);
                            document.getElementById('estimated-delivery').value = estimatedDate.toISOString().split('T')[0];
                            break;
                        case 'delivered':
                            newStatusText.textContent = 'Delivered';
                            shippingDetailsContainer.style.display = 'none';
                            break;
                        case 'cancelled':
                            newStatusText.textContent = 'Cancelled';
                            shippingDetailsContainer.style.display = 'none';
                            break;
                    }
                    
                    statusUpdateModal.show();
                });
            });
            
            // Toggle other courier field
            courierSelect.addEventListener('change', function() {
                if (this.value === 'Other') {
                    otherCourierContainer.style.display = 'block';
                } else {
                    otherCourierContainer.style.display = 'none';
                }
            });
            
            // Submit status update
            confirmStatusUpdateBtn.addEventListener('click', function() {
                const data = {
                    order_id: currentOrderId,
                    status: currentStatus,
                    comments: document.getElementById('comments')?.value || ''
                };
                
                // Add shipping details if applicable
                if (currentStatus === 'shipped') {
                    data.tracking_number = document.getElementById('tracking-number').value;
                    data.courier = courierSelect.value === 'Other' ? 
                        document.getElementById('other-courier').value : courierSelect.value;
                    data.estimated_delivery = document.getElementById('estimated-delivery').value;
                }
                
                // Send AJAX request to update status
                fetch('../ajax/update_order_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal and reload page
                        statusUpdateModal.hide();
                        location.reload();
                    } else {
                        alert('Error updating order: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the order.');
                });
            });
            
            // Status update from detail page
            const detailUpdateButtons = document.querySelectorAll('.detail-update-status');
            const currentOrderStatus = document.getElementById('currentOrderStatus');
            const statusToast = document.getElementById('statusUpdateToast') ? 
                new bootstrap.Toast(document.getElementById('statusUpdateToast')) : null;
            const statusUpdateMessage = document.getElementById('statusUpdateMessage');
            
            detailUpdateButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const newStatus = this.getAttribute('data-status');
                    const comments = prompt("Add a comment for this status change (optional):");
                    
                    // Update via AJAX
                    fetch('../ajax/update_order_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            order_id: <?php echo $order_id; ?>,
                            status: newStatus,
                            comments: comments || 'Status updated by admin'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update displayed status
                            if (currentOrderStatus) {
                                currentOrderStatus.innerHTML = `<span class="status-badge status-${newStatus}">${newStatus.charAt(0).toUpperCase() + newStatus.slice(1)}</span>`;
                            }
                            
                            // Add new entry to timeline without reloading
                            const timeline = document.getElementById('statusTimeline');
                            if (timeline) { // Check if the timeline element exists
                                const now = new Date();
                                const formattedDate = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + 
                                                    ' ' + now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                                
                                const newTimelineItem = document.createElement('div');
                                newTimelineItem.className = 'timeline-item';
                                newTimelineItem.innerHTML = `
                                    <div class="timeline-marker">
                                        <i class="fas fa-circle fa-xs"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>
                                                <strong>${newStatus.charAt(0).toUpperCase() + newStatus.slice(1)}</strong>
                                                by <?php echo htmlspecialchars($_SESSION['username']); ?>
                                            </span>
                                            <span class="text-muted">${formattedDate}</span>
                                        </div>
                                        ${comments ? '<p class="mb-0 text-muted">' + comments + '</p>' : ''}
                                    </div>
                                `;
                                
                                // Insert at the beginning of the timeline if it has child elements
                                if (timeline.firstChild) {
                                    timeline.insertBefore(newTimelineItem, timeline.firstChild);
                                } else {
                                    // If timeline is empty, just append
                                    timeline.appendChild(newTimelineItem);
                                }
                            }
                            
                            // Show success message
                            if (statusToast && statusUpdateMessage) {
                                statusUpdateMessage.textContent = 'Order status updated to ' + newStatus;
                                statusUpdateMessage.className = 'toast-body text-success';
                                statusToast.show();
                            }
                            
                            // Optional: Reload page after a short delay to ensure all data is fresh
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            // Show error
                            if (statusToast && statusUpdateMessage) {
                                statusUpdateMessage.textContent = 'Error: ' + (data.message || 'Failed to update status');
                                statusUpdateMessage.className = 'toast-body text-danger';
                                statusToast.show();
                            } else {
                                alert('Error: ' + (data.message || 'Failed to update status'));
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error: Could not connect to server');
                    });
                });
            });
        });
        
        // Function to print shipping label
        function printShippingLabel() {
            // Validate if shipping info is available
            const shippingAddress = "<?php echo addslashes($shipping_address); ?>";
            if (!shippingAddress) {
                alert("Shipping address is incomplete. Please update the shipping information before printing.");
                return;
            }
            
            // Create a barcode for the order number
            JsBarcode("#order-barcode", "<?php echo $order['invoice_number']; ?>", {
                format: "CODE128",
                lineColor: "#000",
                width: 2,
                height: 50,
                displayValue: true
            });
            
            // Open print dialog
            const printWindow = window.open('', '_blank');
            const labelTemplate = document.getElementById('shipping-label-template').innerHTML;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Shipping Label - <?php echo htmlspecialchars($order['invoice_number']); ?></title>
                    <style>
                        @media print {
                            @page {
                                size: 4in 6in; /* Standard shipping label size */
                                margin: 0;
                            }
                            body {
                                margin: 0;
                                padding: 0;
                            }
                        }
                        
                        body {
                            font-family: Arial, sans-serif;
                        }
                        
                        .shipping-label {
                            width: 4in;
                            height: 6in;
                            border: 1px solid #000;
                            padding: 0.25in;
                            box-sizing: border-box;
                            position: relative;
                        }
                        
                        .label-header {
                            display: flex;
                            justify-content: space-between;
                            border-bottom: 1px solid #000;
                            padding-bottom: 10px;
                            margin-bottom: 10px;
                        }
                        
                        .company-info h2 {
                            margin: 0 0 5px 0;
                            font-size: 14pt;
                        }
                        
                        .company-info p {
                            margin: 0;
                            font-size: 8pt;
                        }
                        
                        .postage {
                            text-align: right;
                        }
                        
                        .postage h3 {
                            margin: 0;
                            font-size: 12pt;
                        }
                        
                        .to-address {
                            margin: 15px 0;
                        }
                        
                        .to-address h3 {
                            margin: 0 0 5px 0;
                            font-size: 10pt;
                            text-decoration: underline;
                        }
                        
                        .recipient-details {
                            margin-left: 15px;
                        }
                        
                        .recipient-details h4 {
                            margin: 0 0 5px 0;
                            font-size: 14pt;
                        }
                        
                        .recipient-details p {
                            margin: 0 0 3px 0;
                            font-size: 11pt;
                        }
                        
                        .barcode-section {
                            text-align: center;
                            margin: 15px 0;
                        }
                        
                        .barcode {
                            margin-top: 10px;
                        }
                        
                        .order-info {
                            position: absolute;
                            bottom: 0.25in;
                            left: 0.25in;
                            right: 0.25in;
                            font-size: 8pt;
                            border-top: 1px solid #000;
                            padding-top: 10px;
                        }
                        
                        .order-info p {
                            margin: 0 0 3px 0;
                        }
                        
                        .print-buttons {
                            margin-top: 20px;
                            text-align: center;
                            display: none; /* Hide in print view */
                        }
                        
                        @media screen {
                            body {
                                padding: 20px;
                                background-color: #f0f0f0;
                            }
                            
                            .shipping-label {
                                margin: 0 auto;
                                background-color: white;
                                box-shadow: 0 1px 5px rgba(0,0,0,0.2);
                            }
                            
                            .print-buttons {
                                display: block;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${labelTemplate}
                    <div class="print-buttons">
                        <button onclick="window.print();" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; margin-right: 10px;">Print Label</button>
                        <button onclick="window.close();" style="padding: 10px 20px; background: #6c757d; color: white; border: none; cursor: pointer;">Close</button>
                    </div>
                    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
                    <script>
                        // Generate barcode
                        JsBarcode("#order-barcode", "<?php echo $order['invoice_number']; ?>", {
                            format: "CODE128",
                            lineColor: "#000",
                            width: 2,
                            height: 50,
                            displayValue: true
                        });
                        
                        // Auto print after load
                        window.onload = function() {
                            setTimeout(function() {
                                window.print();
                            }, 500);
                        }
                    </script>
                </body>
                </html>
            `);
            
            printWindow.document.close();
        }

        // Initialize any elements once DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Other initialization code can go here
        });
    </script>
</body>
</html>
