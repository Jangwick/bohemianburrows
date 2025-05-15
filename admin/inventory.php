<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Process filter if set
$where = "1=1";
$params = array();
$types = "";

if(isset($_GET['category']) && !empty($_GET['category'])) {
    $where .= " AND p.category = ?";
    $params[] = $_GET['category'];
    $types .= "s";
}

if(isset($_GET['stock']) && $_GET['stock'] == 'low') {
    $where .= " AND i.quantity <= 10";
}

// Prepare the statement
$sql = "
    SELECT p.id, p.barcode, p.name, p.category, p.price, i.quantity, p.supplier
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE $where
    ORDER BY p.name
";

$stmt = $conn->prepare($sql);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get categories for filter
$cat_stmt = $conn->prepare("SELECT DISTINCT category FROM products");
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - The Bohemian Burrows</title>
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
                    <h1 class="h2">Inventory Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addStockModal">
                            <i class="fas fa-plus"></i> Add Stock
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printInventory()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-4">
                                        <label for="category" class="form-label">Filter by Category</label>
                                        <select class="form-select" id="category" name="category">
                                            <option value="">All Categories</option>
                                            <?php while($cat = $cat_result->fetch_assoc()): ?>
                                                <option value="<?php echo $cat['category']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['category']) ? 'selected' : ''; ?>>
                                                    <?php echo $cat['category']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="stock" class="form-label">Stock Status</label>
                                        <select class="form-select" id="stock" name="stock">
                                            <option value="">All Stock</option>
                                            <option value="low" <?php echo (isset($_GET['stock']) && $_GET['stock'] == 'low') ? 'selected' : ''; ?>>
                                                Low Stock (≤ 10)
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                        <a href="inventory.php" class="btn btn-secondary">Reset</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Table -->
                <div class="table-container">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Barcode</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['barcode']; ?></td>
                                        <td><?php echo $row['name']; ?></td>
                                        <td><?php echo $row['category']; ?></td>
                                        <td>₱ <?php echo number_format($row['price'], 2); ?></td>
                                        <td><?php echo $row['quantity'] ?? 0; ?></td>
                                        <td><?php echo $row['supplier']; ?></td>
                                        <td>
                                            <?php if(($row['quantity'] ?? 0) <= 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php elseif(($row['quantity'] ?? 0) <= 10): ?>
                                                <span class="badge bg-warning text-dark">Low Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">In Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary update-stock" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateStockModal" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-name="<?php echo $row['name']; ?>"
                                                    data-quantity="<?php echo $row['quantity'] ?? 0; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No products found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Stock Modal -->
    <div class="modal fade" id="addStockModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStockModalLabel">Add New Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addStockForm" action="inventory_process.php" method="post">
                        <div class="mb-3">
                            <label for="product" class="form-label">Select Product</label>
                            <select class="form-select" id="product" name="product_id" required>
                                <option value="">Select a product</option>
                                <?php 
                                $prod_stmt = $conn->prepare("SELECT id, name FROM products ORDER BY name");
                                $prod_stmt->execute();
                                $prod_result = $prod_stmt->get_result();
                                while($prod = $prod_result->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $prod['id']; ?>"><?php echo $prod['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                        </div>
                        <input type="hidden" name="action" value="add">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addStockForm" class="btn btn-primary">Add Stock</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div class="modal fade" id="updateStockModal" tabindex="-1" aria-labelledby="updateStockModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStockModalLabel">Update Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="updateStockForm" action="inventory_process.php" method="post">
                        <div class="mb-3">
                            <label for="update_product_name" class="form-label">Product</label>
                            <input type="text" class="form-control" id="update_product_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="current_quantity" class="form-label">Current Quantity</label>
                            <input type="number" class="form-control" id="current_quantity" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="new_quantity" class="form-label">New Quantity</label>
                            <input type="number" class="form-control" id="new_quantity" name="quantity" min="0" required>
                        </div>
                        <input type="hidden" name="product_id" id="update_product_id">
                        <input type="hidden" name="action" value="update">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="updateStockForm" class="btn btn-primary">Update Stock</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle update stock modal
        const updateButtons = document.querySelectorAll('.update-stock');
        updateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                const productName = this.getAttribute('data-name');
                const quantity = this.getAttribute('data-quantity');
                
                document.getElementById('update_product_id').value = productId;
                document.getElementById('update_product_name').value = productName;
                document.getElementById('current_quantity').value = quantity;
                document.getElementById('new_quantity').value = quantity;
            });
        });
        
        // Print function
        window.printInventory = function() {
            window.print();
        };
    });
    </script>
</body>
</html>
