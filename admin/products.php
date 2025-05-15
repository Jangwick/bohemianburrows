<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Define a base64 encoded placeholder image.
$base64PlaceholderImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAQAAADOPi6zAAAAH0lEQVR42u3OIQEAAAACIL/n/4WNDwYg7AABAAAAAAAAAACAAQChNEwAAV1jYQAAAABJRU5ErkJggg==';

// Handle product deletion
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = $_GET['delete'];
    
    // First check if the product has any sales
    $check_sales = $conn->prepare("SELECT COUNT(*) as count FROM sale_items WHERE product_id = ?");
    $check_sales->bind_param("i", $product_id);
    $check_sales->execute();
    $result = $check_sales->get_result();
    $has_sales = $result->fetch_assoc()['count'] > 0;
    
    if($has_sales) {
        $error = "Cannot delete product because it has associated sales records.";
    } else {
        // Delete product image if exists
        $img_query = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
        $img_query->bind_param("i", $product_id);
        $img_query->execute();
        $img_result = $img_query->get_result();
        if($img_result->num_rows > 0) {
            $image_path = $img_result->fetch_assoc()['image_path'];
            if(!empty($image_path) && file_exists("../" . $image_path)) {
                unlink("../" . $image_path);
            }
        }
        
        // Delete from inventory first (due to foreign key)
        $delete_inventory = $conn->prepare("DELETE FROM inventory WHERE product_id = ?");
        $delete_inventory->bind_param("i", $product_id);
        $delete_inventory->execute();
        
        // Then delete the product
        $delete_product = $conn->prepare("DELETE FROM products WHERE id = ?");
        $delete_product->bind_param("i", $product_id);
        $delete_product->execute();
        
        if($delete_product->affected_rows > 0) {
            $success = "Product deleted successfully.";
        } else {
            $error = "Failed to delete product.";
        }
    }
}

// Get filtering and search parameters
$where = "1=1"; // Always true condition to start
$params = [];
$types = "";

if(isset($_GET['category']) && !empty($_GET['category'])) {
    $where .= " AND category = ?";
    $params[] = $_GET['category'];
    $types .= "s";
}

if(isset($_GET['supplier']) && !empty($_GET['supplier'])) {
    $where .= " AND supplier = ?";
    $params[] = $_GET['supplier'];
    $types .= "s";
}

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where .= " AND (name LIKE ? OR barcode LIKE ? OR description LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

// Get all unique categories and suppliers for filters
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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Count total products for pagination
$count_sql = "SELECT COUNT(*) as total FROM products WHERE $where";
$count_stmt = $conn->prepare($count_sql);
if(!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $items_per_page);

// Get products
$sql = "SELECT p.*, i.quantity FROM products p LEFT JOIN inventory i ON p.id = i.product_id WHERE $where ORDER BY p.name LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

// Add pagination parameters
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while($row = $result->fetch_assoc()) {
    $products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            background-color: #f0f0f0; /* Light background for images */
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
                    <h1 class="h2">Product Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add_product.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add New Product
                        </a>
                    </div>
                </div>

                <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Filter Controls -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Name, barcode, description..." 
                                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $category) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="supplier" class="form-label">Supplier</label>
                                <select class="form-select" id="supplier" name="supplier">
                                    <option value="">All Suppliers</option>
                                    <?php foreach($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier; ?>" <?php echo (isset($_GET['supplier']) && $_GET['supplier'] == $supplier) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supplier); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filter</button>
                                <a href="products.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Products List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Barcode</th>
                                        <th>Price</th>
                                        <th>Cost</th>
                                        <th>Category</th>
                                        <th>Stock</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($products) > 0): ?>
                                        <?php foreach($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <img src="<?php echo !empty($product['image_path']) ? '../' . $product['image_path'] : '../assets/images/no-image.jpg'; ?>" 
                                                         class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         onerror="this.onerror=null; this.src='<?php echo $base64PlaceholderImage; ?>';">
                                                </td>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><?php echo htmlspecialchars($product['barcode'] ?? 'N/A'); ?></td>
                                                <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                                <td>₱<?php echo number_format($product['cost_price'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></td>
                                                <td>
                                                    <?php 
                                                    $qty = $product['quantity'] ?? 0;
                                                    $stockClass = $qty > 10 ? 'in-stock' : ($qty > 0 ? 'low-stock' : 'out-of-stock');
                                                    ?>
                                                    <span class="stock-status <?php echo $stockClass; ?>"></span>
                                                    <?php echo $qty; ?> units
                                                </td>
                                                <td>
                                                    <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-warning" title="Edit Product">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="javascript:void(0);" class="btn btn-sm btn-danger delete-product" 
                                                       data-id="<?php echo $product['id']; ?>" 
                                                       data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                       title="Delete Product">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">No products found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Previous</a>
                                </li>
                                
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Product Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete product <strong id="delete-product-name"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirm-delete" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete product confirmation
        document.querySelectorAll('.delete-product').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                const productName = this.getAttribute('data-name');
                
                document.getElementById('delete-product-name').textContent = productName;
                document.getElementById('confirm-delete').href = 'products.php?delete=' + productId;
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            });
        });
    </script>
</body>
</html>
