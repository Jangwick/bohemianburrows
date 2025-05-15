<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Get categories for filter
$cat_stmt = $conn->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
$cat_stmt->execute();
$categories = $cat_stmt->get_result();

// Get products with filtering
$where = "1=1";
$params = array();
$types = "";

if(isset($_GET['category']) && !empty($_GET['category'])) {
    $where .= " AND p.category = ?";
    $params[] = $_GET['category'];
    $types .= "s";
}

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

// Add price range filter
if(isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $where .= " AND p.price >= ?";
    $params[] = $_GET['min_price'];
    $types .= "d";
}

if(isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $where .= " AND p.price <= ?";
    $params[] = $_GET['max_price'];
    $types .= "d";
}

// Pagination setup
$limit = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE $where AND (i.quantity > 0 OR i.quantity IS NULL)
";
$count_stmt = $conn->prepare($count_sql);
if(!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);

// Get products with stock information - only show products with stock
$sql = "
    SELECT p.*, i.quantity 
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE $where AND (i.quantity > 0 OR i.quantity IS NULL)
    ORDER BY p.name
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $start;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .product-card {
            transition: all 0.3s ease;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        .price {
            font-size: 1.25rem;
            font-weight: 600;
            color: #6d4c41;
        }
        .shop-header {
            background: linear-gradient(135deg, #8d6e63, #6d4c41);
            padding: 40px 0;
            color: white;
            margin-bottom: 30px;
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .bohemian-divider {
            height: 3px;
            width: 60%;
            margin: 1rem auto;
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
        .badge-stock {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
        }
        .wishlist-icon {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 2;
            background-color: white;
            border-radius: 50%;
            padding: 8px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        .wishlist-icon:hover {
            transform: scale(1.1);
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
                <!-- Shop Header -->
                <div class="shop-header text-center">
                    <h1>The Bohemian Collection</h1>
                    <p class="lead">Discover unique pieces that speak to your free spirit</p>
                    <div class="bohemian-divider"></div>
                    <p>Find the perfect additions to your bohemian lifestyle</p>
                </div>
                
                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Search products..." name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php while($category = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $category['category']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $category['category']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control" placeholder="Min Price" name="min_price" value="<?php echo isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control" placeholder="Max Price" name="max_price" value="<?php echo isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : ''; ?>">
                        </div>
                        <div class="col-md-1">
                            <?php if(isset($_GET['search']) || isset($_GET['category']) || isset($_GET['min_price']) || isset($_GET['max_price'])): ?>
                                <a href="shop.php" class="btn btn-secondary w-100">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Products Grid -->
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 mb-4">
                    <?php if($products->num_rows > 0): ?>
                        <?php while($product = $products->fetch_assoc()): ?>
                            <div class="col">
                                <div class="card product-card position-relative">
                                    <!-- Wishlist Icon -->
                                    <div class="wishlist-icon add-to-wishlist" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="far fa-heart"></i>
                                    </div>
                                    
                                    <!-- Stock Badge -->
                                    <?php if(($product['quantity'] ?? 0) <= 5 && ($product['quantity'] ?? 0) > 0): ?>
                                        <span class="badge bg-warning badge-stock">Only <?php echo $product['quantity']; ?> left!</span>
                                    <?php endif; ?>
                                    
                                    <a href="product_details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                                        <?php if(!empty($product['image_path'])): ?>
                                            <img src="../<?php echo $product['image_path']; ?>" class="card-img-top product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <img src="../assets/images/product-placeholder.png" class="card-img-top product-image" alt="Product Image">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                            <p class="card-text text-muted small">
                                                <?php
                                                if (!empty($product['description'])) {
                                                    echo (strlen($product['description']) > 60) ? 
                                                        htmlspecialchars(substr($product['description'], 0, 60)) . '...' : 
                                                        htmlspecialchars($product['description']);
                                                } else {
                                                    echo 'No description available';
                                                }
                                                ?>
                                            </p>
                                            <p class="price">₱<?php echo number_format($product['price'], 2); ?></p>
                                        </div>
                                    </a>
                                    <div class="card-footer bg-white border-top-0 text-center">
                                        <button class="btn btn-outline-primary add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                            <i class="fas fa-shopping-cart me-1"></i> Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <div class="alert alert-info">
                                <i class="fas fa-exclamation-circle me-2"></i> No products found matching your criteria.
                            </div>
                            <a href="shop.php" class="btn btn-primary">View All Products</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="my-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo isset($_GET['category']) ? '&category=' . urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['min_price']) ? '&min_price=' . urlencode($_GET['min_price']) : ''; ?><?php echo isset($_GET['max_price']) ? '&max_price=' . urlencode($_GET['max_price']) : ''; ?>">
                                Previous
                            </a>
                        </li>
                        
                        <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['category']) ? '&category=' . urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['min_price']) ? '&min_price=' . urlencode($_GET['min_price']) : ''; ?><?php echo isset($_GET['max_price']) ? '&max_price=' . urlencode($_GET['max_price']) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo isset($_GET['category']) ? '&category=' . urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['min_price']) ? '&min_price=' . urlencode($_GET['min_price']) : ''; ?><?php echo isset($_GET['max_price']) ? '&max_price=' . urlencode($_GET['max_price']) : ''; ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add to Cart Toast Notification -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="cartToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="fas fa-shopping-cart me-2 text-success"></i>
                <strong class="me-auto">Added to Cart</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                Item has been added to your shopping cart!
                <div class="mt-2 pt-2 border-top">
                    <a href="cart.php" class="btn btn-primary btn-sm">View Cart</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Wishlist Toast Notification -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="wishlistToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="fas fa-heart me-2 text-danger"></i>
                <strong class="me-auto">Wishlist Updated</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                Your wishlist has been updated!
                <div class="mt-2 pt-2 border-top">
                    <a href="wishlist.php" class="btn btn-primary btn-sm">View Wishlist</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cartToast = new bootstrap.Toast(document.getElementById('cartToast'));
        const wishlistToast = new bootstrap.Toast(document.getElementById('wishlistToast'));
        
        // Add to cart functionality
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.dataset.productId;
                
                // AJAX call to add item to cart
                fetch('../ajax/add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'product_id=' + productId + '&quantity=1'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show toast notification
                        cartToast.show();
                        
                        // Optional: Update cart count in the navbar
                        const cartCountElement = document.getElementById('cart-count');
                        if (cartCountElement) {
                            cartCountElement.textContent = data.cart_count;
                        }
                    } else {
                        alert(data.message || 'Failed to add item to cart.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });
        
        // Wishlist functionality
        document.querySelectorAll('.add-to-wishlist').forEach(icon => {
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const productId = this.dataset.productId;
                const heartIcon = this.querySelector('i');
                
                // Toggle heart icon
                if (heartIcon.classList.contains('far')) {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                    heartIcon.style.color = '#dc3545'; // Red color
                } else {
                    heartIcon.classList.remove('fas');
                    heartIcon.classList.add('far');
                    heartIcon.style.color = ''; // Reset color
                }
                
                // AJAX call to toggle wishlist item
                fetch('../ajax/toggle_wishlist.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        wishlistToast.show();
                    } else {
                        alert(data.message || 'Failed to update wishlist.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });
    });
    </script>
</body>
</html>
