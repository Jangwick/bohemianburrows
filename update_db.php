<?php
// This script updates the database schema to ensure all required fields exist
require_once "includes/db_connect.php";

// Check if sales table has all required shipping fields
$columns_to_check = [
    'shipping_address' => 'VARCHAR(255)',
    'shipping_city' => 'VARCHAR(100)',
    'shipping_postal' => 'VARCHAR(20)',
    'phone' => 'VARCHAR(20)',
    'email' => 'VARCHAR(100)',
    'payment_status' => "ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled')",
    'notes' => 'TEXT'
];

// Get existing columns in sales table
$columns_query = $conn->query("SHOW COLUMNS FROM sales");
$existing_columns = [];
while($column = $columns_query->fetch_assoc()) {
    $existing_columns[] = $column['Field'];
}

// Add missing columns
foreach($columns_to_check as $column => $definition) {
    if(!in_array($column, $existing_columns)) {
        $conn->query("ALTER TABLE sales ADD COLUMN $column $definition");
        echo "Added missing column '$column' to sales table.<br>";
    }
}

// Create order_status_history table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS order_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        status VARCHAR(50) NOT NULL,
        comments TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES sales(id) ON DELETE CASCADE
    )
");
echo "Ensured order_status_history table exists.<br>";

// Create order_shipping table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS order_shipping (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        tracking_number VARCHAR(100),
        courier VARCHAR(100),
        estimated_delivery DATE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES sales(id) ON DELETE CASCADE
    )
");
echo "Ensured order_shipping table exists.<br>";

echo "<p>Database update completed successfully!</p>";
echo "<p><a href='admin/dashboard.php'>Return to Dashboard</a></p>";
?>
