<?php
// Load config before the session so the mounted session path is used.
require_once __DIR__ . '/config.php';
session_start();
session_destroy();
header("Location: student_login");
exit; 
