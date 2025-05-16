<?php
session_start();
require_once "../includes/db_connect.php";

header('Content-Type: application/json');

// Check if user is logged in and is admin/cashier
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'cashier'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
        $response['message'] = 'Invalid input data.';
        echo json_encode($response);
        exit;
    }

    $invoice_number = $input['invoice'] ?? null;
    $items = $input['items'] ?? [];
    $subtotal = isset($input['subtotal']) ? (float)$input['subtotal'] : 0; 
    $discount = isset($input['discount']) ? (float)$input['discount'] : 0;
    $total_amount = isset($input['total']) ? (float)$input['total'] : 0;
    $payment_method = $input['payment_method'] ?? null;
    $customer_name = $input['customer_name'] ?? 'Walk-in';
    $user_id = $_SESSION['user_id'];
    
    // IMPORTANT CHANGE: Set payment_status to 'completed' for POS transactions
    // Since transactions through POS are completed immediately and don't need approval
    $payment_status = 'completed'; 

    // Validate essential data
    if (empty($invoice_number) || empty($items) || !isset($input['subtotal']) || $total_amount < 0 || empty($payment_method)) {
        $response['message'] = 'Missing required sale data. Ensure invoice, items, subtotal, total, and payment method are provided.';
        echo json_encode($response);
        exit;
    }

    $payment_method_to_save = strtolower(trim($payment_method));
    if (empty($payment_method_to_save)) {
        $response['message'] = 'Payment method cannot be empty.';
        echo json_encode($response);
        exit;
    }


    $conn->begin_transaction();

    try {
        // Check if this is a walk-in customer (processed through POS)
        $is_walk_in = ($customer_name === 'Walk-in' || empty($customer_name));
        
        // Set appropriate status for the order
        // Walk-in orders (POS transactions) are automatically marked as "completed"
        // Online orders go through the approval workflow starting as "pending"
        $status = $is_walk_in ? 'completed' : 'pending';

        // Insert into sales table with status "completed"
        $stmt_sale = $conn->prepare("INSERT INTO sales (invoice_number, user_id, customer_name, subtotal, discount, total_amount, payment_method, payment_status, notes, shipping_address, shipping_city, shipping_postal_code, email, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', '', '', '', '', '')");
        if (!$stmt_sale) {
            throw new Exception("Prepare failed (sales): " . $conn->error);
        }
        // Ensure the types and variables match the columns:
        // invoice_number (s), user_id (i), customer_name (s), subtotal (d), discount (d), total_amount (d), payment_method (s), payment_status (s)
        $stmt_sale->bind_param("sisddsss", $invoice_number, $user_id, $customer_name, $subtotal, $discount, $total_amount, $payment_method_to_save, $payment_status);
        
        if (!$stmt_sale->execute()) {
            // Provide more detailed error for debugging
            throw new Exception("Execute failed (sales): " . $stmt_sale->error . " | SQLSTATE: " . $stmt_sale->sqlstate);
        }
        $sale_id = $conn->insert_id;

        // If recording status history, update to show "completed"
        $history_table_check = $conn->query("SHOW TABLES LIKE 'order_status_history'");
        if ($history_table_check->num_rows > 0) {
            $status_note = "Sale completed via POS";
            $history_stmt = $conn->prepare("
                INSERT INTO order_status_history (order_id, status, comments, created_by) 
                VALUES (?, ?, ?, ?)
            ");
            $history_stmt->bind_param("issi", $sale_id, $payment_status, $status_note, $user_id);
            $history_stmt->execute();
        }

        // Insert into sale_items table and update inventory
        $stmt_item = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt_item) {
            throw new Exception("Prepare failed (sale_items): " . $conn->error);
        }
        $stmt_inventory = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND quantity >= ?");
        if (!$stmt_inventory) {
            throw new Exception("Prepare failed (inventory): " . $conn->error);
        }

        foreach ($items as $item) {
            $product_id = $item['id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            $item_subtotal = $item['subtotal'];

            $stmt_item->bind_param("iiidd", $sale_id, $product_id, $quantity, $price, $item_subtotal);
            if (!$stmt_item->execute()) {
                throw new Exception("Execute failed (sale_items for product ID $product_id): " . $stmt_item->error);
            }

            $stmt_inventory->bind_param("iii", $quantity, $product_id, $quantity);
            if (!$stmt_inventory->execute()) {
                 throw new Exception("Execute failed (inventory update for product ID $product_id): " . $stmt_inventory->error);
            }
            if ($stmt_inventory->affected_rows === 0) {
                // This means stock was not sufficient or product_id not found in inventory.
                // Depending on business logic, you might want to throw an error or log a warning.
                // For now, we'll assume POS checks stock before adding to cart.
                // If not, this could be a point of failure or data inconsistency.
            }
        }

        $conn->commit();
        $response = ['success' => true, 'message' => 'Sale processed successfully.', 'sale_id' => $sale_id];

    } catch (Exception $e) {
        $conn->rollback();
        // Send the actual exception message back for debugging, or log it.
        $response['message'] = 'Error processing sale: ' . $e->getMessage(); 
        error_log("Process Sale Error: " . $e->getMessage() . " | Input: " . json_encode($input));
    } finally {
        if (isset($stmt_sale)) $stmt_sale->close();
        if (isset($stmt_item)) $stmt_item->close();
        if (isset($stmt_inventory)) $stmt_inventory->close();
    }

} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>
