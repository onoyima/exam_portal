<?php
require 'db.php';
$stmt = $db->query("DESCRIBE exams");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
