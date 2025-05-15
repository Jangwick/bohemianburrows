<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

require_once "../includes/db_connect.php";

// Get product_id and quantity
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

if($product_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity']);
    exit;
}

// Check if product exists and has stock
$stmt = $conn->prepare("
    SELECT p.*, i.quantity as stock
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

$product = $result->fetch_assoc();

// Check stock availability
if(($product['stock'] ?? 0) < $quantity) {
    echo json_encode([
        'success' => false, 
        'message' => 'Not enough stock available. Only ' . ($product['stock'] ?? 0) . ' units left.'
    ]);
    exit;
}

// Initialize cart session if not exists
if(!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if product already in cart
$found = false;
foreach($_SESSION['cart'] as &$item) {
    if($item['product_id'] == $product_id) {
        $item['quantity'] += $quantity;
        $found = true;
        break;
    }
}

// Add product to cart if not already there
if(!$found) {
    $_SESSION['cart'][] = [
        'product_id' => $product_id,
        'name' => $product['name'],
        'price' => $product['price'],
        'image_path' => $product['image_path'],
        'quantity' => $quantity
    ];
}

// Calculate total number of items in cart
$cart_count = 0;
foreach($_SESSION['cart'] as $item) {
    $cart_count += $item['quantity'];
}

echo json_encode([
    'success' => true, 
    'message' => 'Product added to cart',
    'cart_count' => $cart_count
]);
