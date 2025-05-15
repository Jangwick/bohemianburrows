<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Initialize variables
$success_message = '';
$error_message = '';

// Get user data
$stmt = $conn->prepare("SELECT username, full_name, email, created_at, last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user orders count and total spent
$order_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as total_spent
    FROM sales
    WHERE user_id = ? OR customer_name = ?
");
$order_stmt->bind_param("is", $_SESSION['user_id'], $user['full_name']);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order_stats = $order_result->fetch_assoc();

// Process profile update
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    if (empty($full_name)) {
        $error_message = "Full name cannot be empty";
    } else {
        // Update user information
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $full_name, $email, $_SESSION['user_id']);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Update local user data
            $user['full_name'] = $full_name;
            $user['email'] = $email;
        } else {
            $error_message = "Failed to update profile: " . $conn->error;
        }
    }
}

// Process password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $pwd_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $pwd_stmt->bind_param("i", $_SESSION['user_id']);
    $pwd_stmt->execute();
    $pwd_result = $pwd_stmt->get_result();
    $pwd_data = $pwd_result->fetch_assoc();
    
    if (!password_verify($current_password, $pwd_data['password'])) {
        $error_message = "Current password is incorrect";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Password must be at least 6 characters";
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_pwd_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_pwd_stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
        
        if ($update_pwd_stmt->execute()) {
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Failed to update password: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #8d6e63, #6d4c41);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        .bohemian-divider {
            height: 3px;
            width: 60%;
            margin: 1rem auto;
            background: linear-gradient(90deg, 
                rgba(255,255,255,0), 
                #d7ccc8, 
                #a1887f, 
                #8d6e63, 
                #a1887f, 
                #d7ccc8, 
                rgba(255,255,255,0));
            border-radius: 10px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #a1887f;
            color: white;
            font-size: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .profile-stat {
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .profile-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .input-group-text {
            background-color: #f7f7f7;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Profile Header -->
                <div class="profile-header text-center">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['full_name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p class="lead">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                    <div class="bohemian-divider"></div>
                </div>
                
                <?php if(!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body profile-stat">
                                <i class="fas fa-shopping-bag fa-3x mb-3"></i>
                                <h2><?php echo $order_stats['order_count'] ?? 0; ?></h2>
                                <p>Orders Placed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body profile-stat">
                                <i class="fas fa-coins fa-3x mb-3"></i>
                                <h2>₱<?php echo number_format($order_stats['total_spent'] ?? 0, 2); ?></h2>
                                <p>Total Spent</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Profile Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i> Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                        </div>
                                        <div class="form-text">Username cannot be changed</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Last Login</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                            <input type="text" class="form-control" value="<?php echo $user['last_login'] ? date('F j, Y, g:i a', strtotime($user['last_login'])) : 'Never'; ?>" readonly>
                                        </div>
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Changes
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-key me-2"></i> Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        </div>
                                        <div class="form-text">Password must be at least 6 characters</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="fas fa-key me-1"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Quick Links -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-link me-2"></i> Quick Links</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <a href="orders.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-shopping-bag me-2"></i> My Orders
                                    </a>
                                    <a href="wishlist.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-heart me-2"></i> My Wishlist
                                    </a>
                                    <a href="shop.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-store me-2"></i> Continue Shopping
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePassword() {
                if(password.value != confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            password.addEventListener('change', validatePassword);
            confirmPassword.addEventListener('keyup', validatePassword);
        });
    </script>
</body>
</html>
