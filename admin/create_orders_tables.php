<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

$messages = [];
$error = false;

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check if order_status_history table exists
    $result = $conn->query("SHOW TABLES LIKE 'order_status_history'");
    if ($result->num_rows == 0) {
        // Create order_status_history table
        $sql = "CREATE TABLE IF NOT EXISTS order_status_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            status VARCHAR(50) NOT NULL,
            comments TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )";
        
        if ($conn->query($sql)) {
            $messages[] = "Created order_status_history table successfully.";
        } else {
            throw new Exception("Failed to create order_status_history table: " . $conn->error);
        }
    } else {
        $messages[] = "order_status_history table already exists.";
    }
    
    // Check if order_shipping table exists
    $result = $conn->query("SHOW TABLES LIKE 'order_shipping'");
    if ($result->num_rows == 0) {
        // Create order_shipping table
        $sql = "CREATE TABLE IF NOT EXISTS order_shipping (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            tracking_number VARCHAR(100),
            courier VARCHAR(100) NOT NULL,
            status VARCHAR(50) DEFAULT 'in_transit',
            estimated_delivery DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES sales(id) ON DELETE CASCADE
        )";
        
        if ($conn->query($sql)) {
            $messages[] = "Created order_shipping table successfully.";
        } else {
            throw new Exception("Failed to create order_shipping table: " . $conn->error);
        }
    } else {
        $messages[] = "order_shipping table already exists.";
    }
    
    // Check if shipping columns exist in sales table
    $result = $conn->query("SHOW COLUMNS FROM sales LIKE 'shipping_address'");
    if ($result->num_rows == 0) {
        // Add shipping columns to sales table
        $sql = "ALTER TABLE sales 
            ADD COLUMN shipping_address VARCHAR(255) NULL,
            ADD COLUMN shipping_city VARCHAR(100) NULL,
            ADD COLUMN shipping_postal VARCHAR(20) NULL,
            ADD COLUMN phone VARCHAR(20) NULL";
        
        if ($conn->query($sql)) {
            $messages[] = "Added shipping columns to sales table successfully.";
        } else {
            throw new Exception("Failed to add shipping columns: " . $conn->error);
        }
    } else {
        $messages[] = "Shipping columns already exist in sales table.";
    }
    
    // Commit transaction if we got this far
    $conn->commit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $error = true;
    $messages[] = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Order Tables - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content pt-3">
                <h1 class="h2 mb-4">Setup Order Tables</h1>
                
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Setup Failed</h4>
                        <ul class="mb-0">
                            <?php foreach($messages as $message): ?>
                                <li><?php echo $message; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <h4><i class="fas fa-check-circle me-2"></i>Setup Completed</h4>
                        <ul class="mb-0">
                            <?php foreach($messages as $message): ?>
                                <li><?php echo $message; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5>What was fixed?</h5>
                        <p>This script has created or verified the necessary database tables for the order management system:</p>
                        <ul>
                            <li><strong>order_status_history</strong> - For tracking changes to order status</li>
                            <li><strong>order_shipping</strong> - For storing shipping and tracking information</li>
                            <li><strong>shipping columns</strong> - Added shipping address fields to the sales table</li>
                        </ul>
                        
                        <div class="mt-4">
                            <a href="orders.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i> Return to Orders
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
