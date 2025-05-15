<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Check if ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$product_id = $_GET['id'];

// Get product details
$stmt = $conn->prepare("
    SELECT p.*, i.quantity 
    FROM products p 
    LEFT JOIN inventory i ON p.id = i.product_id 
    WHERE p.id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: products.php");
    exit;
}

$product = $result->fetch_assoc();

// Get sales history for this product
$sales_stmt = $conn->prepare("
    SELECT si.quantity, si.price, si.subtotal, s.invoice_number, s.created_at, s.payment_method
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE si.product_id = ?
    ORDER BY s.created_at DESC
    LIMIT 10
");
$sales_stmt->bind_param("i", $product_id);
$sales_stmt->execute();
$sales_history = $sales_stmt->get_result();

// Calculate product statistics
$stats_stmt = $conn->prepare("
    SELECT 
        SUM(si.quantity) as total_sold,
        SUM(si.subtotal) as total_revenue,
        COUNT(DISTINCT si.sale_id) as transactions
    FROM sale_items si
    WHERE si.product_id = ?
");
$stats_stmt->bind_param("i", $product_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$total_sold = $stats['total_sold'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0;
$profit = ($total_sold * $product['price']) - ($total_sold * ($product['cost_price'] ?? 0));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Product - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .product-image {
            max-height: 300px;
            width: auto;
            object-fit: contain;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        .stats-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .price-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
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
                    <h1 class="h2">Product Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="edit_product.php?id=<?php echo $product_id; ?>" class="btn btn-warning me-2">
                            <i class="fas fa-edit"></i> Edit Product
                        </a>
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                    </div>
                </div>

                <div class="row mb-4">
                    <!-- Product Image and Basic Information -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <?php if(!empty($product['image_path'])): ?>
                                    <img src="../<?php echo $product['image_path']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image img-fluid mb-3">
                                <?php else: ?>
                                    <img src="../assets/images/no-image.jpg" alt="No Image" class="product-image img-fluid mb-3">
                                <?php endif; ?>
                                
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="price-section mt-3 mb-3">
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-1 detail-label">Selling Price</p>
                                            <h4 class="text-primary">₱<?php echo number_format($product['price'], 2); ?></h4>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-1 detail-label">Cost Price</p>
                                            <h4 class="text-secondary">₱<?php echo number_format($product['cost_price'] ?? 0, 2); ?></h4>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php 
                                $qty = $product['quantity'] ?? 0;
                                $stockClass = $qty > 10 ? 'success' : ($qty > 0 ? 'warning' : 'danger');
                                $stockText = $qty > 10 ? 'In Stock' : ($qty > 0 ? 'Low Stock' : 'Out of Stock');
                                ?>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-<?php echo $stockClass; ?>">
                                        <i class="fas fa-box"></i> <?php echo $stockText; ?>: <?php echo $qty; ?> units
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Details -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Product Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="detail-label mb-1">Barcode</p>
                                        <p><?php echo htmlspecialchars($product['barcode'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="detail-label mb-1">Category</p>
                                        <p><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="detail-label mb-1">Supplier</p>
                                        <p><?php echo htmlspecialchars($product['supplier'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="detail-label mb-1">Created On</p>
                                        <p><?php echo date('F j, Y', strtotime($product['created_at'])); ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="detail-label mb-1">Description</p>
                                    <p><?php echo htmlspecialchars($product['description'] ?? 'No description available.'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product Stats -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card stats-card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted">Units Sold</h6>
                                        <h2><?php echo number_format($total_sold); ?></h2>
                                        <p class="text-muted">All time</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card stats-card h-100" style="border-left-color: #28a745;">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted">Total Revenue</h6>
                                        <h2>₱<?php echo number_format($total_revenue, 2); ?></h2>
                                        <p class="text-muted">From <?php echo $stats['transactions'] ?? 0; ?> sales</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card stats-card h-100" style="border-left-color: #dc3545;">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted">Estimated Profit</h6>
                                        <h2>₱<?php echo number_format($profit, 2); ?></h2>
                                        <p class="text-muted">Based on cost price</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sales History -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Sales</h5>
                            </div>
                            <div class="card-body">
                                <?php if($sales_history->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Invoice</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($sale = $sales_history->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($sale['created_at'])); ?></td>
                                                <td><?php echo $sale['invoice_number']; ?></td>
                                                <td><?php echo $sale['quantity']; ?></td>
                                                <td>₱<?php echo number_format($sale['price'], 2); ?></td>
                                                <td>₱<?php echo number_format($sale['subtotal'], 2); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    No sales history found for this product.
                                </div>
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
