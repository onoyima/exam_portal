<?php
/**
 * admin_promo_exams.php — Manage Staff Promotional Exams (admin only)
 *
 * Imitates admin_objective_exams.php: full CRUD for promotional exams plus
 * inline question management. Isolated from the core exam system.
 */

require 'auth.php';
check_auth('admin');
require 'db.php';
require_once __DIR__ . '/includes/promo_schema.php';
promo_ensure_schema($db);

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    // ── Create exam ──
    if (isset($_POST['create'])) {
        $name = trim($_POST['name']);
        $type = in_array($_POST['type'] ?? '', ['objective', 'subjective'], true) ? $_POST['type'] : 'objective';
        $instructions = trim($_POST['instructions']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $duration = intval($_POST['duration_minutes']);
        $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
        if ($name === '') {
            $error = 'Exam name is required.';
        } else {
            $db->prepare("INSERT INTO promo_exams (name, type, instructions, is_active, duration_minutes, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$name, $type, $instructions, $is_active, $duration, $start_time, $end_time]);
            $_SESSION['promo_notice'] = 'Promotional exam created.';
            header('Location: admin_promo_exams?success=created');
            exit;
        }
    }

    // ── Edit exam ──
    if (isset($_POST['edit']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $type = in_array($_POST['type'] ?? '', ['objective', 'subjective'], true) ? $_POST['type'] : 'objective';
        $instructions = trim($_POST['instructions']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $duration = intval($_POST['duration_minutes']);
        $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
        if ($name === '') {
            $error = 'Exam name is required.';
        } else {
            $db->prepare("UPDATE promo_exams SET name=?, type=?, instructions=?, is_active=?, duration_minutes=?, start_time=?, end_time=? WHERE id=?")
                ->execute([$name, $type, $instructions, $is_active, $duration, $start_time, $end_time, $id]);
            $_SESSION['promo_notice'] = 'Promotional exam updated.';
            header('Location: admin_promo_exams?success=edited');
            exit;
        }
    }

    // ── Deactivate exam ──
    if (isset($_POST['delete']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $db->prepare("UPDATE promo_exams SET is_active = 0 WHERE id = ?")->execute([$id]);
        $_SESSION['promo_notice'] = 'Promotional exam deactivated.';
        header('Location: admin_promo_exams?success=deleted');
        exit;
    }
}

$exams = $db->query("SELECT * FROM promo_exams ORDER BY is_active DESC, created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($exams as &$e) {
    $e['staff_count'] = (int) $db->query("SELECT COUNT(*) FROM promo_exam_staff WHERE exam_id = " . (int) $e['id'])->fetchColumn();
    $e['question_count'] = (int) $db->query("SELECT COUNT(*) FROM promo_exam_questions WHERE exam_id = " . (int) $e['id'])->fetchColumn();
    $e['attempt_count'] = (int) $db->query("SELECT COUNT(*) FROM promo_exam_attempts WHERE exam_id = " . (int) $e['id'])->fetchColumn();
}
unset($e);

$admin_name = $_SESSION['user'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Promotional Exams - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Outfit', sans-serif; color: #1e293b; }
        .wrap { max-width: 1150px; margin: 0 auto; padding: 24px 16px 80px; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; gap: 12px; flex-wrap: wrap; }
        .page-title { font-weight: 800; font-size: 1.25rem; color: #092c19; margin: 0; display: flex; align-items: center; gap: 8px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; }
        .card-body { padding: 18px 20px; }
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; display: block; margin-bottom: 6px; }
        .form-control { width: 100%; box-sizing: border-box; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; background: #fff; }
        .form-control:focus { border-color: #092c19; outline: none; box-shadow: 0 0 0 3px rgba(9,44,25,0.1); }
        textarea.form-control { min-height: 90px; resize: vertical; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: all .2s; }
        .btn-primary { background: #092c19; color: #fff; border-color: #092c19; }
        .btn-primary:hover { background: #061d10; }
        .btn-outline { background: #fff; color: #475569; border-color: #cbd5e1; }
        .btn-outline:hover { border-color: #092c19; color: #092c19; }
        .btn-success { background: #22c55e; color: #060d16; border-color: #22c55e; }
        .btn-success:hover { background: #4ade80; }
        .btn-danger { background: #dc2626; color: #fff; border-color: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .table th { text-align: left; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
        .table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; font-size: 0.92rem; vertical-align: middle; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #f1f5f9; color: #64748b; }
        .badge-objective { background: #dbeafe; color: #1e40af; }
        .badge-subjective { background: #f0fdf4; color: #15803d; }
        .empty { text-align: center; padding: 48px 20px; color: #64748b; }
        .empty i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
        .flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; }
        .flash-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .flash-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.4); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: #fff; border-radius: 12px; padding: 24px; width: 90%; max-width: 520px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-title { font-weight: 800; color: #0f172a; margin: 0 0 16px 0; font-size: 1.1rem; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .close-modal { background: none; border: none; font-size: 1.5rem; color: #94a3b8; position: absolute; top: 10px; right: 14px; cursor: pointer; }
        .close-modal:hover { color: #0f172a; }
        .relative { position: relative; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .stat-row { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
        .stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; flex: 1; min-width: 140px; }
        .stat-value { font-size: 1.5rem; font-weight: 800; color: #092c19; }
        .stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
        .d-none { display: none !important; }
        @media (max-width: 640px) { .grid-2 { grid-template-columns: 1fr; } .page-header { flex-direction: column; align-items: flex-start; } .stat-row { flex-direction: column; } }
    </style>
</head>
<body>
<nav class="admin-navbar" style="background:#fff;border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:50;">
    <div class="admin-navbar-inner" style="padding:0 16px;">
        <div class="admin-navbar-start" style="display:flex;align-items:center;gap:10px;">
            <a href="admin_dashboard" class="btn btn-outline" style="padding:8px 14px;font-size:.85rem;"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
        </div>
        <div class="admin-navbar-end">
            <span style="background:rgba(9,44,25,0.08);color:#092c19;padding:6px 12px;border-radius:8px;font-weight:700;font-size:.85rem;"><?= htmlspecialchars(strtoupper(substr($admin_name, 0, 1))) ?></span>
            <a href="logout" class="btn btn-outline" style="padding:8px 14px;font-size:.85rem;">Sign out</a>
        </div>
    </div>
</nav>

<div class="wrap">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-mortarboard-fill" style="color:#092c19;"></i> Promotional Exams</h1>
        <div style="display:flex;gap:8px;">
            <a href="#createModal" class="btn btn-primary" onclick="openModal('createModal')"><i class="bi bi-plus me-1"></i> Create exam</a>
            <a href="admin_promo_staff" class="btn btn-outline"><i class="bi bi-people me-1"></i> Manage staff</a>
            <a href="admin_promo_results" class="btn btn-outline"><i class="bi bi-bar-chart me-1"></i> View results</a>
        </div>
    </div>

    <?php if (isset($_SESSION['promo_notice'])): ?>
        <div class="flash flash-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['promo_notice']); unset($_SESSION['promo_notice']); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flash flash-error"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stat-row">
        <div class="stat">
            <div class="stat-value"><?= count($exams) ?></div>
            <div class="stat-label">Total exams</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?= array_sum(array_map(fn($e) => (int) $e['staff_count'], $exams)) ?></div>
            <div class="stat-label">Staff enrolments</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?= array_sum(array_map(fn($e) => (int) $e['question_count'], $exams)) ?></div>
            <div class="stat-label">Questions</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?= array_sum(array_map(fn($e) => (int) $e['attempt_count'], $exams)) ?></div>
            <div class="stat-label">Attempts</div>
        </div>
    </div>

    <?php if (!$exams): ?>
        <div class="empty"><i class="bi bi-mortarboard"></i><p style="font-weight:600;margin-bottom:6px;">No promotional exams yet</p><p class="small">Create one to begin assigning staff and questions.</p></div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:34px;">#</th>
                    <th>Exam name</th>
                    <th style="width:110px;">Type</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:120px;">Duration</th>
                    <th style="width:100px;">Staff</th>
                    <th style="width:110px;">Questions</th>
                    <th style="width:100px;">Attempts</th>
                    <th style="width:170px;">Window</th>
                    <th style="width:170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exams as $e): ?>
                <tr>
                    <td><?= (int) $e['id'] ?></td>
                    <td><?= htmlspecialchars((string) $e['name']) ?></td>
                    <td><span class="badge <?= $e['type'] === 'objective' ? 'badge-objective' : 'badge-subjective' ?>"><?= htmlspecialchars(ucfirst((string) $e['type'])) ?></span></td>
                    <td><span class="badge <?= (int) $e['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= (int) $e['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td><?= (int) $e['duration_minutes'] ?> min</td>
                    <td><?= (int) $e['staff_count'] ?></td>
                    <td><?= (int) $e['question_count'] ?></td>
                    <td><?= (int) $e['attempt_count'] ?></td>
                    <td class="small" style="color:#64748b;">
                        <?= !empty($e['start_time']) ? date('M j, g:i A', strtotime((string) $e['start_time'])) : '—' ?>
                        <span style="color:#94a3b8;"> to </span><?= !empty($e['end_time']) ? date('M j, g:i A', strtotime((string) $e['end_time'])) : '—' ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <a href="admin_promo_questions?exam_id=<?= (int) $e['id'] ?>" class="btn btn-sm btn-outline"><i class="bi bi-pencil-square me-1"></i>Questions</a>
                            <button class="btn btn-sm btn-outline" onclick="editExam(<?= json_encode($e) ?>)"><i class="bi bi-pencil me-1"></i>Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteExam(<?= (int) $e['id'] ?>, <?= json_encode(htmlspecialchars((string) $e['name'])) ?>)"><i class="bi bi-trash me-1"></i>Deactivate</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Create / Edit modal -->
<div class="modal" id="createModal">
    <div class="modal-content relative">
        <button class="close-modal" onclick="closeModal('createModal')">&times;</button>
        <h2 class="modal-title" id="modalTitle">Create promotional exam</h2>
        <form method="post" id="examForm">
            <?php csrf_field(); ?>
            <input type="hidden" name="id" id="modalExamId">
            <input type="hidden" name="edit" value="0">
            <div class="mb-3">
                <label class="form-label">Exam name</label>
                <input type="text" name="name" id="modalName" class="form-control" required>
            </div>
            <div class="grid-2">
                <div>
                    <label class="form-label">Type</label>
                    <select name="type" id="modalType" class="form-control">
                        <option value="objective">Objective (MCQ)</option>
                        <option value="subjective">Subjective (Essay)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Duration (minutes)</label>
                    <input type="number" name="duration_minutes" id="modalDuration" class="form-control" value="60" min="0">
                </div>
            </div>
            <div class="grid-2">
                <div>
                    <label class="form-label">Start time</label>
                    <input type="datetime-local" name="start_time" id="modalStart" class="form-control">
                </div>
                <div>
                    <label class="form-label">End time</label>
                    <input type="datetime-local" name="end_time" id="modalEnd" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Instructions to staff</label>
                <textarea name="instructions" id="modalInstructions" class="form-control" placeholder="e.g. Read each question carefully. This attempt will be recorded."></textarea>
            </div>
            <div class="mb-3 form-check" style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" name="is_active" id="modalActive" class="form-check-input" checked>
                <label class="form-check-label mb-0" style="cursor:pointer;">Active (staff can see and take this exam)</label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" name="create" class="btn btn-primary" id="modalSubmitBtn">Create exam</button>
                <button type="submit" name="edit" class="btn btn-primary d-none" id="modalEditBtn">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const createModal = document.getElementById('createModal');
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

function editExam(e) {
    document.getElementById('modalTitle').textContent = 'Edit promotional exam';
    document.getElementById('modalExamId').value = e.id;
    document.getElementById('modalName').value = e.name;
    document.getElementById('modalType').value = e.type;
    document.getElementById('modalDuration').value = e.duration_minutes;
    document.getElementById('modalInstructions').value = e.instructions ?? '';
    document.getElementById('modalActive').checked = e.is_active == 1;
    const start = e.start_time ? new Date(e.start_time).toISOString().slice(0, 16) : '';
    const end = e.end_time ? new Date(e.end_time).toISOString().slice(0, 16) : '';
    document.getElementById('modalStart').value = start;
    document.getElementById('modalEnd').value = end;
    document.getElementById('examForm').querySelector('input[name="edit"]').value = '1';
    document.getElementById('modalSubmitBtn').classList.add('d-none');
    document.getElementById('modalEditBtn').classList.remove('d-none');
    openModal('createModal');
}

function deleteExam(id, name) {
    if (!confirm('Deactivate the promotional exam "' + name + '"? Staff will no longer see or take it.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fd.append('delete', '1');
    fd.append('csrf_token', '<?= bin2hex(random_bytes(16)) ?>');
    fetch('admin_promo_exams', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
        .then(() => location.reload());
}

createModal.addEventListener('click', function(e) { if (e.target === createModal) closeModal('createModal'); });
</script>

<?php if (isset($_GET['success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){});
</script>
<?php endif; ?>
</body>
</html>
