<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Redirect to specific dashboard based on role
if($_SESSION['role'] == 'admin') {
    header("Location: admin/dashboard.php");
} else {
    header("Location: cashier/pos.php");
}
exit;
?>
