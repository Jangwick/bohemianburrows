<?php
// Comprehensive database fix utility for adding all missing columns
require_once "includes/db_connect.php";

echo "<h2>Database Fix Utility</h2>";
$fixes_needed = 0;
$fixes_applied = 0;

// Array of columns to check and add if missing
$columns_to_check = [
    'subtotal' => "ADD COLUMN `subtotal` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount before discount' AFTER `customer_name`",
    'notes' => "ADD COLUMN `notes` TEXT NULL COMMENT 'Customer order notes' AFTER `payment_status`",
    'shipping_address' => "ADD COLUMN `shipping_address` VARCHAR(255) NULL COMMENT 'Customer shipping address' AFTER `notes`",
    'shipping_city' => "ADD COLUMN `shipping_city` VARCHAR(100) NULL COMMENT 'Customer shipping city' AFTER `shipping_address`",
    'shipping_postal_code' => "ADD COLUMN `shipping_postal_code` VARCHAR(20) NULL COMMENT 'Customer shipping postal code' AFTER `shipping_city`",
    'email' => "ADD COLUMN `email` VARCHAR(100) NULL COMMENT 'Customer email address' AFTER `shipping_postal_code`",
    'phone' => "ADD COLUMN `phone` VARCHAR(50) NULL COMMENT 'Customer phone number' AFTER `email`"
];

// Function to check and add column if missing
function checkAndAddColumn($conn, $column_name, $alter_statement) {
    global $fixes_needed, $fixes_applied;
    
    // Check if column exists
    $column_check = $conn->query("SHOW COLUMNS FROM `sales` LIKE '$column_name'");
    $column_exists = ($column_check && $column_check->num_rows > 0);
    
    if ($column_exists) {
        echo "<p style='color: green;'>✓ The $column_name column already exists in the sales table.</p>";
        return;
    }
    
    // Column doesn't exist, try to add it
    $fixes_needed++;
    echo "<p>Adding '$column_name' column to the sales table...</p>";
    
    $alter_query = "ALTER TABLE `sales` $alter_statement";
    
    if ($conn->query($alter_query)) {
        echo "<p style='color: green;'>✓ Successfully added '$column_name' column to the sales table.</p>";
        $fixes_applied++;
    } else {
        echo "<p style='color: red;'>✗ Failed to add '$column_name' column: " . $conn->error . "</p>";
    }
}

// Check and add each missing column
foreach ($columns_to_check as $column_name => $alter_statement) {
    checkAndAddColumn($conn, $column_name, $alter_statement);
}

// Special handling for subtotal - update values if column was just added
$column_check = $conn->query("SHOW COLUMNS FROM `sales` LIKE 'subtotal'");
$subtotal_exists = ($column_check && $column_check->num_rows > 0);

if ($subtotal_exists) {
    // Check if there are any records with subtotal = 0 but total_amount > 0
    $needs_update = $conn->query("SELECT COUNT(*) as count FROM `sales` WHERE subtotal = 0 AND total_amount > 0")->fetch_assoc()['count'];
    
    if ($needs_update > 0) {
        echo "<p>Updating existing sales records with calculated subtotal values...</p>";
        if ($conn->query("UPDATE `sales` SET `subtotal` = (`total_amount` + `discount`) WHERE subtotal = 0 AND total_amount > 0")) {
            echo "<p style='color: green;'>✓ Successfully updated existing records with calculated subtotal values.</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Failed to update existing records: " . $conn->error . "</p>";
        }
    }
}

// Display summary
echo "<hr>";
if ($fixes_needed == 0) {
    echo "<h3 style='color: green;'>✓ Database schema is up-to-date. No fixes needed.</h3>";
} else if ($fixes_applied == $fixes_needed) {
    echo "<h3 style='color: green;'>✓ Successfully applied all needed fixes ($fixes_applied).</h3>";
} else {
    echo "<h3 style='color: red;'>✗ Applied $fixes_applied out of $fixes_needed needed fixes.</h3>";
}

// Provide instructions for next steps
echo "<div style='margin-top: 20px; padding: 10px; background-color: #f8f9fa; border-left: 4px solid #007bff;'>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Return to your application and try processing a sale again.</li>";
echo "<li>If you still see errors, check if your database user has ALTER TABLE permissions.</li>";
echo "</ol>";
echo "</div>";

echo "<div style='margin-top: 20px;'>";
echo "<a href='admin/pos.php' class='btn btn-primary' style='text-decoration: none; padding: 8px 16px; background-color: #007bff; color: white; border-radius: 4px;'>Return to POS</a>";
echo "</div>";

// Close the connection
$conn->close();
?>
