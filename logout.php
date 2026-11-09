<?php
session_start();
if (!empty($_SESSION['group_examiner'])) {
    require __DIR__ . '/db.php';
    require_once __DIR__ . '/auth.php';
    release_examiner_session($db);
}
session_unset();
session_destroy();
header("Location: login");
exit;
