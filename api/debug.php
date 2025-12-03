<?php
// Database Connection Debug Tool
// Access this file directly to test your database connection
// Example: https://yourdomain.com/api/debug.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Micron Tracking - Database Debug</h1>";
echo "<hr>";

// Test 1: PHP Version
echo "<h2>✓ Test 1: PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Required: 7.4+<br>";
if (version_compare(phpversion(), '7.4.0', '>=')) {
    echo "<span style='color:green'>PASS</span><br>";
} else {
    echo "<span style='color:red'>FAIL - Please upgrade PHP</span><br>";
}

echo "<hr>";

// Test 2: PDO Extension
echo "<h2>✓ Test 2: PDO Extension</h2>";
if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
    echo "<span style='color:green'>PASS - PDO and PDO_MySQL are available</span><br>";
} else {
    echo "<span style='color:red'>FAIL - PDO or PDO_MySQL not available</span><br>";
    exit;
}

echo "<hr>";

// Test 3: Config File
echo "<h2>✓ Test 3: Configuration File</h2>";
if (file_exists(__DIR__ . '/config.php')) {
    echo "config.php found<br>";
    require_once 'config.php';
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "DB_USER: " . DB_USER . "<br>";
    echo "DB_PASS: " . str_repeat('*', strlen(DB_PASS)) . "<br>";
    echo "<span style='color:green'>PASS</span><br>";
} else {
    echo "<span style='color:red'>FAIL - config.php not found</span><br>";
    exit;
}

echo "<hr>";

// Test 4: Database Connection
echo "<h2>✓ Test 4: Database Connection</h2>";
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "<span style='color:green'>PASS - Connected to database successfully</span><br>";
} catch (PDOException $e) {
    echo "<span style='color:red'>FAIL - Connection error: " . $e->getMessage() . "</span><br>";
    echo "<h3>Common Solutions:</h3>";
    echo "<ul>";
    echo "<li>Check if database 'micron_tracking' exists</li>";
    echo "<li>Verify DB_USER has access to the database</li>";
    echo "<li>Check DB_PASS is correct</li>";
    echo "<li>Ensure MySQL service is running</li>";
    echo "<li>Check if DB_HOST should be 'localhost' or '127.0.0.1'</li>";
    echo "</ul>";
    exit;
}

echo "<hr>";

// Test 5: Check Tables
echo "<h2>✓ Test 5: Database Tables</h2>";
try {
    $tables = ['parts', 'stages', 'bins', 'inventory', 'movements'];
    $missing = [];
    
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "Table '$table': <span style='color:green'>EXISTS</span><br>";
        } else {
            echo "Table '$table': <span style='color:red'>MISSING</span><br>";
            $missing[] = $table;
        }
    }
    
    if (empty($missing)) {
        echo "<br><span style='color:green'>PASS - All tables exist</span><br>";
    } else {
        echo "<br><span style='color:red'>FAIL - Missing tables: " . implode(', ', $missing) . "</span><br>";
        echo "<p>Solution: Import database.sql file first</p>";
    }
} catch (PDOException $e) {
    echo "<span style='color:red'>Error checking tables: " . $e->getMessage() . "</span><br>";
}

echo "<hr>";

// Test 6: Check Data
echo "<h2>✓ Test 6: Sample Data</h2>";
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM parts");
    $row = $stmt->fetch();
    echo "Parts count: " . $row['count'] . "<br>";
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM stages");
    $row = $stmt->fetch();
    echo "Stages count: " . $row['count'] . "<br>";
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM bins");
    $row = $stmt->fetch();
    echo "Bins count: " . $row['count'] . "<br>";
    
    echo "<br><span style='color:green'>PASS - Data check complete</span><br>";
    
    if ($row['count'] == 0) {
        echo "<p style='color:orange'>Note: No bins found. Import database.sql to create default bins.</p>";
    }
} catch (PDOException $e) {
    echo "<span style='color:red'>Error: " . $e->getMessage() . "</span><br>";
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>If all tests pass, your database is ready. Otherwise, follow the solutions above.</p>";
echo "<p><a href='../index.html'>Go to Dashboard</a></p>";
?>
