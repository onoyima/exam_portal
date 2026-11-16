<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
require_once 'includes/db_export.php';
require_once 'audit_system.php';

$name = $_SESSION['user'] ?? 'Admin';
$admin_id = isset($_SESSION['examiner_id']) ? (int) $_SESSION['examiner_id'] : null;
$dbname = (string) $db->query('SELECT DATABASE()')->fetchColumn();

$audit = null;
try {
    $audit = new AuditSystem($db);
} catch (Throwable $e) {
    error_log('Backup page audit init failed: ' . $e->getMessage());
}

/** Best-effort audit entry. An export must not fail because logging failed. */
function backup_log(PDO $db, ?AuditSystem $audit, ?int $uid, string $user, string $action, ?int $record_id, array $new): void
{
    try {
        if ($audit) {
            $audit->logAction($uid, 'admin', $user, $action, 'database', $record_id, null, $new);
        } else {
            $db->prepare("INSERT INTO audit_trail
                (user_id, user_type, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, 'admin', ?, ?, 'database', ?, NULL, ?, ?, ?)")
                ->execute([
                    $uid, $user, $action, $record_id, json_encode($new),
                    $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
        }
    } catch (Throwable $e) {
        error_log('Backup audit write failed: ' . $e->getMessage());
    }
}

function backup_log_export(?AuditSystem $audit, ?int $uid, string $user, string $type, string $file, int $rows): void
{
    if (!$audit) {
        return;
    }
    try {
        $audit->logExport($uid, $user, $type, $file, $rows);
    } catch (Throwable $e) {
        error_log('Export history write failed: ' . $e->getMessage());
    }
}

function backup_stream_headers(string $filename): void
{
    // The export can run for a while; do not hold the session lock meanwhile.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    @set_time_limit(0);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Accel-Buffering: no');
}

function backup_emit(string $chunk): void
{
    echo $chunk;
    if (function_exists('flush')) {
        flush();
    }
}

/* ─────────────────────────────────────────────────────────────────────
 *  Stream an export
 * ───────────────────────────────────────────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'export') {
    validate_csrf();

    $type = (string) ($_POST['export_type'] ?? '');
    $exam_id = (int) ($_POST['exam_id'] ?? 0);
    $stamp = date('Ymd_His');

    try {
        if ($type === 'exam') {
            if ($exam_id <= 0) {
                throw new RuntimeException('Choose an examination to back up.');
            }
            $st = $db->prepare("SELECT * FROM exams WHERE id = ? AND deleted_at IS NULL");
            $st->execute([$exam_id]);
            $exam = $st->fetch(PDO::FETCH_ASSOC);
            if (!$exam) {
                throw new RuntimeException('That examination no longer exists.');
            }

            // Tables that carry exam_id and therefore belong to this exam.
            $candidates = ['objective_questions', 'questions', 'exam_questions', 'exam_students',
                'exam_examiners', 'objective_attempts', 'objective_answers', 'exam_violations', 'answers'];
            $exam_tables = [];
            foreach ($candidates as $table) {
                if (dbx_table_exists($db, $table)) {
                    $cols = dbx_columns($db, $table);
                    if (in_array('exam_id', $cols, true)) {
                        $exam_tables[] = $table;
                    }
                }
            }

            // Reference rows (student records and the exam group/session) so the
            // results in this file still point at something after a restore.
            $student_parts = [];
            $student_args = [];
            foreach ($exam_tables as $table) {
                if (in_array('student_id', dbx_columns($db, $table), true)) {
                    $student_parts[] = "SELECT student_id FROM `$table` WHERE exam_id = ?";
                    $student_args[] = $exam_id;
                }
            }
            $student_where = $student_parts ? 'id IN (' . implode(' UNION ', $student_parts) . ')' : null;

            $row_count = 1; // the exam row itself
            foreach ($exam_tables as $table) {
                $row_count += dbx_count($db, $table, 'exam_id = ?', [$exam_id]);
            }
            if ($student_where !== null) {
                $row_count += dbx_count($db, 'students', $student_where, $student_args);
            }

            $filename = 'exam_' . $exam_id . '_' . dbx_slug((string) ($exam['name'] ?? 'exam')) . '_' . $stamp . '.sql';

            backup_log($db, $audit, $admin_id, $name, 'backup_exam_export', $exam_id, [
                'type' => 'exam', 'file' => $filename, 'rows' => $row_count,
            ]);
            backup_log_export($audit, $admin_id, $name, 'exam', $filename, $row_count);

            backup_stream_headers($filename);
            backup_emit(dbx_sql_header(
                'Examination backup: ' . ($exam['name'] ?? ('Exam #' . $exam_id)),
                $dbname,
                "Restoring this file DELETEs the current copy of exam #$exam_id and then\n"
                . "re-inserts the exam, its questions, students, attempts, answers and violations."
            ));

            // Delete children before parents so foreign keys stay satisfied.
            backup_emit("-- Remove the existing copy of this exam first.\n");
            foreach (dbx_exam_delete_order($exam_tables) as $table) {
                backup_emit("DELETE FROM `$table` WHERE `exam_id` = $exam_id;\n");
            }
            backup_emit("DELETE FROM `exams` WHERE `id` = $exam_id;\n\n");

            // Parent rows that the exam refers to.
            if (!empty($exam['group_id']) && dbx_table_exists($db, 'exam_groups')) {
                backup_emit("-- Exam group\n");
                dbx_emit_table_data($db, 'exam_groups', 'id = ?', [(int) $exam['group_id']], 'backup_emit', 250, 'INSERT IGNORE INTO');
                backup_emit("\n");
            }
            if (!empty($exam['session_id']) && dbx_table_exists($db, 'academic_sessions')) {
                backup_emit("-- Academic session\n");
                dbx_emit_table_data($db, 'academic_sessions', 'id = ?', [(int) $exam['session_id']], 'backup_emit', 250, 'INSERT IGNORE INTO');
                backup_emit("\n");
            }

            if ($student_where !== null) {
                backup_emit("-- Students referenced by this exam\n");
                dbx_emit_table_data($db, 'students', $student_where, $student_args, 'backup_emit', 250, 'INSERT IGNORE INTO');
                backup_emit("\n");
            }

            backup_emit("-- Exam row\n");
            dbx_emit_table_data($db, 'exams', 'id = ?', [$exam_id], 'backup_emit');
            backup_emit("\n");

            foreach ($exam_tables as $table) {
                backup_emit("-- Table `$table`\n");
                dbx_emit_table_data($db, $table, 'exam_id = ?', [$exam_id], 'backup_emit');
                backup_emit("\n");
            }

            backup_emit(dbx_sql_footer());
            exit;
        }

        if ($type === 'data' || $type === 'full') {
            $tables = dbx_table_names($db);
            if (!$tables) {
                throw new RuntimeException('No tables were found in the database.');
            }

            $row_count = 0;
            foreach ($tables as $table) {
                $row_count += dbx_count($db, $table);
            }

            $filename = ($type === 'full' ? 'veritas_full_' : 'veritas_data_') . $stamp . '.sql';
            backup_log($db, $audit, $admin_id, $name, 'backup_' . $type . '_export', null, [
                'type' => $type, 'file' => $filename, 'tables' => count($tables), 'rows' => $row_count,
            ]);
            backup_log_export($audit, $admin_id, $name, $type, $filename, $row_count);

            backup_stream_headers($filename);
            backup_emit(dbx_sql_header(
                $type === 'full' ? 'Full database export (schema + data)' : 'Data-only export (all tables)',
                $dbname,
                $type === 'full'
                    ? "This file drops and recreates every table before loading the data."
                    : "This file contains data only. Create the schema first, then import it."
            ));

            foreach ($tables as $table) {
                backup_emit("--\n-- Table `$table`\n--\n");
                if ($type === 'full') {
                    backup_emit("DROP TABLE IF EXISTS `$table`;\n");
                    backup_emit(dbx_create_sql($db, $table) . ";\n\n");
                }
                dbx_emit_table_data($db, $table, null, [], 'backup_emit');
                backup_emit("\n");
            }

            if ($type === 'full') {
                backup_emit("--\n-- Triggers (audit trail)\n--\n");
                dbx_emit_triggers($db, 'backup_emit');
                backup_emit("\n");
            }

            backup_emit(dbx_sql_footer());
            exit;
        }

        throw new RuntimeException('Unknown export type.');
    } catch (Throwable $e) {
        error_log('Backup export failed: ' . $e->getMessage());
        if (!headers_sent()) {
            $_SESSION['backup_error'] = $e->getMessage();
            header('Location: admin_backup');
        } else {
            // The download already started; make the truncation explicit.
            echo "\n-- EXPORT ABORTED: " . str_replace(["\r", "\n"], ' ', $e->getMessage()) . "\n";
        }
        exit;
    }
}

/* ─────────────────────────────────────────────────────────────────────
 *  Page data
 * ───────────────────────────────────────────────────────────────────── */
$notice = $_SESSION['backup_notice'] ?? '';
$error = $_SESSION['backup_error'] ?? '';
unset($_SESSION['backup_notice'], $_SESSION['backup_error']);

$exams = [];
try {
    $exams = $db->query("
        SELECT e.id, e.name, e.type, e.start_time, e.end_time, e.is_active, e.total_mark,
               (SELECT COUNT(*) FROM objective_questions q
                 WHERE q.exam_id = e.id AND q.deleted_at IS NULL) AS question_count,
               (SELECT COUNT(*) FROM objective_attempts a WHERE a.exam_id = e.id) AS attempt_count
        FROM exams e
        WHERE e.deleted_at IS NULL
        ORDER BY e.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: 'Could not load the examination list.';
}

$db_size_mb = 0.0;
$table_count = 0;
$approx_rows = 0;
try {
    $table_count = count(dbx_table_names($db));
    $stat = $db->query("SELECT
            COALESCE(SUM(data_length + index_length), 0) AS bytes,
            COALESCE(SUM(table_rows), 0) AS rows_approx
        FROM information_schema.tables
        WHERE table_schema = DATABASE()")->fetch(PDO::FETCH_ASSOC);
    $db_size_mb = round(((float) ($stat['bytes'] ?? 0)) / 1048576, 2);
    $approx_rows = (int) ($stat['rows_approx'] ?? 0);
} catch (Throwable $e) {
    // non-fatal
}

$recent_exports = [];
try {
    $recent_exports = $db->query("SELECT username, export_type, file_name, record_count, ip_address, created_at
        FROM export_history ORDER BY created_at DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // export_history is created by AuditSystem; ignore when unavailable
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Backup &amp; Export - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <h1 class="admin-navbar-title">Backup &amp; Export</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= htmlspecialchars(strtoupper(substr($name, 0, 1))) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Backup &amp; Export</li>
        </ol>
      </nav>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Backup &amp; Export</h2>
        <div class="admin-page-actions">
          <a class="admin-btn admin-btn-outline" href="admin_backup" title="Refresh"><i class="bi bi-arrow-clockwise"></i></a>
        </div>
      </div>

      <?php display_auth_errors(); ?>
      <?php if ($notice): ?>
        <div class="admin-alert admin-alert-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($notice) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="admin-alert admin-alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= number_format($table_count) ?></div>
            <div class="admin-stat-label">Tables</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= number_format($approx_rows) ?></div>
            <div class="admin-stat-label">Rows (approx.)</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= number_format($db_size_mb, 2) ?> MB</div>
            <div class="admin-stat-label">Database size</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= number_format(count($exams)) ?></div>
            <div class="admin-stat-label">Examinations</div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-5">
          <div class="admin-card h-100">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-journal-check me-2"></i>Back up one examination</h3>
            </div>
            <div class="admin-card-body">
              <p class="small text-muted">Download one exam with its questions, enrolled students, attempts, answers and violations. The file is self-contained and restores that exam.</p>
              <form method="post" onsubmit="return confirm('Download a backup of the selected examination?');">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="export_type" value="exam">
                <div class="admin-form-group">
                  <label class="admin-form-label" for="exam_id">Examination</label>
                  <select name="exam_id" id="exam_id" class="admin-input admin-select" required>
                    <option value="">Select an examination…</option>
                    <?php foreach ($exams as $ex): ?>
                      <option value="<?= (int) $ex['id'] ?>">
                        <?= htmlspecialchars((string) $ex['name']) ?>
                        — <?= htmlspecialchars((string) $ex['type']) ?>
                        (<?= (int) $ex['question_count'] ?> questions, <?= (int) $ex['attempt_count'] ?> attempts)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button type="submit" class="admin-btn admin-btn-primary">
                  <i class="bi bi-download me-1"></i> Download exam backup (.sql)
                </button>
              </form>
              <p class="small text-muted mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Restoring this file deletes the current copy of that exam before re-inserting it.
              </p>
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="admin-card mb-4">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-database-down me-2"></i>All data (data-only)</h3>
            </div>
            <div class="admin-card-body">
              <p class="small text-muted">Every row in every table, without <code>CREATE TABLE</code> statements. Use this when the schema already exists on the destination.</p>
              <form method="post" onsubmit="return confirm('Download a data-only export of all tables?');">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="export_type" value="data">
                <button type="submit" class="admin-btn admin-btn-outline">
                  <i class="bi bi-download me-1"></i> Download all data (.sql)
                </button>
              </form>
            </div>
          </div>

          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-hdd-stack me-2"></i>Entire database (schema + data)</h3>
            </div>
            <div class="admin-card-body">
              <p class="small text-muted">A complete portable dump: every table is dropped, recreated and repopulated. Keep a copy off the server.</p>
              <form method="post" onsubmit="return confirm('Download a full database export? Large databases can take a while.');">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="export_type" value="full">
                <button type="submit" class="admin-btn admin-btn-danger">
                  <i class="bi bi-download me-1"></i> Download full database (.sql)
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card mt-4">
        <div class="admin-card-header">
          <h3 class="admin-card-title"><i class="bi bi-clock-history me-2"></i>Recent exports</h3>
        </div>
        <div class="admin-card-body-flush">
          <div class="table-responsive">
            <table class="admin-table mb-0">
              <thead>
                <tr>
                  <th>When</th>
                  <th>Admin</th>
                  <th>Type</th>
                  <th>File</th>
                  <th>Rows</th>
                  <th>IP</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$recent_exports): ?>
                  <tr>
                    <td colspan="6" class="text-muted text-center py-4">
                      <i class="bi bi-info-circle me-2"></i>No exports have been downloaded yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recent_exports as $ex): ?>
                    <tr>
                      <td class="small"><?= htmlspecialchars(date('M j, Y H:i', strtotime((string) $ex['created_at']))) ?></td>
                      <td><?= htmlspecialchars((string) ($ex['username'] ?? '—')) ?></td>
                      <td><span class="admin-badge admin-badge-neutral"><?= htmlspecialchars((string) $ex['export_type']) ?></span></td>
                      <td class="font-monospace small"><?= htmlspecialchars((string) ($ex['file_name'] ?? '—')) ?></td>
                      <td><?= number_format((int) ($ex['record_count'] ?? 0)) ?></td>
                      <td class="small text-muted"><?= htmlspecialchars((string) ($ex['ip_address'] ?? '—')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="admin-alert admin-alert-warning mt-4">
        <i class="bi bi-shield-lock me-2"></i>
        These files contain student records and answers. Download them only to a trusted computer and do not leave them in a shared folder.
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
</body>

</html>
