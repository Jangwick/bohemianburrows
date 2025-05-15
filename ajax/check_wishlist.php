<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    echo json_encode(['success' => false, 'in_wishlist' => false, 'message' => 'Not logged in as customer']);
    exit;
}

require_once "../includes/db_connect.php";

// Validate product_id
if(!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
    echo json_encode(['success' => false, 'in_wishlist' => false, 'message' => 'Invalid product ID']);
    exit;
}

$product_id = (int)$_POST['product_id'];
$user_id = $_SESSION['user_id'];

// Check if item is in wishlist
$stmt = $conn->prepare("SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();
$result = $stmt->get_result();
$in_wishlist = $result->num_rows > 0;

echo json_encode([
    'success' => true,
    'in_wishlist' => $in_wishlist
]);
