<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

try {
    echo "START\n";
    flush();
    
    echo "Testing getAttribute...\n";
    flush();
    
    $attr = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "Driver: $attr\n";
    flush();
    
    $attr = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
    echo "Server: $attr\n";
    flush();
    
    echo "DONE\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}