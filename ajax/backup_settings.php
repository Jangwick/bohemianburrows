<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once "../includes/db_connect.php";

// Get settings data
$settings_query = $conn->query("SELECT * FROM settings");
$settings = [];

while ($row = $settings_query->fetch_assoc()) {
    $settings[] = $row;
}

// Generate JSON file
$json_data = json_encode($settings, JSON_PRETTY_PRINT);
$filename = 'settings_backup_' . date('Y-m-d_H-i-s') . '.json';

// Output JSON file for download
header('Content-disposition: attachment; filename=' . $filename);
header('Content-type: application/json');
echo $json_data;
exit;
