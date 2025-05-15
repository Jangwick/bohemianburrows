<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Check if form was submitted
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect_url = "user_management.php"; // Default redirect

    if(isset($_POST['redirect_to']) && !empty($_POST['redirect_to'])) {
        // Basic validation for redirect URL to prevent open redirect
        if (filter_var("http://localhost/" . ltrim($_POST['redirect_to'], '/'), FILTER_VALIDATE_URL)) { // Check if it's a relative path within the domain
             $redirect_url = $_POST['redirect_to'];
        }
    }
    
    // Add new user
    if($action === 'add') {
        $username = $_POST['username'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'cashier';
        
        // Validate inputs
        if(empty($username) || empty($password) || empty($full_name)) {
            $_SESSION['user_message'] = "Required fields cannot be empty.";
            header("Location: user_management.php");
            exit;
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if username already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        if($check_stmt->get_result()->num_rows > 0) {
            $_SESSION['user_message'] = "Username already exists. Please choose a different one.";
            header("Location: user_management.php");
            exit;
        }
        
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $hashed_password, $full_name, $email, $role);
        
        if($stmt->execute()) {
            $_SESSION['user_message'] = "User added successfully.";
        } else {
            $_SESSION['user_message'] = "Error adding user: " . $stmt->error;
        }
    }
    // Update existing user
    elseif($action === 'update') {
        $user_id = $_POST['user_id'] ?? 0;
        $username = $_POST['username'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? '';
        
        // Validate inputs
        if(empty($username) || empty($full_name) || empty($user_id)) {
            $_SESSION['user_message'] = "Required fields cannot be empty.";
            header("Location: user_management.php");
            exit;
        }
        
        // Check if username already exists (for another user)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $user_id);
        $check_stmt->execute();
        if($check_stmt->get_result()->num_rows > 0) {
            $_SESSION['user_message'] = "Username already exists for another user. Please choose a different one.";
            header("Location: user_management.php");
            exit;
        }
        
        // Update user
        $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $username, $full_name, $email, $role, $user_id);
        
        if($stmt->execute()) {
            $_SESSION['user_message'] = "User updated successfully.";
        } else {
            $_SESSION['user_message'] = "Error updating user: " . $stmt->error;
        }
    }
    // Reset password
    elseif($action === 'reset_password') {
        $user_id = $_POST['user_id'] ?? 0;
        $password = $_POST['password'] ?? '';
        
        // Validate inputs
        if(empty($user_id) || empty($password)) {
            $_SESSION['user_message'] = "User ID and new password are required.";
            header("Location: user_management.php");
            exit;
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if($stmt->execute()) {
            $_SESSION['user_message'] = "Password reset successfully.";
        } else {
            $_SESSION['user_message'] = "Error resetting password: " . $stmt->error;
        }
    }
    // Delete user
    elseif($action === 'delete') {
        $user_id = $_POST['user_id'] ?? 0;
        
        // Cannot delete yourself
        if($user_id == $_SESSION['user_id']) {
            $_SESSION['user_message'] = "You cannot delete your own account.";
            header("Location: user_management.php");
            exit;
        }
        
        // Check if this user has associated sales records
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE user_id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $has_sales = $result->fetch_assoc()['count'] > 0;
        
        if($has_sales) {
            $_SESSION['user_message'] = "Cannot delete user because they have associated sales records.";
        } else {
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if($stmt->execute()) {
                $_SESSION['user_message'] = "User deleted successfully.";
            } else {
                $_SESSION['user_message'] = "Error deleting user: " . $stmt->error;
            }
        }
    } else {
        $_SESSION['user_message'] = "Invalid action specified.";
    }
    
    // Redirect back to user management
    header("Location: " . $redirect_url);
    exit;
} else {
    // If we get here, it wasn't a POST request
    $_SESSION['user_message'] = "Invalid request method.";
    header("Location: user_management.php");
    exit;
}
?>
