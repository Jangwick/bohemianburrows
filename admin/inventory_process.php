<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    if($action == 'add') {
        // Check if product exists in inventory
        $check_stmt = $conn->prepare("SELECT * FROM inventory WHERE product_id = ?");
        $check_stmt->bind_param("i", $product_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if($result->num_rows > 0) {
            // Update existing inventory
            $row = $result->fetch_assoc();
            $new_quantity = $row['quantity'] + $quantity;
            
            $update_stmt = $conn->prepare("UPDATE inventory SET quantity = ?, last_restock = NOW() WHERE product_id = ?");
            $update_stmt->bind_param("ii", $new_quantity, $product_id);
            $update_stmt->execute();
        } else {
            // Insert new inventory item
            $insert_stmt = $conn->prepare("INSERT INTO inventory (product_id, quantity, last_restock) VALUES (?, ?, NOW())");
            $insert_stmt->bind_param("ii", $product_id, $quantity);
            $insert_stmt->execute();
        }
        
        $_SESSION['inventory_message'] = "Stock added successfully!";
    } elseif($action == 'update') {
        // Update inventory quantity
        $check_stmt = $conn->prepare("SELECT * FROM inventory WHERE product_id = ?");
        $check_stmt->bind_param("i", $product_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if($result->num_rows > 0) {
            // Update existing inventory
            $update_stmt = $conn->prepare("UPDATE inventory SET quantity = ?, last_restock = NOW() WHERE product_id = ?");
            $update_stmt->bind_param("ii", $quantity, $product_id);
            $update_stmt->execute();
        } else {
            // Insert new inventory item
            $insert_stmt = $conn->prepare("INSERT INTO inventory (product_id, quantity, last_restock) VALUES (?, ?, NOW())");
            $insert_stmt->bind_param("ii", $product_id, $quantity);
            $insert_stmt->execute();
        }
        
        $_SESSION['inventory_message'] = "Stock updated successfully!";
    }
    
    header("Location: inventory.php");
    exit;
}
?>
