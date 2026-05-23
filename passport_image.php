<?php
require 'db.php';
$type = $_GET['type'] ?? '';
$id   = intval($_GET['id']  ?? 0);
$allowed = ['student', 'examiner'];

if (!in_array($type, $allowed) || !$id) {
    serve_initial_avatar('?');
    exit;
}

$path = __DIR__ . "/uploads/passports/$type/$id.jpg";
if (file_exists($path)) {
    header('Content-Type: ' . mime_content_type($path));
    header('Cache-Control: public, max-age=86400');
    readfile($path);
    exit;
}

$name = '';
if ($type === 'student') {
    $stmt = $db->prepare("SELECT name FROM students WHERE id = ?");
} else {
    $stmt = $db->prepare("SELECT name FROM examiners WHERE id = ?");
}
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    serve_initial_avatar($row['name']);
} else {
    serve_initial_avatar('?');
}

function serve_initial_avatar(string $name): void {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        if (!empty($p)) { $initials .= strtoupper(mb_substr($p, 0, 1)); }
    }
    if ($initials === '') { $initials = '?'; }

    $hash = crc32($name);
    $hue = $hash % 360;
    $bg = "hsl($hue, 55%, 45%)";

    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=86400');
    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
  <rect width="200" height="200" rx="100" fill="$bg"/>
  <text x="100" y="100" text-anchor="middle" dominant-baseline="central"
        font-family="Arial, sans-serif" font-size="80" font-weight="bold"
        fill="white">$initials</text>
</svg>
SVG;
}
