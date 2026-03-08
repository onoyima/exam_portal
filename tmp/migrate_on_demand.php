<?php
require 'db.php';

try {
    $check = $db->query("SHOW COLUMNS FROM `objective_attempts` LIKE 'is_live_requested'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE `objective_attempts` ADD COLUMN `is_live_requested` TINYINT(1) DEFAULT 0 AFTER `snapshot_path`;");
        echo "Successfully added is_live_requested column.\n";
    } else {
        echo "Column already exists.\n";
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>