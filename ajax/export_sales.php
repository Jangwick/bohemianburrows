<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Access denied';
    exit;
}

require_once "../includes/db_connect.php";

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_export_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV header row
fputcsv($output, [
    'Invoice #',
    'Date & Time',
    'Cashier',
    'Customer',
    'Payment Method',
    'Status',
    'Discount',
    'Total Amount'
]);

// Base query
$where = "1=1";
$params = array();
$types = "";

// Apply filters if any
if(isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $where .= " AND DATE(s.created_at) >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}

if(isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $where .= " AND DATE(s.created_at) <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}

if(isset($_GET['payment_method']) && !empty($_GET['payment_method'])) {
    $where .= " AND s.payment_method = ?";
    $params[] = $_GET['payment_method'];
    $types .= "s";
}

if(isset($_GET['cashier']) && !empty($_GET['cashier'])) {
    $where .= " AND s.user_id = ?";
    $params[] = $_GET['cashier'];
    $types .= "i";
}

// Get sales
$sql = "
    SELECT 
        s.invoice_number, 
        s.created_at, 
        u.username as cashier_name,
        s.customer_name,
        s.payment_method, 
        s.payment_status,
        s.discount,
        s.total_amount
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE $where
    ORDER BY s.created_at DESC
";

$stmt = $conn->prepare($sql);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Output each row
while ($row = $result->fetch_assoc()) {
    // Convert data as needed
    $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
    $row['customer_name'] = $row['customer_name'] ?: 'Walk-in';
    $row['payment_method'] = ucfirst($row['payment_method']);
    $row['payment_status'] = ucfirst($row['payment_status'] ?? 'completed');
    
    // Write to CSV
    fputcsv($output, $row);
}

// Close file handle
fclose($output);
exit;
