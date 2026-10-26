<?php

require_once __DIR__ . '/logger.php';

// Check if environment variables exist, otherwise use local defaults
$db    = getenv('DB_NAME') ?: 'ettzz_db';
$user  = getenv('DB_USER') ?: 'root';
$pass  = getenv('DB_PASS') ?: ''; 
$host  = getenv('DB_HOST') ?: 'localhost';
$port  = '3306'; // Standard MySQL port
$char  = "utf8mb4";

// Added ';port=' to the connection string below
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$char";

try {
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    // Optional: Echo this to verify it finally worked when testing
    // echo "Connected successfully!"; 
} catch (PDOException $e) {
    log_exception($e, 'Database connection failed', ['host' => $host, 'db' => $db]);
    die("Database connection failed: " . $e->getMessage());
}

?>
