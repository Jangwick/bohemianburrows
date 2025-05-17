<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Handle user deletion
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // Don't allow admin to delete themselves
    if($user_id != $_SESSION['user_id']) {
        // First check if the user has any sales records
        $check_sales_stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE user_id = ?");
        $check_sales_stmt->bind_param("i", $user_id);
        $check_sales_stmt->execute();
        $sales_result = $check_sales_stmt->get_result();
        $sales_count = $sales_result->fetch_assoc()['count'];
        
        if($sales_count > 0) {
            // User has sales, cannot delete
            $error = "Cannot delete this user because they have $sales_count associated orders. Consider deactivating the account instead.";
        } else {
            // Safe to delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            if($stmt->affected_rows > 0) {
                $success = "User deleted successfully.";
            } else {
                $error = "Failed to delete user.";
            }
        }
    } else {
        $error = "You cannot delete your own account.";
    }
}

// Handle role update
if(isset($_POST['update_role']) && isset($_POST['user_id']) && isset($_POST['role'])) {
    $user_id = $_POST['user_id'];
    $role = $_POST['role'];
    
    // Don't allow admin to change their own role
    if($user_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $role, $user_id);
        $stmt->execute();
        
        if($stmt->affected_rows > 0) {
            $success = "User role updated successfully.";
        } else {
            $error = "No changes made to user role.";
        }
    } else {
        $error = "You cannot change your own role.";
    }
}

// Get all users with filtering options
$where = "1=1"; // Always true condition to start
$params = [];
$types = "";

if(isset($_GET['role']) && !empty($_GET['role'])) {
    $where .= " AND role = ?";
    $params[] = $_GET['role'];
    $types .= "s";
}

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

$sql = "SELECT * FROM users WHERE $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);

if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$users = [];
while($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - The Bohemian Burrows</title>
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
                    <h1 class="h2">User Management</h1>
                </div>

                <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Filter Controls -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               placeholder="Username, name, email..." 
                                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="role" class="form-label">Role</label>
                                        <select class="form-select" id="role" name="role">
                                            <option value="">All Roles</option>
                                            <option value="admin" <?php echo (isset($_GET['role']) && $_GET['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            <option value="cashier" <?php echo (isset($_GET['role']) && $_GET['role'] == 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                                            <option value="customer" <?php echo (isset($_GET['role']) && $_GET['role'] == 'customer') ? 'selected' : ''; ?>>Customer</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                                        <a href="customers.php" class="btn btn-secondary">Reset</a>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end justify-content-end">
                                        <a href="add_user.php" class="btn btn-success">Add New User</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Registered</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($users) > 0): ?>
                                        <?php foreach($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                            <td>
                                                <form method="POST" class="d-inline role-form">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="update_role" value="1">
                                                    <select name="role" class="form-select form-select-sm role-select" 
                                                            <?php echo ($user['id'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                                        <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                                        <option value="cashier" <?php echo ($user['role'] == 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                                                        <option value="customer" <?php echo ($user['role'] == 'customer') ? 'selected' : ''; ?>>Customer</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td><?php echo ($user['last_login']) ? date('M d, Y', strtotime($user['last_login'])) : 'Never'; ?></td>
                                            <td>
                                                <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteCustomerModal<?php echo $user['id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>

                                                <!-- Delete Confirmation Modal -->
                                                <div class="modal fade" id="deleteCustomerModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Confirm Delete</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete user <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>?</p>
                                                                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Note: Customers with purchase history cannot be deleted.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <a href="customers.php?delete=<?php echo $user['id']; ?>" class="btn btn-danger">Delete</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No users found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Role select change
        document.querySelectorAll('.role-select').forEach(select => {
            select.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });
    </script>
</body>
</html>                deleteModal.show();
            });
        });
    </script>
</body>
</html>
