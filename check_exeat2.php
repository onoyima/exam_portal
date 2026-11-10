<?php
$pdo = new PDO('mysql:host=c67239.sgvps.net;port=3306;dbname=dbipfkhop9oqna;charset=utf8mb4', 'uwee0g3bir9pr', '4&e21%61sh11', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
echo "Tables:\n";
$r = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
print_r($r);

echo "\n--- student_academics columns ---\n";
$r = $pdo->query('DESCRIBE student_academics')->fetchAll(PDO::FETCH_ASSOC);
print_r($r);

echo "\n--- students columns ---\n";
$r = $pdo->query('DESCRIBE students')->fetchAll(PDO::FETCH_ASSOC);
print_r($r);

echo "\n--- departments/levels/programs tables ---\n";
foreach (['departments', 'levels', 'programmes', 'courses', 'faculties', 'academic_sessions', 'vu_sessions', 'vu_semesters'] as $t) {
    try {
        $r = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        echo "\n--- $t ---\n";
        print_r($r);
    } catch (Exception $e) {}
}

echo "\n--- Sample academic data ---\n";
$r = $pdo->query("SELECT sa.*, s.fname, s.lname FROM student_academics sa JOIN students s ON s.id=sa.student_id LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($r);