<?php
/**
 * For AJAX helpers under includes/: only signed-in staff may call them, and an
 * examiner only for exams assigned to them.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/access.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'examiner'], true)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Please sign in again.']);
    exit;
}
$__guard_exam = (int) ($_POST['exam_id'] ?? $_GET['exam_id'] ?? 0);
if ($__guard_exam > 0) {
    acc_require_exam($db, $__guard_exam);
} elseif (!acc_is_admin() && basename($_SERVER['SCRIPT_NAME']) === 'import_exeat2_student.php') {
    acc_require_exam($db, 0);
}
