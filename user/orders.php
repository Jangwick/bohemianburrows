<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";
require_once "../includes/functions.php"; // Include the functions file
require_once "../includes/display_helpers.php"; // Include our helper functions

$user_id = $_SESSION['user_id'];

// Get user's full name to match against sales.customer_name for guest checkouts linked to user ID
$user_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_details = $user_result->fetch_assoc();
$user_full_name = $user_details ? $user_details['full_name'] : null;

// Pagination
$limit = 10; // Number of orders per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Get total number of orders for the user
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM sales 
    WHERE user_id = ? OR (customer_name = ? AND user_id IS NULL)
");
$count_stmt->bind_param("is", $user_id, $user_full_name);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $limit);

// Get orders for the current user
$stmt = $conn->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as item_count
    FROM sales s
    WHERE s.user_id = ? OR (s.customer_name = ? AND s.user_id IS NULL)
    ORDER BY s.created_at DESC
    LIMIT ?, ?
");
$stmt->bind_param("isii", $user_id, $user_full_name, $start, $limit);
$stmt->execute();
$orders = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .orders-table th {
            font-weight: 600;
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.4em 0.7em;
            min-width: 90px;
            text-align: center;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-processing { background-color: #17a2b8; color: #fff; }
        .status-shipped { background-color: #007bff; color: #fff; }
        .status-delivered { background-color: #28a745; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
        .card.orders-card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
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
                    <h1 class="h2">My Orders</h1>
                </div>

                <?php if(isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card orders-card">
                    <div class="card-body">
                        <?php if($orders->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover orders-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Date</th>
                                            <th>Items</th>
                                            <th>Payment Method</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($order = $orders->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($order['invoice_number']); ?></td>
                                                <td><?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></td>
                                                <td><?php echo $order['item_count']; ?> item(s)</td>
                                                <td>
                                                    <?php 
                                                    // FIXED: Always show cash icon for null/empty payment methods
                                                    $payment_method = !empty($order['payment_method']) ? $order['payment_method'] : 'cash';
                                                    echo display_payment_method($payment_method, true); 
                                                    ?>
                                                </td>
                                                <td><?php echo display_order_status($order['payment_status']); ?></td>
                                                <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i> View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                    </li>
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                <p class="lead">You haven't placed any orders yet.</p>
                                <a href="shop.php" class="btn btn-primary">Start Shopping</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
