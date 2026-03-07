<?php
require 'db.php';
$stmt = $db->query("SELECT id, name, duration_minutes, is_active FROM exams");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
