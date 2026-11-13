<?php
/**
 * admin_promo_staff.php — Manage Staff Promotional Examination accounts (admin only)
 *
 * Create individual staff (email), upload staff by file (CSV), import from the
 * exeat2 school portal staff table, and enrol staff to promotional exams.
 * Isolated from the core staff/student system.
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

    // ── Create a single staff member ──
    if (isset($_POST['create_staff'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '' || $email === '') {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (substr($email, -15) !== '@veritas.edu.ng') {
            $error = 'Only @veritas.edu.ng staff emails are accepted.';
        } else {
            $emailLower = strtolower($email);
            $chk = $db->prepare("SELECT 1 FROM promo_staff WHERE LOWER(email) = ? LIMIT 1");
            $chk->execute([$emailLower]);
            if ($chk->fetchColumn()) {
                $error = 'This email is already registered.';
            } else {
                $db->prepare("INSERT INTO promo_staff (name, email, source) VALUES (?, ?, 'manual')")->execute([$name, $emailLower]);
                $_SESSION['promo_notice'] = 'Staff member created.';
                header('Location: admin_promo_staff?success=created');
                exit;
            }
        }
    }

    // ── Upload staff by file (CSV: name,email) ──
    if (isset($_POST['upload_staff'])) {
        if (empty($_FILES['staff_file']['tmp_name'])) {
            $error = 'Please choose a CSV file.';
        } else {
            $row = 0; $imported = 0; $skipped = 0; $errs = [];
            if (($handle = fopen($_FILES['staff_file']['tmp_name'], 'r')) !== false) {
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $row++;
                    if ($row === 1 && (strcasecmp(trim($data[0] ?? ''), 'name') === 0 || strcasecmp(trim($data[0] ?? ''), 'email') === 0)) {
                        continue; // skip header
                    }
                    $name = trim($data[0] ?? '');
                    $email = trim($data[1] ?? '');
                    if ($name === '' || $email === '') { $skipped++; continue; }
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || substr($email, -15) !== '@veritas.edu.ng') { $errs[] = "Row $row: invalid email $email"; $skipped++; continue; }
                    $emailLower = strtolower($email);
                    $chk = $db->prepare("SELECT 1 FROM promo_staff WHERE LOWER(email) = ? LIMIT 1");
                    $chk->execute([$emailLower]);
                    if ($chk->fetchColumn()) { $skipped++; continue; }
                    $db->prepare("INSERT INTO promo_staff (name, email, source) VALUES (?, ?, 'upload')")->execute([$name, $emailLower]);
                    $imported++;
                }
                fclose($handle);
            }
            $_SESSION['promo_notice'] = "Uploaded: $imported added, $skipped skipped" . ($errs ? " — " . implode('; ', array_slice($errs, 0, 5)) : '');
            header('Location: admin_promo_staff?success=uploaded');
            exit;
        }
    }

    // ── Enrol staff to an exam ──
    if (isset($_POST['enrol_staff'])) {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $examId = (int) ($_POST['exam_id'] ?? 0);
        if ($staffId <= 0 || $examId <= 0) { $error = 'Select staff and exam.'; }
        else {
            $db->prepare("INSERT IGNORE INTO promo_exam_staff (exam_id, staff_id) VALUES (?, ?)")->execute([$examId, $staffId]);
            $_SESSION['promo_notice'] = 'Staff enrolled to exam.';
            header('Location: admin_promo_staff?success=enrolled');
            exit;
        }
    }

    // ── Import from exeat2 staff table ──
    if (isset($_POST['import_exeat2'])) {
        $exeat2Ids = $_POST['exeat2_ids'] ?? [];
        if (!is_array($exeat2Ids) || empty($exeat2Ids)) {
            $error = 'Select at least one staff member from exeat2.';
        } else {
            require_once __DIR__ . '/db_exeat2.php';
            if (!$exeat2_db) {
                $error = $exeat2_error ?: 'School portal database not reachable.';
            } else {
                $placeholders = implode(',', array_fill(0, count($exeat2Ids), '?'));
                $stmt = $exeat2_db->prepare("SELECT st.id AS exeat2_staff_id, CONCAT(st.fname, ' ', COALESCE(st.mname,''), ' ', st.lname) AS name, st.email AS email FROM staff st WHERE st.id IN ($placeholders) AND st.status = 1");
                $stmt->execute($exeat2Ids);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $imported = 0;
                foreach ($rows as $row) {
                    if (!$row['email']) continue;
                    $emailLower = strtolower(trim($row['email']));
                    if (substr($emailLower, -15) !== '@veritas.edu.ng') continue;
                    $chk = $db->prepare("SELECT 1 FROM promo_staff WHERE LOWER(email) = ? LIMIT 1");
                    $chk->execute([$emailLower]);
                    if ($chk->fetchColumn()) continue;
                    $db->prepare("INSERT INTO promo_staff (name, email, source, external_id) VALUES (?, ?, 'exeat2', ?)")
                        ->execute([$row['name'], $emailLower, (int) $row['exeat2_staff_id']]);
                    $imported++;
                }
                $_SESSION['promo_notice'] = "Imported $imported staff member(s) from exeat2.";
                header('Location: admin_promo_staff?success=imported');
                exit;
            }
        }
    }

    // ── Unenrol staff from exam ──
    if (isset($_POST['unenrol'])) {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM promo_exam_staff WHERE id = ?")->execute([$id]);
            $_SESSION['promo_notice'] = 'Staff unenrolled.';
            header('Location: admin_promo_staff?success=unenrolled');
            exit;
        }
    }
}

// Load all promo staff with enrolment info
$staff = $db->query("SELECT ps.*, GROUP_CONCAT(pe.name SEPARATOR ', ') AS enrolled_exams
    FROM promo_staff ps
    LEFT JOIN promo_exam_staff pes ON pes.staff_id = ps.id
    LEFT JOIN promo_exams pe ON pe.id = pes.exam_id AND pe.is_active = 1
    GROUP BY ps.id
    ORDER BY ps.created_at DESC, ps.id DESC")->fetchAll(PDO::FETCH_ASSOC);

// All active exams for enrolment dropdown
$exams = $db->query("SELECT * FROM promo_exams WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// All staff-enrolment pairs for the enrolment table (who is enrolled where)
$enrolments = $db->query("SELECT pes.id, ps.name AS staff_name, ps.email, pe.name AS exam_name
    FROM promo_exam_staff pes
    JOIN promo_staff ps ON ps.id = pes.staff_id
    JOIN promo_exams pe ON pe.id = pes.exam_id
    ORDER BY pe.name, ps.name")->fetchAll(PDO::FETCH_ASSOC);

$admin_name = $_SESSION['user'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Promo Staff - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Outfit', sans-serif; color: #1e293b; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px 80px; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; gap: 12px; flex-wrap: wrap; }
        .page-title { font-weight: 800; font-size: 1.25rem; color: #092c19; margin: 0; display: flex; align-items: center; gap: 8px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; }
        .card-body { padding: 18px 20px; }
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; display: block; margin-bottom: 6px; }
        .form-control { width: 100%; box-sizing: border-box; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; background: #fff; }
        .form-control:focus { border-color: #092c19; outline: none; box-shadow: 0 0 0 3px rgba(9,44,25,0.1); }
        textarea.form-control { min-height: 80px; resize: vertical; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: all .2s; }
        .btn-primary { background: #092c19; color: #fff; border-color: #092c19; }
        .btn-primary:hover { background: #061d10; }
        .btn-outline { background: #fff; color: #475569; border-color: #cbd5e1; }
        .btn-outline:hover { border-color: #092c19; color: #092c19; }
        .btn-success { background: #22c55e; color: #060d16; border-color: #22c55e; }
        .btn-success:hover { background: #4ade80; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .table th { text-align: left; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
        .table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; font-size: 0.92rem; vertical-align: middle; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
        .badge-manual { background: #e2e8f0; color: #475569; }
        .badge-upload { background: #dbeafe; color: #1e40af; }
        .badge-exeat2 { background: #f0fdf4; color: #15803d; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fef2f2; color: #991b1b; }
        .empty { text-align: center; padding: 48px 20px; color: #64748b; }
        .empty i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
        .flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; }
        .flash-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .flash-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .tab { display: inline-block; padding: 8px 16px; margin-bottom: 2px; border-bottom: 2px solid transparent; font-weight: 700; color: #64748b; cursor: pointer; font-size: 0.9rem; }
        .tab.active { color: #092c19; border-bottom-color: #092c19; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
        .exeat2-list { max-height: 320px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #fafafa; margin-bottom: 10px; }
        .exeat2-item { display: flex; align-items: center; gap: 10px; padding: 6px; border-radius: 6px; margin-bottom: 6px; background: #fff; border: 1px solid #f1f5f9; }
        .exeat2-item.selected { background: #ecfdf5; border-color: #22c55e; }
        .exeat2-checkbox { width: 18px; height: 18px; cursor: pointer; }
        .exeat2-meta { flex: 1; font-size: 0.88rem; color: #334155; }
        .exeat2-email { color: #64748b; font-size: 0.8rem; display: block; }
        .count-chip { font-size: 0.78rem; padding: 2px 8px; border-radius: 6px; background: #e2e8f0; color: #475569; }
        .hint { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
        @media (max-width: 768px) { .grid-2,.grid-3 { grid-template-columns: 1fr; } .page-header { flex-direction: column; align-items: flex-start; } }
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
        <h1 class="page-title"><i class="bi bi-people" style="color:#092c19;"></i> Promo Staff</h1>
        <a href="admin_promo_exams" class="btn btn-outline"><i class="bi bi-mortarboard me-1"></i> Manage promo exams</a>
    </div>

    <?php if (isset($_SESSION['promo_notice'])): ?>
        <div class="flash flash-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['promo_notice']); unset($_SESSION['promo_notice']); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flash flash-error"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div style="display:flex;gap:0;border-bottom:1px solid #e2e8f0;margin-bottom:20px;overflow:auto;">
        <span class="tab active" onclick="showTab('staff')">Staff accounts</span>
        <span class="tab" onclick="showTab('enrol')">Enrol to exams</span>
    </div>

    <!-- Tab: Staff accounts -->
    <div id="tab-staff" class="tab-content active">
        <div class="grid-2">
            <div class="card">
                <div class="card-body">
                    <h3 style="font-weight:800;color:#092c19;margin:0 0 12px 0;font-size:1rem;">Create staff (email)</h3>
                    <form method="post">
                        <?php csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label">Full name</label>
                            <input type="text" name="name" class="form-control" required placeholder="John Doe">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Staff email</label>
                            <input type="email" name="email" class="form-control" required placeholder="john.doe@veritas.edu.ng">
                            <div class="hint">Only @veritas.edu.ng emails accepted.</div>
                        </div>
                        <button type="submit" name="create_staff" class="btn btn-primary">Create staff</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 style="font-weight:800;color:#092c19;margin:0 0 12px 0;font-size:1rem;">Upload staff by file</h3>
                    <p class="small" style="color:#64748b;margin-bottom:12px;">CSV file with two columns: <code>name,email</code>. First row may be a header.</p>
                    <form method="post" enctype="multipart/form-data">
                        <?php csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label">CSV file</label>
                            <input type="file" name="staff_file" class="form-control" accept=".csv" required>
                        </div>
                        <button type="submit" name="upload_staff" class="btn btn-primary">Upload & import</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 style="font-weight:800;color:#092c19;margin:0 0 12px 0;font-size:1rem;">Import from school portal (exeat2)</h3>
                <p class="small" style="color:#64748b;margin-bottom:12px;">Select staff from the exeat2 school portal database. Only <code>@veritas.edu.ng</code> emails are imported.</p>
                <div class="mb-3">
                    <button type="button" class="btn btn-outline" id="loadExeat2Btn" onclick="loadExeat2()"><i class="bi bi-cloud-arrow-down me-1"></i> Load exeat2 staff</button>
                    <span id="exeat2Status" class="count-chip" style="margin-left:8px;"></span>
                    <div id="exeat2Error" style="color:#dc2626;font-size:0.85rem;margin-top:6px;"></div>
                </div>
                <div id="exeat2List" class="exeat2-list" style="display:none;"></div>
                <div id="exeat2Actions" style="margin-top:10px;display:none;">
                    <form method="post" onsubmit="return confirm('Import selected staff from exeat2?')">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="exeat2_ids" id="exeat2Ids">
                        <button type="submit" name="import_exeat2" class="btn btn-success"><i class="bi bi-box-arrow-in-right me-1"></i> Import selected staff</button>
                    </form>
                </div>
            </div>
        </div>

        <h3 style="font-weight:800;color:#092c19;margin:20px 0 10px 0;font-size:1rem;">Existing staff accounts</h3>
        <?php if (!$staff): ?>
            <div class="empty"><i class="bi bi-person"></i><p style="font-weight:600;margin-bottom:6px;">No staff accounts yet</p><p class="small">Create, upload, or import staff above.</p></div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th style="width:40px;">#</th><th>Name</th><th style="width:200px;">Email</th><th style="width:120px;">Source</th><th style="width:100px;">Status</th><th style="width:220px;">Enrolled exams</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $s): ?>
                    <tr>
                        <td><?= (int) $s['id'] ?></td>
                        <td><?= htmlspecialchars((string) $s['name']) ?></td>
                        <td style="font-size:0.88rem;color:#334155;"><?= htmlspecialchars((string) $s['email']) ?></td>
                        <td><span class="badge badge-<?= htmlspecialchars((string) $s['source']) ?>"><?= htmlspecialchars(ucfirst((string) $s['source'])) ?></span></td>
                        <td><span class="badge <?= (int) $s['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= (int) $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td style="font-size:0.88rem;color:#64748b;"><?= htmlspecialchars((string) ($s['enrolled_exams'] ?? '—')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Tab: Enrol staff to exams -->
    <div id="tab-enrol" class="tab-content">
        <div class="card">
            <div class="card-body">
                <h3 style="font-weight:800;color:#092c19;margin:0 0 12px 0;font-size:1rem;">Enrol staff to a promotional exam</h3>
                <form method="post">
                    <?php csrf_field(); ?>
                    <div class="grid-2">
                        <div>
                            <label class="form-label">Staff member</label>
                            <select name="staff_id" class="form-control" required>
                                <option value="">Select staff…</option>
                                <?php foreach ($staff as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars((string) $s['name']) ?> (<?= htmlspecialchars((string) $s['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Promotional exam</label>
                            <select name="exam_id" class="form-control" required>
                                <option value="">Select exam…</option>
                                <?php foreach ($exams as $e): ?>
                                    <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars((string) $e['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" name="enrol_staff" class="btn btn-primary">Enrol staff</button>
                    </div>
                </form>
            </div>
        </div>

        <h3 style="font-weight:800;color:#092c19;margin:20px 0 10px 0;font-size:1rem;">Current enrolments</h3>
        <?php if (!$enrolments): ?>
            <div class="empty"><i class="bi bi-person-gear"></i><p style="font-weight:600;margin-bottom:6px;">No enrolments yet</p></div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th style="width:40px;">#</th><th>Staff</th><th style="width:200px;">Email</th><th>Exam</th><th style="width:150px;">Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($enrolments as $en): ?>
                    <tr>
                        <td><?= (int) $en['id'] ?></td>
                        <td><?= htmlspecialchars((string) $en['staff_name']) ?></td>
                        <td style="font-size:0.88rem;color:#64748b;"><?= htmlspecialchars((string) $en['email']) ?></td>
                        <td><?= htmlspecialchars((string) $en['exam_name']) ?></td>
                        <td>
                            <form method="post" style="margin:0;" onsubmit="return confirm('Unenrol this staff from this exam?')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="id" value="<?= (int) $en['id'] ?>">
                                <button type="submit" name="unenrol" class="btn btn-sm btn-outline"><i class="bi bi-person-dash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
const tabs = document.querySelectorAll('.tab');
const contents = document.querySelectorAll('.tab-content');
function showTab(name) {
    tabs.forEach(t => t.classList.toggle('active', (t.getAttribute('onclick') || '').includes(name)));
    contents.forEach(c => c.classList.toggle('active', c.id === 'tab-' + name));
}

let exeat2Selected = new Set();

async function loadExeat2() {
    const btn = document.getElementById('loadExeat2Btn');
    const status = document.getElementById('exeat2Status');
    const list = document.getElementById('exeat2List');
    const actions = document.getElementById('exeat2Actions');
    const err = document.getElementById('exeat2Error');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-loader me-1"></i> Loading...';
    err.textContent = '';
    list.style.display = 'none';
    actions.style.display = 'none';
    try {
        const r = await fetch('includes/fetch_exeat2_staff.php', { headers: { 'X-Requested-With': 'fetch' } });
        const data = await r.json();
        if (data.error) { err.textContent = data.error; btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-arrow-down me-1"></i> Load exeat2 staff'; return; }
        exeat2Selected = new Set();
        list.innerHTML = '';
        data.forEach(s => {
            const item = document.createElement('div');
            item.className = 'exeat2-item';
            item.innerHTML = `
                <input type="checkbox" class="exeat2-checkbox" value="${s.exeat2_staff_id}" ${s.status === 'exists_exeat2' ? 'disabled title="Already imported"' : ''} onchange="toggleExeat2(${s.exeat2_staff_id}, this)">
                <div class="exeat2-meta"><div style="font-weight:700;color:#0f172a;">${htmlEscape(s.name)}</div><span class="exeat2-email">${htmlEscape(s.email)}</span></div>
            `;
            list.appendChild(item);
        });
        list.style.display = 'block';
        actions.style.display = data.length ? 'block' : 'none';
        status.textContent = data.length + ' staff found';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-arrow-down me-1"></i> Load exeat2 staff';
    } catch (e) {
        err.textContent = 'Could not reach exeat2 staff endpoint.';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-arrow-down me-1"></i> Load exeat2 staff';
    }
}

function toggleExeat2(id, cb) {
    if (cb.disabled) return;
    if (cb.checked) exeat2Selected.add(id); else exeat2Selected.delete(id);
    document.getElementById('exeat2Ids').value = [...exeat2Selected].join(',');
    cb.closest('.exeat2-item').classList.toggle('selected', cb.checked);
}

function htmlEscape(t) { return String(t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c])); }
</script>
</body>
</html>
