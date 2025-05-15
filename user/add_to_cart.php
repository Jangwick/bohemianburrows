<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    echo json_encode(['success' => false, 'message' => 'You must be logged in as a customer to add items to cart']);
    exit;
}

header('Content-Type: application/json');
require_once "../includes/db_connect.php";

// Validate product_id
if(!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

$product_id = (int)$_POST['product_id'];
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if($quantity < 1) {
    $quantity = 1;
}

// Check product exists and has stock
$stmt = $conn->prepare("
    SELECT p.*, i.quantity AS stock
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

$product = $result->fetch_assoc();

// Check if there's enough stock
if(($product['stock'] ?? 0) < $quantity) {
    echo json_encode([
        'success' => false, 
        'message' => 'Not enough stock available. Only ' . ($product['stock'] ?? 0) . ' items left.'
    ]);
    exit;
}

// Initialize cart if needed
if(!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if product is already in cart
$found = false;
foreach($_SESSION['cart'] as &$item) {
    if($item['product_id'] == $product_id) {
        $item['quantity'] += $quantity;
        $item['subtotal'] = $item['quantity'] * $item['price'];
        $found = true;
        break;
    }
}
unset($item);

// Add to cart if not found
if(!$found) {
    $_SESSION['cart'][] = [
        'product_id' => $product_id,
        'name' => $product['name'],
        'price' => $product['price'],
        'quantity' => $quantity,
        'subtotal' => $product['price'] * $quantity,
        'image_path' => $product['image_path']
    ];
}

// Return success
echo json_encode([
    'success' => true, 
    'message' => 'Product added to cart',
    'cart_count' => count($_SESSION['cart'])
]);
