<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Define a base64 encoded placeholder image.
// This is a small, transparent PNG. Replace with your preferred placeholder if you have a specific one.
$base64PlaceholderImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAQAAADOPi6zAAAAH0lEQVR42u3OIQEAAAACIL/n/4WNDwYg7AABAAAAAAAAAACAAQChNEwAAV1jYQAAAABJRU5ErkJggg==';

// Get categories for filter
$cat_stmt = $conn->prepare("SELECT DISTINCT category FROM products");
$cat_stmt->execute();
$categories = $cat_stmt->get_result();

// Get products (limited to 12 by default)
$where = "1=1";
$params = array();
$types = "";

if(isset($_GET['category']) && !empty($_GET['category'])) {
    $where .= " AND category = ?";
    $params[] = $_GET['category'];
    $types .= "s";
}

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    $where .= " AND (name LIKE ? OR barcode LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

$sql = "
    SELECT p.*, i.quantity 
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE $where
    ORDER BY p.name
    LIMIT 12
";

$stmt = $conn->prepare($sql);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Get the next invoice number
$inv_stmt = $conn->prepare("
    SELECT IFNULL(MAX(CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED)), 0) + 1 AS next_number
    FROM sales
    WHERE invoice_number LIKE 'INV-%'
");
$inv_stmt->execute();
$inv_result = $inv_stmt->get_result();
$next_number = $inv_result->fetch_assoc()['next_number'];
$next_invoice = 'INV-' . str_pad($next_number, 6, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .product-image {
            height: 150px;
            object-fit: cover;
            cursor: pointer;
            width: 100%;
            background-color: #f0f0f0; /* Light background for images */
        }
        .modal-product-image {
            max-height: 400px;
            object-fit: contain;
            width: 100%;
            margin: 0 auto;
            display: block;
        }
        .product-card {
            transition: transform 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        /* Fix for product image clickable area */
        .product-image-container {
            position: relative;
            cursor: pointer;
            height: 150px; /* Fixed height for consistency */
            overflow: hidden;
        }
        .product-image {
            height: 100%;
            object-fit: cover;
            width: 100%;
            transition: transform 0.3s ease;
        }
        .product-image-container:hover .product-image {
            transform: scale(1.05);
        }
        .product-image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
            background-color: rgba(0, 0, 0, 0.05); /* Subtle effect on hover */
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .product-image-container:hover .product-image-overlay {
            opacity: 1;
        }
        .modal-product-image {
            max-height: 400px;
            max-width: 100%;
            object-fit: contain;
            margin: 0 auto;
            display: block;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
                    <h1 class="h2">Point of Sale</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="printReceipt()">
                                <i class="fas fa-print"></i> Print Last Receipt
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Products Section (Left Side) -->
                    <div class="col-lg-8 mb-4">
                        <!-- Search and Filter -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Search by name or barcode" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="category" onchange="this.form.submit()">
                                            <option value="">All Categories</option>
                                            <?php while($category = $categories->fetch_assoc()): ?>
                                                <option value="<?php echo $category['category']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $category['category']) ? 'selected' : ''; ?>>
                                                    <?php echo $category['category']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <?php if(isset($_GET['search']) || isset($_GET['category'])): ?>
                                            <a href="pos.php" class="btn btn-secondary w-100">Reset</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Products Grid -->
                        <div class="row row-cols-1 row-cols-md-3 g-4" id="products-container">
                            <?php if($products->num_rows > 0): ?>
                                <?php while($product = $products->fetch_assoc()): ?>
                                    <div class="col">
                                        <div class="card h-100 product-card" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>" data-price="<?php echo $product['price']; ?>" data-barcode="<?php echo $product['barcode']; ?>">
                                            <div class="position-relative product-image-container">
                                                <?php 
                                                $imagePath = !empty($product['image_path']) ? '../' . $product['image_path'] : '../assets/images/product-placeholder.png';
                                                ?>
                                                <img src="<?php echo $imagePath; ?>" 
                                                     class="card-img-top product-image" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                     onerror="this.onerror=null; this.src='<?php echo $base64PlaceholderImage; ?>';">
                                                <div class="product-image-overlay" data-bs-toggle="modal" data-bs-target="#productImageModal" 
                                                     data-product-id="<?php echo $product['id']; ?>"
                                                     data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                     data-product-price="<?php echo $product['price']; ?>"
                                                     data-product-barcode="<?php echo $product['barcode']; ?>"
                                                     data-product-image="<?php echo $imagePath; ?>"
                                                     data-product-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                                                     data-product-stock="<?php echo $product['quantity'] ?? 0; ?>"></div>
                                                <?php if(($product['quantity'] ?? 0) <= 0): ?>
                                                    <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background-color: rgba(0,0,0,0.5); z-index: 2;">
                                                        <span class="badge bg-danger">Out of Stock</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <p class="card-text text-primary fw-bold">₱<?php echo number_format($product['price'], 2); ?></p>
                                                <p class="card-text small text-muted">Stock: <?php echo $product['quantity'] ?? 0; ?></p>
                                            </div>
                                            <div class="card-footer bg-white border-top-0">
                                                <button class="btn btn-sm btn-primary w-100 add-to-cart-btn" <?php echo ($product['quantity'] ?? 0) <= 0 ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        No products found. Try a different search or category.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Cart Section (Right Side) -->
                    <div class="col-lg-4">
                        <div class="card sticky-top" style="top: 20px;">
                            <div class="card-header bg-primary text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Shopping Cart</h5>
                                    <div>
                                        <span id="invoice-number"><?php echo $next_invoice; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="barcode-input" class="form-label">Scan Barcode</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="barcode-input" placeholder="Scan or enter barcode">
                                        <button class="btn btn-secondary" type="button" id="search-barcode-btn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="cart-items-container" style="max-height: 300px; overflow-y: auto;">
                                    <div id="cart-items">
                                        <!-- Cart items will be added here -->
                                        <div class="text-center text-muted py-3" id="empty-cart-message">
                                            <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                            <p>Cart is empty</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span id="subtotal">₱0.00</span>
                                </div>
                                <div class="mb-3">
                                    <label for="discount" class="form-label">Discount Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="discount" value="0" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mb-2 fw-bold">
                                    <span>Total:</span>
                                    <span id="total">₱0.00</span>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="customer-name" class="form-label">Customer Name (Optional)</label>
                                    <input type="text" class="form-control" id="customer-name">
                                </div>
                                
                                <div class="payment-methods mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <div class="d-flex flex-wrap">
                                        <button class="btn btn-outline-success flex-grow-1 m-1 payment-method" data-method="cash">
                                            <i class="fas fa-money-bill-wave"></i> Cash
                                        </button>
                                        <button class="btn btn-outline-primary flex-grow-1 m-1 payment-method" data-method="card">
                                            <i class="fas fa-credit-card"></i> Card
                                        </button>
                                        <button class="btn btn-outline-info flex-grow-1 m-1 payment-method" data-method="gcash">
                                            <i class="fas fa-mobile-alt"></i> GCash
                                        </button>
                                        <button class="btn btn-outline-warning flex-grow-1 m-1 payment-method" data-method="paymaya">
                                            <i class="fas fa-wallet"></i> PayMaya
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button class="btn btn-success" id="checkout-btn" disabled>
                                        <i class="fas fa-check-circle"></i> Complete Sale
                                    </button>
                                    <button class="btn btn-danger" id="clear-cart-btn" disabled>
                                        <i class="fas fa-trash"></i> Clear Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Product Image Modal -->
    <div class="modal fade" id="productImageModal" tabindex="-1" aria-labelledby="productImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productImageModalLabel">Product Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img src="" id="modalProductImage" class="img-fluid" alt="Product Image" 
                         style="max-height: 80vh; width: auto; max-width: 100%; background-color: #f0f0f0;"
                         onerror="this.onerror=null; this.src='<?php echo $base64PlaceholderImage; ?>';">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-primary" id="modalAddToCartBtn">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">Complete Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="cash-payment-section">
                        <div class="mb-3">
                            <label for="cash-amount" class="form-label">Amount Received</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" id="cash-amount" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="change-amount" class="form-label">Change</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="text" class="form-control" id="change-amount" readonly>
                            </div>
                        </div>
                    </div>
                    <div id="card-payment-section" style="display: none;">
                        <div class="mb-3">
                            <label for="card-number" class="form-label">Card Number</label>
                            <input type="text" class="form-control" id="card-number" placeholder="XXXX XXXX XXXX XXXX">
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="card-expiry" class="form-label">Expiry Date</label>
                                    <input type="text" class="form-control" id="card-expiry" placeholder="MM/YY">
                                </div>
                            </div>
                            <div class="col">
                                <div class="mb-3">
                                    <label for="card-cvc" class="form-label">CVC</label>
                                    <input type="text" class="form-control" id="card-cvc" placeholder="CVC">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="gcash-payment-section" style="display: none;">
                        <div class="mb-3">
                            <label for="gcash-number" class="form-label">GCash Number</label>
                            <input type="text" class="form-control" id="gcash-number" placeholder="09XX XXX XXXX">
                        </div>
                        <div class="mb-3">
                            <label for="gcash-reference" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="gcash-reference">
                        </div>
                    </div>
                    <div id="paymaya-payment-section" style="display: none;">
                        <div class="mb-3">
                            <label for="paymaya-number" class="form-label">PayMaya Number</label>
                            <input type="text" class="form-control" id="paymaya-number" placeholder="09XX XXX XXXX">
                        </div>
                        <div class="mb-3">
                            <label for="paymaya-reference" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="paymaya-reference">
                        </div>
                    </div>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i> Total Amount: <span id="modal-total-amount">₱0.00</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-payment-btn">Confirm Payment</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receiptModalLabel">Sale Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="receipt-content">
                        <div class="text-center mb-3">
                            <h4>The Bohemian Burrows</h4>
                            <p class="mb-0">123 Fashion Street</p>
                            <p class="mb-0">Makati City, Philippines</p>
                            <p>Tel: (02) 8123-4567</p>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <p class="mb-0"><strong>Invoice:</strong> <span id="receipt-invoice"></span></p>
                                <p class="mb-0"><strong>Date:</strong> <span id="receipt-date"></span></p>
                            </div>
                            <div class="col-6 text-end">
                                <p class="mb-0"><strong>Cashier:</strong> <span id="receipt-cashier"><?php echo $_SESSION['username']; ?></span></p>
                                <p class="mb-0"><strong>Customer:</strong> <span id="receipt-customer">Walk-in</span></p>
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
                            <tbody id="receipt-items">
                                <!-- Receipt items will be added here -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                    <td class="text-end" id="receipt-subtotal">₱0.00</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                    <td class="text-end" id="receipt-discount">₱0.00</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td class="text-end" id="receipt-total">₱0.00</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Payment Method:</strong></td>
                                    <td class="text-end" id="receipt-payment-method">Cash</td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <div class="text-center mt-4">
                            <p>Thank you for shopping at The Bohemian Burrows!</p>
                            <p class="small">Please keep this receipt for exchanges or returns within 7 days.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printReceipt()">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let cart = [];
        let selectedPaymentMethod = 'cash';
        let subtotal = 0;
        let total = 0;
        let currentProductForModal = null;
        let lastSaleData = null;
        
        // DOM elements
        const cartItems = document.getElementById('cart-items');
        const subtotalElement = document.getElementById('subtotal');
        const totalElement = document.getElementById('total');
        const discountInput = document.getElementById('discount');
        const checkoutBtn = document.getElementById('checkout-btn');
        const clearCartBtn = document.getElementById('clear-cart-btn');
        const emptyCartMessage = document.getElementById('empty-cart-message');
        const barcodeInput = document.getElementById('barcode-input');
        const searchBarcodeBtn = document.getElementById('search-barcode-btn');
        const customerNameInput = document.getElementById('customer-name');
        const paymentMethodButtons = document.querySelectorAll('.payment-method');
        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
        const cashAmountInput = document.getElementById('cash-amount');
        const changeAmountInput = document.getElementById('change-amount');
        const modalTotalAmount = document.getElementById('modal-total-amount');
        const confirmPaymentBtn = document.getElementById('confirm-payment-btn');
        const base64PlaceholderImageJS = '<?php echo $base64PlaceholderImage; ?>'; // Make PHP variable available to JS

        // Initialize
        updateCartDisplay();
        
        // Product image modal handling
        const productImageModal = document.getElementById('productImageModal');
        const modalProductImage = document.getElementById('modalProductImage');
        
        // Safely update modal elements
        productImageModal.addEventListener('show.bs.modal', function (event) {
            try {
                // Get the element that triggered the modal
                const trigger = event.relatedTarget;
                if (!trigger) return;
                
                // Extract product data from data attributes
                const productId = trigger.dataset.productId;
                const productName = trigger.dataset.productName;
                const productPrice = parseFloat(trigger.dataset.productPrice);
                const productBarcode = trigger.dataset.productBarcode;
                const productImage = trigger.dataset.productImage;
                const productDescription = trigger.dataset.productDescription || 'No description available.';
                const productStock = parseInt(trigger.dataset.productStock) || 0;
                
                // Safely update modal content - check if elements exist first
                const modalProductNameEl = document.getElementById('modalProductName');
                const modalProductPriceEl = document.getElementById('modalProductPrice');
                const modalProductStockEl = document.getElementById('modalProductStock');
                const modalProductDescriptionEl = document.getElementById('modalProductDescription');
                const modalAddToCartBtn = document.getElementById('modalAddToCartBtn');
                
                if (modalProductNameEl) modalProductNameEl.textContent = productName;
                if (modalProductPriceEl) modalProductPriceEl.textContent = `₱${productPrice.toFixed(2)}`;
                if (modalProductStockEl) modalProductStockEl.textContent = productStock;
                if (modalProductDescriptionEl) modalProductDescriptionEl.textContent = productDescription;
                
                // Store current product data for the "Add to Cart" button
                currentProductForModal = {
                    id: productId,
                    name: productName,
                    price: productPrice,
                    barcode: productBarcode
                };
                
                // Set image with error handling
                if (modalProductImage) {
                    modalProductImage.onerror = function() {
                        this.onerror = null; // Prevent infinite loop
                        this.src = base64PlaceholderImageJS;
                    };
                    modalProductImage.src = productImage || '../assets/images/product-placeholder.png';
                }
                
                // Disable "Add to Cart" button if product is out of stock
                if (modalAddToCartBtn) {
                    if (productStock <= 0) {
                        modalAddToCartBtn.disabled = true;
                        modalAddToCartBtn.classList.add('disabled');
                        modalAddToCartBtn.innerHTML = '<i class="fas fa-exclamation-circle"></i> Out of Stock';
                    } else {
                        modalAddToCartBtn.disabled = false;
                        modalAddToCartBtn.classList.remove('disabled');
                        modalAddToCartBtn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
                    }
                }
            } catch (error) {
                console.error('Error updating product modal:', error);
            }
        });
        
        // Add to cart button in the modal
        const modalAddToCartBtn = document.getElementById('modalAddToCartBtn');
        if (modalAddToCartBtn) {
            modalAddToCartBtn.addEventListener('click', function() {
                if (currentProductForModal) {
                    addToCart(
                        currentProductForModal.id, 
                        currentProductForModal.name, 
                        currentProductForModal.price, 
                        currentProductForModal.barcode
                    );
                    
                    // Close the modal
                    try {
                        bootstrap.Modal.getInstance(productImageModal).hide();
                    } catch (e) {
                        console.error('Error closing modal:', e);
                    }
                }
            });
        }

        // Fix AJAX requests to handle errors better
        function fetchWithErrorHandling(url, options = {}) {
            return fetch(url, options)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error ${response.status}`);
                    }
                    return response.text(); // Get response as text first
                })
                .then(text => {
                    try {
                        // Try to parse as JSON
                        return JSON.parse(text);
                    } catch (e) {
                        // If not valid JSON, throw error with response text
                        console.error("Invalid JSON response:", text);
                        throw new Error('Server returned invalid JSON response');
                    }
                });
        }
        
        // Add product to cart
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Make sure we're only handling the add to cart button clicks
                // and not the image clicks which should trigger the modal
                if (e.target.classList.contains('add-to-cart-btn') || e.target.closest('.add-to-cart-btn')) {
                    const productId = this.dataset.id;
                    const productName = this.dataset.name;
                    const productPrice = parseFloat(this.dataset.price);
                    const productBarcode = this.dataset.barcode;
                    
                    addToCart(productId, productName, productPrice, productBarcode);
                }
            });
        });
        
        // Barcode search
        searchBarcodeBtn.addEventListener('click', function() {
            searchByBarcode(barcodeInput.value);
        });
        
        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchByBarcode(barcodeInput.value);
            }
        });
        
        // Update total when discount changes
        discountInput.addEventListener('input', function() {
            updateTotals();
        });
        
        // Payment method selection
        paymentMethodButtons.forEach(button => {
            button.addEventListener('click', function() {
                selectedPaymentMethod = this.dataset.method;
                
                paymentMethodButtons.forEach(btn => {
                    btn.classList.remove('active');
                });
                
                this.classList.add('active');
            });
        });
        
        // Checkout button
        checkoutBtn.addEventListener('click', function() {
            if (cart.length === 0) return;
            
            // Display the appropriate payment section
            document.getElementById('cash-payment-section').style.display = 'none';
            document.getElementById('card-payment-section').style.display = 'none';
            document.getElementById('gcash-payment-section').style.display = 'none';
            document.getElementById('paymaya-payment-section').style.display = 'none';
            
            modalTotalAmount.textContent = `₱${total.toFixed(2)}`;
            
            switch(selectedPaymentMethod) {
                case 'cash':
                    document.getElementById('cash-payment-section').style.display = 'block';
                    cashAmountInput.value = '';
                    changeAmountInput.value = '';
                    break;
                case 'card':
                    document.getElementById('card-payment-section').style.display = 'block';
                    break;
                case 'gcash':
                    document.getElementById('gcash-payment-section').style.display = 'block';
                    break;
                case 'paymaya':
                    document.getElementById('paymaya-payment-section').style.display = 'block';
                    break;
            }
            
            paymentModal.show();
        });
        
        // Calculate change for cash payments
        cashAmountInput.addEventListener('input', function() {
            const cashAmount = parseFloat(this.value) || 0;
            const change = cashAmount - total;
            
            changeAmountInput.value = change >= 0 ? change.toFixed(2) : '0.00';
        });
        
        // Confirm payment
        confirmPaymentBtn.addEventListener('click', function() {
            // Validate payment details
            if (selectedPaymentMethod === 'cash') {
                const cashAmount = parseFloat(cashAmountInput.value) || 0;
                if (cashAmount < total) {
                    alert('Cash amount must be at least equal to the total.');
                    return;
                }
            }
            
            // Process the sale via AJAX
            const saleData = {
                invoice: document.getElementById('invoice-number').textContent,
                items: cart,
                subtotal: subtotal,
                discount: parseFloat(discountInput.value) || 0,
                total: total,
                payment_method: selectedPaymentMethod,
                customer_name: customerNameInput.value || 'Walk-in'
            };
            
            // AJAX request to process_sale.php
            fetchWithErrorHandling('../ajax/process_sale.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(saleData)
            })
            .then(data => {
                if (data.success) {
                    paymentModal.hide();
                    showReceipt(saleData);
                    // Clear cart after successful sale
                    cart = [];
                    updateCartDisplay();
                    
                    // Update invoice number
                    const currentInvoiceNum = parseInt(document.getElementById('invoice-number').textContent.split('-')[1]);
                    const nextInvoiceNum = currentInvoiceNum + 1;
                    document.getElementById('invoice-number').textContent = 'INV-' + String(nextInvoiceNum).padStart(6, '0');
                } else {
                    alert('Error processing sale: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the sale.');
            });
        });
        
        // Clear cart button
        clearCartBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                updateCartDisplay();
            }
        });
        
        // Function to add item to cart
        function addToCart(id, name, price, barcode) {
            // Check if the item is already in the cart
            const existingItem = cart.find(item => item.id === id);
            
            if (existingItem) {
                existingItem.quantity += 1;
                existingItem.subtotal = existingItem.quantity * existingItem.price;
            } else {
                cart.push({
                    id: id,
                    name: name,
                    price: price,
                    barcode: barcode,
                    quantity: 1,
                    subtotal: price
                });
            }
            
            if (barcodeInput) barcodeInput.value = '';
            updateCartDisplay();
        }
        
        // Function to update cart display
        function updateCartDisplay() {
            if (cart.length === 0) {
                emptyCartMessage.style.display = 'block';
                checkoutBtn.disabled = true;
                clearCartBtn.disabled = true;
            } else {
                emptyCartMessage.style.display = 'none';
                checkoutBtn.disabled = false;
                clearCartBtn.disabled = false;
                
                let cartHTML = '';
                
                cart.forEach((item, index) => {
                    cartHTML += `
                    <div class="cart-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="mb-0">${item.name}</h6>
                                <span class="text-muted small">₱${item.price.toFixed(2)} × ${item.quantity}</span>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold">₱${item.subtotal.toFixed(2)}</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <button class="btn btn-sm btn-outline-secondary" onclick="decreaseQuantity(${index})">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="mx-2">${item.quantity}</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="increaseQuantity(${index})">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger ms-auto" onclick="removeItem(${index})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    `;
                });
                
                cartItems.innerHTML = cartHTML;
            }
            
            updateTotals();
        }
        
        // Function to update totals
        function updateTotals() {
            subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
            const discount = parseFloat(discountInput.value) || 0;
            total = Math.max(0, subtotal - discount);
            
            subtotalElement.textContent = `₱${subtotal.toFixed(2)}`;
            totalElement.textContent = `₱${total.toFixed(2)}`;
        }
        
        // Function to search by barcode - using improved error handling
        function searchByBarcode(barcode) {
            if (!barcode) return;
            
            // Search for product in our loaded products first
            const productCard = Array.from(document.querySelectorAll('.product-card'))
                .find(card => card.dataset.barcode === barcode);
            
            if (productCard) {
                const productId = productCard.dataset.id;
                const productName = productCard.dataset.name;
                const productPrice = parseFloat(productCard.dataset.price);
                
                addToCart(productId, productName, productPrice, barcode);
            } else {
                // If not found in loaded products, search via AJAX
                fetchWithErrorHandling(`../ajax/search_product.php?barcode=${barcode}`)
                    .then(data => {
                        if (data.found) {
                            addToCart(data.id, data.name, data.price, data.barcode);
                        } else {
                            alert('Product not found!');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while searching for the product.');
                    });
            }
        }
        
        // Function to show receipt
        function showReceipt(saleData) {
            document.getElementById('receipt-invoice').textContent = saleData.invoice;
            document.getElementById('receipt-date').textContent = new Date().toLocaleString();
            document.getElementById('receipt-customer').textContent = saleData.customer_name || 'Walk-in';
            
            let receiptItemsHTML = '';
            saleData.items.forEach(item => {
                receiptItemsHTML += `
                <tr>
                    <td>${item.name}</td>
                    <td class="text-center">${item.quantity}</td>
                    <td class="text-end">₱${item.price.toFixed(2)}</td>
                    <td class="text-end">₱${item.subtotal.toFixed(2)}</td>
                </tr>
                `;
            });
            
            document.getElementById('receipt-items').innerHTML = receiptItemsHTML;
            document.getElementById('receipt-subtotal').textContent = `₱${saleData.subtotal.toFixed(2)}`;
            document.getElementById('receipt-discount').textContent = `₱${saleData.discount.toFixed(2)}`;
            document.getElementById('receipt-total').textContent = `₱${saleData.total.toFixed(2)}`;
            // Use the robust JavaScript formatPaymentMethod function here
            document.getElementById('receipt-payment-method').textContent = formatPaymentMethod(saleData.payment_method); 
            
            receiptModal.show();
        }
        
        // Make functions available globally
        window.increaseQuantity = function(index) {
            cart[index].quantity += 1;
            cart[index].subtotal = cart[index].quantity * cart[index].price;
            updateCartDisplay();
        };
        
        window.decreaseQuantity = function(index) {
            if (cart[index].quantity > 1) {
                cart[index].quantity -= 1;
                cart[index].subtotal = cart[index].quantity * cart[index].price;
            } else {
                removeItem(index);
            }
            updateCartDisplay();
        };
        
        window.removeItem = function(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        };
        
        window.printReceipt = function() {
            const receiptContent = document.getElementById('receipt-content');
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
                        window.onload = function() { window.print(); window.close(); }
                    <\/script>
                </body>
                </html>
            `);
            
            printWindow.document.close();
        };

        // Add this helper function at the beginning of the script:
        // (or ensure it's defined before use if it's already there)
        function formatPaymentMethod(method) { // Renamed from getPaymentMethodNameJS to match existing call
            if (method === null || typeof method === 'undefined') {
                return 'Not Specified';
            }
            let trimmedMethod = String(method).trim();
            if (trimmedMethod === '') {
                return 'Not Specified';
            }
            let lowerMethod = trimmedMethod.toLowerCase();
            const methodMap = {
                'cod': 'Cash on Delivery',
                'cash': 'Cash',
                'gcash': 'GCash',
                'paymaya': 'PayMaya',
                'card': 'Credit/Debit Card'
            };
            
            return methodMap[lowerMethod] || (trimmedMethod.charAt(0).toUpperCase() + trimmedMethod.slice(1));
        }
    });
    </script>
</body>
</html>
