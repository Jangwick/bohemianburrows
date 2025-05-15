<?php
// Include the database connection file
require_once 'includes/db_connect.php';

// If we get here without errors, connection was successful
echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin: 10px; border-radius: 5px;'>
      <h3>Connection Test</h3>
      <p>Successfully connected to the '$dbname' database on '$servername'!</p>
      </div>";

// Show some basic database info
$tables_query = $conn->query("SHOW TABLES");
if ($tables_query->num_rows > 0) {
    echo "<div style='padding: 15px; margin: 10px; border-radius: 5px; border: 1px solid #ccc;'>";
    echo "<h4>Database Tables:</h4><ul>";
    while($table = $tables_query->fetch_array()) {
        echo "<li>{$table[0]}</li>";
    }
    echo "</ul></div>";
} else {
    echo "<div style='background-color: #fff3cd; color: #856404; padding: 15px; margin: 10px; border-radius: 5px;'>
          <p>No tables found in the database. You might need to run the <a href='includes/db_setup.php'>database setup</a>.</p>
          </div>";
}

// Close the connection
$conn->close();
?>
