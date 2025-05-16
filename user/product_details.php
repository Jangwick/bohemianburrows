<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Get product ID
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($product_id <= 0) {
    header("Location: shop.php");
    exit;
}

// Get product details
$stmt = $conn->prepare("
    SELECT p.*, i.quantity as stock 
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

// Check if product is in the user's wishlist - modified with error handling
$is_in_wishlist = false;
if(isset($_SESSION['user_id'])) {
    try {
        $wishlist_check = $conn->prepare("
            SELECT id 
            FROM wishlist 
            WHERE user_id = ? AND product_id = ?
        ");
        $wishlist_check->bind_param("ii", $_SESSION['user_id'], $product_id);
        $wishlist_check->execute();
        $wishlist_result = $wishlist_check->get_result();
        $is_in_wishlist = $wishlist_result->num_rows > 0;
    } catch (mysqli_sql_exception $e) {
        // If the query fails due to missing table, just set is_in_wishlist to false (already set)
        // Check if it's specifically a "table doesn't exist" error
        if (strpos($e->getMessage(), "doesn't exist") !== false) {
            // Redirect to database update utility
            $_SESSION['wishlist_error'] = "The wishlist feature needs setup. Please run the database update first.";
            header("Location: ../database_update.php");
            exit;
        }
    }
}

// Get related products (same category, excluding current)
$related_stmt = $conn->prepare("
    SELECT p.*, i.quantity as stock
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE p.category = ? AND p.id != ? AND (i.quantity > 0 OR i.quantity IS NULL)
    LIMIT 4
");
$related_stmt->bind_param("si", $product['category'], $product_id);
$related_stmt->execute();
$related_products = $related_stmt->get_result();

// Handle wishlist actions - modified with error handling
if(isset($_POST['action'])) {
    // Check if the user is logged in
    if(!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = "Please log in to add items to your wishlist.";
        header("Location: login.php");
        exit;
    }
    
    try {
        if($_POST['action'] == 'add_to_wishlist') {
            // Check if already in wishlist
            $check_stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
            $check_stmt->bind_param("ii", $_SESSION['user_id'], $product_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if($check_result->num_rows == 0) {
                // Not in wishlist, add it
                $add_stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
                $add_stmt->bind_param("ii", $_SESSION['user_id'], $product_id);
                $add_stmt->execute();
                $is_in_wishlist = true;
                $_SESSION['success_message'] = "Product added to your wishlist!";
            } else {
                $_SESSION['error_message'] = "This product is already in your wishlist.";
            }
            header("Location: product_details.php?id=$product_id");
            exit;
        } elseif($_POST['action'] == 'remove_from_wishlist') {
            // Remove from wishlist
            $remove_stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $remove_stmt->bind_param("ii", $_SESSION['user_id'], $product_id);
            $remove_stmt->execute();
            $is_in_wishlist = false;
            $_SESSION['success_message'] = "Product removed from your wishlist.";
            header("Location: product_details.php?id=$product_id");
            exit;
        }
    } catch (mysqli_sql_exception $e) {
        // If there's an error, redirect to database update
        $_SESSION['wishlist_error'] = "The wishlist feature needs setup. Please run the database update first.";
        header("Location: ../database_update.php");
        exit;
    }
}
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
            max-height: 400px;
            object-fit: contain;
        }
        .price {
            font-size: 1.75rem;
            font-weight: 600;
            color: #6d4c41;
        }
        .quantity-selector {
            max-width: 120px;
        }
        .related-product-card {
            transition: all 0.3s ease;
        }
        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .related-product-image {
            height: 150px;
            object-fit: cover;
        }
        .nav-tabs .nav-link.active {
            color: #6d4c41;
            border-color: #6d4c41;
            border-bottom-color: transparent;
        }
        .nav-tabs .nav-link:not(.active) {
            color: #8d6e63;
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Product Details -->
                <div class="py-5">
                    <nav aria-label="breadcrumb" class="mb-4">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="shop.php">Shop</a></li>
                            <li class="breadcrumb-item"><a href="shop.php?category=<?php echo urlencode($product['category']); ?>"><?php echo htmlspecialchars($product['category']); ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
                        </ol>
                    </nav>
                
                    <div class="row">
                        <!-- Product Image -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <?php if(!empty($product['image_path'])): ?>
                                        <img src="../<?php echo $product['image_path']; ?>" class="img-fluid product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <img src="../assets/images/product-placeholder.png" class="img-fluid product-image" alt="Product Image">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product Info -->
                        <div class="col-md-6">
                            <h1 class="h2 mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                            <p class="text-muted"><?php echo htmlspecialchars($product['category']); ?></p>
                            
                            <div class="bohemian-divider" style="margin: 1rem 0;"></div>
                            
                            <p class="price mb-3">₱<?php echo number_format($product['price'], 2); ?></p>
                            
                            <?php if(($product['stock'] ?? 0) > 0): ?>
                                <p class="text-success mb-4">
                                    <i class="fas fa-check-circle me-1"></i> In Stock (<?php echo $product['stock']; ?> available)
                                </p>
                            <?php else: ?>
                                <p class="text-danger mb-4">
                                    <i class="fas fa-times-circle me-1"></i> Out of Stock
                                </p>
                            <?php endif; ?>
                            
                            <form id="add-to-cart-form" class="mb-4">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                
                                <div class="mb-3">
                                    <label for="quantity" class="form-label fw-bold">Quantity</label>
                                    <div class="input-group quantity-selector">
                                        <button type="button" class="btn btn-outline-secondary" id="decrease-quantity">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="form-control text-center" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock'] ?? 1; ?>" <?php echo ($product['stock'] ?? 0) <= 0 ? 'disabled' : ''; ?>>
                                        <button type="button" class="btn btn-outline-secondary" id="increase-quantity">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-block mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg" <?php echo ($product['stock'] ?? 0) <= 0 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-shopping-cart me-1"></i> Add to Cart
                                    </button>
                                    <button type="button" id="add-to-wishlist" class="btn btn-outline-danger btn-lg">
                                        <i class="<?php echo $is_in_wishlist ? 'fas' : 'far'; ?> fa-heart me-1"></i> Wishlist
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Product Short Details -->
                            <div class="mb-4">
                                <h5 class="mb-3">Product Details</h5>
                                <p><?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabs for reviews, etc. -->
                    <div class="row mt-5">
                        <div class="col-12">
                            <ul class="nav nav-tabs" id="productTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab" aria-controls="description" aria-selected="true">Description</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="false">Details</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" aria-controls="reviews" aria-selected="false">Reviews</button>
                                </li>
                            </ul>
                            <div class="tab-content p-4 bg-white border border-top-0 rounded-bottom" id="productTabsContent">
                                <div class="tab-pane fade show active" id="description" role="tabpanel" aria-labelledby="description-tab">
                                    <p><?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?></p>
                                </div>
                                <div class="tab-pane fade" id="details" role="tabpanel" aria-labelledby="details-tab">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span>Product Code</span>
                                                    <span class="fw-bold"><?php echo htmlspecialchars($product['barcode'] ?? 'N/A'); ?></span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span>Category</span>
                                                    <span class="fw-bold"><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span>Supplier</span>
                                                    <span class="fw-bold"><?php echo htmlspecialchars($product['supplier'] ?? 'N/A'); ?></span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                                    <p class="text-center py-5 text-muted">No reviews yet for this product.</p>
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
                                    <div class="card h-100 related-product-card">
                                        <a href="product_details.php?id=<?php echo $related['id']; ?>" class="text-decoration-none">
                                            <?php if(!empty($related['image_path'])): ?>
                                                <img src="../<?php echo $related['image_path']; ?>" class="card-img-top related-product-image" alt="<?php echo htmlspecialchars($related['name']); ?>">
                                            <?php else: ?>
                                                <img src="../assets/images/product-placeholder.png" class="card-img-top related-product-image" alt="Product Image">
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($related['name']); ?></h5>
                                                <p class="card-text price">₱<?php echo number_format($related['price'], 2); ?></p>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
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
            <div class="toast-body" id="wishlistToastMessage">
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
        const wishlistHeartIcon = document.querySelector('#add-to-wishlist i');
        const wishlistToastMessage = document.getElementById('wishlistToastMessage');
        
        // Quantity selector
        document.getElementById('decrease-quantity').addEventListener('click', function() {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        });
        
        document.getElementById('increase-quantity').addEventListener('click', function() {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            const maxValue = parseInt(quantityInput.max);
            if (currentValue < maxValue) {
                quantityInput.value = currentValue + 1;
            }
        });
        
        // Add to cart form submission
        document.getElementById('add-to-cart-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const productId = document.querySelector('input[name="product_id"]').value;
            const quantity = document.getElementById('quantity').value;
            
            // AJAX call to add item to cart
            fetch('../ajax/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cartToast.show();
                    
                    // Update cart count in the sidebar
                    const cartCountElement = document.querySelector('.nav-link .badge');
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
        
        // Toggle wishlist
        document.getElementById('add-to-wishlist').addEventListener('click', function() {
            const productId = document.querySelector('input[name="product_id"]').value;
            
            // AJAX call to toggle wishlist
            fetch('../ajax/toggle_wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Toggle heart icon
                    if (data.in_wishlist) {
                        wishlistHeartIcon.classList.remove('far');
                        wishlistHeartIcon.classList.add('fas');
                        wishlistToastMessage.innerHTML = 'Product added to your wishlist!<div class="mt-2 pt-2 border-top"><a href="wishlist.php" class="btn btn-primary btn-sm">View Wishlist</a></div>';
                    } else {
                        wishlistHeartIcon.classList.remove('fas');
                        wishlistHeartIcon.classList.add('far');
                        wishlistToastMessage.innerHTML = 'Product removed from your wishlist!<div class="mt-2 pt-2 border-top"><a href="wishlist.php" class="btn btn-primary btn-sm">View Wishlist</a></div>';
                    }
                    
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
    </script>
</body>
</html>
