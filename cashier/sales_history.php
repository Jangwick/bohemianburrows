<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'cashier') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";
require_once "../includes/functions.php"; // Include the functions file
require_once "../includes/display_helpers.php"; // Include our helper functions

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Base query - only show this cashier's sales
$where = "s.user_id = ?";
$params = array($_SESSION['user_id']);
$types = "i";

// Apply date filters if any
if(isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $where .= " AND DATE(s.created_at) >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}

if(isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $where .= " AND DATE(s.created_at) <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}

if(isset($_GET['payment_method']) && !empty($_GET['payment_method'])) {
    $where .= " AND s.payment_method = ?";
    $params[] = $_GET['payment_method'];
    $types .= "s";
}

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM sales s 
    WHERE $where
";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
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
        s.payment_status, /* Added payment_status */
        s.customer_name
    FROM sales s
    WHERE $where
    ORDER BY s.created_at DESC
    LIMIT ?, ?
";
$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sales = $stmt->get_result();

// Calculate sales summary
$summary_sql = "
    SELECT 
        COUNT(*) as total_transactions,
        SUM(total_amount) as total_sales,
        SUM(discount) as total_discounts
    FROM sales s
    WHERE $where
";
// Remove the pagination parameters
array_pop($params);
array_pop($params);
$types = substr($types, 0, -2);

$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param($types, ...$params);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Transactions - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .status-badge {
            min-width: 90px; /* Adjusted width */
            text-align: center;
            color: #fff;
            font-weight: 500;
            padding: 0.3em 0.6em; /* Adjusted padding */
            font-size: 0.8em;    /* Adjusted font size */
            border-radius: .25rem;
            display: inline-block; /* Ensures proper display */
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-processing { background-color: #17a2b8; color: #fff; }
        .status-shipped { background-color: #007bff; color: #fff; }
        .status-delivered, .status-completed, .status-approved { background-color: #28a745; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
        .status-paid { background-color: #28a745; color: #fff; } /* For POS transactions */
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
                    <h1 class="h2">My Transactions</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printTransactions()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Sales</h5>
                                <h2 class="card-text">₱ <?php echo number_format($summary['total_sales'] ?? 0, 2); ?></h2>
                                <p class="card-text"><small>All your transactions</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Transactions</h5>
                                <h2 class="card-text"><?php echo $summary['total_transactions'] ?? 0; ?></h2>
                                <p class="card-text"><small>Total orders processed</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Average Sale</h5>
                                <h2 class="card-text">₱ <?php 
                                    echo number_format($summary['total_transactions'] > 0 ? 
                                        $summary['total_sales'] / $summary['total_transactions'] : 0, 2); 
                                ?></h2>
                                <p class="card-text"><small>Per transaction</small></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $_GET['start_date'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $_GET['end_date'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <select class="form-select" id="payment_method" name="payment_method">
                                    <option value="">All</option>
                                    <option value="cash" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                    <option value="card" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'card') ? 'selected' : ''; ?>>Card</option>
                                    <option value="gcash" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'gcash') ? 'selected' : ''; ?>>GCash</option>
                                    <option value="paymaya" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'paymaya') ? 'selected' : ''; ?>>PayMaya</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div>
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                    <a href="sales_history.php" class="btn btn-secondary">Reset</a>
                                </div>
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
                                        <th>Payment Method</th>
                                        <th>Status</th> <!-- Added Status Header -->
                                        <th>Discount</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($sales->num_rows > 0): ?>
                                        <?php while($sale = $sales->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $sale['invoice_number']; ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?></td>
                                                <td>
                                                    <?php echo display_payment_method($sale['payment_method'], true); ?>
                                                </td>
                                                <td>
                                                    <?php echo display_order_status($sale['payment_status'] ?? 'paid'); ?>
                                                </td>
                                                <td>₱ <?php echo number_format($sale['discount'], 2); ?></td>
                                                <td>₱ <?php echo number_format($sale['total_amount'], 2); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info view-sale" data-bs-toggle="modal" data-bs-target="#viewSaleModal" data-id="<?php echo $sale['id']; ?>" data-invoice="<?php echo $sale['invoice_number']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-secondary print-receipt" data-id="<?php echo $sale['id']; ?>" data-invoice="<?php echo $sale['invoice_number']; ?>">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No transactions found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?><?php echo isset($_GET['payment_method']) ? '&payment_method=' . $_GET['payment_method'] : ''; ?>">
                                        Previous
                                    </a>
                                </li>
                                
                                <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?><?php echo isset($_GET['payment_method']) ? '&payment_method=' . $_GET['payment_method'] : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?><?php echo isset($_GET['payment_method']) ? '&payment_method=' . $_GET['payment_method'] : ''; ?>">
                                        Next
                                    </a>
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
    <script>
    // Add this JavaScript helper function
    function getPaymentMethodNameJS(method) {
        if (method === null || typeof method === 'undefined') {
            return 'Not Specified';
        }
        let trimmedMethod = String(method).trim();
        if (trimmedMethod === '') {
            return 'Not Specified';
        }
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

    document.addEventListener('DOMContentLoaded', function() {
        // Handle view sale modal
        const viewButtons = document.querySelectorAll('.view-sale');
        const saleDetailsContainer = document.getElementById('sale-details');
        const printModalReceiptBtn = document.getElementById('print-modal-receipt');
        let currentSaleData = null;
        
        // View sale button click event
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
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
                            currentSaleData = data; // Store for printing later
                            
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
                                                <td class="text-end">${getPaymentMethodNameJS(data.sale.payment_method)}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    
                                    <div class="text-center mt-4">
                                        <p>Thank you for shopping at The Bohemian Burrows!</p>
                                        <p class="small">Please keep this receipt for exchanges or returns within 7 days.</p>
                                    </div>
                                </div>`;
                                
                            saleDetailsContainer.innerHTML = receiptHTML;
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
        
        // Print from modal button
        printModalReceiptBtn.addEventListener('click', function() {
            if (currentSaleData) {
                printSaleReceipt(currentSaleData);
            } else {
                alert("No receipt data available");
            }
        });
        
        // Print receipt buttons in the table
        const printReceiptButtons = document.querySelectorAll('.print-receipt');
        printReceiptButtons.forEach(button => {
            button.addEventListener('click', function() {
                const saleId = this.getAttribute('data-id');
                
                // Fetch the sale data first, then print
                fetch(`../ajax/get_sale_details.php?id=${saleId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            printSaleReceipt(data);
                        } else {
                            alert('Error loading receipt: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to load receipt data. Please try again.');
                    });
            });
        });
        
        // Function to print a receipt
        function printSaleReceipt(data) {
            const printWindow = window.open('', '_blank');
            
            let itemsHTML = '';
            data.items.forEach(item => {
                itemsHTML += `
                    <tr>
                        <td>${item.name}</td>
                        <td class="text-center">${item.quantity}</td>
                        <td class="text-end">₱${parseFloat(item.price).toFixed(2)}</td>
                        <td class="text-end">₱${parseFloat(item.subtotal).toFixed(2)}</td>
                    </tr>
                `;
            });
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Receipt - The Bohemian Burrows</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { 
                            font-family: 'Courier New', monospace; 
                            padding: 20px; 
                        }
                        .receipt-container { 
                            width: 80mm; 
                            margin: 0 auto;
                            padding: 10px; 
                        }
                        @media print {
                            .no-print { display: none; }
                            body { padding: 0; }
                        }
                    </style>
                </head>
                <body>
                    <div class="no-print mb-3 text-center">
                        <button class="btn btn-primary" onclick="window.print()">Print</button>
                        <button class="btn btn-secondary" onclick="window.close()">Close</button>
                    </div>
                    
                    <div class="receipt-container">
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
                            <tbody>
                                ${itemsHTML}
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
                                    <td class="text-end">${getPaymentMethodNameJS(data.sale.payment_method)}</td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <div class="text-center mt-4">
                            <p>Thank you for shopping at The Bohemian Burrows!</p>
                            <p class="small">Please keep this receipt for exchanges or returns within 7 days.</p>
                        </div>
                    </div>
                    
                    <script>
                        // Auto print after a short delay
                        setTimeout(function() { window.print(); }, 500);
                    </script>
                </body>
                </html>
            `);
            
            printWindow.document.close();
        }
        
        // Print all transactions
        window.printTransactions = function() {
            window.print();
        };
    });
    </script>
</body>
</html>
