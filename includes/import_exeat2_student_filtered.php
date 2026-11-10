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
$student_ids_json = $_POST['student_ids'] ?? '[]';
$student_ids = json_decode($student_ids_json, true);

// New: filter parameters
$level_filter = isset($_POST['level']) ? trim($_POST['level']) : '';           // e.g., "100", "400"
$dept_filter = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0; // department ID
$faculty_filter = isset($_POST['faculty_id']) ? intval($_POST['faculty_id']) : 0;     // faculty ID
$course_study_filter = isset($_POST['course_study_id']) ? intval($_POST['course_study_id']) : 0;
$level_str = isset($_POST['level_str']) ? trim($_POST['level_str']) : '';         // "100", "200", etc.

if (empty($student_ids) && empty($level_filter) && empty($dept_filter) && empty($faculty_filter) && empty($course_study_filter) || !$exeat2_db) {
    echo json_encode(['error' => 'Invalid request or exeat2 DB unreachable.']);
    exit;
}

// Build the exeat2 query with optional filters
$where_clauses = ["s.id IN ($placeholders)"];
$params = $student_ids;

if (!empty($student_ids)) {
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
} else {
    // When filtering without explicit student IDs, we need a different approach
    $placeholders = '1=1'; // Will be replaced by filters
}

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

if (!empty($student_ids)) {
    $where_clauses[] = "s.id IN ($placeholders)";
} else {
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
    // Convert "100" -> 1000, "200" -> 2000, etc.
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
$sql = $base_query . ' WHERE ' . $where_sql . ' ORDER BY sa.matric_no';

try {
    $stmt = $exeat2_db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['error' => 'exeat2 query failed: ' . $e->getMessage()]);
    exit;
}

// ... rest of the import logic (same as before) ...

// [The rest of the import logic remains the same - omitted for brevity]
// You would copy the rest from the original file starting from line 50
// and replace the student_ids_json handling with the new filter logic

echo json_encode(['message' => 'Template ready - replace the rest of the import logic', 'rows_found' => count($rows)]);