<?php
$db = new PDO('mysql:host=localhost', 'root', '');
$stmt = $db->query('SHOW DATABASES');
while ($row = $stmt->fetch()) {
    echo $row[0] . "\n";
}
?>
