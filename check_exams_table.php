<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

echo "START\n";
flush();

try {
    echo "Connected to DB\n";
    flush();
    
    $stmt = $pdo->query("SELECT 1");
    echo "Query executed\n";
    flush();
    
    $version = $stmt->fetchColumn();
    echo "Version: $version\n";
    flush();
    
    echo "DONE\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}