<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db_connect.php';

$error = '';
$success = '';

// Check if users table has the 'customer' role
$checkRoleQuery = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
$roleColumn = $checkRoleQuery->fetch_assoc();
if ($roleColumn) {
    $roleType = $roleColumn['Type'];
    // If 'customer' role doesn't exist in ENUM
    if (strpos($roleType, 'customer') === false) {
        // Alter table to add 'customer' role
        $conn->query("ALTER TABLE users MODIFY role ENUM('admin', 'cashier', 'customer') NOT NULL");
    }
}

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    // Validation
    if (empty($username) || empty($password) || empty($confirm_password) || empty($full_name)) {
        $error = "All fields marked with * are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = "Username already exists. Please choose another one.";
        } else {
            // Insert new user with 'customer' role
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = "customer";
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $hashed_password, $role, $full_name, $email);
            
            if ($stmt->execute()) {
                $success = "Registration successful! You can now <a href='index.php'>login</a>.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), 
                         url('assets/images/bohemian-bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 100vh;
            font-family: 'Poppins', sans-serif;
        }
        
        .registration-card {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 2.5rem !important;
        }
        
        .registration-title {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: #6d4c41;
            margin-bottom: 0;
            font-size: 2rem;
        }
        
        .registration-subtitle {
            color: #8d6e63;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8d6e63, #6d4c41);
            border: none;
            font-weight: 600;
            letter-spacing: 1px;
            padding: 12px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #6d4c41, #5d4037);
            box-shadow: 0 5px 15px rgba(109, 76, 65, 0.4);
            transform: translateY(-3px);
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #d7ccc8;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(141, 110, 99, 0.25);
            border-color: #8d6e63;
        }
        
        .form-label {
            color: #5d4037;
            font-weight: 500;
        }
        
        .login-links a {
            color: #8d6e63;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .login-links a:hover {
            color: #6d4c41;
            text-decoration: underline;
        }
        
        .store-logo {
            width: 80px;
            height: auto;
            margin-bottom: 1rem;
        }
        
        .required:after {
            content: " *";
            color: #e57373;
        }
        
        /* Add a decorative bohemian element */
        .bohemian-divider {
            height: 5px;
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
        
        .form-text {
            color: #8d6e63;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6">
                <div class="card registration-card">
                    <div class="card-body text-center">
                        <!-- Logo with proper styling -->
                        <img src="assets/images/logo.png" alt="Bohemian Burrows" class="store-logo mb-3" style="width: 120px; height: auto;">
                        
                        <h1 class="registration-title">The Bohemian Burrows</h1>
                        <p class="registration-subtitle">Create Your Account</p>
                        
                        <div class="bohemian-divider"></div>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="text-start">
                            <div class="mb-3">
                                <label for="username" class="form-label required">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label required">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">At least 6 characters long</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label required">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label required">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            
                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                            </div>
                        </form>
                        
                        <div class="login-links">
                            <p class="mb-0">Already have an account? <a href="index.php">Sign In</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
