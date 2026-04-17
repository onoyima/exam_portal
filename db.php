<?php
$host = 'localhost';
$dbname = 'backup_db_pre_implementation';
$user = 'root';      // Change to your DB username
$pass = '';          // Change to your DB password

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
require_once 'exam_logic.php';
?>