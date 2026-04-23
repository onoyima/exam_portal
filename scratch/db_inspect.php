<?php
require 'db.php';
echo "Database: " . $dbname . "\n";
$stmt = $db->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $table = $row[0];
    echo "Table: $table\n";
    $columns = $db->query("DESCRIBE $table");
    while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}
?>
