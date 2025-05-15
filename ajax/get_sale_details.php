<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once "../includes/db_connect.php";

// Get sale ID from request
$sale_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($sale_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
    exit;
}

// Get sale details
$stmt = $conn->prepare("
    SELECT s.*, u.username as cashier_name
    FROM sales s
    JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");

$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sale not found']);
    exit;
}

$sale = $result->fetch_assoc();

// Allow access if admin or the cashier who processed the sale
if($_SESSION['role'] !== 'admin' && $sale['user_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to view this sale']);
    exit;
}

// Get sale items
$items_stmt = $conn->prepare("
    SELECT si.*, p.name
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$items_stmt->bind_param("i", $sale_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$items = [];
while($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

// Return the data
echo json_encode([
    'success' => true,
    'sale' => $sale,
    'items' => $items
]);
?>
