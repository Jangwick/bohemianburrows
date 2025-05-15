<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once "../includes/db_connect.php";

try {
    // Get all tables
    $tables_result = $conn->query("SHOW TABLES");
    $tables = [];
    
    while($row = $tables_result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    if(empty($tables)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No tables found']);
        exit;
    }
    
    // Optimize each table
    foreach($tables as $table) {
        $conn->query("OPTIMIZE TABLE `$table`");
    }
    
    // Success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
