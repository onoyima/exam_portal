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
$sql = "SELECT
  st.id            AS exeat2_staff_id,
  CONCAT(st.fname, ' ', COALESCE(st.mname,''), ' ', st.lname) AS name,
  st.email         AS email,
  COALESCE(st.email, CONCAT(LOWER(st.lname), '.', LOWER(SUBSTRING(st.fname,1,1)), '@veritas.edu.ng')) AS username,
  st.password      AS password,
  st.passport      AS passport
FROM exeat2.staff st
WHERE st.id IN ($placeholders) AND st.status = 1";

$stmt = $exeat2_db->prepare($sql);
$stmt->execute($staff_ids);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [
    'imported' => 0,
    'updated' => 0,
    'skipped_manual' => 0,
    'skipped_upload' => 0,
    'details' => [],
];

$passport_dir = __DIR__ . '/../uploads/passports/examiners';
if (!is_dir($passport_dir)) {
    mkdir($passport_dir, 0755, true);
}

foreach ($rows as $row) {
    $username = $row['username'];
    $existing = $db->prepare("SELECT id, source FROM examiners WHERE username = ?");
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

        if (!empty($row['passport'])) {
            file_put_contents("$passport_dir/{$local_id}.jpg", $row['passport']);
        }

        $results['imported']++;
        $results['details'][] = ['name' => $row['name'], 'username' => $username, 'status' => 'imported'];
    } elseif ($local['source'] === 'exeat2') {
        $db->prepare("
            UPDATE examiners SET
                name=?, password=?, email=?, external_id=?
            WHERE id=?
        ")->execute([
            $row['name'], $row['password'], $row['email'],
            $row['exeat2_staff_id'], $local['id']
        ]);

        if (!empty($row['passport'])) {
            file_put_contents("$passport_dir/{$local['id']}.jpg", $row['passport']);
        }

        $results['updated']++;
        $results['details'][] = ['name' => $row['name'], 'username' => $username, 'status' => 'updated'];
        $local_id = $local['id'];
    } else {
        $key = ($local['source'] === 'upload') ? 'skipped_upload' : 'skipped_manual';
        $results[$key]++;
        $results['details'][] = ['name' => $row['name'], 'username' => $username, 'status' => 'skipped_' . $local['source']];
    }
}

$results['total'] = $results['imported'] + $results['updated'] + $results['skipped_manual'] + $results['skipped_upload'];
echo json_encode($results);
