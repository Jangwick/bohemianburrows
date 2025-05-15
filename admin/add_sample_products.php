<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Check if form was submitted
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $num_products = isset($_POST['num_products']) ? intval($_POST['num_products']) : 10;
    
    // Ensure we have a valid number (between 1 and 100)
    $num_products = max(1, min(100, $num_products));
    
    // Create uploads directory if it doesn't exist
    $upload_dir = "../uploads/products/";
    if(!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Check if GD library is available
    $gd_available = function_exists('imagecreate');
    
    // Generate sample products
    $products_added = 0;
    $categories = ['Clothing', 'Accessories', 'Jewelry', 'Home Decor', 'Art', 'Vintage', 'Handmade'];
    
    for($i = 1; $i <= $num_products; $i++) {
        $name = "Sample Product " . rand(100, 999);
        $description = "This is a sample product description for " . $name;
        $price = rand(50, 5000) / 10; // Random price between 5.0 and 500.0
        $category = $categories[array_rand($categories)];
        $barcode = "BB" . str_pad(rand(1000, 9999), 8, "0", STR_PAD_LEFT);
        
        // Generate a random color image if GD is available, otherwise use a placeholder
        $image_path = '';
        if($gd_available) {
            // GD library is available, generate a random color image
            $width = 400;
            $height = 400;
            $image = imagecreate($width, $height);
            
            // Create a random color
            $r = rand(100, 255);
            $g = rand(100, 255);
            $b = rand(100, 255);
            $color = imagecolorallocate($image, $r, $g, $b);
            
            // Fill the image
            imagefill($image, 0, 0, $color);
            
            // Add some text
            $text_color = imagecolorallocate($image, 255 - $r, 255 - $g, 255 - $b);
            $text = substr($name, 0, 2);
            imagestring($image, 5, $width/2 - 10, $height/2 - 10, $text, $text_color);
            
            // Save the image
            $filename = "product_" . time() . "_" . $i . ".png";
            $filepath = $upload_dir . $filename;
            imagepng($image, $filepath);
            imagedestroy($image);
            
            $image_path = "uploads/products/" . $filename;
        } else {
            // GD library is not available, use placeholder or external image service
            // Option 1: Use an existing placeholder in assets folder
            $image_path = "assets/images/product-placeholder.png";
            
            // Option 2: Use a placeholder service like Placeholder.com
            // $image_path = "https://via.placeholder.com/400x400.png?text=" . urlencode(substr($name, 0, 2));
        }
        
        // Insert product into database
        $stmt = $conn->prepare("INSERT INTO products (name, description, price, category, image_path, barcode) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsss", $name, $description, $price, $category, $image_path, $barcode);
        
        if($stmt->execute()) {
            $product_id = $conn->insert_id;
            
            // Add to inventory with random quantity
            $quantity = rand(0, 50);
            $inv_stmt = $conn->prepare("INSERT INTO inventory (product_id, quantity) VALUES (?, ?)");
            $inv_stmt->bind_param("ii", $product_id, $quantity);
            $inv_stmt->execute();
            
            $products_added++;
        }
    }
    
    // Redirect back with success message
    header("Location: dashboard.php?msg=Added " . $products_added . " sample products successfully.");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Sample Products - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Add Sample Products</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="inventory.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Inventory
                        </a>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 offset-md-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Generate Sample Products</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!function_exists('imagecreate')): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Warning: The GD library is not enabled in your PHP installation. Sample products will be created with placeholder images instead of unique generated images.
                                </div>
                                <?php endif; ?>
                                
                                <p>This utility will generate random sample products for testing purposes. Please specify how many sample products you want to create:</p>
                                
                                <form method="post" action="">
                                    <div class="mb-3">
                                        <label for="num_products" class="form-label">Number of Products</label>
                                        <input type="number" class="form-control" id="num_products" name="num_products" value="10" min="1" max="100">
                                        <div class="form-text">Enter a number between 1 and 100.</div>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Generate Sample Products</button>
                                    </div>
                                </form>
                            </div>
                            <div class="card-footer text-muted">
                                <small>Note: This is intended for testing purposes only. Please don't use this in a production environment with real data.</small>
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
