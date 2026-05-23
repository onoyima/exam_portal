<?php
require_once __DIR__ . '/config.php';

$exeat2_db = null;
$required = ['EXEAT2_DB_HOST', 'EXEAT2_DB_NAME', 'EXEAT2_DB_USER'];
$all_defined = true;
foreach ($required as $c) {
    if (!defined($c)) { $all_defined = false; break; }
}

if ($all_defined) {
    try {
        $exeat2_db = new PDO(
            "mysql:host=" . EXEAT2_DB_HOST . ";port=" . EXEAT2_DB_PORT . ";dbname=" . EXEAT2_DB_NAME . ";charset=" . EXEAT2_DB_CHARSET,
            EXEAT2_DB_USER,
            defined('EXEAT2_DB_PASS') ? EXEAT2_DB_PASS : ''
        );
        $exeat2_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $exeat2_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("exeat2 connection failed: " . $e->getMessage());
    }
}
