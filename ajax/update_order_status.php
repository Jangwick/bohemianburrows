<?php
session_start();
require_once "../includes/db_connect.php";

// Return JSON and prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Only allow admin or cashier to update order status
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'cashier'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get input data - handle both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
}

if (!isset($input['order_id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$order_id = (int)$input['order_id'];
$status = $input['status'];
$comments = isset($input['comments']) ? $input['comments'] : '';
$user_id = $_SESSION['user_id'];

// Validate status
$valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded', 'approved', 'paid'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Start a transaction for data consistency
$conn->begin_transaction();

try {
    // IMPORTANT: Get the current status first
    $check_stmt = $conn->prepare("SELECT payment_status FROM sales WHERE id = ?");
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $current_status = $check_stmt->get_result()->fetch_assoc()['payment_status'] ?? null;
    
    // DEBUG
    error_log("Updating Order #$order_id: Current status='$current_status', New status='$status'");
    
    // Always update regardless of current status (don't check current status in the WHERE clause)
    $update_stmt = $conn->prepare("UPDATE sales SET payment_status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $status, $order_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update status: " . $conn->error);
    }
    
    // Check if update was successful based on affected_rows
    $status_changed = ($update_stmt->affected_rows > 0);
    
    // Log to history table regardless of actual change
    $history_table_check = $conn->query("SHOW TABLES LIKE 'order_status_history'");
    if ($history_table_check->num_rows > 0) {
        $history_stmt = $conn->prepare("
            INSERT INTO order_status_history (order_id, status, comments, created_by) 
            VALUES (?, ?, ?, ?)
        ");
        $history_stmt->bind_param("issi", $order_id, $status, $comments, $user_id);
        
        if (!$history_stmt->execute()) {
            throw new Exception("Failed to log history: " . $conn->error);
        }
    }
    
    // Get customer ID for notification
    $customer_query = $conn->prepare("SELECT user_id, customer_name FROM sales WHERE id = ?");
    $customer_query->bind_param("i", $order_id);
    $customer_query->execute();
    $customer_data = $customer_query->get_result()->fetch_assoc();
    
    // Commit all changes
    $conn->commit();
    
    // Return success response with additional data
    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully',
        'status_changed' => $status_changed,
        'previous_status' => $current_status,
        'current_status' => $status,
        'order_id' => $order_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Roll back on error
    $conn->rollback();
    error_log("Status update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
