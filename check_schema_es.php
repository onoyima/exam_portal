<?php
require 'db.php';
$stmt = $db->query("DESCRIBE exam_students");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
