<?php
require_once __DIR__ . '/../db_exeat2.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$exam_id = intval($_POST['exam_id'] ?? 0);
$student_ids_json = $_POST['student_ids'] ?? '[]';
$student_ids = json_decode($student_ids_json, true);

if (empty($student_ids) || !$exeat2_db) {
    echo json_encode(['error' => 'Invalid request or exeat2 DB unreachable.']);
    exit;
}

// Fetch selected students from exeat2
$placeholders = implode(',', array_fill(0, count($student_ids), '?'));
$sql = "SELECT
  s.id                 AS exeat2_id,
  CONCAT(s.fname, ' ', COALESCE(s.mname,''), ' ', s.lname) AS name,
  s.fname              AS firstname,
  s.mname              AS middlename,
  s.lname              AS lastname,
  s.email,
  sa.matric_no,
  s.username,
  s.password,
  s.passport
FROM students s
JOIN student_academics sa ON sa.student_id = s.id
  AND sa.academic_session_id = (
    SELECT MAX(sa2.academic_session_id)
    FROM student_academics sa2
    WHERE sa2.student_id = s.id
  )
WHERE s.id IN ($placeholders) AND s.status = 1 AND sa.status = 1";

$stmt = $exeat2_db->prepare($sql);
$stmt->execute($student_ids);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [
    'imported' => 0,
    'updated' => 0,
    'skipped_upload' => 0,
    'skipped_manual' => 0,
    'skipped_assigned' => 0,
    'passport_found' => 0,
    'passport_saved' => 0,
    'details' => [],
];

$passport_dir_students = __DIR__ . '/../uploads/passports/students';
if (!is_dir($passport_dir_students)) {
    mkdir($passport_dir_students, 0755, true);
}

foreach ($rows as $row) {
    $matric_no = $row['matric_no'];
    $existing = $db->prepare("SELECT id, source FROM students WHERE matric_no = ?");
    $existing->execute([$matric_no]);
    $local = $existing->fetch(PDO::FETCH_ASSOC);

    if (!$local) {
        $insert = $db->prepare("
            INSERT INTO students
                (name, firstname, middlename, lastname, email,
                 matric_no, username, password, source, external_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'exeat2', ?)
        ");
        $insert->execute([
            $row['name'], $row['firstname'], $row['middlename'],
            $row['lastname'], $row['email'], $row['matric_no'],
            $row['username'], $row['password'], $row['exeat2_id']
        ]);
        $local_id = $db->lastInsertId();

        if (!empty($row['passport'])) {
            $results['passport_found']++;
            $written = file_put_contents("$passport_dir_students/{$local_id}.jpg", $row['passport']);
            if ($written !== false) $results['passport_saved']++;
        }

        $passport_path = "$passport_dir_students/{$local_id}.jpg";
        $results['imported']++;
        $results['details'][] = ['matric_no' => $matric_no, 'status' => 'imported', 'name' => $row['name'], 'passport_bytes' => strlen($row['passport'] ?? ''), 'passport_path' => $passport_path, 'file_exists' => file_exists($passport_path)];
    } elseif ($local['source'] === 'exeat2') {
        $db->prepare("
            UPDATE students SET
                name=?, firstname=?, middlename=?, lastname=?,
                email=?, username=?, password=?, external_id=?, deleted_at=NULL
            WHERE id=?
        ")->execute([
            $row['name'], $row['firstname'], $row['middlename'],
            $row['lastname'], $row['email'], $row['username'],
            $row['password'], $row['exeat2_id'], $local['id']
        ]);

        $passport_path = "$passport_dir_students/{$local['id']}.jpg";
        if (!empty($row['passport'])) {
            $results['passport_found']++;
            $written = file_put_contents($passport_path, $row['passport']);
            if ($written !== false) $results['passport_saved']++;
        }
        $results['details'][] = ['matric_no' => $matric_no, 'status' => 'updated', 'name' => $row['name'], 'passport_bytes' => strlen($row['passport'] ?? ''), 'passport_path' => $passport_path, 'file_exists' => file_exists($passport_path)];

        $results['updated']++;
        $results['details'][] = ['matric_no' => $matric_no, 'status' => 'updated', 'name' => $row['name'], 'passport_bytes' => strlen($row['passport'] ?? '')];
        $local_id = $local['id'];
    } else {
        $local_id = $local['id'];
        $key = ($local['source'] === 'upload') ? 'skipped_upload' : 'skipped_manual';
        $results[$key]++;
        $results['details'][] = ['matric_no' => $matric_no, 'status' => 'skipped_' . $local['source'], 'name' => $row['name']];
    }

    // Link to exam (optional — skip if no exam_id given)
    if ($exam_id) {
        $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) VALUES (?, ?)")
           ->execute([$exam_id, $local_id]);
    }
}

$results['total'] = $results['imported'] + $results['updated'] + $results['skipped_upload'] + $results['skipped_manual'];
echo json_encode($results);
