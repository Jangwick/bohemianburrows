<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'cashier') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Get cashier information
$stmt = $conn->prepare("SELECT full_name, last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$cashier = $result->fetch_assoc();

// Handle time period filter for sales statistics
$period = isset($_GET['period']) ? $_GET['period'] : 'today';

// Determine date range based on period
$start_date = date('Y-m-d');
$end_date = date('Y-m-d');

switch($period) {
    case 'today':
        $period_text = "Today";
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $period_text = "Last 7 Days";
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_text = "Last 30 Days";
        break;
    case 'all':
        $start_date = '2000-01-01'; // Far in the past to include all
        $period_text = "All Time";
        break;
    default:
        $period_text = "Today";
}

// Get cashier's sales for the selected period
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(total_amount) as total_sales,
        AVG(total_amount) as average_sale,
        SUM(discount) as total_discounts
    FROM sales 
    WHERE user_id = ? AND created_at BETWEEN ? AND ? + INTERVAL 1 DAY
");
$stmt->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
$stmt->execute();
$sales_stats = $stmt->get_result()->fetch_assoc();

// Get recent sales by this cashier
$stmt = $conn->prepare("
    SELECT 
        id,
        invoice_number, 
        created_at, 
        total_amount, 
        payment_method,
        customer_name
    FROM sales
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_sales = $stmt->get_result();

// Get most sold products by this cashier
$stmt = $conn->prepare("
    SELECT 
        p.name,
        SUM(si.quantity) as total_quantity,
        SUM(si.subtotal) as total_amount
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    WHERE s.user_id = ?
    GROUP BY p.id
    ORDER BY total_quantity DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$top_products = $stmt->get_result();

// Get daily sales data for the chart (last 7 days)
$daily_sales = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime("-$i days"));
    
    $stmt = $conn->prepare("
        SELECT SUM(total_amount) as total 
        FROM sales 
        WHERE user_id = ? AND DATE(created_at) = ?
    ");
    $stmt->bind_param("is", $_SESSION['user_id'], $day);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total'] ?: 0;
    
    $daily_sales[$day_name] = $total;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .dashboard-card {
            transition: transform 0.2s;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .welcome-header {
            background: linear-gradient(135deg, #6d4c41, #8d6e63);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .quick-action {
            text-align: center;
            padding: 15px;
            border-radius: 12px;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .quick-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .quick-action .icon {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
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
                <!-- Welcome Header -->
                <div class="welcome-header d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">Welcome back, <?php echo htmlspecialchars($cashier['full_name']); ?></h1>
                        <p class="mb-0">
                            <i class="fas fa-clock me-2"></i>
                            <?php echo date('l, F j, Y'); ?>
                        </p>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" id="periodDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-calendar me-2"></i><?php echo $period_text; ?>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="periodDropdown">
                            <li><a class="dropdown-item <?php echo $period == 'today' ? 'active' : ''; ?>" href="?period=today">Today</a></li>
                            <li><a class="dropdown-item <?php echo $period == 'week' ? 'active' : ''; ?>" href="?period=week">Last 7 Days</a></li>
                            <li><a class="dropdown-item <?php echo $period == 'month' ? 'active' : ''; ?>" href="?period=month">Last 30 Days</a></li>
                            <li><a class="dropdown-item <?php echo $period == 'all' ? 'active' : ''; ?>" href="?period=all">All Time</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Sales Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $period_text; ?> Sales</h5>
                                <h2 class="card-text">₱ <?php echo number_format($sales_stats['total_sales'] ?: 0, 2); ?></h2>
                                <p class="card-text">
                                    <small>
                                        <i class="fas fa-receipt me-1"></i>
                                        <?php echo $sales_stats['total_transactions'] ?: 0; ?> transactions
                                    </small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Average Sale</h5>
                                <h2 class="card-text">₱ <?php echo number_format($sales_stats['average_sale'] ?: 0, 2); ?></h2>
                                <p class="card-text">
                                    <small>
                                        <i class="fas fa-chart-line me-1"></i>
                                        Per transaction
                                    </small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Discounts</h5>
                                <h2 class="card-text">₱ <?php echo number_format($sales_stats['total_discounts'] ?: 0, 2); ?></h2>
                                <p class="card-text">
                                    <small>
                                        <i class="fas fa-tags me-1"></i>
                                        Given to customers
                                    </small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Time Period</h5>
                                <h2 class="card-text"><?php echo $period_text; ?></h2>
                                <p class="card-text">
                                    <small>
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        <?php echo $start_date; ?> to <?php echo $end_date; ?>
                                    </small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3">Quick Actions</h5>
                    </div>
                    <div class="col-md-3">
                        <a href="pos.php" class="quick-action card dashboard-card bg-light">
                            <span class="icon text-primary"><i class="fas fa-cash-register"></i></span>
                            <h5>New Sale</h5>
                            <p class="small text-muted mb-0">Process a new transaction</p>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="sales_history.php" class="quick-action card dashboard-card bg-light">
                            <span class="icon text-success"><i class="fas fa-history"></i></span>
                            <h5>Transaction History</h5>
                            <p class="small text-muted mb-0">View your past sales</p>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="#" class="quick-action card dashboard-card bg-light" onclick="printDailySummary()">
                            <span class="icon text-info"><i class="fas fa-print"></i></span>
                            <h5>Print Daily Summary</h5>
                            <p class="small text-muted mb-0">Generate today's report</p>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="profile.php" class="quick-action card dashboard-card bg-light">
                            <span class="icon text-secondary"><i class="fas fa-user-cog"></i></span>
                            <h5>My Profile</h5>
                            <p class="small text-muted mb-0">Update your information</p>
                        </a>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Transactions -->
                    <div class="col-md-6 mb-4">
                        <div class="card dashboard-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Transactions</h5>
                                <a href="sales_history.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th>Date</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($recent_sales->num_rows > 0): ?>
                                                <?php while($sale = $recent_sales->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="#" class="view-sale" data-bs-toggle="modal" data-bs-target="#viewSaleModal" data-id="<?php echo $sale['id']; ?>">
                                                                <?php echo $sale['invoice_number']; ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo date('M d, g:i A', strtotime($sale['created_at'])); ?></td>
                                                        <td><?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?></td>
                                                        <td>₱ <?php echo number_format($sale['total_amount'], 2); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No recent transactions found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Products -->
                    <div class="col-md-6 mb-4">
                        <div class="card dashboard-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Top Selling Products</h5>
                            </div>
                            <div class="card-body">
                                <?php if($top_products->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Quantity Sold</th>
                                                    <th>Total Sales</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($product = $top_products->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                        <td><?php echo $product['total_quantity']; ?> units</td>
                                                        <td>₱ <?php echo number_format($product['total_amount'], 2); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-4">
                                        <p class="mb-0">No product sales data available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Chart -->
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">Last 7 Days Sales</h5>
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

    <!-- View Sale Modal -->
    <div class="modal fade" id="viewSaleModal" tabindex="-1" aria-labelledby="viewSaleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewSaleModalLabel">Sale Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="sale-details">
                        <!-- Sale details will be loaded here -->
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Loading sale details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="print-modal-receipt">Print Receipt</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sales chart
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            const salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_keys($daily_sales)); ?>,
                    datasets: [{
                        label: 'Daily Sales',
                        data: <?php echo json_encode(array_values($daily_sales)); ?>,
                        backgroundColor: 'rgba(141, 110, 99, 0.4)',
                        borderColor: 'rgba(141, 110, 99, 1)',
                        borderWidth: 1
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
                            text: 'Daily Sales Over the Last Week'
                        }
                    },
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
            
            // View sale modal functionality
            const viewSaleLinks = document.querySelectorAll('.view-sale');
            const saleDetailsContainer = document.getElementById('sale-details');
            const printModalReceiptBtn = document.getElementById('print-modal-receipt');
            
            viewSaleLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const saleId = this.getAttribute('data-id');
                    
                    // Show loading
                    saleDetailsContainer.innerHTML = `
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Loading sale details...</p>
                        </div>
                    `;
                    
                    // Fetch sale details
                    fetch(`../ajax/get_sale_details.php?id=${saleId}`)
                        .then(response => response.json())
                        .then(data => {
                            if(data.success) {
                                // Build receipt content
                                let receiptHTML = `
                                    <div class="receipt-content">
                                        <div class="text-center mb-3">
                                            <h4>The Bohemian Burrows</h4>
                                            <p class="mb-0">123 Fashion Street</p>
                                            <p class="mb-0">Makati City, Philippines</p>
                                            <p>Tel: (02) 8123-4567</p>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <p class="mb-0"><strong>Invoice:</strong> ${data.sale.invoice_number}</p>
                                                <p class="mb-0"><strong>Date:</strong> ${new Date(data.sale.created_at).toLocaleString()}</p>
                                            </div>
                                            <div class="col-6 text-end">
                                                <p class="mb-0"><strong>Cashier:</strong> ${data.sale.cashier_name || '<?php echo $_SESSION['username']; ?>'}</p>
                                                <p class="mb-0"><strong>Customer:</strong> ${data.sale.customer_name || 'Walk-in'}</p>
                                            </div>
                                        </div>
                                        
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Price</th>
                                                    <th class="text-end">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>`;
                                            
                                data.items.forEach(item => {
                                    receiptHTML += `
                                        <tr>
                                            <td>${item.name}</td>
                                            <td class="text-center">${item.quantity}</td>
                                            <td class="text-end">₱${parseFloat(item.price).toFixed(2)}</td>
                                            <td class="text-end">₱${parseFloat(item.subtotal).toFixed(2)}</td>
                                        </tr>`;
                                });
                                
                                receiptHTML += `
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                                    <td class="text-end">₱${(parseFloat(data.sale.total_amount) + parseFloat(data.sale.discount)).toFixed(2)}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                                    <td class="text-end">₱${parseFloat(data.sale.discount).toFixed(2)}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                                    <td class="text-end">₱${parseFloat(data.sale.total_amount).toFixed(2)}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="text-end"><strong>Payment Method:</strong></td>
                                                    <td class="text-end">${data.sale.payment_method.charAt(0).toUpperCase() + data.sale.payment_method.slice(1)}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        
                                        <div class="text-center mt-4">
                                            <p>Thank you for shopping at The Bohemian Burrows!</p>
                                            <p class="small">Please keep this receipt for exchanges or returns within 7 days.</p>
                                        </div>
                                    </div>`;
                                    
                                saleDetailsContainer.innerHTML = receiptHTML;
                                
                                // Update print button
                                printModalReceiptBtn.setAttribute('data-id', saleId);
                                printModalReceiptBtn.setAttribute('data-invoice', data.sale.invoice_number);
                            } else {
                                saleDetailsContainer.innerHTML = `
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle"></i> ${data.message || 'An error occurred loading sale details.'}
                                    </div>
                                `;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            saleDetailsContainer.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle"></i> Failed to load sale details. Please try again.
                                </div>
                            `;
                        });
                });
            });
            
            // Print receipt from modal
            printModalReceiptBtn.addEventListener('click', function() {
                const receiptContent = document.querySelector('.receipt-content');
                if (!receiptContent) return;
                
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                    <head>
                        <title>Receipt - The Bohemian Burrows</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            body { font-family: 'Courier New', monospace; }
                            .receipt-container { width: 80mm; margin: 0 auto; }
                        </style>
                    </head>
                    <body>
                        <div class="receipt-container">
                            ${receiptContent.innerHTML}
                        </div>
                        <script>
                            window.onload = function() { window.print(); setTimeout(function() { window.close(); }, 500); }
                        <\/script>
                    </body>
                    </html>
                `);
                printWindow.document.close();
            });
            
            // Print daily summary function
            window.printDailySummary = function() {
                const today = new Date().toLocaleDateString();
                const printWindow = window.open('', '_blank');
                
                printWindow.document.write(`
                    <html>
                    <head>
                        <title>Daily Summary - ${today}</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            body { font-family: 'Arial', sans-serif; padding: 20px; }
                            .summary-container { max-width: 800px; margin: 0 auto; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .cashier-info { margin-bottom: 20px; }
                            .stats-card { border: 1px solid #ddd; border-radius: 10px; padding: 15px; margin-bottom: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class="summary-container">
                            <div class="header">
                                <h2>The Bohemian Burrows</h2>
                                <h4>Daily Sales Summary</h4>
                                <p>Date: ${today}</p>
                            </div>
                            
                            <div class="cashier-info">
                                <p><strong>Cashier:</strong> <?php echo htmlspecialchars($cashier['full_name']); ?></p>
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="stats-card">
                                        <h5>Today's Sales</h5>
                                        <h3>₱ <?php echo number_format($daily_sales[array_key_last($daily_sales)], 2); ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stats-card">
                                        <h5>Transactions</h5>
                                        <h3><?php echo $sales_stats['total_transactions'] ?: 0; ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stats-card">
                                <h5>Weekly Performance</h5>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Day</th>
                                            <th>Sales</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($daily_sales as $day => $amount): ?>
                                        <tr>
                                            <td><?php echo $day; ?></td>
                                            <td>₱ <?php echo number_format($amount, 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="footer text-center mt-5">
                                <p>Generated on: ${new Date().toLocaleString()}</p>
                            </div>
                        </div>
                        <script>
                            window.onload = function() { window.print(); }
                        <\/script>
                    </body>
                    </html>
                `);
                printWindow.document.close();
            };
        });
    </script>
</body>
</html>
