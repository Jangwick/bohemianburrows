<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];
    
    if($action == 'add') {
        // Handle product image upload
        $image_path = '';
        if(isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $target_dir = "../uploads/products/";
            
            // Create directory if it doesn't exist
            if(!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if(move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                $image_path = "uploads/products/" . $new_filename;
            } else {
                $_SESSION['product_message'] = "Error uploading product image.";
                header("Location: products.php");
                exit;
            }
        }
        
        // Insert product
        $stmt = $conn->prepare("INSERT INTO products (barcode, name, description, price, cost_price, category, supplier, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdddsss", 
            $_POST['barcode'],
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
            $_POST['cost_price'],
            $_POST['category'],
            $_POST['supplier'],
            $image_path
        );
        
        if($stmt->execute()) {
            $product_id = $conn->insert_id;
            
            // Add initial stock if quantity > 0
            if(isset($_POST['quantity']) && $_POST['quantity'] > 0) {
                $quantity = $_POST['quantity'];
                $stock_stmt = $conn->prepare("INSERT INTO inventory (product_id, quantity, last_restock) VALUES (?, ?, NOW())");
                $stock_stmt->bind_param("ii", $product_id, $quantity);
                $stock_stmt->execute();
            }
            
            $_SESSION['product_message'] = "Product added successfully!";
        } else {
            $_SESSION['product_message'] = "Error: " . $conn->error;
        }
    } elseif($action == 'update') {
        $product_id = $_POST['product_id'];
        $image_path = $_POST['current_image']; // Keep current image by default
        
        // Handle image upload if new image is provided
        if(isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $target_dir = "../uploads/products/";
            
            // Create directory if it doesn't exist
            if(!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if(move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                // Delete old image if exists
                if(!empty($_POST['current_image']) && file_exists("../" . $_POST['current_image'])) {
                    unlink("../" . $_POST['current_image']);
                }
                $image_path = "uploads/products/" . $new_filename;
            } else {
                $_SESSION['product_message'] = "Error uploading product image.";
                header("Location: products.php");
                exit;
            }
        }
        
        // Update product
        $stmt = $conn->prepare("UPDATE products SET barcode=?, name=?, description=?, price=?, cost_price=?, category=?, supplier=?, image_path=? WHERE id=?");
        $stmt->bind_param("ssdddssi", 
            $_POST['barcode'],
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
            $_POST['cost_price'],
            $_POST['category'],
            $_POST['supplier'],
            $image_path,
            $product_id
        );
        
        if($stmt->execute()) {
            $_SESSION['product_message'] = "Product updated successfully!";
        } else {
            $_SESSION['product_message'] = "Error updating product: " . $conn->error;
        }
    } elseif($action == 'delete') {
        $product_id = $_POST['product_id'];
        
        // Get product image path to delete file
        $img_stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
        $img_stmt->bind_param("i", $product_id);
        $img_stmt->execute();
        $result = $img_stmt->get_result();
        if($row = $result->fetch_assoc()) {
            if(!empty($row['image_path']) && file_exists("../" . $row['image_path'])) {
                unlink("../" . $row['image_path']);
            }
        }
        
        // Delete product
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        
        if($stmt->execute()) {
            $_SESSION['product_message'] = "Product deleted successfully!";
        } else {
            $_SESSION['product_message'] = "Error deleting product: " . $conn->error;
        }
    }
    
    header("Location: products.php");
    exit;
}
?>
