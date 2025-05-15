<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Initialize response
$response = ['success' => false, 'message' => 'No action specified'];

// Handle update item quantity
if(isset($_POST['update_item_quantity']) && isset($_POST['item_index']) && isset($_POST['quantity'])) {
    $index = (int)$_POST['item_index'];
    $quantity = (int)$_POST['quantity'];
    
    // Validate quantity
    if($quantity < 1) {
        $response = ['success' => false, 'message' => 'Quantity must be at least 1'];
        echo json_encode($response);
        exit;
    }
    
    // Check if the item exists in the cart
    if(isset($_SESSION['cart'][$index])) {
        // Update the quantity
        $_SESSION['cart'][$index]['quantity'] = $quantity;
        // Update the subtotal
        $_SESSION['cart'][$index]['subtotal'] = $_SESSION['cart'][$index]['price'] * $quantity;
        
        // Calculate new cart total
        $cart_total = 0;
        foreach($_SESSION['cart'] as $item) {
            $cart_total += $item['subtotal'];
        }
        
        $response = [
            'success' => true, 
            'message' => 'Cart updated',
            'subtotal' => $_SESSION['cart'][$index]['subtotal'],
            'cart_total' => $cart_total
        ];
    } else {
        $response = ['success' => false, 'message' => 'Item not found in cart'];
    }
}

// Handle remove item
if(isset($_POST['remove_item']) && isset($_POST['item_index'])) {
    $index = (int)$_POST['item_index'];
    
    // Check if the item exists in the cart
    if(isset($_SESSION['cart'][$index])) {
        // Remove the item
        array_splice($_SESSION['cart'], $index, 1);
        
        $response = [
            'success' => true, 
            'message' => 'Item removed from cart'
        ];
    } else {
        $response = ['success' => false, 'message' => 'Item not found in cart'];
    }
}

// Send response
echo json_encode($response);
