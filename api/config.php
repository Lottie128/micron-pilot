<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
// UPDATE THESE VALUES WITH YOUR DATABASE CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');      // Change this
define('DB_PASS', 'your_db_password');  // Change this
define('DB_NAME', 'micron_tracking');

// Create database connection
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch (PDOException $e) {
        // Log the actual error
        error_log("Database connection failed: " . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'error' => 'Database connection failed',
            'message' => 'Please check your database configuration in api/config.php',
            'details' => $e->getMessage(),
            'help' => 'Run api/debug.php to diagnose the issue'
        ]);
        exit();
    }
}

function sendJSON($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

function sendError($message, $code = 400, $details = null) {
    $response = ['error' => $message];
    if ($details) {
        $response['details'] = $details;
    }
    sendJSON($response, $code);
}
?>
