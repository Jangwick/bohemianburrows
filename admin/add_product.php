<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

$success = '';
$error = '';

// Get all categories and suppliers for dropdowns
$categories = [];
$category_query = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
while($row = $category_query->fetch_assoc()) {
    $categories[] = $row['category'];
}

$suppliers = [];
$supplier_query = $conn->query("SELECT DISTINCT supplier FROM products WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier");
while($row = $supplier_query->fetch_assoc()) {
    $suppliers[] = $row['supplier'];
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate input
    $name = trim($_POST['name']);
    $barcode = trim($_POST['barcode']);
    $price = (float)$_POST['price'];
    $cost_price = (float)$_POST['cost_price'];
    $category = trim($_POST['category']);
    $supplier = trim($_POST['supplier']);
    $description = trim($_POST['description']);
    $quantity = (int)$_POST['quantity'];
    
    if(empty($name)) {
        $error = "Product name is required.";
    } elseif($price <= 0) {
        $error = "Price must be greater than zero.";
    } elseif($cost_price <= 0) {
        $error = "Cost price must be greater than zero.";
    } else {
        // Check if barcode exists
        if(!empty($barcode)) {
            $check = $conn->prepare("SELECT id FROM products WHERE barcode = ?");
            $check->bind_param("s", $barcode);
            $check->execute();
            if($check->get_result()->num_rows > 0) {
                $error = "A product with this barcode already exists.";
            }
        }
        
        // If no errors, proceed with insertion
        if(empty($error)) {
            // Handle image upload
            $image_path = '';
            if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                
                if(!in_array($_FILES['image']['type'], $allowed_types)) {
                    $error = "Only JPG, PNG, and GIF images are allowed.";
                } else {
                    $upload_dir = '../uploads/products/';
                    
                    // Create directory if it doesn't exist
                    if(!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $filename = time() . '_' . basename($_FILES['image']['name']);
                    $target_file = $upload_dir . $filename;
                    
                    if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $image_path = 'uploads/products/' . $filename;
                    } else {
                        $error = "Failed to upload image.";
                    }
                }
            }
            
            // If no errors after image upload, insert product
            if(empty($error)) {
                $conn->begin_transaction();
                try {
                    // Insert product
                    $stmt = $conn->prepare("INSERT INTO products (name, barcode, description, price, cost_price, category, supplier, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssddssss", $name, $barcode, $description, $price, $cost_price, $category, $supplier, $image_path);
                    $stmt->execute();
                    
                    $product_id = $conn->insert_id;
                    
                    // Insert inventory entry
                    $stmt = $conn->prepare("INSERT INTO inventory (product_id, quantity) VALUES (?, ?)");
                    $stmt->bind_param("ii", $product_id, $quantity);
                    $stmt->execute();
                    
                    $conn->commit();
                    $success = "Product added successfully!";
                    
                    // Clear form after successful submission
                    $_POST = [];
                } catch(Exception $e) {
                    $conn->rollback();
                    $error = "Error adding product: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - The Bohemian Burrows</title>
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
                    <h1 class="h2">Add New Product</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                    </div>
                </div>

                <?php if(!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Product Name*</label>
                                        <input type="text" class="form-control" id="name" name="name" required 
                                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="barcode" class="form-label">Barcode</label>
                                        <input type="text" class="form-control" id="barcode" name="barcode"
                                               value="<?php echo isset($_POST['barcode']) ? htmlspecialchars($_POST['barcode']) : ''; ?>">
                                        <small class="text-muted">Leave empty for auto-generation</small>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="price" class="form-label">Selling Price*</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required
                                                       value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="cost_price" class="form-label">Cost Price*</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" required
                                                       value="<?php echo isset($_POST['cost_price']) ? htmlspecialchars($_POST['cost_price']) : ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <input type="text" class="form-control" id="category" name="category" list="categoryList"
                                               value="<?php echo isset($_POST['category']) ? htmlspecialchars($_POST['category']) : ''; ?>">
                                        <datalist id="categoryList">
                                            <?php foreach($categories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="supplier" class="form-label">Supplier</label>
                                        <input type="text" class="form-control" id="supplier" name="supplier" list="supplierList"
                                               value="<?php echo isset($_POST['supplier']) ? htmlspecialchars($_POST['supplier']) : ''; ?>">
                                        <datalist id="supplierList">
                                            <?php foreach($suppliers as $sup): ?>
                                                <option value="<?php echo htmlspecialchars($sup); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="quantity" class="form-label">Initial Stock Quantity</label>
                                        <input type="number" min="0" class="form-control" id="quantity" name="quantity"
                                               value="<?php echo isset($_POST['quantity']) ? (int)$_POST['quantity'] : '0'; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Product Image</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <div class="mt-2" id="imagePreviewContainer" style="display: none;">
                                            <img id="imagePreview" src="#" alt="Preview" style="max-width: 200px; max-height: 200px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">Add Product</button>
                                <button type="reset" class="btn btn-secondary">Reset Form</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview
        document.getElementById('image').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const previewContainer = document.getElementById('imagePreviewContainer');
            
            if(this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                };
                
                reader.readAsDataURL(this.files[0]);
            } else {
                previewContainer.style.display = 'none';
            }
        });
    </script>
</body>
</html>
