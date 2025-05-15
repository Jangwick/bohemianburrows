<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    echo json_encode(['success' => false, 'message' => 'You must be logged in as a customer to manage your wishlist']);
    exit;
}

require_once "../includes/db_connect.php";

// Ensure product_id is provided and valid
if(!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

$product_id = (int)$_POST['product_id'];
$user_id = $_SESSION['user_id'];

// Create wishlist table if it doesn't exist (for robustness)
$create_table = "CREATE TABLE IF NOT EXISTS wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id)
)";
$conn->query($create_table);

// Check if product exists
$product_check = $conn->prepare("SELECT id FROM products WHERE id = ?");
$product_check->bind_param("i", $product_id);
$product_check->execute();
$product_result = $product_check->get_result();
if($product_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

// Check if item is already in wishlist
$check_stmt = $conn->prepare("SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?");
$check_stmt->bind_param("ii", $user_id, $product_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$in_wishlist = $result->num_rows > 0;

if($in_wishlist) {
    // Remove from wishlist
    $delete_stmt = $conn->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?");
    $delete_stmt->bind_param("ii", $user_id, $product_id);
    if($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Item removed from wishlist']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove item from wishlist']);
    }
} else {
    // Add to wishlist
    $insert_stmt = $conn->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $user_id, $product_id);
    
    try {
        if($insert_stmt->execute()) {
            echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Item added to wishlist']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add item to wishlist']);
        }
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>
