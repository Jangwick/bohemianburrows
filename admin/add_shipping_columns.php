<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

$success = false;
$error = '';

try {
    // Check if columns already exist
    $check = $conn->query("SHOW COLUMNS FROM sales LIKE 'shipping_address'");
    
    // Only add if they don't exist
    if ($check->num_rows == 0) {
        // Start transaction
        $conn->begin_transaction();
        
        // Add shipping columns to sales table
        $conn->query("ALTER TABLE sales 
            ADD COLUMN shipping_address VARCHAR(255) NULL,
            ADD COLUMN shipping_city VARCHAR(100) NULL,
            ADD COLUMN shipping_postal VARCHAR(20) NULL,
            ADD COLUMN phone VARCHAR(20) NULL
        ");
        
        // Create shipping tracking table if it doesn't exist
        $conn->query("CREATE TABLE IF NOT EXISTS order_shipping (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            tracking_number VARCHAR(100),
            courier VARCHAR(100) NOT NULL,
            status VARCHAR(50) DEFAULT 'in_transit',
            estimated_delivery DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES sales(id) ON DELETE CASCADE
        )");
        
        // Commit transaction
        $conn->commit();
        
        $success = true;
    } else {
        $error = 'Shipping columns already exist in the database.';
    }
} catch (Exception $e) {
    // Roll back on error
    if ($conn->connect_error) {
        $conn->rollback();
    }
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Shipping Support - The Bohemian Burrows</title>
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
                    <h1 class="h2">Add Shipping Support</h1>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Success!</strong> Shipping columns have been added to the database.
                            </div>
                            <p>Your system now supports the following shipping features:</p>
                            <ul>
                                <li>Shipping address information storage</li>
                                <li>Shipment tracking</li>
                                <li>Delivery status updates</li>
                            </ul>
                            <div class="text-center mt-4">
                                <a href="orders.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Return to Orders
                                </a>
                            </div>
                        <?php elseif($error): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                            <div class="text-center mt-4">
                                <a href="orders.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Return to Orders
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
