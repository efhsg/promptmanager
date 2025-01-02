<?php

// Test file to verify MySQL, PHP-FPM and Nginx connectivity
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Connection Test</h1>";

// Basic PHP Info
echo "<h2>PHP Configuration</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PHP-FPM Server Port: " . $_SERVER['SERVER_PORT'] . "<br>";

// MySQL Connection Test
echo "<h2>MySQL Connection Test</h2>";
try {
    $host = 'pma_mysql';  // Docker service name
    $dbname = getenv('DB_DATABASE');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');

    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "MySQL Connection: SUCCESS<br>";
    echo "MySQL Version: " . $conn->query('select version()')->fetchColumn() . "<br>";
    
} catch(PDOException $e) {
    echo "MySQL Connection: FAILED<br>";
    echo "Error: " . $e->getMessage();
}

// Environment Variables Test
echo "<h2>Environment Variables</h2>";
echo "Timezone: " . getenv('TZ') . "<br>";
echo "PHP_FPM_PORT: " . getenv('PHP_FPM_PORT') . "<br>";

// Server Information
echo "<h2>Server Information</h2>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Server Name: " . $_SERVER['SERVER_NAME'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
?>
