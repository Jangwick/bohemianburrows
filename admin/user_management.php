<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Get all users except current admin
$stmt = $conn->prepare("SELECT id, username, role, full_name, email, last_login FROM users WHERE id != ? ORDER BY role, username");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
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
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus"></i> Add New User
                        </button>
                    </div>
                </div>

                <?php if(isset($_SESSION['user_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['user_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['user_message']); ?>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($result->num_rows > 0): ?>
                                        <?php while($user = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if($user['last_login']): ?>
                                                        <?php echo date('M d, Y h:i A', strtotime($user['last_login'])); ?>
                                                    <?php else: ?>
                                                        Never
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary edit-user" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editUserModal"
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                            data-fullname="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                            data-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                                            data-role="<?php echo $user['role']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning reset-password" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#resetPasswordModal"
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger delete-user" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteUserModal"
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No users found.</td>
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm" action="user_process.php" method="post">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="cashier">Cashier</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <input type="hidden" name="action" value="add">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addUserForm" class="btn btn-primary">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
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
                                <option value="cashier">Cashier</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <input type="hidden" name="action" value="update">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editUserForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Reset password for user: <strong id="reset_username"></strong></p>
                    <form id="resetPasswordForm" action="user_process.php" method="post">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="password" required>
                        </div>
                        <input type="hidden" name="user_id" id="reset_user_id">
                        <input type="hidden" name="action" value="reset_password">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="resetPasswordForm" class="btn btn-warning">Reset Password</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user: <strong id="delete_username"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                    <form id="deleteUserForm" action="user_process.php" method="post">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <input type="hidden" name="action" value="delete">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="deleteUserForm" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle edit user modal
        const editButtons = document.querySelectorAll('.edit-user');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
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
        });
        
        // Form validation for edit user
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            const username = document.getElementById('edit_username').value.trim();
            const fullName = document.getElementById('edit_full_name').value.trim();
            
            if (!username || !fullName) {
                e.preventDefault();
                alert('Username and Full Name are required fields.');
            }
        });
        
        // Handle reset password modal
        const resetButtons = document.querySelectorAll('.reset-password');
        resetButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const username = this.getAttribute('data-username');
                
                document.getElementById('reset_user_id').value = id;
                document.getElementById('reset_username').textContent = username;
            });
        });
        
        // Handle delete user modal
        const deleteButtons = document.querySelectorAll('.delete-user');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const username = this.getAttribute('data-username');
                
                document.getElementById('delete_user_id').value = id;
                document.getElementById('delete_username').textContent = username;
            });
        });
    });
    </script>
</body>
</html>
