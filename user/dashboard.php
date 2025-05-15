<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Get user information
$stmt = $conn->prepare("SELECT username, full_name, email, created_at, last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get recent orders
$order_stmt = $conn->prepare("
    SELECT s.id, s.invoice_number, s.created_at, s.total_amount, s.payment_method, s.payment_status
    FROM sales s
    WHERE s.customer_name = ? OR (s.user_id = ? AND s.customer_name IS NULL)
    ORDER BY s.created_at DESC
    LIMIT 5
");
$order_stmt->bind_param("si", $user['full_name'], $_SESSION['user_id']);
$order_stmt->execute();
$recent_orders = $order_stmt->get_result();

// Get order summary
$summary_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount) as total_spent,
        MAX(created_at) as last_order_date
    FROM sales
    WHERE customer_name = ? OR (user_id = ? AND customer_name IS NULL)
");
$summary_stmt->bind_param("si", $user['full_name'], $_SESSION['user_id']);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Get recently viewed products (via a separate table that would track user views)
$recent_products = [];
$featured_products_stmt = $conn->prepare("
    SELECT p.*, i.quantity 
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    ORDER BY RAND()
    LIMIT 4
");
$featured_products_stmt->execute();
$featured_products = $featured_products_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .welcome-header {
            background: linear-gradient(135deg, #8d6e63, #6d4c41);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            border-radius: 10px;
        }
        .dashboard-card {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-radius: 12px;
            transition: all 0.3s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .product-card .card-img-top {
            height: 200px;
            object-fit: cover;
        }
        .quick-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .quick-link:hover .card {
            transform: translateY(-5px);
        }
        .quick-link .icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        /* Add status badge styles if not already in styles.css or for consistency */
        .status-badge {
            font-size: 0.85rem;
            padding: 0.4em 0.7em;
            min-width: 90px;
            text-align: center;
            border-radius: 0.25rem; /* Standard Bootstrap badge radius */
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-processing { background-color: #17a2b8; color: #fff; }
        .status-shipped { background-color: #007bff; color: #fff; }
        .status-delivered { background-color: #28a745; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
        .status-default { background-color: #6c757d; color: #fff; } /* Fallback */
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Welcome Header -->
                <div class="welcome-header text-center mb-4 mt-3">
                    <h1>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
                    <p class="lead">Explore your personal dashboard</p>
                </div>
                
                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-bag mb-2 fa-2x"></i>
                                <h5>Orders</h5>
                                <h2><?php echo $summary['total_orders'] ?? 0; ?></h2>
                                <p>Total orders placed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-coins mb-2 fa-2x"></i>
                                <h5>Total Spent</h5>
                                <h2>₱<?php echo number_format($summary['total_spent'] ?? 0, 2); ?></h2>
                                <p>On all purchases</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar mb-2 fa-2x"></i>
                                <h5>Last Order</h5>
                                <h2><?php echo $summary['last_order_date'] ? date('M d, Y', strtotime($summary['last_order_date'])) : 'None'; ?></h2>
                                <p>Most recent purchase</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="row mb-4">
                    <div class="col-12 mb-3">
                        <h4>Quick Links</h4>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="shop.php" class="quick-link">
                            <div class="card dashboard-card h-100">
                                <div class="card-body text-center">
                                    <div class="icon text-primary">
                                        <i class="fas fa-store"></i>
                                    </div>
                                    <h5>Shop Now</h5>
                                    <p class="text-muted">Explore our collection</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="orders.php" class="quick-link">
                            <div class="card dashboard-card h-100">
                                <div class="card-body text-center">
                                    <div class="icon text-success">
                                        <i class="fas fa-shopping-bag"></i>
                                    </div>
                                    <h5>My Orders</h5>
                                    <p class="text-muted">View order history</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="wishlist.php" class="quick-link">
                            <div class="card dashboard-card h-100">
                                <div class="card-body text-center">
                                    <div class="icon text-danger">
                                        <i class="fas fa-heart"></i>
                                    </div>
                                    <h5>Wishlist</h5>
                                    <p class="text-muted">Saved items</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="profile.php" class="quick-link">
                            <div class="card dashboard-card h-100">
                                <div class="card-body text-center">
                                    <div class="icon text-info">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <h5>Profile</h5>
                                    <p class="text-muted">Manage your details</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Orders</h5>
                                <a href="orders.php" class="btn btn-outline-primary btn-sm">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if($recent_orders->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($order = $recent_orders->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($order['invoice_number']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                        <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                                        <td>
                                                            <span class="badge status-badge status-<?php echo strtolower(htmlspecialchars($order['payment_status'] ?? 'default')); ?>">
                                                                <?php echo ucfirst(htmlspecialchars($order['payment_status'] ?? 'N/A')); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-info btn-sm">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                        <p class="mb-0">You haven't placed any orders yet.</p>
                                        <a href="shop.php" class="btn btn-primary mt-3">Start Shopping</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Featured Products -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4>Featured Products</h4>
                    </div>
                    
                    <?php while($product = $featured_products->fetch_assoc()): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card product-card h-100">
                                <img src="<?php echo !empty($product['image_path']) ? '../' . $product['image_path'] : '../assets/images/product-placeholder.png'; ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text text-primary fw-bold">₱<?php echo number_format($product['price'], 2); ?></p>
                                    <?php if(($product['quantity'] ?? 0) <= 0): ?>
                                        <div class="d-grid">
                                            <button class="btn btn-secondary" disabled>Out of Stock</button>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-grid">
                                            <a href="product_details.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary">View Details</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
