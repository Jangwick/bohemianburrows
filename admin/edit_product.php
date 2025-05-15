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

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = $_POST['name'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $cost_price = $_POST['cost_price'];
    $barcode = $_POST['barcode'];
    $supplier = $_POST['supplier'];
    $quantity = $_POST['quantity'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Handle image upload
        $image_path = $product['image_path']; // Default to existing image
        
        if(isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload_dir = "../uploads/products/";
            
            // Create directory if it doesn't exist
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $unique_filename = "product_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;
            $upload_path = $upload_dir . $unique_filename;
            
            // Move the uploaded file
            if(move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Delete old image if it exists
                if(!empty($product['image_path']) && file_exists("../" . $product['image_path'])) {
                    unlink("../" . $product['image_path']);
                }
                
                $image_path = "uploads/products/" . $unique_filename;
            }
        }
        
        // Update product
        $update_stmt = $conn->prepare("
            UPDATE products 
            SET name = ?, description = ?, category = ?, price = ?, cost_price = ?, 
                barcode = ?, supplier = ?, image_path = ?
            WHERE id = ?
        ");
        $update_stmt->bind_param("sssddsssi", $name, $description, $category, $price, $cost_price, $barcode, $supplier, $image_path, $product_id);
        $update_stmt->execute();
        
        // Update inventory
        $check_stmt = $conn->prepare("SELECT product_id FROM inventory WHERE product_id = ?");
        $check_stmt->bind_param("i", $product_id);
        $check_stmt->execute();
        $inventory_result = $check_stmt->get_result();
        
        if($inventory_result->num_rows > 0) {
            // Update existing inventory record
            $inventory_stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE product_id = ?");
            $inventory_stmt->bind_param("ii", $quantity, $product_id);
            $inventory_stmt->execute();
        } else {
            // Insert new inventory record
            $inventory_stmt = $conn->prepare("INSERT INTO inventory (product_id, quantity) VALUES (?, ?)");
            $inventory_stmt->bind_param("ii", $product_id, $quantity);
            $inventory_stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        $success = "Product updated successfully.";
    } catch(Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = "Error updating product: " . $e->getMessage();
    }
    
    // Get updated product data
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
}

// Get all unique categories for dropdown
$categories = [];
$category_query = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
while($row = $category_query->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Get all unique suppliers for dropdown
$suppliers = [];
$supplier_query = $conn->query("SELECT DISTINCT supplier FROM products WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier");
while($row = $supplier_query->fetch_assoc()) {
    $suppliers[] = $row['supplier'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .preview-image {
            max-height: 200px;
            max-width: 100%;
            margin-top: 10px;
            border-radius: 5px;
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
                    <h1 class="h2">Edit Product</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="view_product.php?id=<?php echo $product_id; ?>" class="btn btn-info me-2">
                            <i class="fas fa-eye"></i> View Product
                        </a>
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                    </div>
                </div>

                <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <div class="row">
                                <!-- Left Column -->
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Product Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="price" class="form-label">Selling Price *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo $product['price']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="cost_price" class="form-label">Cost Price</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="cost_price" name="cost_price" step="0.01" min="0" value="<?php echo $product['cost_price'] ?? 0; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="category" class="form-label">Category</label>
                                            <input type="text" class="form-control" id="category" name="category" list="categoryOptions" value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>">
                                            <datalist id="categoryOptions">
                                                <?php foreach($categories as $category): ?>
                                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="barcode" class="form-label">Barcode / SKU</label>
                                            <input type="text" class="form-control" id="barcode" name="barcode" value="<?php echo htmlspecialchars($product['barcode'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="supplier" class="form-label">Supplier</label>
                                            <input type="text" class="form-control" id="supplier" name="supplier" list="supplierOptions" value="<?php echo htmlspecialchars($product['supplier'] ?? ''); ?>">
                                            <datalist id="supplierOptions">
                                                <?php foreach($suppliers as $supplier): ?>
                                                    <option value="<?php echo htmlspecialchars($supplier); ?>">
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="quantity" class="form-label">Stock Quantity</label>
                                            <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="<?php echo $product['quantity'] ?? 0; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Right Column - Image Upload -->
                                <div class="col-md-4">
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <h5 class="card-title">Product Image</h5>
                                            <div class="mb-3">
                                                <?php if(!empty($product['image_path'])): ?>
                                                    <div class="text-center mb-3">
                                                        <img src="../<?php echo $product['image_path']; ?>" alt="Current Product Image" class="img-fluid preview-image">
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <label for="image" class="form-label">Update Image</label>
                                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                                <div class="form-text">Leave empty to keep current image</div>
                                                
                                                <div id="imagePreviewContainer" class="text-center mt-2" style="display: none;">
                                                    <img id="imagePreview" class="preview-image" alt="Image Preview">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            <div class="d-flex justify-content-between">
                                <a href="products.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Product</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview functionality
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                const imagePreview = document.getElementById('imagePreview');
                const previewContainer = document.getElementById('imagePreviewContainer');
                
                reader.onload = function(event) {
                    imagePreview.src = event.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
