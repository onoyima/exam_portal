<?php
/**
 * database/clean_sql.php
 * Cleans the SQL dump to make it extremely portable and error-free on any MySQL/MariaDB server.
 */

$inputFile = __DIR__ . '/database_export.tmp.sql';
$outputFile = __DIR__ . '/database_export.sql';
$portableFile = __DIR__ . '/exam_portal_portable.sql';

if (!file_exists($inputFile)) {
    die("Error: Temporary SQL dump file not found at " . $inputFile . "\n");
}

echo "Reading temporary SQL dump file...\n";
$sql = file_get_contents($inputFile);

echo "Stripping DEFINER clauses to ensure user privilege compatibility...\n";
// Strip definer comments
$sql = preg_replace('/\/\*!50017\s+DEFINER=`[^`]+`@`[^`]+`\s*\*\//i', '', $sql);
$sql = preg_replace('/\/\*!50017\s+DEFINER=[^\*]+\s*\*\//i', '', $sql);
// Strip raw definer definitions
$sql = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`/i', '', $sql);
$sql = preg_replace('/DEFINER\s*=\s*\'[^\']+\'@\'[^\']+\'/i', '', $sql);
$sql = preg_replace('/DEFINER\s*=\s*[^\s]+/i', '', $sql);

echo "Removing system-specific CREATE DATABASE and USE statements...\n";
$sql = preg_replace('/CREATE\s+DATABASE\s+[^;]+;/i', '', $sql);
$sql = preg_replace('/USE\s+`[^`]+`;/i', '', $sql);
$sql = preg_replace('/USE\s+[^\s;]+;/i', '', $sql);

echo "Applying foreign key check disables and transaction wrappers...\n";
$header = "-- ==========================================================================\n"
        . "-- Exam Portal Portable Database Export\n"
        . "-- Optimized for zero-error import on any MariaDB or MySQL database engine.\n"
        . "-- Generated: " . date('Y-m-d H:i:s') . "\n"
        . "-- ==========================================================================\n\n"
        . "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n"
        . "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n"
        . "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n"
        . "/*!50503 SET NAMES utf8mb4 */;\n"
        . "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n"
        . "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n"
        . "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n"
        . "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n"
        . "SET FOREIGN_KEY_CHECKS = 0;\n"
        . "SET UNIQUE_CHECKS = 0;\n"
        . "SET AUTOCOMMIT = 0;\n"
        . "START TRANSACTION;\n\n";

$footer = "\n\nCOMMIT;\n"
        . "SET FOREIGN_KEY_CHECKS = 1;\n"
        . "SET UNIQUE_CHECKS = 1;\n"
        . "SET AUTOCOMMIT = 1;\n\n"
        . "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n"
        . "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n"
        . "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n"
        . "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n"
        . "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n"
        . "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n"
        . "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n";

$finalSql = $header . trim($sql) . $footer;

echo "Saving cleaned SQL file to database/database_export.sql...\n";
file_put_contents($outputFile, $finalSql);

echo "Saving cleaned SQL file to database/exam_portal_portable.sql...\n";
file_put_contents($portableFile, $finalSql);

echo "Cleaning up temporary files...\n";
if (file_exists($inputFile)) {
    unlink($inputFile);
}

echo "Successfully exported portable database!\n";
