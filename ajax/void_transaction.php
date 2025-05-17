<?php
session_start();
require_once "../includes/db_connect.php";

// Set response header
header('Content-Type: application/json');

// Only allow admin or cashier to void transactions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'cashier'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['sale_id']) || !isset($input['reason']) || !isset($input['passcode'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$sale_id = (int)$input['sale_id'];
$reason = $input['reason'];
$passcode = $input['passcode'];

// Verify manager passcode - in real world, this would be a proper authentication system
// For demo purposes, we'll use a hardcoded passcode
$valid_passcode = 'manager123'; // In production, use a secure method

if ($passcode !== $valid_passcode) {
    echo json_encode(['success' => false, 'message' => 'Invalid manager passcode']);
    exit;
}

// Check if the transaction belongs to the current user (cashier)
$check_stmt = $conn->prepare("SELECT id, payment_status FROM sales WHERE id = ? AND user_id = ?");
$check_stmt->bind_param("ii", $sale_id, $_SESSION['user_id']);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found or not authorized']);
    exit;
}

$transaction = $result->fetch_assoc();

// Check if transaction is already cancelled or refunded
if ($transaction['payment_status'] === 'cancelled' || $transaction['payment_status'] === 'refunded') {
    echo json_encode(['success' => false, 'message' => 'This transaction has already been voided']);
    exit;
}

// Start transaction for data consistency
$conn->begin_transaction();

try {
    // Update transaction status to cancelled
    $update_stmt = $conn->prepare("UPDATE sales SET payment_status = 'cancelled' WHERE id = ?");
    $update_stmt->bind_param("i", $sale_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update transaction status: " . $conn->error);
    }
    
    // Log the void action in transaction history if table exists
    $history_check = $conn->query("SHOW TABLES LIKE 'transaction_history'");
    if ($history_check->num_rows > 0) {
        $history_stmt = $conn->prepare("
            INSERT INTO transaction_history (sale_id, action, reason, performed_by)
            VALUES (?, 'void', ?, ?)
        ");
        $history_stmt->bind_param("isi", $sale_id, $reason, $_SESSION['user_id']);
        
        if (!$history_stmt->execute()) {
            throw new Exception("Failed to log transaction history: " . $conn->error);
        }
    }
    
    // Also add to order_status_history if it exists
    $order_history_check = $conn->query("SHOW TABLES LIKE 'order_status_history'");
    if ($order_history_check->num_rows > 0) {
        $order_history_stmt = $conn->prepare("
            INSERT INTO order_status_history (order_id, status, comments, created_by)
            VALUES (?, 'cancelled', ?, ?)
        ");
        $order_history_stmt->bind_param("isi", $sale_id, $reason, $_SESSION['user_id']);
        $order_history_stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Transaction successfully voided'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
