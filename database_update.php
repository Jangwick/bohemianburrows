<?php
require_once "includes/db_connect.php";

echo "<h2>Database Structure Update Utility</h2>";

// Check if the wishlist table exists
$wishlist_check = $conn->query("SHOW TABLES LIKE 'wishlist'");
$wishlist_exists = ($wishlist_check && $wishlist_check->num_rows > 0);

if($wishlist_exists) {
    echo "<p style='color: green;'>✓ The wishlist table already exists in the database.</p>";
} else {
    echo "<p>Creating wishlist table...</p>";
    
    $create_wishlist_sql = "
    CREATE TABLE `wishlist` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `wishlist_user_fk` (`user_id`),
      KEY `wishlist_product_fk` (`product_id`),
      CONSTRAINT `wishlist_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      CONSTRAINT `wishlist_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if($conn->query($create_wishlist_sql)) {
        echo "<p style='color: green;'>✓ Successfully created the wishlist table.</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create wishlist table: " . $conn->error . "</p>";
        
        // Alternative approach without foreign keys if the previous one failed
        echo "<p>Trying alternative approach without foreign key constraints...</p>";
        
        $simple_create_sql = "
        CREATE TABLE `wishlist` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `product_id` int(11) NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if($conn->query($simple_create_sql)) {
            echo "<p style='color: green;'>✓ Successfully created the wishlist table (without constraints).</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed again to create wishlist table: " . $conn->error . "</p>";
        }
    }
}

echo "<div style='margin-top: 20px;'>";
echo "<a href='user/product_details.php' class='btn btn-primary' style='text-decoration: none; padding: 8px 16px; background-color: #007bff; color: white; border-radius: 4px;'>Return to Product Details</a>";
echo "</div>";

$conn->close();
?>
