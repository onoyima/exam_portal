<?php
require_once __DIR__ . '/../db_exeat2.php';
require_once __DIR__ . '/staff_guard.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$exam_id = intval($_POST['exam_id'] ?? 0);

// Original student IDs method
$student_ids_json = $_POST['student_ids'] ?? '[]';
$student_ids = json_decode($student_ids_json, true);

// NEW: Filter parameters for bulk import without explicit student IDs
$level_filter = isset($_POST['level']) ? trim($_POST['level']) : '';           // e.g., "100", "400" -> converted to 1000, 4000
$dept_filter = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0;
$faculty_filter = isset($_POST['faculty_id']) ? intval($_POST['faculty_id']) : 0;
$course_study_filter = isset($_POST['course_study_id']) ? intval($_POST['course_study_id']) : 0;
$level_str = isset($_POST['level_str']) ? trim($_POST['level_str']) : '';         // "100", "200", etc. -> 1000, 2000

$use_filters = (!empty($level_filter) || !empty($level_str) || $dept_filter > 0 || $faculty_filter > 0 || $course_study_filter > 0);

if ((empty($student_ids) && !$use_filters) || !$exeat2_db) {
    echo json_encode(['error' => 'Invalid request or exeat2 DB unreachable.']);
    exit;
}

// Build the exeat2 query
$base_query = "
    SELECT
      s.id                 AS exeat2_id,
      CONCAT(s.fname, ' ', COALESCE(s.mname,''), ' ', s.lname) AS name,
      s.fname              AS firstname,
      s.mname              AS middlename,
      s.lname              AS lastname,
      s.email,
      sa.matric_no,
      s.username,
      s.password,
      s.passport,
      sa.level,
      sa.department_id,
      sa.faculty_id,
      sa.course_study_id,
      d.name AS department_name,
      l.level AS level_name
    FROM students s
    JOIN student_academics sa ON sa.student_id = s.id
      AND sa.academic_session_id = (
        SELECT MAX(sa2.academic_session_id)
        FROM student_academics sa2
        WHERE sa2.student_id = s.id
      )
    LEFT JOIN departments d ON d.id = sa.department_id
    LEFT JOIN levels l ON l.id = sa.level
    WHERE s.status = 1 AND sa.status = 1
";

$where_clauses = [];
$params = [];

if (!empty($student_ids)) {
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $where_clauses[] = "s.id IN ($placeholders)";
    $params = array_merge($params, $student_ids);
} elseif ($use_filters) {
    $where_clauses[] = "1=1";
}

if ($dept_filter > 0) {
    $where_clauses[] = "sa.department_id = ?";
    $params[] = $dept_filter;
}
if ($faculty_filter > 0) {
    $where_clauses[] = "sa.faculty_id = ?";
    $params[] = $faculty_filter;
}
if ($course_study_filter > 0) {
    $where_clauses[] = "sa.course_study_id = ?";
    $params[] = $course_study_filter;
}
if ($level_str !== '') {
    $level_num = intval($level_str);
    if ($level_num > 0) {
        $level_num = $level_num * 10;
        $where_clauses[] = "sa.level = ?";
        $params[] = $level_num;
    }
} elseif ($level_filter !== '') {
    $where_clauses[] = "sa.level = ?";
    $params[] = intval($level_filter);
}

$where_sql = implode(' AND ', $where_clauses);
if (!empty($where_clauses)) {
    $sql = $base_query . ' WHERE ' . $where_sql . ' ORDER BY sa.matric_no';
} else {
    $sql = $base_query . ' ORDER BY sa.matric_no';
}

try {
    $stmt = $exeat2_db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['error' => 'exeat2 query failed: ' . $e->getMessage()]);
    exit;
}

if (empty($rows)) {
    echo json_encode(['error' => 'No students found matching the criteria.']);
    exit;
}

// Import logic (same as before)
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

function save_passport_blob($data, $path) {
    $prefix = substr($data, 0, 4);
    $hex = bin2hex($prefix);
    if (preg_match('/^(ffd8|8950|47494638|52494646)/i', $hex)) {
        $ext = strncmp($hex, 'ffd8', 4) === 0 ? 'jpg' : (strncmp($hex, '8950', 4) === 0 ? 'png' : 'gif');
        $new_path = substr($path, 0, -4) . ".$ext";
        file_put_contents($new_path, $data);
        return ['format' => 'raw', 'saved_path' => $new_path];
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
            return ['format' => 'base64', 'saved_path' => $new_path];
        }
    }
    $new_path = $path;
    file_put_contents($new_path, $data);
    return ['format' => 'unknown', 'saved_path' => $new_path];
}

try {

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

        $detail = ['matric_no' => $matric_no, 'status' => 'imported', 'name' => $row['name']];
        if (!empty($row['passport'])) {
            $results['passport_found']++;
            $path = "$passport_dir_students/{$local_id}.jpg";
            $info = save_passport_blob($row['passport'], $path);
            if (file_exists($info['saved_path'])) {
                $results['passport_saved']++;
                $detail['file_exists'] = true;
            }
            $detail['passport_bytes'] = strlen($row['passport']);
            $detail['passport_format'] = $info['format'];
            $detail['passport_path'] = $info['saved_path'];
        } else {
            $detail['passport_bytes'] = 0;
        }
        $results['details'][] = $detail;
        $results['imported']++;
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

        $detail = ['matric_no' => $matric_no, 'status' => 'updated', 'name' => $row['name']];
        if (!empty($row['passport'])) {
            $results['passport_found']++;
            $path = "$passport_dir_students/{$local['id']}.jpg";
            $info = save_passport_blob($row['passport'], $path);
            if (file_exists($info['saved_path'])) {
                $results['passport_saved']++;
                $detail['file_exists'] = true;
            }
            $detail['passport_bytes'] = strlen($row['passport']);
            $detail['passport_format'] = $info['format'];
            $detail['passport_path'] = $info['saved_path'];
        } else {
            $detail['passport_bytes'] = 0;
        }
        $results['details'][] = $detail;
        $results['updated']++;
        $local_id = $local['id'];
    } else {
        $local_id = $local['id'];
        // Still save passport even if skipping data update
        $detail = ['matric_no' => $matric_no, 'status' => 'skipped_' . $local['source'], 'name' => $row['name']];
        if (!empty($row['passport'])) {
            $results['passport_found']++;
            $path = "$passport_dir_students/{$local_id}.jpg";
            $info = save_passport_blob($row['passport'], $path);
            if (file_exists($info['saved_path'])) {
                $results['passport_saved']++;
                $detail['file_exists'] = true;
            }
            $detail['passport_bytes'] = strlen($row['passport']);
            $detail['passport_format'] = $info['format'];
            $detail['passport_path'] = $info['saved_path'];
        } else {
            $detail['passport_bytes'] = 0;
        }
        $key = ($local['source'] === 'upload') ? 'skipped_upload' : 'skipped_manual';
        $results[$key]++;
        $results['details'][] = $detail;
    }

    if ($exam_id) {
        $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) VALUES (?, ?)")
           ->execute([$exam_id, $local_id]);
    }
}

$results['total'] = $results['imported'] + $results['updated'] + $results['skipped_upload'] + $results['skipped_manual'];
echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(['error' => 'Import error: ' . $e->getMessage()]);
}