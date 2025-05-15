<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Check if user ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: customers.php");
    exit;
}

$user_id = $_GET['id'];
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'customers.php';
$return_page = (strpos($referrer, 'user_management.php') !== false) ? 'user_management.php' : 'customers.php';

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: $return_page");
    exit;
}

$user = $result->fetch_assoc();

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? $user['role'];
    $password = $_POST['password'] ?? '';
    
    // Validation
    $errors = [];
    
    if(empty($username)) {
        $errors[] = "Username is required";
    }
    
    if(empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if username already exists (for another user)
    if(!empty($username)) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $user_id);
        $check_stmt->execute();
        if($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Username already exists. Please choose a different one.";
        }
    }
    
    if(empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            if(!empty($password)) {
                // Update with new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $username, $full_name, $email, $role, $hashed_password, $user_id);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $username, $full_name, $email, $role, $user_id);
            }
            
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $success = "User updated successfully!";
            
            // Refresh user data after update
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } catch(Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Error updating user: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit User</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="<?php echo $return_page; ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="role" class="form-label">User Role</label>
                                        <select class="form-select" id="role" name="role">
                                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="cashier" <?php echo $user['role'] == 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                            <option value="customer" <?php echo $user['role'] == 'customer' ? 'selected' : ''; ?>>Customer</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">New Password <small class="text-muted">(Leave blank to keep current password)</small></label>
                                        <input type="password" class="form-control" id="password" name="password">
                                        <div class="form-text">Minimum 6 characters recommended</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="created_at" class="form-label">Registered Date</label>
                                        <input type="text" class="form-control" id="created_at" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Last Login</label>
                                <p><?php echo $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></p>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <a href="<?php echo $return_page; ?>" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
