<?php
$pdo = new PDO('mysql:host=c67239.sgvps.net;port=3306;dbname=dbnhedghziuzs9;charset=utf8mb4', 'un1mtnf5uvkod', 'k1`~%,8w&8oz', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$r = $pdo->query('SHOW CREATE TABLE exam_students')->fetchAll(PDO::FETCH_ASSOC);
print_r($r);