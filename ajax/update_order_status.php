<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');
require_once "../includes/db_connect.php";

// Get the JSON data from the request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if(!isset($data['order_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$order_id = intval($data['order_id']);
$status = $data['status'];
$comments = $data['comments'] ?? '';
$tracking_number = $data['tracking_number'] ?? null;
$courier = $data['courier'] ?? null;
$estimated_delivery = $data['estimated_delivery'] ?? null;

// Start a transaction
$conn->begin_transaction();

try {
    // Update the order status
    $status_stmt = $conn->prepare("UPDATE sales SET payment_status = ? WHERE id = ?");
    $status_stmt->bind_param("si", $status, $order_id);
    $status_stmt->execute();
    
    // Check if order_status_history table exists, create if it doesn't
    $table_check = $conn->query("SHOW TABLES LIKE 'order_status_history'");
    if ($table_check->num_rows == 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS order_status_history (
                id INT PRIMARY KEY AUTO_INCREMENT,
                order_id INT NOT NULL,
                status VARCHAR(50) NOT NULL,
                comments TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES sales(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
    }
    
    // Record status change in order history
    $history_stmt = $conn->prepare("
        INSERT INTO order_status_history (order_id, status, comments, created_by) 
        VALUES (?, ?, ?, ?)
    ");
    $history_stmt->bind_param("issi", $order_id, $status, $comments, $_SESSION['user_id']);
    $history_stmt->execute();
    
    // If status is shipped, add shipping details
    if ($status == 'shipped' && !empty($tracking_number)) {
        // Check if order_shipping table exists, create if it doesn't
        $shipping_table_check = $conn->query("SHOW TABLES LIKE 'order_shipping'");
        if ($shipping_table_check->num_rows == 0) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS order_shipping (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    order_id INT NOT NULL,
                    tracking_number VARCHAR(50),
                    courier VARCHAR(100),
                    status VARCHAR(50),
                    estimated_delivery DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (order_id) REFERENCES sales(id) ON DELETE CASCADE
                )
            ");
        }
        
        // Add shipping details
        $shipping_stmt = $conn->prepare("
            INSERT INTO order_shipping (order_id, tracking_number, courier, status, estimated_delivery)
            VALUES (?, ?, ?, ?, ?)
        ");
        $shipping_stmt->bind_param("issss", $order_id, $tracking_number, $courier, $status, $estimated_delivery);
        $shipping_stmt->execute();
    }
    
    // Commit the transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
    
} catch (Exception $e) {
    // Rollback if any errors
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating order status: ' . $e->getMessage()]);
}
?>
