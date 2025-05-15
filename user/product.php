<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Get product ID from URL
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: shop.php");
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
    header("Location: shop.php");
    exit;
}

$product = $result->fetch_assoc();

// Get related products
$cat_stmt = $conn->prepare("
    SELECT p.*, i.quantity 
    FROM products p 
    LEFT JOIN inventory i ON p.id = i.product_id 
    WHERE p.category = ? AND p.id != ? AND i.quantity > 0
    LIMIT 4
");
$cat_stmt->bind_param("si", $product['category'], $product_id);
$cat_stmt->execute();
$related_products = $cat_stmt->get_result();

// Check if product is in user's wishlist
$wishlist_stmt = $conn->prepare("
    SELECT COUNT(*) as is_in_wishlist 
    FROM wishlist 
    WHERE user_id = ? AND product_id = ?
");
$wishlist_stmt->bind_param("ii", $_SESSION['user_id'], $product_id);
$wishlist_stmt->execute();
$is_in_wishlist = $wishlist_stmt->get_result()->fetch_assoc()['is_in_wishlist'] > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .product-image {
            width: 100%;
            height: 400px;
            object-fit: contain;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .product-price {
            font-size: 24px;
            font-weight: bold;
            color: #6d4c41;
        }
        .stock-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .in-stock {
            background-color: #28a745;
        }
        .low-stock {
            background-color: #ffc107;
        }
        .out-of-stock {
            background-color: #dc3545;
        }
        .related-product {
            transition: transform 0.3s;
        }
        .related-product:hover {
            transform: translateY(-5px);
        }
        .related-product-img {
            height: 150px;
            object-fit: cover;
        }
        .breadcrumb a {
            text-decoration: none;
        }
        .wishlist-btn.active {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        .wishlist-btn.active i {
            color: white;
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
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="my-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="shop.php">Shop</a></li>
                        <?php if($product['category']): ?>
                            <li class="breadcrumb-item"><a href="shop.php?category=<?php echo urlencode($product['category']); ?>"><?php echo htmlspecialchars($product['category']); ?></a></li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
                    </ol>
                </nav>

                <div class="row mb-5">
                    <!-- Product Image -->
                    <div class="col-md-6 mb-4">
                        <img src="<?php echo !empty($product['image_path']) ? '../' . $product['image_path'] : '../assets/images/product-placeholder.png'; ?>" 
                             class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    
                    <!-- Product Details -->
                    <div class="col-md-6">
                        <h1 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
                        
                        <div class="mb-3">
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></span>
                            <?php 
                            $qty = $product['quantity'] ?? 0;
                            $stockClass = $qty > 10 ? 'in-stock' : ($qty > 0 ? 'low-stock' : 'out-of-stock');
                            ?>
                            <span class="ms-2">
                                <span class="stock-status <?php echo $stockClass; ?>"></span>
                                <?php 
                                    if($qty > 10) echo 'In Stock';
                                    elseif($qty > 0) echo 'Low Stock';
                                    else echo 'Out of Stock';
                                ?>
                            </span>
                        </div>
                        
                        <div class="product-price mb-3">
                            ₱<?php echo number_format($product['price'], 2); ?>
                        </div>
                        
                        <div class="mb-4">
                            <?php if(!empty($product['description'])): ?>
                                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                            <?php else: ?>
                                <p class="text-muted">No description available.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Actions -->
                        <div class="mb-3">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <button class="btn btn-primary w-100 <?php echo ($qty <= 0) ? 'disabled' : ''; ?>" 
                                            onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>)">
                                        <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button class="btn btn-outline-danger w-100 wishlist-btn <?php echo $is_in_wishlist ? 'active' : ''; ?>" 
                                            data-id="<?php echo $product['id']; ?>" 
                                            onclick="toggleWishlist(this, <?php echo $product['id']; ?>)">
                                        <i class="<?php echo $is_in_wishlist ? 'fas' : 'far'; ?> fa-heart me-2"></i> Wishlist
                                    </button>
                                </div>
                                <?php if($product['barcode']): ?>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body p-2 text-center">
                                            <small class="d-block text-muted">Barcode</small>
                                            <span><?php echo htmlspecialchars($product['barcode']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Related Products -->
                <?php if($related_products->num_rows > 0): ?>
                <div class="mt-5">
                    <h3 class="mb-4">Related Products</h3>
                    <div class="row row-cols-1 row-cols-md-4 g-4">
                        <?php while($related = $related_products->fetch_assoc()): ?>
                            <div class="col">
                                <div class="card related-product h-100">
                                    <img src="<?php echo !empty($related['image_path']) ? '../' . $related['image_path'] : '../assets/images/product-placeholder.png'; ?>" 
                                         class="card-img-top related-product-img" alt="<?php echo htmlspecialchars($related['name']); ?>">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($related['name']); ?></h5>
                                        <p class="card-text fw-bold">₱<?php echo number_format($related['price'], 2); ?></p>
                                    </div>
                                    <div class="card-footer bg-transparent border-top-0">
                                        <a href="product.php?id=<?php echo $related['id']; ?>" class="btn btn-sm btn-outline-primary w-100">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleWishlist(button, productId) {
            const isActive = button.classList.contains('active');
            const action = isActive ? 'remove' : 'add';
            
            fetch(`../ajax/${action}_from_wishlist.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ product_id: productId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (isActive) {
                        button.classList.remove('active');
                        button.innerHTML = '<i class="far fa-heart me-2"></i> Wishlist';
                    } else {
                        button.classList.add('active');
                        button.innerHTML = '<i class="fas fa-heart me-2"></i> Wishlist';
                    }
                } else {
                    alert(data.message || 'An error occurred');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        function addToCart(productId, productName, productPrice) {
            // Send to cart service
            fetch('../ajax/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    product_id: productId,
                    product_name: productName,
                    price: productPrice,
                    quantity: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product added to cart!');
                } else {
                    alert(data.message || 'Could not add to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    </script>
</body>
</html>
