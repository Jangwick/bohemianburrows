<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";
require_once "../includes/functions.php"; // Include the functions file
require_once "../includes/display_helpers.php"; // Include our helper functions

// Force no caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Base query with explicit columns
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

// Search filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where .= " AND (s.invoice_number LIKE ? OR s.customer_name LIKE ? OR u.username LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM sales s 
    LEFT JOIN users u ON s.user_id = u.id
    WHERE $where
";
$count_stmt = $conn->prepare($count_sql);
if(!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// IMPORTANT CHANGE: Add cache-busting timestamp to prevent stale data
$cache_bust = time();

// Define debug flag - fix undefined variable warning
$debug_enabled = false; // Set to true to enable debugging information

// Add FOR UPDATE to lock rows during read to prevent stale data
$sql = "
    SELECT 
        s.id, 
        s.invoice_number, 
        s.created_at, 
        s.customer_name,
        s.payment_method, 
        s.payment_status, 
        s.total_amount,
        s.discount,
        u.username as cashier_username,
        (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
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

// Get all possible statuses for the filter dropdown
$status_query = $conn->query("SELECT DISTINCT payment_status FROM sales WHERE payment_status IS NOT NULL ORDER BY payment_status");
$all_statuses = [];
while($status = $status_query->fetch_assoc()) {
    if(!empty($status['payment_status'])) {
        $all_statuses[] = $status['payment_status'];
    }
}
// Add default statuses if not already in the list
$default_statuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded'];
foreach($default_statuses as $status) {
    if(!in_array($status, $all_statuses)) {
        $all_statuses[] = $status;
    }
}
sort($all_statuses);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Admin - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        /* Custom status badges */
        .status-badge {
            min-width: 90px;
            text-align: center;
            padding: 0.4em 0.6em;
            font-weight: 500;
            border-radius: .25rem;
            display: inline-block;
            line-height: 1;
        }
        .status-pending { background-color: #ffc107; color: #212529; }
        .status-paid { background-color: #28a745; color: white; }
        .status-processing { background-color: #17a2b8; color: white; }
        .status-shipped { background-color: #007bff; color: white; }
        .status-delivered { background-color: #28a745; color: white; }
        .status-completed { background-color: #28a745; color: white; }
        .status-cancelled { background-color: #dc3545; color: white; }
        .status-refunded { background-color: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Order Management</h1>
                </div>
                
                <?php if(isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Invoice #, customer, cashier..." 
                                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <?php foreach($all_statuses as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo (isset($_GET['status']) && $_GET['status'] == $status) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($status); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="start_date" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="end_date" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div>
                                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                                    <a href="orders.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Cashier</th>
                                        <th>Items</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($orders->num_rows > 0): 
                                        // Define debug flag for troubleshooting if needed
                                        $debug_enabled = false; 
                                        $cache_bust = time();
                                    ?>
                                        <?php while($order = $orders->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>&t=<?php echo $cache_bust; ?>">
                                                        <?php echo htmlspecialchars($order['invoice_number']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($order['cashier_username'] ?: 'N/A'); ?></td>
                                                <td><?php echo $order['item_count']; ?> item(s)</td>
                                                <td>
                                                    <?php echo display_payment_method($order['payment_method'], true); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Determine if this is a walk-in order (POS transaction)
                                                    // Fix: Add proper checks for user_id to prevent "undefined array key" warning
                                                    $is_walk_in = (isset($order['user_id']) && $order['user_id'] == $_SESSION['user_id'] && 
                                                                ($order['customer_name'] == 'Walk-in' || empty($order['customer_name'])));
                                                    
                                                    // Debug raw value from database if enabled
                                                    if ($debug_enabled) {
                                                        echo '<div class="small text-muted">Raw: [' . ($order['payment_status'] ?? 'null') . ']</div>';
                                                    }
                                                    
                                                    // Ensure we have a fallback status and proper display
                                                    $status = isset($order['payment_status']) && !empty($order['payment_status']) 
                                                        ? $order['payment_status'] 
                                                        : 'pending';
                                                    
                                                    // Pass walk-in flag to display helper
                                                    echo display_order_status($status, $is_walk_in);
                                                    ?>
                                                </td>
                                                <td>₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>&t=<?php echo $cache_bust; ?>" class="btn btn-sm btn-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <!-- Additional action buttons as needed -->
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No orders found</td>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Previous</a>
                                </li>
                                
                                <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add toast notification element for status updates -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="statusToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Status Update</strong>
                <small>Just now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="statusToastMessage">
                Status updated successfully.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add auto-refresh functionality for the orders page
        setTimeout(function() {
            // Refresh every 2 minutes to ensure latest status is shown
            location.reload();
        }, 120000);
        
        // Status update handling with AJAX
        const updateStatusButtons = document.querySelectorAll('.update-status-btn');
        const statusToast = new bootstrap.Toast(document.getElementById('statusToast'));
        const statusToastMessage = document.getElementById('statusToastMessage');
        
        updateStatusButtons.forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                const newStatus = this.getAttribute('data-status');
                const dropdown = this.closest('.status-dropdown');
                const statusButton = dropdown.querySelector('.status-badge');
                
                // Show loading indicator
                const originalText = statusButton.textContent;
                statusButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';
                statusButton.disabled = true;
                
                // Save the original status in case of error
                const originalStatus = statusButton.className.match(/status-(\w+)/?.[1] || 'pending';
                
                // Send AJAX request to update status
                fetch('../ajax/update_order_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        status: newStatus,
                        comments: 'Updated from order management'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update status button appearance
                        statusButton.classList.remove('status-' + originalStatus);
                        statusButton.classList.add('status-' + newStatus);
                        statusButton.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                        statusButton.disabled = false;
                        
                        // Show success toast
                        statusToastMessage.textContent = 'Order #' + orderId + ' status updated to ' + newStatus;
                        statusToastMessage.className = 'toast-body text-success';
                        statusToast.show();
                        
                        // Refresh any other elements that show the status
                        document.querySelectorAll(`[data-order-id="${orderId}"]`).forEach(el => {
                            if (el !== statusButton && el.classList.contains('status-badge')) {
                                el.classList.remove('status-' + originalStatus);
                                el.classList.add('status-' + newStatus);
                                el.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                            }
                        });
                    } else {
                        // Show error and revert to original status
                        statusButton.textContent = originalText;
                        statusButton.disabled = false;
                        
                        statusToastMessage.textContent = 'Error: ' + (data.message || 'Failed to update status');
                        statusToastMessage.className = 'toast-body text-danger';
                        statusToast.show();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Revert to original status on error
                    statusButton.textContent = originalText;
                    statusButton.disabled = false;
                    
                    statusToastMessage.textContent = 'Error: Could not connect to server';
                    statusToastMessage.className = 'toast-body text-danger';
                    statusToast.show();
                });
            });
        });
    });
    </script>
</body>
</html>
