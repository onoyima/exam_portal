<?php
$file = 'C:/xampp/htdocs/CBT/admin_dashboard.php';
$content = file_get_contents($file);
$content = str_replace('AS groups)->fetch', 'AS `groups`)->fetch', $content);
file_put_contents($file, $content);
echo "Fixed\n";