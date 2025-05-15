<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";
require_once "../includes/display_helpers.php"; // Include our helper functions

// Fetch cashiers for filter
$cashiers_stmt = $conn->query("SELECT id, username FROM users WHERE role IN ('admin', 'cashier') ORDER BY username");
$cashiers_list = $cashiers_stmt->fetch_all(MYSQLI_ASSOC);

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Base query
$where_conditions = [];
$params = [];
$types = "";

// Apply filters
if(isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $where_conditions[] = "DATE(s.created_at) >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}

if(isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $where_conditions[] = "DATE(s.created_at) <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}

if(isset($_GET['payment_method']) && !empty($_GET['payment_method'])) {
    $where_conditions[] = "s.payment_method = ?";
    $params[] = $_GET['payment_method'];
    $types .= "s";
}

if(isset($_GET['payment_status']) && !empty($_GET['payment_status'])) {
    $where_conditions[] = "s.payment_status = ?";
    $params[] = $_GET['payment_status'];
    $types .= "s";
}

if(isset($_GET['cashier_id']) && !empty($_GET['cashier_id'])) {
    $where_conditions[] = "s.user_id = ?";
    $params[] = $_GET['cashier_id'];
    $types .= "i";
}

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = "%{$_GET['search']}%";
    $where_conditions[] = "(s.invoice_number LIKE ? OR s.customer_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM sales s {$where_clause}";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get sales with pagination
$sql = "
    SELECT 
        s.id, 
        s.invoice_number, 
        s.created_at, 
        s.total_amount, 
        s.discount,
        s.payment_method, 
        s.payment_status,
        s.customer_name,
        u.username as cashier_name
    FROM sales s
    JOIN users u ON s.user_id = u.id
    {$where_clause}
    ORDER BY s.created_at DESC
    LIMIT ?, ?
";

$current_params = $params; // Keep original params for summary
$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($types)) { // Check if types is not empty before binding
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$sales = $stmt->get_result();

// Calculate sales summary (using $current_params without limit and offset)
$summary_sql = "
    SELECT 
        COUNT(*) as total_transactions,
        SUM(s.total_amount) as total_sales_value,
        SUM(s.discount) as total_discounts_value
    FROM sales s
    {$where_clause}
";

// Adjust types for summary query (remove 'ii' for limit and offset)
$summary_types = substr($types, 0, -2);

$summary_stmt = $conn->prepare($summary_sql);
if (!empty($summary_types)) {
     $summary_stmt->bind_param($summary_types, ...$current_params);
}
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - Admin - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .status-badge {
            min-width: 90px;
            text-align: center;
            color: #fff;
            font-weight: 500;
            padding: 0.3em 0.6em;
            font-size: 0.8em;
            border-radius: .25rem;
            display: inline-block;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-paid { background-color: #28a745; color: #fff; }
        .status-processing { background-color: #17a2b8; color: #fff; }
        .status-shipped { background-color: #007bff; color: #fff; }
        .status-delivered { background-color: #28a745; color: #fff; }
        .status-completed { background-color: #28a745; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
        .status-refunded { background-color: #6c757d; color: #fff; }
        .status-on-hold { background-color: #fd7e14; color: #fff; }
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
                    <h1 class="h2">Sales History</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printSalesHistory()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Sales Value</h5>
                                <h2 class="card-text">₱ <?php echo number_format($summary['total_sales_value'] ?? 0, 2); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Transactions</h5>
                                <h2 class="card-text"><?php echo $summary['total_transactions'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Discounts</h5>
                                <h2 class="card-text">₱ <?php echo number_format($summary['total_discounts_value'] ?? 0, 2); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Invoice #, Customer" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <select class="form-select" id="payment_method" name="payment_method">
                                    <option value="">All</option>
                                    <option value="cash" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                    <option value="card" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'card') ? 'selected' : ''; ?>>Card</option>
                                    <option value="gcash" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'gcash') ? 'selected' : ''; ?>>GCash</option>
                                    <option value="paymaya" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'paymaya') ? 'selected' : ''; ?>>PayMaya</option>
                                    <option value="cod" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'cod') ? 'selected' : ''; ?>>Cash on Delivery</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="payment_status" class="form-label">Status</label>
                                <select class="form-select" id="payment_status" name="payment_status">
                                    <option value="">All</option>
                                    <option value="pending" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="processing" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="completed" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                             <div class="col-md-2">
                                <label for="cashier_id" class="form-label">Cashier</label>
                                <select class="form-select" id="cashier_id" name="cashier_id">
                                    <option value="">All</option>
                                    <?php foreach($cashiers_list as $cashier): ?>
                                        <option value="<?php echo $cashier['id']; ?>" <?php echo (isset($_GET['cashier_id']) && $_GET['cashier_id'] == $cashier['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cashier['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                             <div class="col-md-1 d-flex align-items-end">
                                <a href="sales_history.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Date & Time</th>
                                        <th>Customer</th>
                                        <th>Cashier</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Discount</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($sales->num_rows > 0): ?>
                                        <?php while($sale = $sales->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sale['invoice_number']); ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?></td>
                                                <td><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                                                <td>
                                                    <?php echo display_payment_method($sale['payment_method'], true); ?>
                                                </td>
                                                <td>
                                                    <?php echo display_order_status($sale['payment_status']); ?>
                                                </td>
                                                <td>₱ <?php echo number_format($sale['discount'], 2); ?></td>
                                                <td>₱ <?php echo number_format($sale['total_amount'], 2); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info view-sale" data-bs-toggle="modal" data-bs-target="#viewSaleModal" data-id="<?php echo $sale['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-secondary print-receipt" data-id="<?php echo $sale['id']; ?>">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No transactions found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php
                                $query_params = $_GET;
                                unset($query_params['page']);
                                $query_string = http_build_query($query_params);
                                ?>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $query_string; ?>">Previous</a>
                                </li>
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $query_string; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $query_string; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
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
                    <div id="sale-details-content">
                        <div class="text-center"><div class="spinner-border text-primary"></div><p>Loading...</p></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="print-modal-receipt-btn">Print Receipt</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function getPaymentMethodNameJS(method) {
        if (method === null || typeof method === 'undefined') { return 'Not Specified'; }
        let trimmedMethod = String(method).trim();
        if (trimmedMethod === '') { return 'Not Specified'; }
        let lowerMethod = trimmedMethod.toLowerCase();
        switch (lowerMethod) {
            case 'cod': return 'Cash on Delivery';
            case 'cash': return 'Cash';
            case 'gcash': return 'GCash';
            case 'paymaya': return 'PayMaya';
            case 'card': return 'Credit/Debit Card';
            default: return trimmedMethod.charAt(0).toUpperCase() + trimmedMethod.slice(1);
        }
    }

    function getOrderStatusNameJS(status) { // For displaying status in JS if needed
        if (status === null || typeof status === 'undefined') { return 'Pending'; }
        let trimmedStatus = String(status).trim();
        if (trimmedStatus === '') { return 'Pending'; }
        return trimmedStatus.charAt(0).toUpperCase() + trimmedStatus.slice(1);
    }


    document.addEventListener('DOMContentLoaded', function() {
        const saleDetailsContainer = document.getElementById('sale-details-content');
        let currentSaleDataForModal = null;

        document.querySelectorAll('.view-sale').forEach(button => {
            button.addEventListener('click', function() {
                const saleId = this.getAttribute('data-id');
                saleDetailsContainer.innerHTML = '<div class="text-center"><div class="spinner-border text-primary"></div><p>Loading...</p></div>';
                
                fetch(`../ajax/get_sale_details.php?id=${saleId}`)
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            currentSaleDataForModal = data;
                            let itemsHTML = '';
                            data.items.forEach(item => {
                                itemsHTML += `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td class="text-center">${item.quantity}</td>
                                        <td class="text-end">₱${parseFloat(item.price).toFixed(2)}</td>
                                        <td class="text-end">₱${parseFloat(item.subtotal).toFixed(2)}</td>
                                    </tr>`;
                            });

                            const subtotal = parseFloat(data.sale.total_amount) + parseFloat(data.sale.discount);

                            saleDetailsContainer.innerHTML = `
                                <div class="receipt-content-modal">
                                    <div class="text-center mb-3">
                                        <h4>The Bohemian Burrows</h4>
                                        <p class="mb-0">123 Fashion Street, Makati City, Philippines</p>
                                        <p>Tel: (02) 8123-4567</p>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Invoice:</strong> ${data.sale.invoice_number}</div>
                                        <div class="col-6 text-end"><strong>Date:</strong> ${new Date(data.sale.created_at).toLocaleString()}</div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Cashier:</strong> ${data.sale.cashier_name || 'N/A'}</div>
                                        <div class="col-6 text-end"><strong>Customer:</strong> ${data.sale.customer_name || 'Walk-in'}</div>
                                    </div>
                                    <table class="table table-sm">
                                        <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th></tr></thead>
                                        <tbody>${itemsHTML}</tbody>
                                        <tfoot>
                                            <tr><td colspan="3" class="text-end"><strong>Subtotal:</strong></td><td class="text-end">₱${subtotal.toFixed(2)}</td></tr>
                                            <tr><td colspan="3" class="text-end"><strong>Discount:</strong></td><td class="text-end">₱${parseFloat(data.sale.discount).toFixed(2)}</td></tr>
                                            <tr><td colspan="3" class="text-end"><strong>Total:</strong></td><td class="text-end">₱${parseFloat(data.sale.total_amount).toFixed(2)}</td></tr>
                                            <tr><td colspan="3" class="text-end"><strong>Payment Method:</strong></td><td class="text-end">${getPaymentMethodNameJS(data.sale.payment_method)}</td></tr>
                                            <tr><td colspan="3" class="text-end"><strong>Status:</strong></td><td class="text-end">${getOrderStatusNameJS(data.sale.payment_status)}</td></tr>
                                        </tfoot>
                                    </table>
                                    <div class="text-center mt-3 small">Thank you for your purchase!</div>
                                </div>`;
                        } else {
                            saleDetailsContainer.innerHTML = '<div class="alert alert-danger">Could not load sale details.</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching sale details:', error);
                        saleDetailsContainer.innerHTML = '<div class="alert alert-danger">Error fetching details. Please try again.</div>';
                    });
            });
        });

        document.getElementById('print-modal-receipt-btn').addEventListener('click', function() {
            if (currentSaleDataForModal) {
                printSaleReceipt(currentSaleDataForModal);
            }
        });
        
        document.querySelectorAll('.print-receipt').forEach(button => {
            button.addEventListener('click', function() {
                const saleId = this.getAttribute('data-id');
                fetch(`../ajax/get_sale_details.php?id=${saleId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            printSaleReceipt(data);
                        } else {
                            alert('Error loading receipt data: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to load receipt data for printing.');
                    });
            });
        });

        function printSaleReceipt(data) {
            let itemsPrintHTML = '';
            data.items.forEach(item => {
                itemsPrintHTML += `
                    <tr>
                        <td>${item.name}</td>
                        <td style="text-align:center;">${item.quantity}</td>
                        <td style="text-align:right;">₱${parseFloat(item.price).toFixed(2)}</td>
                        <td style="text-align:right;">₱${parseFloat(item.subtotal).toFixed(2)}</td>
                    </tr>`;
            });
            const subtotal = parseFloat(data.sale.total_amount) + parseFloat(data.sale.discount);

            const printContent = `
                <div style="width:80mm; margin:auto; font-family: 'Courier New', monospace; font-size:10pt;">
                    <div style="text-align:center; margin-bottom:10px;">
                        <h4 style="margin:0;">The Bohemian Burrows</h4>
                        <p style="margin:0; font-size:8pt;">123 Fashion Street, Makati City, Philippines</p>
                        <p style="margin:0; font-size:8pt;">Tel: (02) 8123-4567</p>
                    </div>
                    <p style="margin:2px 0;"><strong>Invoice:</strong> ${data.sale.invoice_number}</p>
                    <p style="margin:2px 0;"><strong>Date:</strong> ${new Date(data.sale.created_at).toLocaleString()}</p>
                    <p style="margin:2px 0;"><strong>Cashier:</strong> ${data.sale.cashier_name || 'N/A'}</p>
                    <p style="margin:2px 0;"><strong>Customer:</strong> ${data.sale.customer_name || 'Walk-in'}</p>
                    <hr/>
                    <table style="width:100%; font-size:9pt; border-collapse:collapse;">
                        <thead><tr><th style="text-align:left;">Item</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Price</th><th style="text-align:right;">Total</th></tr></thead>
                        <tbody>${itemsPrintHTML}</tbody>
                    </table>
                    <hr/>
                    <table style="width:100%; font-size:9pt;">
                        <tr><td style="text-align:right;">Subtotal:</td><td style="text-align:right;">₱${subtotal.toFixed(2)}</td></tr>
                        <tr><td style="text-align:right;">Discount:</td><td style="text-align:right;">₱${parseFloat(data.sale.discount).toFixed(2)}</td></tr>
                        <tr><td style="text-align:right;"><strong>Total:</strong></td><td style="text-align:right;"><strong>₱${parseFloat(data.sale.total_amount).toFixed(2)}</strong></td></tr>
                        <tr><td style="text-align:right;">Payment:</td><td style="text-align:right;">${getPaymentMethodNameJS(data.sale.payment_method)}</td></tr>
                        <tr><td style="text-align:right;">Status:</td><td style="text-align:right;">${getOrderStatusNameJS(data.sale.payment_status)}</td></tr>
                    </table>
                    <hr/>
                    <div style="text-align:center; margin-top:10px; font-size:8pt;">
                        Thank you for shopping at The Bohemian Burrows!
                    </div>
                </div>
            `;
            const printWindow = window.open('', '_blank', 'width=302,height=500'); // Approx 80mm width
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            // Delay print to ensure content is loaded
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }
        
        window.printSalesHistory = function() {
            window.print();
        }
    });
    </script>
</body>
</html>
