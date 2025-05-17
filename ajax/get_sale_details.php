<?php
session_start();
require_once "../includes/db_connect.php";

// Verify user authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'cashier'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Get the sale ID
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid sale ID'
    ]);
    exit;
}

try {
    // Get sale information
    $sale_stmt = $conn->prepare("
        SELECT s.*, u.username as cashier_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    $sale_stmt->bind_param("i", $sale_id);
    $sale_stmt->execute();
    $sale_result = $sale_stmt->get_result();
    
    if ($sale_result->num_rows == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Sale not found'
        ]);
        exit;
    }
    
    $sale = $sale_result->fetch_assoc();
    
    // Get sale items
    $items_stmt = $conn->prepare("
        SELECT si.*, p.name
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
    ");
    $items_stmt->bind_param("i", $sale_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    
    // Return the data
    echo json_encode([
        'success' => true,
        'sale' => $sale,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
