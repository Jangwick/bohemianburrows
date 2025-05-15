<?php
// This is the script that handles barcode searches
header('Content-Type: application/json'); 

// Prevent PHP warnings/notices from corrupting JSON output
error_reporting(E_ERROR);
ini_set('display_errors', '0');

// Start session
session_start();

// Check if user is logged in and is admin or cashier
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'cashier')) {
    echo json_encode(['success' => false, 'found' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once "../includes/db_connect.php";

if(isset($_GET['barcode']) && !empty($_GET['barcode'])) {
    $barcode = $_GET['barcode'];
    
    try {
        $stmt = $conn->prepare("
            SELECT p.*, i.quantity 
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.barcode = ?
            LIMIT 1
        ");
        
        $stmt->bind_param("s", $barcode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'found' => true,
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'barcode' => $product['barcode'],
                'stock' => $product['quantity'] ?? 0
            ]);
        } else {
            echo json_encode(['success' => true, 'found' => false, 'message' => 'Product not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'found' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'found' => false, 'message' => 'Barcode parameter missing']);
}
?>
