<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'cashier') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

$success_message = '';
$error_message = '';

// Get current user data
$stmt = $conn->prepare("SELECT username, full_name, email, created_at, last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle profile update
if(isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    if(empty($full_name)) {
        $error_message = "Full name cannot be empty";
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $full_name, $email, $_SESSION['user_id']);
        
        if($update_stmt->execute()) {
            $success_message = "Profile updated successfully";
            // Update local user data
            $user['full_name'] = $full_name;
            $user['email'] = $email;
        } else {
            $error_message = "Failed to update profile: " . $conn->error;
        }
    }
}

// Handle password change
if(isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // First verify current password
    $pwd_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $pwd_stmt->bind_param("i", $_SESSION['user_id']);
    $pwd_stmt->execute();
    $pwd_result = $pwd_stmt->get_result();
    $stored_hash = $pwd_result->fetch_assoc()['password'];
    
    if(!password_verify($current_password, $stored_hash)) {
        $error_message = "Current password is incorrect";
    } elseif(strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters";
    } elseif($new_password !== $confirm_password) {
        $error_message = "New passwords do not match";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
        
        if($update_stmt->execute()) {
            $success_message = "Password changed successfully";
        } else {
            $error_message = "Failed to change password: " . $conn->error;
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
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .profile-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #a1887f;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            margin: 0 auto 15px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .form-label {
            font-weight: 500;
        }
        .account-details li {
            padding: 8px 0;
            border-bottom: 1px solid #f1f1f1;
        }
        .account-details li:last-child {
            border-bottom: none;
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Profile</h1>
                </div>
                
                <?php if($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <div class="profile-image">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        <div class="col-md-10">
                            <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                            <p class="mb-0"><i class="fas fa-id-badge me-2"></i> Cashier</p>
                            <p class="mb-0"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($user['email'] ?? 'No email provided'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Account Details -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Account Details</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled account-details">
                                    <li>
                                        <strong>Username:</strong>
                                        <div class="text-muted"><?php echo htmlspecialchars($user['username']); ?></div>
                                    </li>
                                    <li>
                                        <strong>Account Created:</strong>
                                        <div class="text-muted"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                                    </li>
                                    <li>
                                        <strong>Last Login:</strong>
                                        <div class="text-muted">
                                            <?php echo $user['last_login'] ? date('F j, Y, g:i a', strtotime($user['last_login'])) : 'Never'; ?>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Your Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get total sales by this cashier
                                $stats_stmt = $conn->prepare("
                                    SELECT 
                                        COUNT(*) as total_sales,
                                        COALESCE(SUM(total_amount), 0) as total_amount
                                    FROM sales
                                    WHERE user_id = ?
                                ");
                                $stats_stmt->bind_param("i", $_SESSION['user_id']);
                                $stats_stmt->execute();
                                $stats = $stats_stmt->get_result()->fetch_assoc();
                                ?>
                                
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4><?php echo $stats['total_sales']; ?></h4>
                                        <p class="text-muted mb-0">Transactions</p>
                                    </div>
                                    <div class="col-6">
                                        <h4>₱<?php echo number_format($stats['total_amount'], 2); ?></h4>
                                        <p class="text-muted mb-0">Total Sales</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Information -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Update Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                        <small class="form-text text-muted">Username cannot be changed.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Changes
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Change Password -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="" id="password-form">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                        <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-danger">
                                        <i class="fas fa-key me-1"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password form validation
            const passwordForm = document.getElementById('password-form');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            passwordForm.addEventListener('submit', function(event) {
                if (newPassword.value !== confirmPassword.value) {
                    event.preventDefault();
                    alert('New passwords do not match!');
                }
            });
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>
