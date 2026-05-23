<?php
/**
 * database/export.php — Portable Database Export
 *
 * Creates a clean SQL dump that imports without errors on any MySQL/MariaDB server.
 * Run: php database/export.php
 */

// ─── Config ───────────────────────────────────────────────────────────────
$host   = 'localhost';
$dbname = 'backup_db_pre_implementation';
$user   = 'root';
$pass   = '';
$charset = 'utf8mb4';

// Output file
$outputFile = __DIR__ . '/exam_portal_portable.sql';

// ─── Connect ──────────────────────────────────────────────────────────────
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// ─── Helpers ──────────────────────────────────────────────────────────────
function escapeValue($value, $db) {
    if ($value === null) return 'NULL';
    $value = $db->quote($value);
    // Ensure binary strings are hex-encoded (for BLOB safety)
    return $value;
}

function buildInsert($table, $columns, $row, $db) {
    $vals = [];
    foreach ($columns as $col) {
        $vals[] = escapeValue($row[$col], $db);
    }
    $cols = '`' . implode('`, `', $columns) . '`';
    return 'INSERT INTO `' . $table . '` (' . $cols . ') VALUES (' . implode(', ', $vals) . ');';
}

// ─── Build output ─────────────────────────────────────────────────────────
$sql = '';

// Header
$sql .= "-- ============================================================\n";
$sql .= "-- exam_portal_portable.sql — Clean Portable Export\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- ============================================================\n\n";

$sql .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
$sql .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
$sql .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
$sql .= "/*!50503 SET NAMES utf8mb4 */;\n";
$sql .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n";
$sql .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n";
$sql .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n";
$sql .= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n";

$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
$sql .= "SET UNIQUE_CHECKS = 0;\n";
$sql .= "SET AUTOCOMMIT = 0;\n";
$sql .= "START TRANSACTION;\n\n";

// ─── Get all tables ─────────────────────────────────────────────────────
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "Processing: $table\n";

    // Get CREATE TABLE
    $createStmt = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
    $createSQL = $createStmt[1];

    // Strip DEFINER from CREATE TABLE (unlikely but safe)
    $createSQL = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`\s*/i', '', $createSQL);
    $createSQL = preg_replace('/DEFINER\s*=\s*[^\s]+\s*/i', '', $createSQL);

    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $sql .= $createSQL . ";\n\n";

    // Get columns for building INSERTs
    $colStmt = $db->query("SHOW COLUMNS FROM `$table`");
    $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Get data
    $dataStmt = $db->query("SELECT * FROM `$table`");
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($rows)) {
        $sql .= "LOCK TABLES `$table` WRITE;\n";
        $sql .= "/*!40000 ALTER TABLE `$table` DISABLE KEYS */;\n";

        // Batch INSERTs (500 rows per statement for performance)
        $batch = [];
        $count = 0;
        foreach ($rows as $row) {
            $batch[] = buildInsert($table, $columns, $row, $db);
            $count++;
            if ($count % 500 === 0) {
                $sql .= implode("\n", $batch) . "\n";
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $sql .= implode("\n", $batch) . "\n";
        }

        $sql .= "/*!40000 ALTER TABLE `$table` ENABLE KEYS */;\n";
        $sql .= "UNLOCK TABLES;\n\n";
    }
}

// ─── Triggers ─────────────────────────────────────────────────────────────
echo "Processing: triggers\n";

$triggers = $db->query("SHOW TRIGGERS")->fetchAll(PDO::FETCH_ASSOC);
if (!empty($triggers)) {
    foreach ($triggers as $trigger) {
        // Use SHOW CREATE TRIGGER for the full definition
        $tName = $trigger['Trigger'];
        try {
            $defStmt = $db->query("SHOW CREATE TRIGGER `$tName`")->fetch(PDO::FETCH_NUM);
            $triggerSQL = $defStmt[2];

            // Strip DEFINER
            $triggerSQL = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`\s*/i', '', $triggerSQL);
            $triggerSQL = preg_replace('/DEFINER\s*=\s*[^\s]+\s*/i', '', $triggerSQL);

            // Wrap DELIMITER for phpMyAdmin compatibility
            $sql .= "DELIMITER ;;\n";
            $sql .= $triggerSQL . " ;;\n";
            $sql .= "DELIMITER ;\n\n";
        } catch (Exception $e) {
            echo "  Warning: Could not export trigger `$tName`: " . $e->getMessage() . "\n";
        }
    }
}

// ─── Stored routines ──────────────────────────────────────────────────────
echo "Processing: stored routines\n";

try {
    $routines = $db->query("SHOW FUNCTION STATUS WHERE Db = " . $db->quote($dbname))->fetchAll(PDO::FETCH_ASSOC);
    foreach ($routines as $routine) {
        $rName = $routine['Name'];
        $defStmt = $db->query("SHOW CREATE FUNCTION `$rName`")->fetch(PDO::FETCH_NUM);
        $routineSQL = $defStmt[2];
        $routineSQL = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`\s*/i', '', $routineSQL);
        $routineSQL = preg_replace('/DEFINER\s*=\s*[^\s]+\s*/i', '', $routineSQL);
        $sql .= "DELIMITER ;;\n";
        $sql .= $routineSQL . " ;;\n";
        $sql .= "DELIMITER ;\n\n";
    }
} catch (Exception $e) {
    echo "  Note: No functions or couldn't export: " . $e->getMessage() . "\n";
}

try {
    $routines = $db->query("SHOW PROCEDURE STATUS WHERE Db = " . $db->quote($dbname))->fetchAll(PDO::FETCH_ASSOC);
    foreach ($routines as $routine) {
        $rName = $routine['Name'];
        $defStmt = $db->query("SHOW CREATE PROCEDURE `$rName`")->fetch(PDO::FETCH_NUM);
        $routineSQL = $defStmt[2];
        $routineSQL = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`\s*/i', '', $routineSQL);
        $routineSQL = preg_replace('/DEFINER\s*=\s*[^\s]+\s*/i', '', $routineSQL);
        $sql .= "DELIMITER ;;\n";
        $sql .= $routineSQL . " ;;\n";
        $sql .= "DELIMITER ;\n\n";
    }
} catch (Exception $e) {
    echo "  Note: No procedures or couldn't export: " . $e->getMessage() . "\n";
}

// ─── Footer ───────────────────────────────────────────────────────────────
$sql .= "COMMIT;\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
$sql .= "SET UNIQUE_CHECKS = 1;\n";
$sql .= "SET AUTOCOMMIT = 1;\n\n";
$sql .= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n";
$sql .= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n";
$sql .= "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n";
$sql .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
$sql .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
$sql .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
$sql .= "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n";

// ─── Write output ─────────────────────────────────────────────────────────
file_put_contents($outputFile, $sql);

echo "\nDone! Clean export saved to: $outputFile\n";
echo "Size: " . number_format(filesize($outputFile)) . " bytes\n";
