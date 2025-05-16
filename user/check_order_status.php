<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once "../includes/db_connect.php";

// Get order ID from request
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Verify this order belongs to the current user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT payment_status 
    FROM sales 
    WHERE id = ? AND (user_id = ? OR (customer_name = (SELECT full_name FROM users WHERE id = ?) AND user_id IS NULL))
");
$stmt->bind_param("iii", $order_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
    exit;
}

$order = $result->fetch_assoc();

// Return current status
echo json_encode([
    'success' => true,
    'status' => $order['payment_status']
]);
?>
