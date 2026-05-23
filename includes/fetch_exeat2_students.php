<?php
require_once __DIR__ . '/../db_exeat2.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!$exeat2_db) {
    echo json_encode(['error' => $exeat2_error ?: 'School Portal database not reachable.']);
    exit;
}

$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$level_id      = isset($_GET['level_id']) ? intval($_GET['level_id']) : 0;
$course_study_id = isset($_GET['course_study_id']) ? intval($_GET['course_study_id']) : 0;

$conditions = [];
$params = [];

$conditions[] = "s.status = 1";
$conditions[] = "sa.status = 1";
$conditions[] = "sa.matric_no IS NOT NULL AND sa.matric_no != ''";
// Exclude non-standard levels (1000+) not present in the levels table
$conditions[] = "sa.level < 1000";

if ($department_id > 0) {
    $conditions[] = "sa.department_id = ?";
    $params[] = $department_id;
}
if ($level_id > 0) {
    // Convert level ID to actual level value (e.g. id=1 → level='100')
    $lv = $exeat2_db->prepare("SELECT level FROM levels WHERE id = ?");
    $lv->execute([$level_id]);
    $level_val = $lv->fetchColumn();
    if ($level_val !== false) {
        $conditions[] = "sa.level = ?";
        $params[] = $level_val;
    }
}
if ($course_study_id > 0) {
    $conditions[] = "sa.course_study_id = ?";
    $params[] = $course_study_id;
}

$where = implode(' AND ', $conditions);

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
FROM exeat2.students s
JOIN exeat2.student_academics sa ON sa.student_id = s.id
  AND sa.academic_session_id = (
    SELECT MAX(sa2.academic_session_id)
    FROM exeat2.student_academics sa2
    WHERE sa2.student_id = s.id
  )
WHERE $where
ORDER BY sa.matric_no ASC
LIMIT 500";

$stmt = $exeat2_db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Detect inline status for each student against local DB
$matric_nos = array_column($students, 'matric_no');
$local_map = [];

if (!empty($matric_nos)) {
    $placeholders = implode(',', array_fill(0, count($matric_nos), '?'));
    $local_stmt = $db->prepare("SELECT matric_no, id, source FROM students WHERE matric_no IN ($placeholders)");
    $local_stmt->execute($matric_nos);
    foreach ($local_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $local_map[$row['matric_no']] = $row;
    }
}

// Also check exam_students for this exam
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$assigned_ids = [];
if ($exam_id && !empty($matric_nos)) {
    $local_ids = array_column($local_map, 'id');
    if (!empty($local_ids)) {
        $id_placeholders = implode(',', array_fill(0, count($local_ids), '?'));
        $ass_stmt = $db->prepare("SELECT student_id FROM exam_students WHERE exam_id = ? AND student_id IN ($id_placeholders)");
        $ass_stmt->execute(array_merge([$exam_id], $local_ids));
        $assigned_ids = array_column($ass_stmt->fetchAll(PDO::FETCH_ASSOC), 'student_id');
    }
}

foreach ($students as &$stu) {
    $matric = $stu['matric_no'];
    if (isset($local_map[$matric])) {
        $local = $local_map[$matric];
        $stu['local_id'] = $local['id'];
        $stu['local_source'] = $local['source'];
        if (in_array($local['id'], $assigned_ids)) {
            $stu['status'] = 'assigned';
        } elseif ($local['source'] === 'exeat2') {
            $stu['status'] = 'exists_exeat2';
        } else {
            $stu['status'] = 'exists_' . $local['source'];
        }
    } else {
        $stu['local_id'] = null;
        $stu['local_source'] = null;
        $stu['status'] = 'new';
    }
}
unset($stu);

// Strip passport blob from JSON (too large)
$response = $students;
foreach ($response as &$r) {
    unset($r['passport']);
}
unset($r);

echo json_encode($response);
