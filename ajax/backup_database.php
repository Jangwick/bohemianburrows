<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo "Unauthorized";
    exit;
}

require_once "../includes/db_connect.php";

// Get database connection details from db_connect.php
$host = $db_host ?? 'localhost';
$username = $db_user ?? 'root';
$password = $db_password ?? '';
$database = $db_name ?? 'bohemianburrows';

// Set filename for the backup
$filename = 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';

// Set path for the backup
$backup_path = '../backups/';
if (!file_exists($backup_path)) {
    mkdir($backup_path, 0755, true);
}

$command = "mysqldump --host=$host --user=$username ";
if(!empty($password)) {
    $command .= "--password=$password ";
}
$command .= "$database > $backup_path$filename";

// Execute backup command
exec($command, $output, $return_val);

if($return_val === 0) {
    // Success - offer file for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($filename));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize("$backup_path$filename"));
    ob_clean();
    flush();
    readfile("$backup_path$filename");
    exit;
} else {
    // Error
    echo "Database backup failed. Please check server configuration.";
    exit;
}
