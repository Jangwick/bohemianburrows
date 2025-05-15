<?php
// Include database connection
require_once 'includes/db_connect.php';

// Reset admin password
$admin_username = "admin";
$new_password = "admin123";
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Check if admin exists
$check = $conn->query("SELECT id FROM users WHERE username = 'admin'");

if ($check->num_rows > 0) {
    // Update existing admin
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $hashed_password, $admin_username);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin: 10px; border-radius: 5px;'>
              Admin password reset successfully to 'admin123'</div>";
    } else {
        echo "<div style='background-color: #fff3cd; color: #856404; padding: 15px; margin: 10px; border-radius: 5px;'>
              No changes made to admin account</div>";
    }
    $stmt->close();
} else {
    // Create new admin account
    $admin_role = "admin";
    $admin_fullname = "System Administrator";
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $admin_username, $hashed_password, $admin_role, $admin_fullname);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin: 10px; border-radius: 5px;'>
              Admin account created successfully with password 'admin123'</div>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border-radius: 5px;'>
              Failed to create admin account</div>";
    }
    $stmt->close();
}

$conn->close();
?>
