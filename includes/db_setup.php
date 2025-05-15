<?php
// Database configuration
$servername = "localhost:3307";
$username = "root";
$password = "";
$dbname = "bohemian";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === FALSE) {
    die("Error creating database: " . $conn->error);
}

// Select database
$conn->select_db($dbname);

// Create users table - Now including 'customer' role
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier', 'customer') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
)";
if ($conn->query($sql) === FALSE) {
    die("Error creating users table: " . $conn->error);
}

// Create products table
$sql = "CREATE TABLE IF NOT EXISTS products (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(50) UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50),
    supplier VARCHAR(100),
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === FALSE) {
    die("Error creating products table: " . $conn->error);
}

// Create inventory table
$sql = "CREATE TABLE IF NOT EXISTS inventory (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    product_id INT(11) NOT NULL,
    quantity INT(11) NOT NULL DEFAULT 0,
    last_restock TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)";
if ($conn->query($sql) === FALSE) {
    die("Error creating inventory table: " . $conn->error);
}

// Create sales table
$sql = "CREATE TABLE IF NOT EXISTS sales (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    user_id INT(11) NOT NULL,
    customer_name VARCHAR(100),
    total_amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    payment_method ENUM('cash', 'card', 'gcash', 'paymaya') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";
if ($conn->query($sql) === FALSE) {
    die("Error creating sales table: " . $conn->error);
}

// Create sale items table
$sql = "CREATE TABLE IF NOT EXISTS sale_items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    sale_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    quantity INT(11) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
)";
if ($conn->query($sql) === FALSE) {
    die("Error creating sale_items table: " . $conn->error);
}

// Insert default admin account - Check if exists first
$check_admin = $conn->query("SELECT id FROM users WHERE username = 'admin'");
if ($check_admin->num_rows == 0) {
    $admin_username = "admin";
    $admin_password = password_hash("admin123", PASSWORD_DEFAULT);
    $admin_role = "admin";
    $admin_fullname = "System Administrator";

    $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $admin_username, $admin_password, $admin_role, $admin_fullname);
    $stmt->execute();
    $stmt->close();
    
    echo "Admin account created successfully!<br>";
} else {
    echo "Admin account already exists.<br>";
}

echo "Database setup completed successfully!";
$conn->close();
?>
