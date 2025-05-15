<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Handle time period filter
$period = isset($_GET['period']) ? $_GET['period'] : 'week';

// Determine date range based on period
$start_date = date('Y-m-d');
$end_date = date('Y-m-d');

switch($period) {
    case 'today':
        $start_date = date('Y-m-d');
        $period_text = "Today";
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $period_text = "This Week";
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_text = "This Month";
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-365 days'));
        $period_text = "This Year";
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $period_text = "This Week";
}

// Get total products
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM products");
$stmt->execute();
$result = $stmt->get_result();
$total_products = $result->fetch_assoc()['total'];

// Get total sales based on period
$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM sales WHERE created_at BETWEEN ? AND ? + INTERVAL 1 DAY");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$sales_period = $result->fetch_assoc()['total'] ?: 0;

// Get total sales today (keep this for quick comparison)
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM sales WHERE DATE(created_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$sales_today = $result->fetch_assoc()['total'] ?: 0;

// Get total cashiers
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'cashier'");
$stmt->execute();
$result = $stmt->get_result();
$total_cashiers = $result->fetch_assoc()['total'];

// Get total customers
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
$stmt->execute();
$result = $stmt->get_result();
$total_customers = $result->fetch_assoc()['total'];

// Get low stock products
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM inventory WHERE quantity <= 10");
$stmt->execute();
$result = $stmt->get_result();
$low_stock = $result->fetch_assoc()['total'];

// Get recent users
$stmt = $conn->prepare("
    SELECT id, username, full_name, role, created_at, last_login 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_users = $stmt->get_result();

// Get monthly sales data for chart
$monthly_sales = [];
for ($i = 0; $i < 12; $i++) {
    $month = date('m', strtotime("-$i months"));
    $year = date('Y', strtotime("-$i months"));
    $month_name = date('M', strtotime("-$i months"));
    
    $stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM sales WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
    $stmt->bind_param("ss", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total'] ?: 0;
    
    $monthly_sales[$month_name] = $total;
}
// Reverse the array to show chronological order
$monthly_sales = array_reverse($monthly_sales);

// Get recent transactions
$stmt = $conn->prepare("
    SELECT s.invoice_number, s.created_at, u.username, s.payment_method, s.total_amount
    FROM sales s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_transactions = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .dashboard-card {
            transition: transform 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        @media print {
            .no-print, .no-print * {
                display: none !important;
            }
            .main-content {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar - Remove the no-print wrapper so it matches other pages -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Admin Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0 no-print">
                        <div class="btn-group me-2">
                            <a href="add_sample_products.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus"></i> Add Sample Products
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportBtn">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="printBtn">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="periodDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-calendar"></i> <?php echo $period_text; ?>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="periodDropdown">
                                <li><a class="dropdown-item <?php echo $period == 'today' ? 'active' : ''; ?>" href="?period=today">Today</a></li>
                                <li><a class="dropdown-item <?php echo $period == 'week' ? 'active' : ''; ?>" href="?period=week">This Week</a></li>
                                <li><a class="dropdown-item <?php echo $period == 'month' ? 'active' : ''; ?>" href="?period=month">This Month</a></li>
                                <li><a class="dropdown-item <?php echo $period == 'year' ? 'active' : ''; ?>" href="?period=year">This Year</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Quick Stats -->
                <div class="row mb-4" id="dashboard-stats">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white dashboard-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $period_text; ?> Sales</h5>
                                <h2 class="card-text">₱ <?php echo number_format($sales_period, 2); ?></h2>
                                <p class="card-text"><small>Updated just now</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white dashboard-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Products</h5>
                                <h2 class="card-text"><?php echo $total_products; ?></h2>
                                <p class="card-text"><small>In inventory</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark dashboard-card">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock Items</h5>
                                <h2 class="card-text"><?php echo $low_stock; ?></h2>
                                <p class="card-text"><small>Need reordering</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white dashboard-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <h2 class="card-text"><?php echo $total_cashiers + $total_customers; ?></h2>
                                <p class="card-text"><small><?php echo $total_cashiers; ?> cashiers, <?php echo $total_customers; ?> customers</small></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Access Modules -->
                <div class="row mb-4 no-print">
                    <div class="col-12">
                        <h4>Quick Access</h4>
                    </div>
                    <div class="col-md-2">
                        <a href="inventory.php" class="text-decoration-none">
                            <div class="card dashboard-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-box fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Inventory</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="pos.php" class="text-decoration-none">
                            <div class="card dashboard-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-cash-register fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">POS</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="reports.php" class="text-decoration-none">
                            <div class="card dashboard-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Reports</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="user_management.php" class="text-decoration-none">
                            <div class="card dashboard-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-cog fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Staff</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="customers.php" class="text-decoration-none">
                            <div class="card dashboard-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-secondary mb-3"></i>
                                    <h5 class="card-title">Customers</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="settings.php" class="text-decoration-none">
                            <div class="card dashboard-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-cogs fa-3x text-dark mb-3"></i>
                                    <h5 class="card-title">Settings</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <!-- Recent Users -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Users</h5>
                                <a href="customers.php" class="btn btn-sm btn-primary no-print">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="recent-users-table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Role</th>
                                                <th>Registered</th>
                                                <th class="no-print">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if($recent_users->num_rows > 0) {
                                                while($user = $recent_users->fetch_assoc()) {
                                                    echo "<tr>";
                                                    echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
                                                    echo "<td><span class='badge bg-" . 
                                                        ($user['role'] == 'admin' ? 'danger' : 
                                                        ($user['role'] == 'cashier' ? 'primary' : 'secondary')) . 
                                                        "'>" . ucfirst($user['role']) . "</span></td>";
                                                    echo "<td>" . date('M d, Y', strtotime($user['created_at'])) . "</td>";
                                                    echo "<td class='no-print'>
                                                        <a href='view_user.php?id={$user['id']}' class='btn btn-sm btn-info' title='View'><i class='fas fa-eye'></i></a>
                                                        <a href='edit_user.php?id={$user['id']}' class='btn btn-sm btn-warning' title='Edit'><i class='fas fa-edit'></i></a>
                                                    </td>";
                                                    echo "</tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='4' class='text-center'>No users found</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <!-- Recent Transactions -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Transactions</h5>
                                <a href="sales_history.php" class="btn btn-sm btn-primary no-print">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="recent-transactions-table">
                                        <thead>
                                            <tr>
                                                <th>Invoice #</th>
                                                <th>Date</th>
                                                <th>Cashier</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if($recent_transactions->num_rows > 0) {
                                                while($row = $recent_transactions->fetch_assoc()) {
                                                    echo "<tr>";
                                                    echo "<td>" . $row['invoice_number'] . "</td>";
                                                    echo "<td>" . date('M d, Y', strtotime($row['created_at'])) . "</td>";
                                                    echo "<td>" . $row['username'] . "</td>";
                                                    echo "<td>₱ " . number_format($row['total_amount'], 2) . "</td>";
                                                    echo "</tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='4' class='text-center'>No recent transactions</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Activity Chart -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Sales Overview</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add style to fix print behavior -->
    <style>
        @media print {
            .sidebar, .btn-toolbar, .no-print {
                display: none !important;
            }
            
            .main-content {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sales chart
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            const salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_keys($monthly_sales)); ?>,
                    datasets: [{
                        label: 'Monthly Sales',
                        data: <?php echo json_encode(array_values($monthly_sales)); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Sales Overview'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Print button functionality
            document.getElementById('printBtn').addEventListener('click', function() {
                window.print();
            });
            
            // Export button functionality
            document.getElementById('exportBtn').addEventListener('click', function() {
                // Export dashboard data
                const dashboardData = {
                    stats: {
                        salesPeriod: <?php echo $sales_period; ?>,
                        totalProducts: <?php echo $total_products; ?>,
                        lowStock: <?php echo $low_stock; ?>,
                        totalUsers: <?php echo $total_cashiers + $total_customers; ?>
                    },
                    period: '<?php echo $period_text; ?>',
                    exportDate: new Date().toLocaleString()
                };
                
                // Convert users table to CSV
                const usersTable = document.getElementById('recent-users-table');
                let usersCSV = '\nRecent Users\n';
                usersCSV += 'Name,Role,Registered\n';
                
                const userRows = usersTable.querySelectorAll('tbody tr');
                userRows.forEach(row => {
                    const name = row.cells[0].textContent;
                    const role = row.cells[1].textContent;
                    const registered = row.cells[2].textContent;
                    usersCSV += `${name},${role},${registered}\n`;
                });
                
                // Convert transactions table to CSV
                const transactionsTable = document.getElementById('recent-transactions-table');
                let transactionsCSV = '\nRecent Transactions\n';
                transactionsCSV += 'Invoice #,Date,Cashier,Amount\n';
                
                const transactionRows = transactionsTable.querySelectorAll('tbody tr');
                transactionRows.forEach(row => {
                    const invoice = row.cells[0].textContent;
                    const date = row.cells[1].textContent;
                    const cashier = row.cells[2].textContent;
                    const amount = row.cells[3].textContent;
                    transactionsCSV += `${invoice},${date},${cashier},${amount}\n`;
                });
                
                // Create full CSV
                let csv = 'Bohemian Burrows Dashboard Export\n';
                csv += `Period: ${dashboardData.period}, Export Date: ${dashboardData.exportDate}\n\n`;
                csv += `Sales: ₱${dashboardData.stats.salesPeriod.toFixed(2)}\n`;
                csv += `Total Products: ${dashboardData.stats.totalProducts}\n`;
                csv += `Low Stock Items: ${dashboardData.stats.lowStock}\n`;
                csv += `Total Users: ${dashboardData.stats.totalUsers}\n`;
                
                csv += usersCSV;
                csv += transactionsCSV;
                
                // Create a download link for the CSV
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.setAttribute('href', url);
                link.setAttribute('download', `dashboard_export_${new Date().toISOString().slice(0,10)}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
    </script>
</body>
</html>
