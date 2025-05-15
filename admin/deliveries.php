<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";
require_once "../includes/functions.php"; // Include the functions file

// Pagination setup
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Base query - enhanced to include user information for better order management
$base_sql = "
    SELECT 
        s.id, 
        s.invoice_number, 
        s.created_at, 
        s.total_amount, 
        s.payment_method, 
        s.payment_status,
        s.customer_name,
        s.shipping_address,
        s.shipping_city,
        s.shipping_postal,
        s.phone,
        s.user_id,
        u.username as user_username,
        u.email as user_email, /* Use user email instead of s.email */
        os.tracking_number,
        os.courier,
        os.estimated_delivery,
        os.created_at as shipping_updated_at
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN (
        SELECT * FROM order_shipping os_inner
        WHERE os_inner.id = (SELECT MAX(id) FROM order_shipping WHERE order_id = os_inner.order_id)
    ) os ON s.id = os.order_id
";

// Default includes pending orders that need processing + already processing/shipped orders
$where_conditions = ["s.payment_status IN ('pending', 'processing', 'shipped')"];
$params = [];
$types = "";

// Apply filters
if(isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
    $where_conditions = ["s.payment_status = ?"];
    $params[] = $_GET['status_filter'];
    $types .= "s";
}
if(isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $where_conditions[] = "DATE(s.created_at) >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}
if(isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $where_conditions[] = "DATE(s.created_at) <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}

$where_sql = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT s.id) as total FROM sales s $where_sql";
$count_stmt = $conn->prepare($count_sql);
if(!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get orders with pagination
$order_sql = "$base_sql $where_sql ORDER BY 
    CASE 
        WHEN s.payment_status = 'pending' THEN 1 
        WHEN s.payment_status = 'processing' THEN 2
        WHEN s.payment_status = 'shipped' THEN 3
        ELSE 4
    END, 
    s.created_at DESC LIMIT ?, ?";
$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($order_sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();

// Count orders by status for summary
$summary_sql = "SELECT payment_status, COUNT(*) as count FROM sales WHERE payment_status IN ('pending', 'processing', 'shipped', 'delivered') GROUP BY payment_status";
$summary_result = $conn->query($summary_sql);
$status_counts = [];
while ($row = $summary_result->fetch_assoc()) {
    $status_counts[$row['payment_status']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Deliveries - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .status-badge { min-width: 100px; text-align: center; color: #fff; font-weight: 500; padding: 0.4em 0.65em; font-size: 0.85em; border-radius: .25rem; display: inline-block; line-height: 1; }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-processing { background-color: #17a2b8; }
        .status-shipped { background-color: #007bff; }
        .status-delivered { background-color: #28a745; }
        .status-cancelled { background-color: #dc3545; }
        .action-buttons .btn { margin-right: 5px; }
        .order-count-badge { font-size: 0.8rem; padding: 0.3em 0.6em; }
        .status-legend { margin-bottom: 15px; }
        .status-legend span { margin-right: 15px; display: inline-block; }
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
                    <h1 class="h2">Manage Deliveries</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <!-- Order statuses summary -->
                        <span class="badge bg-warning text-dark order-count-badge me-2">
                            Pending: <?php echo $status_counts['pending'] ?? 0; ?>
                        </span>
                        <span class="badge bg-info order-count-badge me-2">
                            Processing: <?php echo $status_counts['processing'] ?? 0; ?>
                        </span>
                        <span class="badge bg-primary order-count-badge me-2">
                            Shipped: <?php echo $status_counts['shipped'] ?? 0; ?>
                        </span>
                        <span class="badge bg-success order-count-badge">
                            Delivered: <?php echo $status_counts['delivered'] ?? 0; ?>
                        </span>
                    </div>
                </div>

                <?php if(isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="status_filter" class="form-label">Status</label>
                                <select class="form-select" id="status_filter" name="status_filter">
                                    <option value="">All Orders</option>
                                    <option value="pending" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $_GET['start_date'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $_GET['end_date'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <a href="deliveries.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Status Legend -->
                <div class="status-legend">
                    <strong>Status Legend:</strong>
                    <span><span class="badge status-badge status-pending">Pending</span> - New order awaiting processing</span>
                    <span><span class="badge status-badge status-processing">Processing</span> - Order accepted, preparing for shipment</span>
                    <span><span class="badge status-badge status-shipped">Shipped</span> - Order sent to customer</span>
                    <span><span class="badge status-badge status-delivered">Delivered</span> - Customer has received order</span>
                </div>

                <!-- Deliveries Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Shipping Address</th>
                                        <th>Status</th>
                                        <th>Courier</th>
                                        <th>Tracking #</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($orders->num_rows > 0): ?>
                                        <?php while($order = $orders->fetch_assoc()): ?>
                                            <tr class="<?php echo $order['payment_status'] === 'pending' ? 'table-warning' : ''; ?>">
                                                <td><a href="order_details.php?id=<?php echo $order['id']; ?>"><?php echo htmlspecialchars($order['invoice_number']); ?></a></td>
                                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($order['customer_name']); ?>
                                                    <?php if($order['user_email']): ?>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($order['user_email']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if($order['user_username']): ?>
                                                        <div class="small text-muted">@<?php echo htmlspecialchars($order['user_username']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if(isset($order['notes']) && !empty($order['notes'])): ?>
                                                        <div class="mt-1">
                                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#notes-<?php echo $order['id']; ?>">
                                                                <i class="fas fa-sticky-note"></i> Notes
                                                            </button>
                                                            <div class="collapse mt-1" id="notes-<?php echo $order['id']; ?>">
                                                                <div class="card card-body py-1 px-2 small bg-light">
                                                                    <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    echo htmlspecialchars($order['shipping_address'] ?? 'N/A') . ", ";
                                                    echo htmlspecialchars($order['shipping_city'] ?? '') . ", ";
                                                    echo htmlspecialchars($order['shipping_postal'] ?? '');
                                                    if($order['phone']) echo "<br><small class='text-muted'>Phone: " . htmlspecialchars($order['phone']) . "</small>";
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge status-badge status-<?php echo strtolower($order['payment_status']); ?>">
                                                        <?php echo ucfirst($order['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($order['courier'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($order['tracking_number'] ?? 'N/A'); ?></td>
                                                <td class="action-buttons">
                                                    <!-- Different buttons based on order status -->
                                                    <?php if($order['payment_status'] == 'pending'): ?>
                                                        <button class="btn btn-sm btn-success accept-order-btn" data-order-id="<?php echo $order['id']; ?>">
                                                            <i class="fas fa-check"></i> Accept
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($order['payment_status'] == 'processing' || $order['payment_status'] == 'shipped'): ?>
                                                        <button class="btn btn-sm btn-warning update-shipping-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#updateShippingModal"
                                                                data-order-id="<?php echo $order['id']; ?>"
                                                                data-courier="<?php echo htmlspecialchars($order['courier'] ?? ''); ?>"
                                                                data-tracking="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>"
                                                                data-estimated-delivery="<?php echo htmlspecialchars($order['estimated_delivery'] ?? ''); ?>">
                                                            <i class="fas fa-truck-loading"></i> Shipping
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($order['payment_status'] == 'shipped'): ?>
                                                        <button class="btn btn-sm btn-success mark-delivered-btn" data-order-id="<?php echo $order['id']; ?>">
                                                            <i class="fas fa-check-circle"></i> Delivered
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No orders found matching your criteria.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>''])); ?>">Previous</a>
                                </li>
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>''])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>''])); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Update Shipping Modal -->
    <div class="modal fade" id="updateShippingModal" tabindex="-1" aria-labelledby="updateShippingModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateShippingModalLabel">Update Shipping Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateShippingForm">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="shipping_order_id">
                        <input type="hidden" name="status" value="shipped"> <!-- Always set to shipped when updating shipping -->
                        
                        <div class="mb-3">
                            <label for="shipping_courier" class="form-label">Courier</label>
                            <input type="text" class="form-control" id="shipping_courier" name="courier" required>
                        </div>
                        <div class="mb-3">
                            <label for="shipping_tracking_number" class="form-label">Tracking Number</label>
                            <input type="text" class="form-control" id="shipping_tracking_number" name="tracking_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="shipping_estimated_delivery" class="form-label">Estimated Delivery Date</label>
                            <input type="date" class="form-control" id="shipping_estimated_delivery" name="estimated_delivery">
                        </div>
                        <div class="mb-3">
                            <label for="shipping_comments" class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" id="shipping_comments" name="comments" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Shipping Details</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle Update Shipping Modal
        const updateShippingModal = new bootstrap.Modal(document.getElementById('updateShippingModal'));
        document.querySelectorAll('.update-shipping-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('shipping_order_id').value = this.dataset.orderId;
                document.getElementById('shipping_courier').value = this.dataset.courier;
                document.getElementById('shipping_tracking_number').value = this.dataset.tracking;
                document.getElementById('shipping_estimated_delivery').value = this.dataset.estimatedDelivery;
                document.getElementById('shipping_comments').value = ''; // Clear comments
                updateShippingModal.show();
            });
        });

        // Handle Update Shipping Form Submission
        document.getElementById('updateShippingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            submitBtn.disabled = true;

            fetch('../ajax/update_order_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if(result.success) {
                    updateShippingModal.hide();
                    // Show success message and reload
                    alert('Shipping details updated successfully!');
                    window.location.reload(); 
                } else {
                    alert('Error updating shipping: ' + result.message);
                    // Reset button state
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Handle Accept Order Button
        document.querySelectorAll('.accept-order-btn').forEach(button => {
            button.addEventListener('click', function() {
                if(!confirm('Accept this order for processing?')) return;

                const orderId = this.dataset.orderId;
                const data = {
                    order_id: orderId,
                    status: 'processing',
                    comments: 'Order accepted for processing by admin.'
                };

                // Show processing state
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                this.disabled = true;

                fetch('../ajax/update_order_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if(result.success) {
                        window.location.reload();
                    } else {
                        alert('Error accepting order: ' + result.message);
                        // Reset button
                        this.innerHTML = '<i class="fas fa-check"></i> Accept';
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while accepting the order.');
                    // Reset button
                    this.innerHTML = '<i class="fas fa-check"></i> Accept';
                    this.disabled = false;
                });
            });
        });

        // Handle Mark as Delivered
        document.querySelectorAll('.mark-delivered-btn').forEach(button => {
            button.addEventListener('click', function() {
                if(!confirm('Are you sure you want to mark this order as delivered?')) return;

                const orderId = this.dataset.orderId;
                const data = {
                    order_id: orderId,
                    status: 'delivered',
                    comments: 'Order marked as delivered by admin.'
                };

                // Show processing state
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                this.disabled = true;

                fetch('../ajax/update_order_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if(result.success) {
                        window.location.reload();
                    } else {
                        alert('Error marking as delivered: ' + result.message);
                        // Reset button
                        this.innerHTML = '<i class="fas fa-check-circle"></i> Delivered';
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred.');
                    // Reset button
                    this.innerHTML = '<i class="fas fa-check-circle"></i> Delivered';
                    this.disabled = false;
                });
            });
        });
    });
    </script>
</body>
</html>
