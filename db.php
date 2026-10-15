<?php
/**
 * Database Connection File (db.php)
 * Using PDO for secure database operations
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartstay_hotel');  // Change this to your database name
define('DB_USER', 'root');           // Change this to your database username
define('DB_PASS', '');               // Change this to your database password
define('DB_CHARSET', 'utf8mb4');

// PDO options for better error handling and security
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Create PDO instance
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Also create $conn alias for compatibility
    $conn = $pdo;
    
} catch (PDOException $e) {
    // Log the error
    error_log("Database Connection Error: " . $e->getMessage());
    
    // In production, don't expose database errors to users
    // For development, you can uncomment the line below
    // die("Database connection failed: " . $e->getMessage());
    
    die("Database connection failed. Please contact support.");
}

// Helper function for JSON responses (optional)
function jsonResponse($status, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}
?>