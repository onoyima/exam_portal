<?php
// Force errors to display for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config.php';

echo "<h2>Database Connection Diagnostic</h2>";
echo "<strong>Host:</strong> " . DB_HOST . "<br>";
echo "<strong>Port:</strong> " . DB_PORT . "<br>";
echo "<strong>Database:</strong> " . DB_NAME . "<br>";
echo "<strong>User:</strong> " . DB_USER . "<br>";
echo "<hr>";

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h3 style='color:green;'>✔ Success! Connected to database successfully!</h3>";
} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Connection Failed!</h3>";
    echo "<strong>Error Code:</strong> " . $e->getCode() . "<br>";
    echo "<strong>Error Message:</strong> " . $e->getMessage() . "<br>";
}
