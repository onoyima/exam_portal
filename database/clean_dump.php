<?php
/**
 * database/clean_dump.php — Clean an existing mysqldump for portability
 *
 * Takes a raw mysqldump output and strips DEFINER, CREATE DATABASE, USE statements.
 * Run: php database/clean_dump.php [input.sql] [output.sql]
 */

$inputFile  = $argv[1] ?? __DIR__ . '/../backup_db_pre_implementation.sql';
$outputFile = $argv[2] ?? __DIR__ . '/exam_portal_portable.sql';

if (!file_exists($inputFile)) {
    die("Error: Input file not found: $inputFile\n");
}

echo "Reading: $inputFile\n";
$sql = file_get_contents($inputFile);

echo "Stripping DEFINER clauses...\n";
$sql = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`/i', '', $sql);
$sql = preg_replace('/DEFINER\s*=\s*\S+/i', '', $sql);
$sql = preg_replace('/\/\*!50017\s+DEFINER=`[^`]+`@`[^`]+`\s*\*\//i', '', $sql);

echo "Removing CREATE DATABASE and USE statements...\n";
$sql = preg_replace('/CREATE\s+DATABASE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?[^`;\s]+`?\s*[^;]*;/i', '', $sql);
$sql = preg_replace('/USE\s+`[^`]+`;/i', '', $sql);
// Also remove the comment lines referencing the database
$sql = preg_replace('/-- Host:.*Database:.*/i', '-- Portable export', $sql);

echo "Adding safe header/footer...\n";
$header = "-- ============================================================\n"
        . "-- Cleaned Portable Export\n"
        . "-- Source: " . basename($inputFile) . "\n"
        . "-- Cleaned: " . date('Y-m-d H:i:s') . "\n"
        . "-- ============================================================\n\n"
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

$footer = "\nCOMMIT;\n"
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

// Clean up extra whitespace
$sql = preg_replace('/\n{3,}/', "\n\n", trim($sql));

$final = $header . $sql . $footer;

echo "Writing: $outputFile\n";
file_put_contents($outputFile, $final);

echo "Done! Size: " . number_format(strlen($final)) . " bytes\n";
echo "Lines: " . substr_count($final, "\n") . "\n";
