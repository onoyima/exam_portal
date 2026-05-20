<?php
require 'db.php';

try {
    // Check if the snapshot_path column already exists to avoid errors
    $check = $db->query("SHOW COLUMNS FROM `objective_attempts` LIKE 'snapshot_path'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE `objective_attempts` ADD COLUMN `snapshot_path` VARCHAR(255) DEFAULT NULL AFTER `webcam_snapshot`;");
        echo "Successfully added snapshot_path column to objective_attempts table.\n";
    } else {
        echo "snapshot_path column already exists.\n";
    }
} catch (PDOException $e) {
    die("Database error during migration: " . $e->getMessage() . "\n");
}
?>