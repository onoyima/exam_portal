<?php
$file = 'C:/xampp/htdocs/CBT/admin_dashboard.php';
$content = file_get_contents($file);
if ($content === false) {
    die("Cannot read file\n");
}
echo "File size: " . strlen($content) . "\n";

$pos = strpos($content, 'AS groups');
if ($pos === false) {
    die("Pattern 'AS groups' not found\n");
}
echo "Found at position $pos\n";
echo "Context: " . substr($content, $pos - 30, 60) . "\n";

$search = "AS groups')->fetch";
$replace = "AS `groups`)->fetch";
$newContent = str_replace($search, $replace, $content);

if ($newContent !== $content) {
    file_put_contents($file, $newContent);
    echo "Replacement done\n";
} else {
    echo "Search pattern not found exactly. Trying alternatives...\n";
    $alt = 'AS groups")->fetch';
    $newContent = str_replace($alt, $replace, $content);
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Replaced with alternative\n";
    } else {
        echo "No match found\n";
    }
}