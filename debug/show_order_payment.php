<?php
// This is a debugging tool to check payment methods in the database
require_once "../includes/db_connect.php";

// Security check - disable in production
$allowed_ips = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    die("Access denied");
}

// Set content type to plain text
header('Content-Type: text/plain');

// Get order ID from query string
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

echo "==== PAYMENT METHOD INSPECTOR ====\n\n";

if ($order_id) {
    // Get specific order
    $stmt = $conn->prepare("SELECT id, invoice_number, payment_method, customer_name FROM sales WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        echo "Order #{$order['id']} ({$order['invoice_number']})\n";
        echo "Customer: {$order['customer_name']}\n";
        echo "Payment Method (raw): '{$order['payment_method']}'\n";
        echo "Payment Method (lowercase): '" . strtolower($order['payment_method']) . "'\n";
        echo "Payment Method (type): " . gettype($order['payment_method']) . "\n";
        echo "Length: " . strlen($order['payment_method']) . " characters\n\n";
        
        // Debug binary representation
        echo "Binary representation:\n";
        for ($i = 0; $i < strlen($order['payment_method']); $i++) {
            echo ord($order['payment_method'][$i]) . " ";
        }
        echo "\n\n";
    } else {
        echo "Order not found\n";
    }
} else {
    // List 10 most recent orders
    $result = $conn->query("SELECT id, invoice_number, payment_method, customer_name FROM sales ORDER BY created_at DESC LIMIT 10");
    
    echo "10 Most Recent Orders:\n\n";
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "Order #{$row['id']} ({$row['invoice_number']})\n";
            echo "Customer: {$row['customer_name']}\n";
            echo "Payment Method (raw): '{$row['payment_method']}'\n\n";
        }
    } else {
        echo "No orders found\n";
    }
}

echo "\n==== END OF REPORT ====";
?>
