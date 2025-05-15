<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "../includes/db_connect.php";

// Initialize messages array
$messages = [];

// Load current settings
function getSettings($conn) {
    // Check if settings table exists, if not create it
    $conn->query("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_group VARCHAR(50) DEFAULT 'general',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Check for default settings and insert if not present
    $default_settings = [
        ['store_name', 'The Bohemian Burrows', 'store'],
        ['store_address', '123 Fashion Street, Makati City', 'store'],
        ['store_phone', '(02) 8123-4567', 'store'],
        ['store_email', 'info@bohemianburrows.com', 'store'],
        ['currency', 'PHP', 'system'],
        ['tax_rate', '12', 'system'],
        ['invoice_prefix', 'INV-', 'system'],
        ['low_stock_threshold', '10', 'system'],
        ['enable_customer_login', '1', 'system'],
        ['logo_path', 'assets/images/logo.png', 'appearance'],
        ['theme_color', '#007bff', 'appearance'],
        ['enable_dark_mode', '0', 'appearance'],
        ['receipt_footer', 'Thank you for shopping at The Bohemian Burrows!', 'receipt'],
        ['receipt_show_tax', '1', 'receipt'],
        ['enable_email_receipts', '0', 'receipt'],
        ['backup_frequency', 'weekly', 'maintenance'],
        ['enable_sales_notifications', '1', 'notifications'],
        ['notify_low_stock', '1', 'notifications'],
        ['default_payment_method', 'cash', 'payments'],
        ['enable_card_payments', '1', 'payments'],
        ['enable_gcash', '1', 'payments'],
        ['enable_paymaya', '1', 'payments']
    ];

    // Insert default settings if they don't exist
    foreach ($default_settings as $setting) {
        $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $setting[0], $setting[1], $setting[2]);
        $stmt->execute();
    }

    // Get all settings from database
    $settings_query = $conn->query("SELECT * FROM settings ORDER BY setting_group, setting_key");
    $settings = [];
    
    while ($row = $settings_query->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
        // Group settings by their group
        $settings_by_group[$row['setting_group']][$row['setting_key']] = $row['setting_value'];
    }
    
    return ['all' => $settings, 'by_group' => $settings_by_group ?? []];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the active tab from the form
    $active_tab = $_POST['active_tab'] ?? 'store';

    // Process file uploads first
    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] == 0) {
        $target_dir = "../assets/images/";
        $file_extension = pathinfo($_FILES["logo_upload"]["name"], PATHINFO_EXTENSION);
        $new_filename = "logo_" . date("YmdHis") . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check if image file is an actual image
        $check = getimagesize($_FILES["logo_upload"]["tmp_name"]);
        if($check !== false) {
            if (move_uploaded_file($_FILES["logo_upload"]["tmp_name"], $target_file)) {
                $_POST['logo_path'] = "assets/images/" . $new_filename;
                $messages['success'][] = "Logo uploaded successfully.";
            } else {
                $messages['error'][] = "Error uploading logo.";
            }
        } else {
            $messages['error'][] = "File is not an image.";
        }
    }
    
    // Update settings in database
    foreach ($_POST as $key => $value) {
        // Skip non-setting fields
        if (in_array($key, ['submit', 'active_tab'])) {
            continue;
        }
        
        // For checkboxes that might not be set
        if (strpos($key, 'enable_') === 0 && !isset($_POST[$key])) {
            $value = '0';
        }
        
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        if ($stmt->execute()) {
            // Setting updated successfully
        } else {
            $messages['error'][] = "Error updating setting: " . $key;
        }
    }
    
    $messages['success'][] = "Settings updated successfully.";
}

// Load current settings
$settings_data = getSettings($conn);
$settings = $settings_data['all'];
$settings_by_group = $settings_data['by_group'];

// Determine which tab is active
$active_tab = $_GET['tab'] ?? 'store';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - The Bohemian Burrows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .settings-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .settings-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .form-label {
            font-weight: 500;
        }
        .setting-description {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .nav-pills .nav-link.active {
            background-color: #007bff;
        }
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            display: inline-block;
            vertical-align: middle;
            margin-left: 10px;
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
                    <h1 class="h2"><i class="fas fa-cogs me-2"></i>System Settings</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-secondary me-2" id="resetSettings">
                            <i class="fas fa-redo"></i> Reset to Default
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="backupSettings">
                            <i class="fas fa-download"></i> Backup Settings
                        </button>
                    </div>
                </div>

                <!-- Display messages -->
                <?php if (!empty($messages['success'])): ?>
                    <?php foreach ($messages['success'] as $message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($messages['error'])): ?>
                    <?php foreach ($messages['error'] as $message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Settings Categories</h5>
                            </div>
                            <div class="card-body p-0">
                                <nav class="nav flex-column nav-pills">
                                    <a class="nav-link <?php echo $active_tab === 'store' ? 'active' : ''; ?>" href="?tab=store">
                                        <i class="fas fa-store me-2"></i> Store Information
                                    </a>
                                    <a class="nav-link <?php echo $active_tab === 'system' ? 'active' : ''; ?>" href="?tab=system">
                                        <i class="fas fa-cog me-2"></i> System Configuration
                                    </a>
                                    <a class="nav-link <?php echo $active_tab === 'appearance' ? 'active' : ''; ?>" href="?tab=appearance">
                                        <i class="fas fa-palette me-2"></i> Appearance
                                    </a>
                                    <a class="nav-link <?php echo $active_tab === 'receipt' ? 'active' : ''; ?>" href="?tab=receipt">
                                        <i class="fas fa-receipt me-2"></i> Receipt Settings
                                    </a>
                                    <a class="nav-link <?php echo $active_tab === 'payments' ? 'active' : ''; ?>" href="?tab=payments">
                                        <i class="fas fa-credit-card me-2"></i> Payment Methods
                                    </a>
                                    <a class="nav-link <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" href="?tab=notifications">
                                        <i class="fas fa-bell me-2"></i> Notifications
                                    </a>
                                    <a class="nav-link <?php echo $active_tab === 'maintenance' ? 'active' : ''; ?>" href="?tab=maintenance">
                                        <i class="fas fa-tools me-2"></i> Maintenance
                                    </a>
                                </nav>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-9">
                        <div class="card settings-card mb-4">
                            <div class="card-body">
                                <form method="post" enctype="multipart/form-data">
                                    <!-- Pass active tab in form -->
                                    <input type="hidden" name="active_tab" value="<?php echo $active_tab; ?>">
                                    
                                    <?php if ($active_tab === 'store'): ?>
                                        <h4 class="mb-4">Store Information</h4>
                                        <div class="mb-3">
                                            <label for="store_name" class="form-label">Store Name</label>
                                            <p class="setting-description">The name of your business as it appears on receipts and in the system.</p>
                                            <input type="text" class="form-control" id="store_name" name="store_name" value="<?php echo htmlspecialchars($settings['store_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="store_address" class="form-label">Store Address</label>
                                            <p class="setting-description">Full address including street, city, and postal code.</p>
                                            <textarea class="form-control" id="store_address" name="store_address" rows="2"><?php echo htmlspecialchars($settings['store_address'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="store_phone" class="form-label">Phone Number</label>
                                                <p class="setting-description">Contact number that appears on receipts.</p>
                                                <input type="text" class="form-control" id="store_phone" name="store_phone" value="<?php echo htmlspecialchars($settings['store_phone'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="store_email" class="form-label">Email Address</label>
                                                <p class="setting-description">Business email for customer inquiries.</p>
                                                <input type="email" class="form-control" id="store_email" name="store_email" value="<?php echo htmlspecialchars($settings['store_email'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Business Hours</label>
                                            <p class="setting-description">Your regular operating hours.</p>
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label for="opening_time" class="form-label small">Opening Time</label>
                                                    <input type="time" class="form-control" id="opening_time" name="opening_time" value="<?php echo htmlspecialchars($settings['opening_time'] ?? '09:00'); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="closing_time" class="form-label small">Closing Time</label>
                                                    <input type="time" class="form-control" id="closing_time" name="closing_time" value="<?php echo htmlspecialchars($settings['closing_time'] ?? '18:00'); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                    <?php elseif ($active_tab === 'system'): ?>
                                        <h4 class="mb-4">System Configuration</h4>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="currency" class="form-label">Currency</label>
                                                <p class="setting-description">The currency used for pricing and transactions.</p>
                                                <select class="form-select" id="currency" name="currency">
                                                    <option value="PHP" <?php echo ($settings['currency'] ?? '') === 'PHP' ? 'selected' : ''; ?>>Philippine Peso (PHP)</option>
                                                    <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                                    <option value="EUR" <?php echo ($settings['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                                <p class="setting-description">Default tax rate applied to sales.</p>
                                                <input type="number" class="form-control" id="tax_rate" name="tax_rate" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '12'); ?>">
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="invoice_prefix" class="form-label">Invoice Prefix</label>
                                                <p class="setting-description">Text prefix for invoice numbers (e.g. INV-).</p>
                                                <input type="text" class="form-control" id="invoice_prefix" name="invoice_prefix" value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'INV-'); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                                                <p class="setting-description">Item quantity that triggers low stock warnings.</p>
                                                <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold" min="1" value="<?php echo htmlspecialchars($settings['low_stock_threshold'] ?? '10'); ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="enable_customer_login" name="enable_customer_login" <?php echo ($settings['enable_customer_login'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_customer_login">Allow Customer Logins</label>
                                            </div>
                                            <p class="setting-description">Enable customers to create accounts and view their purchase history.</p>
                                        </div>
                                        
                                    <?php elseif ($active_tab === 'appearance'): ?>
                                        <h4 class="mb-4">Appearance Settings</h4>
                                        <div class="mb-3">
                                            <label for="logo_upload" class="form-label">Store Logo</label>
                                            <p class="setting-description">Upload your business logo for receipts and the system.</p>
                                            <?php if (!empty($settings['logo_path'])): ?>
                                                <div class="mb-2">
                                                    <img src="../<?php echo htmlspecialchars($settings['logo_path']); ?>" alt="Store Logo" class="img-thumbnail" style="max-height: 100px;">
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" class="form-control" id="logo_upload" name="logo_upload" accept="image/*">
                                            <input type="hidden" name="logo_path" value="<?php echo htmlspecialchars($settings['logo_path'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="theme_color" class="form-label">Theme Color</label>
                                            <p class="setting-description">Primary color used throughout the system.</p>
                                            <div class="input-group">
                                                <input type="color" class="form-control form-control-color" id="theme_color" name="theme_color" value="<?php echo htmlspecialchars($settings['theme_color'] ?? '#007bff'); ?>">
                                                <input type="text" class="form-control" id="theme_color_hex" value="<?php echo htmlspecialchars($settings['theme_color'] ?? '#007bff'); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="enable_dark_mode" name="enable_dark_mode" <?php echo ($settings['enable_dark_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_dark_mode">Enable Dark Mode</label>
                                            </div>
                                            <p class="setting-description">Use dark color scheme throughout the admin panel.</p>
                                        </div>
                                        
                                    <?php elseif ($active_tab === 'receipt'): ?>
                                        <h4 class="mb-4">Receipt Settings</h4>
                                        <div class="mb-3">
                                            <label for="receipt_footer" class="form-label">Receipt Footer</label>
                                            <p class="setting-description">Message that appears at the bottom of receipts.</p>
                                            <textarea class="form-control" id="receipt_footer" name="receipt_footer" rows="2"><?php echo htmlspecialchars($settings['receipt_footer'] ?? 'Thank you for shopping at The Bohemian Burrows!'); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="receipt_show_tax" name="receipt_show_tax" <?php echo ($settings['receipt_show_tax'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="receipt_show_tax">Show Tax Breakdown</label>
                                            </div>
                                            <p class="setting-description">Display tax calculation details on receipts.</p>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="enable_email_receipts" name="enable_email_receipts" <?php echo ($settings['enable_email_receipts'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_email_receipts">Email Receipts to Customers</label>
                                            </div>
                                            <p class="setting-description">Automatically send receipts to customer email addresses when available.</p>
                                        </div>
                                        
                                    <?php elseif ($active_tab === 'payments'): ?>
                                        <h4 class="mb-4">Payment Method Settings</h4>
                                        <div class="mb-3">
                                            <label for="default_payment_method" class="form-label">Default Payment Method</label>
                                            <p class="setting-description">Pre-selected payment method in POS.</p>
                                            <select class="form-select" id="default_payment_method" name="default_payment_method">
                                                <option value="cash" <?php echo ($settings['default_payment_method'] ?? 'cash') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                                <option value="card" <?php echo ($settings['default_payment_method'] ?? '') === 'card' ? 'selected' : ''; ?>>Card</option>
                                                <option value="gcash" <?php echo ($settings['default_payment_method'] ?? '') === 'gcash' ? 'selected' : ''; ?>>GCash</option>
                                                <option value="paymaya" <?php echo ($settings['default_payment_method'] ?? '') === 'paymaya' ? 'selected' : ''; ?>>PayMaya</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Available Payment Methods</label>
                                            <p class="setting-description">Select which payment methods are available for use.</p>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="enable_cash" name="enable_cash" checked disabled>
                                                <label class="form-check-label" for="enable_cash">Cash (Always enabled)</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="enable_card_payments" name="enable_card_payments" <?php echo ($settings['enable_card_payments'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_card_payments">Card Payments</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="enable_gcash" name="enable_gcash" <?php echo ($settings['enable_gcash'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_gcash">GCash</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="enable_paymaya" name="enable_paymaya" <?php echo ($settings['enable_paymaya'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_paymaya">PayMaya</label>
                                            </div>
                                        </div>
                                        
                                    <?php elseif ($active_tab === 'notifications'): ?>
                                        <h4 class="mb-4">Notification Settings</h4>
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="enable_sales_notifications" name="enable_sales_notifications" <?php echo ($settings['enable_sales_notifications'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_sales_notifications">Sales Notifications</label>
                                            </div>
                                            <p class="setting-description">Receive notifications for new sales.</p>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="notify_low_stock" name="notify_low_stock" <?php echo ($settings['notify_low_stock'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="notify_low_stock">Low Stock Alerts</label>
                                            </div>
                                            <p class="setting-description">Receive notifications when products are running low.</p>
                                        </div>
                                        
                                    <?php elseif ($active_tab === 'maintenance'): ?>
                                        <h4 class="mb-4">System Maintenance</h4>
                                        <div class="mb-3">
                                            <label for="backup_frequency" class="form-label">Automatic Backup Frequency</label>
                                            <p class="setting-description">How often system data should be automatically backed up.</p>
                                            <select class="form-select" id="backup_frequency" name="backup_frequency">
                                                <option value="daily" <?php echo ($settings['backup_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                                <option value="weekly" <?php echo ($settings['backup_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                <option value="monthly" <?php echo ($settings['backup_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                                <option value="never" <?php echo ($settings['backup_frequency'] ?? '') === 'never' ? 'selected' : ''; ?>>Never</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <button type="button" class="btn btn-primary me-2" id="backupNow">
                                                <i class="fas fa-download"></i> Backup Database Now
                                            </button>
                                            <button type="button" class="btn btn-secondary" id="optimizeDb">
                                                <i class="fas fa-broom"></i> Optimize Database
                                            </button>
                                        </div>
                                    <?php endif; ?>

                                    <hr class="my-4">
                                    <div class="d-flex justify-content-end">
                                        <button type="reset" class="btn btn-secondary me-2">Reset Changes</button>
                                        <button type="submit" name="submit" class="btn btn-primary">Save Settings</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Confirm Reset Modal -->
    <div class="modal fade" id="resetConfirmModal" tabindex="-1" aria-labelledby="resetConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetConfirmModalLabel">Reset to Default Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Warning: This will reset all settings to their default values. This action cannot be undone.
                    </div>
                    <p>Are you sure you want to proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmReset">Yes, Reset Settings</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update color input text when color picker changes
            const themeColor = document.getElementById('theme_color');
            const themeColorHex = document.getElementById('theme_color_hex');
            
            if (themeColor && themeColorHex) {
                themeColor.addEventListener('input', function() {
                    themeColorHex.value = this.value;
                });
            }
            
            // Reset settings button
            const resetSettings = document.getElementById('resetSettings');
            const resetConfirmModal = new bootstrap.Modal(document.getElementById('resetConfirmModal'));
            
            if (resetSettings) {
                resetSettings.addEventListener('click', function() {
                    resetConfirmModal.show();
                });
            }
            
            // Confirm reset button
            const confirmReset = document.getElementById('confirmReset');
            if (confirmReset) {
                confirmReset.addEventListener('click', function() {
                    // AJAX call to reset settings
                    fetch('../ajax/reset_settings.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            resetConfirmModal.hide();
                            alert('Settings have been reset to default values.');
                            location.reload();
                        } else {
                            alert('Error resetting settings: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while resetting settings.');
                    });
                });
            }
            
            // Backup settings button
            const backupSettings = document.getElementById('backupSettings');
            if (backupSettings) {
                backupSettings.addEventListener('click', function() {
                    window.location.href = '../ajax/backup_settings.php';
                });
            }
            
            // Backup now button
            const backupNow = document.getElementById('backupNow');
            if (backupNow) {
                backupNow.addEventListener('click', function() {
                    window.location.href = '../ajax/backup_database.php';
                });
            }
            
            // Optimize database button
            const optimizeDb = document.getElementById('optimizeDb');
            if (optimizeDb) {
                optimizeDb.addEventListener('click', function() {
                    fetch('../ajax/optimize_database.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Database optimized successfully.');
                        } else {
                            alert('Error optimizing database: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while optimizing the database.');
                    });
                });
            }
        });
    </script>
</body>
</html>
