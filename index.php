<?php
session_start();
// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Process login
if($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once "includes/db_connect.php";
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if(password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Update last login time
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header("Location: admin/dashboard.php");
                    break;
                case 'cashier':
                    header("Location: cashier/pos.php");
                    break;
                case 'customer':
                    header("Location: user/dashboard.php");
                    break;
                default:
                    header("Location: index.php");
            }
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Username not found!";
    }
    
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
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
        
        .login-card {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 2.5rem !important;
        }
        
        .login-title {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: #6d4c41;
            margin-bottom: 0;
            font-size: 2rem;
        }
        
        .login-subtitle {
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
    </style>
    <!-- Add Google Fonts for better typography -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-body text-center">
                        <!-- Logo with proper styling -->
                        <img src="assets/images/bohemian logo.jpg" alt="Bohemian Burrows" class="store-logo mb-3" style="width: 120px; height: auto;">
                        
                        <h1 class="login-title">The Bohemian Burrows</h1>
                        <p class="login-subtitle">Artisan Fashion & Lifestyle</p>
                        
                        <div class="bohemian-divider"></div>
                        
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="" class="text-start">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
                            </div>
                        </form>
                        <div class="login-links">
                            <p class="mb-1">Don't have an account? <a href="register.php">Register</a></p>
                            <p class="mb-0"><a href="forgot_password.php">Forgot your password?</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
