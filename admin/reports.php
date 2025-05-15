<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Set default date range to current month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get sales summary
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(total_amount) as total_sales,
        SUM(discount) as total_discounts,
        AVG(total_amount) as average_sale
    FROM sales
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Get sales by payment method
$payment_stmt = $conn->prepare("
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(total_amount) as total
    FROM sales
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_method
");
$payment_stmt->bind_param("ss", $start_date, $end_date);
$payment_stmt->execute();
$payment_methods = $payment_stmt->get_result();

// Get daily sales for chart
$daily_stmt = $conn->prepare("
    SELECT 
        DATE(created_at) as date,
        SUM(total_amount) as daily_total
    FROM sales
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
");
$daily_stmt->bind_param("ss", $start_date, $end_date);
$daily_stmt->execute();
$daily_sales = $daily_stmt->get_result();

// Top selling products
$top_products_stmt = $conn->prepare("
    SELECT 
        p.name,
        SUM(si.quantity) as total_quantity,
        SUM(si.subtotal) as total_sales
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY total_quantity DESC
    LIMIT 10
");
$top_products_stmt->bind_param("ss", $start_date, $end_date);
$top_products_stmt->execute();
$top_products = $top_products_stmt->get_result();

// Top performing cashiers
$cashier_stmt = $conn->prepare("
    SELECT 
        u.username,
        u.full_name,
        COUNT(s.id) as total_transactions,
        SUM(s.total_amount) as total_sales
    FROM sales s
    JOIN users u ON s.user_id = u.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY s.user_id
    ORDER BY total_sales DESC
");
$cashier_stmt->bind_param("ss", $start_date, $end_date);
$cashier_stmt->execute();
$cashiers = $cashier_stmt->get_result();

// Format dates for chart
$dates = [];
$sales = [];
$daily_sales_data = [];

while($row = $daily_sales->fetch_assoc()) {
    $dates[] = date('M d', strtotime($row['date']));
    $sales[] = $row['daily_total'];
    $daily_sales_data[$row['date']] = $row['daily_total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - The Bohemian Burrows</title>
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
                    <h1 class="h2">Sales Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printReport()">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                </div>

                <!-- Date Range Selector -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sales Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Sales</h5>
                                <h2 class="card-text">₱ <?php echo number_format($summary['total_sales'] ?? 0, 2); ?></h2>
                                <p class="card-text"><small><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d', strtotime($end_date)); ?></small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Transactions</h5>
                                <h2 class="card-text"><?php echo $summary['total_transactions'] ?? 0; ?></h2>
                                <p class="card-text"><small>Total orders</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Average Sale</h5>
                                <h2 class="card-text">₱ <?php echo number_format($summary['average_sale'] ?? 0, 2); ?></h2>
                                <p class="card-text"><small>Per transaction</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Total Discounts</h5>
                                <h2 class="card-text">₱ <?php echo number_format($summary['total_discounts'] ?? 0, 2); ?></h2>
                                <p class="card-text"><small>Amount discounted</small></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Sales Chart -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Daily Sales</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Sales by Payment Method</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="paymentChart"></canvas>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Payment Method</th>
                                                <th>Transactions</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $payment_data = [];
                                            $payment_labels = [];
                                            $payment_counts = [];
                                            ?>
                                            <?php while($method = $payment_methods->fetch_assoc()): ?>
                                                <?php 
                                                $payment_labels[] = ucfirst($method['payment_method']);
                                                $payment_counts[] = $method['count'];
                                                ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge <?php 
                                                            echo ($method['payment_method'] == 'cash') ? 'bg-success' : 
                                                                (($method['payment_method'] == 'card') ? 'bg-primary' : 
                                                                (($method['payment_method'] == 'gcash') ? 'bg-info' : 'bg-warning')); 
                                                        ?>">
                                                            <?php echo ucfirst($method['payment_method']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $method['count']; ?></td>
                                                    <td>₱ <?php echo number_format($method['total'], 2); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Top Products -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Top Selling Products</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Product</th>
                                                <th>Units Sold</th>
                                                <th>Total Sales</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $count = 1; ?>
                                            <?php while($product = $top_products->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $count++; ?></td>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td><?php echo $product['total_quantity']; ?></td>
                                                    <td>₱ <?php echo number_format($product['total_sales'], 2); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cashier Performance -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Cashier Performance</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Cashier</th>
                                                <th>Transactions</th>
                                                <th>Sales</th>
                                                <th>Average</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($cashier = $cashiers->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($cashier['full_name']); ?></td>
                                                    <td><?php echo $cashier['total_transactions']; ?></td>
                                                    <td>₱ <?php echo number_format($cashier['total_sales'], 2); ?></td>
                                                    <td>₱ <?php echo number_format($cashier['total_sales'] / $cashier['total_transactions'], 2); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Daily Sales (₱)',
                    data: <?php echo json_encode($sales); ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Payment Methods Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($payment_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($payment_counts); ?>,
                    backgroundColor: [
                        'rgba(25, 135, 84, 0.8)',
                        'rgba(13, 110, 253, 0.8)',
                        'rgba(13, 202, 240, 0.8)',
                        'rgba(255, 193, 7, 0.8)'
                    ],
                    borderColor: [
                        'rgba(25, 135, 84, 1)',
                        'rgba(13, 110, 253, 1)',
                        'rgba(13, 202, 240, 1)',
                        'rgba(255, 193, 7, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
        
        // Print report function
        window.printReport = function() {
            window.print();
        };
    });
    </script>
</body>
</html>
