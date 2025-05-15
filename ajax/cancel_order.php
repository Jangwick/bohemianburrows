<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once "../includes/db_connect.php";

if(!isset($_POST['order_id']) || !is_numeric($_POST['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

$order_id = (int)$_POST['order_id'];

// Verify this order belongs to the current user and is in 'pending' status
$check_stmt = $conn->prepare("
    SELECT id, payment_status 
    FROM sales 
    WHERE id = ? AND (
        user_id = ? OR 
        (customer_name = (SELECT full_name FROM users WHERE id = ?) AND user_id IS NOT NULL)
    )
");
$check_stmt->bind_param("iii", $order_id, $_SESSION['user_id'], $_SESSION['user_id']);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or you do not have permission to cancel it']);
    exit;
}

$order = $check_result->fetch_assoc();

if($order['payment_status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled']);
    exit;
}

// Update order status to cancelled
$update_stmt = $conn->prepare("UPDATE sales SET payment_status = 'cancelled' WHERE id = ?");
$update_stmt->bind_param("i", $order_id);

// Execute the update
if($update_stmt->execute()) {
    // Return inventory items
    $return_inventory = true;
    
    if($return_inventory) {
        // Get the items in this order
        $items_stmt = $conn->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
        $items_stmt->bind_param("i", $order_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        // Return each item to inventory
        while($item = $items_result->fetch_assoc()) {
            $inventory_stmt = $conn->prepare("
                UPDATE inventory 
                SET quantity = quantity + ? 
                WHERE product_id = ?
            ");
            $inventory_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
            $inventory_stmt->execute();
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel order: ' . $conn->error]);
}
