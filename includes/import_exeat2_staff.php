<?php
require_once __DIR__ . '/../db_exeat2.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$staff_ids_json = $_POST['staff_ids'] ?? '[]';
$staff_ids = json_decode($staff_ids_json, true);

if (empty($staff_ids) || !$exeat2_db) {
    echo json_encode(['error' => 'Invalid request or exeat2 DB unreachable.']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($staff_ids), '?'));
try {
    $stmt = $exeat2_db->prepare("SELECT
      st.id            AS exeat2_staff_id,
      CONCAT(st.fname, ' ', COALESCE(st.mname,''), ' ', st.lname) AS name,
      st.email         AS email,
      COALESCE(st.email, CONCAT(LOWER(st.lname), '.', LOWER(SUBSTRING(st.fname,1,1)), '@veritas.edu.ng')) AS username,
      st.password      AS password,
      st.passport      AS passport
    FROM staff st
    WHERE st.id IN ($placeholders) AND st.status = 1");
    $stmt->execute($staff_ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['error' => 'exeat2 query failed: ' . $e->getMessage()]);
    exit;
}

$results = [
    'imported' => 0,
    'updated' => 0,
    'skipped_manual' => 0,
    'skipped_upload' => 0,
    'passport_found' => 0,
    'passport_saved' => 0,
    'details' => [],
];

$passport_dir = __DIR__ . '/../uploads/passports/examiners';
if (!is_dir($passport_dir)) {
    mkdir($passport_dir, 0755, true);
}

function save_passport_blob($data, $path) {
    $prefix = substr($data, 0, 4);
    $hex = bin2hex($prefix);
    if (preg_match('/^(ffd8|8950|47494638|52494646)/i', $hex)) {
        file_put_contents($path, $data);
        return ['format' => 'raw', 'hex_prefix' => $hex];
    }
    $b64 = $data;
    if (strncmp($b64, 'data:image/', 11) === 0) {
        $pos = strpos($b64, 'base64,');
        if ($pos !== false) $b64 = substr($b64, $pos + 7);
    }
    $decoded = base64_decode($b64, true);
    if ($decoded !== false) {
        $dhex = bin2hex(substr($decoded, 0, 4));
        if (preg_match('/^(ffd8|8950|47494638|52494646)/i', $dhex)) {
            $ext = strncmp($dhex, 'ffd8', 4) === 0 ? 'jpg' : 'png';
            $new_path = substr($path, 0, -4) . ".$ext";
            file_put_contents($new_path, $decoded);
            return ['format' => 'base64', 'hex_prefix' => substr($dhex, 0, 8)];
        }
    }
    file_put_contents($path, $data);
    return ['format' => 'unknown', 'hex_prefix' => $hex];
}

try {

foreach ($rows as $row) {
    $username = $row['username'];
    $existing = $db->prepare("SELECT id, source FROM examiners WHERE username = ? AND deleted_at IS NULL");
    $existing->execute([$username]);
    $local = $existing->fetch(PDO::FETCH_ASSOC);

    if (!$local) {
        $insert = $db->prepare("
            INSERT INTO examiners
                (name, username, password, role, source, external_id, email)
            VALUES (?, ?, ?, 'examiner', 'exeat2', ?, ?)
        ");
        $insert->execute([
            $row['name'], $row['username'], $row['password'],
            $row['exeat2_staff_id'], $row['email']
        ]);
        $local_id = $db->lastInsertId();

        $detail = ['name' => $row['name'], 'username' => $username, 'status' => 'imported'];
        if (!empty($row['passport'])) {
            $results['passport_found']++;
            $path = "$passport_dir/{$local_id}.jpg";
            $info = save_passport_blob($row['passport'], $path);
            if (file_exists($path)) {
                $results['passport_saved']++;
                $detail['file_exists'] = true;
            }
            $detail['passport_bytes'] = strlen($row['passport']);
            $detail['passport_format'] = $info['format'];
            $detail['passport_prefix'] = $info['hex_prefix'];
            $detail['passport_path'] = $path;
        } else {
            $detail['passport_bytes'] = 0;
        }
        $results['details'][] = $detail;
        $results['imported']++;
    } elseif ($local['source'] === 'exeat2') {
        $db->prepare("
            UPDATE examiners SET
                name=?, password=?, email=?, external_id=?, deleted_at=NULL
            WHERE id=?
        ")->execute([
            $row['name'], $row['password'], $row['email'],
            $row['exeat2_staff_id'], $local['id']
        ]);

        $detail = ['name' => $row['name'], 'username' => $username, 'status' => 'updated'];
        if (!empty($row['passport'])) {
            $results['passport_found']++;
            $path = "$passport_dir/{$local['id']}.jpg";
            $info = save_passport_blob($row['passport'], $path);
            if (file_exists($path)) {
                $results['passport_saved']++;
                $detail['file_exists'] = true;
            }
            $detail['passport_bytes'] = strlen($row['passport']);
            $detail['passport_format'] = $info['format'];
            $detail['passport_prefix'] = $info['hex_prefix'];
            $detail['passport_path'] = $path;
        } else {
            $detail['passport_bytes'] = 0;
        }
        $results['details'][] = $detail;
        $results['updated']++;
    } else {
        $key = ($local['source'] === 'upload') ? 'skipped_upload' : 'skipped_manual';
        $results[$key]++;
        $results['details'][] = ['name' => $row['name'], 'username' => $username, 'status' => 'skipped_' . $local['source']];
    }
}

$results['total'] = $results['imported'] + $results['updated'] + $results['skipped_manual'] + $results['skipped_upload'];
echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(['error' => 'Import error: ' . $e->getMessage()]);
}
