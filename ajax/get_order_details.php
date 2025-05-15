<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once "../includes/db_connect.php";

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

$order_id = (int)$_GET['id'];

// Check if shipping columns exist
$checkShippingColumns = $conn->query("SHOW COLUMNS FROM sales LIKE 'shipping_address'");
$hasShippingColumns = $checkShippingColumns->num_rows > 0;

// Get order details with dynamic column selection
$selectColumns = "s.*, u.username as cashier_name";

// Get order details - security check to ensure this order belongs to the current user
$stmt = $conn->prepare("
    SELECT $selectColumns  
    FROM sales s 
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ? AND (
        s.user_id = ? OR 
        (s.customer_name = (SELECT full_name FROM users WHERE id = ?) AND s.user_id IS NOT NULL)
    )
");
$stmt->bind_param("iii", $order_id, $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or you do not have permission to view it']);
    exit;
}

$order = $result->fetch_assoc();

// Get order items
$items_stmt = $conn->prepare("
    SELECT si.*, p.name, p.image_path
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$items = [];
while($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

// Return JSON response
echo json_encode([
    'success' => true,
    'order' => $order,
    'items' => $items
]);
