<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Create wishlist table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id)
)";
$conn->query($create_table);

// Handle wishlist item removal
if(isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $product_id = $_GET['remove'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    
    // Set notification
    $_SESSION['wishlist_message'] = "Item removed from your wishlist.";
    header("Location: wishlist.php");
    exit;
}

// Get user's wishlist items with product details
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT w.id AS wishlist_id, p.*, i.quantity AS stock, w.created_at 
    FROM wishlists w 
    JOIN products p ON w.product_id = p.id 
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE w.user_id = ? 
    ORDER BY w.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .wishlist-header {
            background: linear-gradient(135deg, #8d6e63, #6d4c41);
            color: white;
            padding: 40px 0;
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
        .product-card {
            transition: all 0.3s;
            height: 100%;
            border: none;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 200px;
            object-fit: cover;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
        }
        .product-title {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #444;
            height: 2.5rem;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .wishlist-date {
            color: #888;
            font-size: 0.8rem;
        }
        .out-of-stock-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 200px; /* Match image height */
            background-color: rgba(0,0,0,0.5);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
        }
        .price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .btn-wishlist-remove {
            color: #dc3545;
            background: none;
            border: none;
            padding: 0;
            font-size: 1.2rem;
            transition: all 0.2s;
        }
        .btn-wishlist-remove:hover {
            color: #c82333;
            transform: scale(1.1);
        }
        .wishlist-empty {
            text-align: center;
            padding: 3rem;
        }
        .wishlist-empty i {
            font-size: 5rem;
            color: #d7ccc8;
            margin-bottom: 1rem;
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
                <!-- Wishlist Header -->
                <div class="wishlist-header text-center">
                    <h1>My Wishlist</h1>
                    <p class="lead">Items you've saved for later</p>
                    <div class="bohemian-divider"></div>
                </div>
                
                <?php if(isset($_SESSION['wishlist_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['wishlist_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['wishlist_message']); ?>
                <?php endif; ?>
                
                <?php if($result->num_rows > 0): ?>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                        <?php while($product = $result->fetch_assoc()): ?>
                            <div class="col">
                                <div class="card product-card">
                                    <div class="position-relative">
                                        <?php 
                                        $image_path = !empty($product['image_path']) ? '../' . $product['image_path'] : '../assets/images/product-placeholder.png';
                                        $out_of_stock = ($product['stock'] ?? 0) <= 0;
                                        ?>
                                        <img src="<?php echo $image_path; ?>" class="product-image w-100" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php if($out_of_stock): ?>
                                            <div class="out-of-stock-overlay">OUT OF STOCK</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <p class="wishlist-date">
                                            <i class="far fa-clock me-1"></i> Added <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                                        </p>
                                        <div class="price-row mt-auto">
                                            <span class="fw-bold text-primary">₱<?php echo number_format($product['price'], 2); ?></span>
                                            <button type="button" class="btn-wishlist-remove" 
                                                data-bs-toggle="tooltip" 
                                                title="Remove from wishlist"
                                                onclick="confirmRemove(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-heart-broken"></i>
                                            </button>
                                        </div>
                                        <div class="d-grid gap-2 mt-3">
                                            <a href="product_details.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-search me-1"></i> View Details
                                            </a>
                                            <?php if(!$out_of_stock): ?>
                                                <button type="button" class="btn btn-primary btn-sm add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                                    <i class="fas fa-shopping-cart me-1"></i> Add to Cart
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary btn-sm" disabled>
                                                    <i class="fas fa-ban me-1"></i> Out of Stock
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="wishlist-empty">
                        <i class="far fa-heart"></i>
                        <h3>Your wishlist is empty</h3>
                        <p class="text-muted">Save items you like while browsing our collections.</p>
                        <a href="shop.php" class="btn btn-primary mt-3">
                            <i class="fas fa-shopping-bag me-2"></i> Explore Products
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Remove Confirmation Modal -->
    <div class="modal fade" id="removeModal" tabindex="-1" aria-labelledby="removeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeModalLabel">Remove from Wishlist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to remove this item from your wishlist?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmRemoveBtn" class="btn btn-danger">Remove</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => {
                new bootstrap.Tooltip(tooltip);
            });
            
            // Add to cart buttons
            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    addToCart(productId);
                });
            });
        });
        
        // Function to confirm removal
        function confirmRemove(productId) {
            // Set the link for the confirm button
            document.getElementById('confirmRemoveBtn').href = `wishlist.php?remove=${productId}`;
            // Show the modal
            new bootstrap.Modal(document.getElementById('removeModal')).show();
        }
        
        // Function to add item to cart
        function addToCart(productId) {
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Item added to your cart!');
                    
                    // Optional: Redirect to cart
                    // window.location.href = 'cart.php';
                } else {
                    alert(data.message || 'Error adding item to cart.');
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
