<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";
require_once "../includes/functions.php"; // Include the functions file
require_once "../includes/display_helpers.php"; // Include our helper functions

// Handle status update via AJAX (will be processed in ajax_update_order.php)

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Base query
$where = "1=1";
$params = array();
$types = "";

// Apply filters if any
if(isset($_GET['status']) && !empty($_GET['status'])) {
    $where .= " AND s.payment_status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

if(isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $where .= " AND DATE(s.created_at) >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}

if(isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $where .= " AND DATE(s.created_at) <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM sales s 
    WHERE $where
";
$count_stmt = $conn->prepare($count_sql);
if(!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get orders with pagination
$sql = "
    SELECT s.id, s.invoice_number, s.created_at, s.customer_name, s.payment_method, s.payment_status, 
           s.total_amount, u.username, 
           (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as item_count
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE $where
    ORDER BY s.created_at DESC
    LIMIT ?, ?
";

$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        /* Enhanced badge styles for better visibility */
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
        
        .status-badge {
            min-width: 100px;
            text-align: center;
            padding: 0.4em 0.6em;
            font-weight: 500;
            border-radius: .25rem;
            display: inline-block;
            line-height: 1;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-processing { background-color: #17a2b8; color: white; }
        .status-shipped { background-color: #007bff; color: white; }
        .status-delivered { background-color: #28a745; color: white; }
        .status-cancelled { background-color: #dc3545; color: white; }
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
                    <h1 class="h2">Order Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printOrders()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="exportBtn">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Status Tabs -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (!isset($_GET['status']) || $_GET['status'] == '') ? 'active' : ''; ?>" href="orders.php">All Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'active' : ''; ?>" href="orders.php?status=pending">
                            Pending
                            <?php 
                            $pending_query = $conn->query("SELECT COUNT(*) as count FROM sales WHERE payment_status = 'pending'");
                            $pending_count = $pending_query->fetch_assoc()['count'];
                            if($pending_count > 0): 
                            ?>
                                <span class="badge bg-danger"><?php echo $pending_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['status']) && $_GET['status'] == 'processing') ? 'active' : ''; ?>" href="orders.php?status=processing">Processing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['status']) && $_GET['status'] == 'shipped') ? 'active' : ''; ?>" href="orders.php?status=shipped">Shipped</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['status']) && $_GET['status'] == 'delivered') ? 'active' : ''; ?>" href="orders.php?status=delivered">Delivered</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['status']) && $_GET['status'] == 'cancelled') ? 'active' : ''; ?>" href="orders.php?status=cancelled">Cancelled</a>
                    </li>
                </ul>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <?php if(isset($_GET['status'])): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($_GET['status']); ?>">
                            <?php endif; ?>
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $_GET['start_date'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $_GET['end_date'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <a href="orders.php<?php echo isset($_GET['status']) ? '?status=' . $_GET['status'] : ''; ?>" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Orders Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($orders->num_rows > 0): ?>
                                        <?php while($order = $orders->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $order['invoice_number']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?></td>
                                                <td><?php echo $order['item_count']; ?> item(s)</td>
                                                <td>
                                                    <?php echo display_payment_method($order['payment_method'], true); ?>
                                                </td>
                                                <td>
                                                    <?php echo display_order_status($order['payment_status']); ?>
                                                </td>
                                                <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <?php if($order['payment_status'] == 'pending'): ?>
                                                                <li>
                                                                    <button class="dropdown-item update-status" data-order-id="<?php echo $order['id']; ?>" data-status="processing" data-bs-toggle="modal" data-bs-target="#statusUpdateModal">
                                                                        <i class="fas fa-check text-success me-2"></i> Accept Order
                                                                    </button>
                                                                </li>
                                                                <li>
                                                                    <button class="dropdown-item update-status" data-order-id="<?php echo $order['id']; ?>" data-status="cancelled" data-bs-toggle="modal" data-bs-target="#statusUpdateModal">
                                                                        <i class="fas fa-times text-danger me-2"></i> Cancel Order
                                                                    </button>
                                                                </li>
                                                            <?php elseif($order['payment_status'] == 'processing'): ?>
                                                                <li>
                                                                    <button class="dropdown-item update-status" data-order-id="<?php echo $order['id']; ?>" data-status="shipped" data-bs-toggle="modal" data-bs-target="#statusUpdateModal">
                                                                        <i class="fas fa-shipping-fast text-primary me-2"></i> Mark as Shipped
                                                                    </button>
                                                                </li>
                                                                <li>
                                                                    <button class="dropdown-item update-status" data-order-id="<?php echo $order['id']; ?>" data-status="cancelled" data-bs-toggle="modal" data-bs-target="#statusUpdateModal">
                                                                        <i class="fas fa-times text-danger me-2"></i> Cancel Order
                                                                    </button>
                                                                </li>
                                                            <?php elseif($order['payment_status'] == 'shipped'): ?>
                                                                <li>
                                                                    <button class="dropdown-item update-status" data-order-id="<?php echo $order['id']; ?>" data-status="delivered" data-bs-toggle="modal" data-bs-target="#statusUpdateModal">
                                                                        <i class="fas fa-check-circle text-success me-2"></i> Mark as Delivered
                                                                    </button>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <a class="dropdown-item" href="generate_shipping_label.php?id=<?php echo $order['id']; ?>" target="_blank">
                                                                    <i class="fas fa-tag text-secondary me-2"></i> Print Shipping Label
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No orders found</td>
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
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?>">
                                        Previous
                                    </a>
                                </li>
                                
                                <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?>">
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
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
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-status-update">Confirm Update</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
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
                    status: currentStatus
                };
                
                // Add shipping details if applicable
                if (currentStatus === 'shipped') {
                    data.tracking_number = document.getElementById('tracking-number').value;
                    data.courier = courierSelect.value === 'Other' ? document.getElementById('other-courier').value : courierSelect.value;
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
            
            // Print orders
            window.printOrders = function() {
                window.print();
            };
            
            // Export orders
            document.getElementById('exportBtn').addEventListener('click', function() {
                // Get current query params
                const params = new URLSearchParams(window.location.search);
                
                // Redirect to export endpoint with same filters
                window.location.href = '../ajax/export_orders.php?' + params.toString();
            });
        });
    </script>
</body>
</html>
