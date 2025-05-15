<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid user ID.";
    header("Location: user_management.php");
    exit;
}

$user_id = (int)$_GET['id'];

// Get user details
$stmt = $conn->prepare("SELECT id, username, full_name, email, role, created_at, last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if(!$user) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: user_management.php");
    exit;
}

// Placeholder for additional user activity (e.g., recent orders for customers, sales for cashiers)
$user_activity = [];
if ($user['role'] == 'customer') {
    // Example: Get recent orders for customer
    $activity_stmt = $conn->prepare("SELECT id, invoice_number, total_amount, created_at, payment_status FROM sales WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $activity_stmt->bind_param("i", $user_id);
    $activity_stmt->execute();
    $user_activity_result = $activity_stmt->get_result();
    while($row = $user_activity_result->fetch_assoc()) {
        $user_activity[] = $row;
    }
} elseif ($user['role'] == 'cashier') {
    // Example: Get recent sales processed by cashier
    $activity_stmt = $conn->prepare("SELECT id, invoice_number, total_amount, created_at FROM sales WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $activity_stmt->bind_param("i", $user_id);
    $activity_stmt->execute();
    $user_activity_result = $activity_stmt->get_result();
    while($row = $user_activity_result->fetch_assoc()) {
        $user_activity[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User: <?php echo htmlspecialchars($user['username']); ?> - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .profile-card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .profile-header {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
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
                    <h1 class="h2">User Profile: <?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="user_management.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Back to User Management
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal"
                                data-id="<?php echo $user['id']; ?>"
                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                data-fullname="<?php echo htmlspecialchars($user['full_name']); ?>"
                                data-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                data-role="<?php echo $user['role']; ?>">
                            <i class="fas fa-edit"></i> Edit User
                        </button>
                    </div>
                </div>

                <div class="card profile-card">
                    <div class="profile-header text-center">
                        <img src="../assets/images/default-avatar.png" alt="<?php echo htmlspecialchars($user['full_name']); ?>" class="profile-avatar mb-2">
                        <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <p class="text-muted mb-0">@<?php echo htmlspecialchars($user['username']); ?></p>
                        <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'cashier' ? 'primary' : 'info'); ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="mb-3">User Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <p class="info-label mb-1">Email Address</p>
                                <p><?php echo htmlspecialchars($user['email'] ?? 'Not provided'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="info-label mb-1">Account Created</p>
                                <p><?php echo date('F j, Y, g:i a', strtotime($user['created_at'])); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="info-label mb-1">Last Login</p>
                                <p><?php echo $user['last_login'] ? date('F j, Y, g:i a', strtotime($user['last_login'])) : 'Never'; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="info-label mb-1">User ID</p>
                                <p><?php echo $user['id']; ?></p>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Recent Activity</h5>
                        <?php if(!empty($user_activity)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th><?php echo $user['role'] == 'customer' ? 'Invoice #' : 'Transaction ID'; ?></th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <?php if ($user['role'] == 'customer'): ?>
                                                <th>Status</th>
                                            <?php endif; ?>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($user_activity as $activity): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($activity['invoice_number']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></td>
                                            <td>₱<?php echo number_format($activity['total_amount'], 2); ?></td>
                                            <?php if ($user['role'] == 'customer'): ?>
                                                <td>
                                                    <span class="badge status-badge status-<?php echo strtolower($activity['payment_status']); ?>">
                                                        <?php echo ucfirst($activity['payment_status']); ?>
                                                    </span>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <a href="order_details.php?id=<?php echo $activity['id']; ?>" class="btn btn-xs btn-outline-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No recent activity found for this user.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit User Modal (Include from user_management.php or define here if needed) -->
    <!-- For simplicity, assuming the modal structure is similar to user_management.php -->
    <!-- You might want to include a shared modal file or ensure this matches -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" action="user_process.php" method="post">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="customer">Customer</option>
                                <option value="cashier">Cashier</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="redirect_to" value="view_user.php?id=<?php echo $user_id; ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editUserForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        const editUserButtonInPage = document.querySelector('button[data-bs-target="#editUserModal"]');

        if (editUserButtonInPage) {
            editUserButtonInPage.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const username = this.getAttribute('data-username');
                const fullname = this.getAttribute('data-fullname');
                const email = this.getAttribute('data-email');
                const role = this.getAttribute('data-role');
                
                document.getElementById('edit_user_id').value = id;
                document.getElementById('edit_username').value = username;
                document.getElementById('edit_full_name').value = fullname;
                document.getElementById('edit_email').value = email;
                document.getElementById('edit_role').value = role;
            });
        }
        
        // Form validation for edit user
        const editUserForm = document.getElementById('editUserForm');
        if (editUserForm) {
            editUserForm.addEventListener('submit', function(e) {
                const username = document.getElementById('edit_username').value.trim();
                const fullName = document.getElementById('edit_full_name').value.trim();
                
                if (!username || !fullName) {
                    e.preventDefault();
                    alert('Username and Full Name are required fields.');
                }
            });
        }
    });
    </script>
</body>
</html>
