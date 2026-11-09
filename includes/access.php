<?php
/**
 * Who may manage which exam. Admins manage every exam; an examiner manages
 * only the exams an admin assigned to them (exam_examiners).
 */

function acc_is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function acc_examiner_id(PDO $db): int
{
    if (!empty($_SESSION['examiner_id'])) {
        return (int) $_SESSION['examiner_id'];
    }
    $st = $db->prepare("SELECT id FROM examiners WHERE username = ? AND deleted_at IS NULL");
    $st->execute([$_SESSION['user'] ?? '']);
    return (int) $st->fetchColumn();
}

/** Exam ids the current staff member may manage, or null for "all" (admin). */
function acc_exam_ids(PDO $db): ?array
{
    static $cache = null;
    if (acc_is_admin()) {
        return null;
    }
    if ($cache === null) {
        // an examiner made for one exam group never reaches exams outside it
        $st = $db->prepare("SELECT ee.exam_id FROM exam_examiners ee JOIN exams e ON e.id = ee.exam_id AND e.deleted_at IS NULL
            JOIN examiners x ON x.id = ee.examiner_id WHERE ee.examiner_id = ? AND (x.group_id IS NULL OR e.group_id = x.group_id)");
        $st->execute([acc_examiner_id($db)]);
        $cache = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    return $cache;
}

function acc_can_exam(PDO $db, int $exam_id): bool
{
    $ids = acc_exam_ids($db);
    return $ids === null || in_array($exam_id, $ids, true);
}

/** Stops the request when the exam is not one this user may manage. */
function acc_require_exam(PDO $db, int $exam_id): void
{
    if ($exam_id > 0 && acc_can_exam($db, $exam_id)) {
        return;
    }
    http_response_code(403);
    if (function_exists('is_ajax_request') && is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'This exam is not assigned to you.']);
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Not allowed</title><body style="font-family:system-ui;padding:40px;color:#1d2a24">'
            . '<h2>This exam is not assigned to you</h2><p>Ask an administrator to assign it to your account.</p><p><a href="'
            . (acc_is_admin() ? 'admin_dashboard' : 'exam_overview') . '">Back</a></p></body>';
    }
    exit;
}

/** SQL fragment limiting exams (alias e by default) to the user's scope. */
function acc_exam_sql(PDO $db, string $col = 'e.id'): string
{
    $ids = acc_exam_ids($db);
    if ($ids === null) {
        return '1=1';
    }
    return $ids ? $col . ' IN (' . implode(',', $ids) . ')' : '1=0';
}

/** Sidebar/back link appropriate for the current role. */
function acc_home(): string
{
    return acc_is_admin() ? 'admin_dashboard' : 'examiner_dashboard';
}

function acc_sidebar(): string
{
    return acc_is_admin() ? 'admin_sidebar.php' : 'examiner_sidebar.php';
}
