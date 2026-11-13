<?php
/**
 * admin_promo_staff.php — Manage Staff Promotional Examination accounts (admin only)
 *
 * Create individual staff (email), upload staff by file (CSV), import from the
 * exeat2 school portal staff table, and enrol staff to promotional exams.
 * Includes search on staff accounts and enrolments.
 * Isolated from the core staff/student system.
 */

require 'auth.php';
check_auth('admin');
require 'db.php';
require_once __DIR__ . '/includes/promo_schema.php';
promo_ensure_schema($db);

$notice = '';
$error = '';
$search = trim($_GET['search'] ?? '');

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
            $row = 0; $imported = 0; $skipped = 0;
            if (($handle = fopen($_FILES['staff_file']['tmp_name'], 'r')) !== false) {
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $row++;
                    if ($row === 1 && (strcasecmp(trim($data[0] ?? ''), 'name') === 0 || strcasecmp(trim($data[0] ?? ''), 'email') === 0)) {
                        continue;
                    }
                    $name = trim($data[0] ?? '');
                    $email = trim($data[1] ?? '');
                    if ($name === '' || $email === '') { $skipped++; continue; }
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || substr($email, -15) !== '@veritas.edu.ng') { $skipped++; continue; }
                    $emailLower = strtolower($email);
                    $chk = $db->prepare("SELECT 1 FROM promo_staff WHERE LOWER(email) = ? LIMIT 1");
                    $chk->execute([$emailLower]);
                    if ($chk->fetchColumn()) { $skipped++; continue; }
                    $db->prepare("INSERT INTO promo_staff (name, email, source) VALUES (?, ?, 'upload')")->execute([$name, $emailLower]);
                    $imported++;
                }
                fclose($handle);
            }
            header('Location: admin_promo_staff?success=uploaded&imported=' . $imported . '&skipped=' . $skipped);
            exit;
        }
    }

    // ── Enrol staff to an exam ──
    if (isset($_POST['enrol_staff'])) {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $examId = (int) ($_POST['exam_id'] ?? 0);
        if ($staffId <= 0 || $examId <= 0) {
            $error = 'Select staff and exam.';
        } else {
            $db->prepare("INSERT IGNORE INTO promo_exam_staff (exam_id, staff_id) VALUES (?, ?)")->execute([$examId, $staffId]);
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
                header('Location: admin_promo_staff?success=imported&imported=' . $imported);
                exit;
            }
        }
    }

    // ── Unenrol staff from exam ──
    if (isset($_POST['unenrol'])) {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM promo_exam_staff WHERE id = ?")->execute([$id]);
            header('Location: admin_promo_staff?success=unenrolled');
            exit;
        }
    }
}

// Load all promo staff with enrolment info (with search)
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE (ps.name LIKE ? OR ps.email LIKE ?)";
    $like = "%{$search}%";
    $params = [$like, $like];
}
$staffStmt = $db->prepare("
    SELECT ps.*, GROUP_CONCAT(pe.name SEPARATOR ', ') AS enrolled_exams
    FROM promo_staff ps
    LEFT JOIN promo_exam_staff pes ON pes.staff_id = ps.id
    LEFT JOIN promo_exams pe ON pe.id = pes.exam_id AND pe.is_active = 1
    $where
    GROUP BY ps.id
    ORDER BY ps.created_at DESC, ps.id DESC
");
$staffStmt->execute($params);
$staff = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// All active exams for enrolment dropdown
$exams = $db->query("SELECT * FROM promo_exams WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// All staff-enrolment pairs for the enrolment table
$enrolWhere = '';
$enrolParams = [];
if ($search !== '') {
    $enrolWhere = "WHERE (ps.name LIKE ? OR ps.email LIKE ? OR pe.name LIKE ?)";
    $enrolLike = "%{$search}%";
    $enrolParams = [$enrolLike, $enrolLike, $enrolLike];
}
$enrolStmt = $db->prepare("
    SELECT pes.id, ps.name AS staff_name, ps.email, pe.name AS exam_name
    FROM promo_exam_staff pes
    JOIN promo_staff ps ON ps.id = pes.staff_id
    JOIN promo_exams pe ON pe.id = pes.exam_id
    $enrolWhere
    ORDER BY pe.name, ps.name
");
$enrolStmt->execute($enrolParams);
$enrolments = $enrolStmt->fetchAll(PDO::FETCH_ASSOC);

$admin_name = $_SESSION['user'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Promo Staff - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
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
          <a href="admin_dashboard" class="admin-back-btn" aria-label="Back to Dashboard">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Promo Staff</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= htmlspecialchars(strtoupper(substr($admin_name, 0, 1))) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($admin_name) ?></span>
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
          <li class="current" aria-current="page">Promo Staff</li>
        </ol>
      </nav>

      <?php display_auth_errors(); ?>
      <?php if (isset($_GET['success'])): ?>
        <div class="admin-alert admin-alert-success"><i class="bi bi-check-circle me-2"></i>
          <?= 'created' === $_GET['success'] ? 'Staff member created.' : ('uploaded' === $_GET['success'] ? "Uploaded: {$_GET['imported']} added, {$_GET['skipped']} skipped." : ('enrolled' === $_GET['success'] ? 'Staff enrolled to exam.' : ('imported' === $_GET['success'] ? "Imported {$_GET['imported']} staff member(s) from exeat2." : 'Staff unenrolled.'))) ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="admin-alert admin-alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Promo Staff</h2>
        <div class="admin-page-actions">
          <a href="admin_promo_exams" class="admin-btn admin-btn-outline">
            <i class="bi bi-mortarboard me-1"></i> Manage promo exams
          </a>
          <a href="admin_promo_results" class="admin-btn admin-btn-outline">
            <i class="bi bi-bar-chart me-1"></i> Promo results
          </a>
        </div>
      </div>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Staff Accounts</h2>
      </div>

      <!-- Search bar -->
      <form method="get" class="admin-search-form mb-3" style="display:flex;gap:8px;max-width:360px;">
        <input type="text" name="search" class="form-control" placeholder="Search by name or email…" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-search"></i></button>
        <?php if ($search !== ''): ?>
          <a href="admin_promo_staff" class="admin-btn admin-btn-outline">Clear</a>
        <?php endif; ?>
      </form>

      <div class="row g-3">
        <!-- Create staff card -->
        <div class="col-12 col-lg-4">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-person-plus me-2"></i>Create staff (email)</h3>
            </div>
            <div class="admin-card-body">
              <form method="post">
                <?php csrf_field(); ?>
                <div class="admin-form-group mb-3">
                  <label class="admin-form-label">Full name</label>
                  <input type="text" name="name" class="admin-input" required placeholder="John Doe">
                </div>
                <div class="admin-form-group mb-3">
                  <label class="admin-form-label">Staff email</label>
                  <input type="email" name="email" class="admin-input" required placeholder="john.doe@veritas.edu.ng">
                  <div class="form-text text-muted">Only @veritas.edu.ng emails accepted.</div>
                </div>
                <button type="submit" name="create_staff" class="admin-btn admin-btn-primary">Create staff</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Upload staff card -->
        <div class="col-12 col-lg-4">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-cloud-arrow-up me-2"></i>Upload by file</h3>
            </div>
            <div class="admin-card-body">
              <p class="small text-muted mb-3">CSV or TXT file with two columns: name,email. First row may be a header.</p>
              <form method="post" enctype="multipart/form-data">
                <?php csrf_field(); ?>
                <div class="admin-form-group mb-3">
                  <label class="admin-form-label">CSV file</label>
                  <input type="file" name="staff_file" class="admin-input" accept=".csv,.txt,text/plain" required>
                </div>
                <button type="submit" name="upload_staff" class="admin-btn admin-btn-primary">Upload & import</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Import from exeat2 card -->
        <div class="col-12 col-lg-4">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-cloud-download me-2"></i>Import from exeat2</h3>
            </div>
            <div class="admin-card-body">
              <p class="small text-muted mb-3">Select staff from the exeat2 school portal database. Only @veritas.edu.ng emails are imported.</p>
              <div class="mb-3">
                <button type="button" class="admin-btn admin-btn-outline" id="loadExeat2Btn" onclick="loadExeat2()">
                  <i class="bi bi-cloud-arrow-down me-1"></i> Load exeat2 staff
                </button>
                <span id="exeat2Status" class="admin-badge admin-badge-neutral ms-2"></span>
                <div id="exeat2Error" class="text-danger small mt-2"></div>
              </div>
              <div id="exeat2List" style="max-height:300px;overflow-y:auto;border:1px solid #dee2e6;border-radius:6px;padding:10px;background:#f8f9fa;display:none;margin-bottom:10px;"></div>
              <div id="exeat2Actions" style="display:none;">
                <form method="post" onsubmit="return confirm('Import selected staff from exeat2?')">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="exeat2_ids" id="exeat2Ids">
                  <button type="submit" name="import_exeat2" class="admin-btn admin-btn-success">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Import selected staff
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Existing staff accounts table -->
      <div class="admin-page-header mt-4">
        <h2 class="admin-page-title">Existing Staff Accounts<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?></h2>
      </div>
      <?php if (!$staff): ?>
        <div class="admin-card">
          <div class="admin-card-body">
            <p class="text-muted text-center py-4"><i class="bi bi-person me-2"></i>No staff accounts found.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Source</th>
                <th>Status</th>
                <th>Enrolled exams</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($staff as $s): ?>
              <tr>
                <td><?= (int) $s['id'] ?></td>
                <td><?= htmlspecialchars((string) $s['name']) ?></td>
                <td class="text-truncate" style="max-width:180px;"><?= htmlspecialchars((string) $s['email']) ?></td>
                <td><span class="admin-badge admin-badge-neutral"><?= htmlspecialchars(ucfirst((string) $s['source'])) ?></span></td>
                <td><span class="admin-badge admin-badge-<?= (int) $s['is_active'] ? 'success' : 'danger' ?>"><?= (int) $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td class="text-muted small"><?= htmlspecialchars((string) ($s['enrolled_exams'] ?? '—')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- Enrol staff to exams section -->
      <div class="admin-page-header mt-4">
        <h2 class="admin-page-title">Enrol Staff to Exams</h2>
      </div>
      <div class="admin-card">
        <div class="admin-card-body">
          <form method="post">
            <?php csrf_field(); ?>
            <div class="row g-3 align-items-end">
              <div class="col-12 col-lg-4">
                <label class="admin-form-label">Staff member</label>
                <select name="staff_id" class="admin-select" required>
                  <option value="">Select staff…</option>
                  <?php foreach ($staff as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars((string) $s['name']) ?> (<?= htmlspecialchars((string) $s['email']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-lg-4">
                <label class="admin-form-label">Promotional exam</label>
                <select name="exam_id" class="admin-select" required>
                  <option value="">Select exam…</option>
                  <?php foreach ($exams as $e): ?>
                    <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars((string) $e['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-lg-4">
                <button type="submit" name="enrol_staff" class="admin-btn admin-btn-primary w-100">Enrol staff</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Current enrolments -->
      <div class="admin-page-header mt-4">
        <h2 class="admin-page-title">Current Enrolments<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?></h2>
      </div>
      <?php if (!$enrolments): ?>
        <div class="admin-card">
          <div class="admin-card-body">
            <p class="text-muted text-center py-4"><i class="bi bi-person-gear me-2"></i>No enrolments yet.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Staff</th>
                <th>Email</th>
                <th>Exam</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($enrolments as $en): ?>
              <tr>
                <td><?= (int) $en['id'] ?></td>
                <td><?= htmlspecialchars((string) $en['staff_name']) ?></td>
                <td class="text-truncate" style="max-width:180px;"><?= htmlspecialchars((string) $en['email']) ?></td>
                <td><?= htmlspecialchars((string) $en['exam_name']) ?></td>
                <td class="text-end">
                  <form method="post" style="margin:0;" onsubmit="return confirm('Unenrol this staff from this exam?')">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="id" value="<?= (int) $en['id'] ?>">
                    <button type="submit" name="unenrol" class="admin-btn admin-btn-outline admin-btn-sm"><i class="bi bi-person-dash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script>
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
            <input type="checkbox" class="form-check-input exeat2-checkbox" value="${s.exeat2_staff_id}" ${s.status === 'exists_exeat2' ? 'disabled title="Already imported"' : ''} onchange="toggleExeat2(${s.exeat2_staff_id}, this)">
            <label class="form-check-label d-block ms-2">${escapeHtml(s.name)} <span class="text-muted small d-block">${escapeHtml(s.email)}</span></label>
          `;
          list.appendChild(item);
        });
        list.style.display = 'block';
        actions.style.display = data.length ? 'block' : 'none';
        status.textContent = data.length + ' staff found';
        status.className = 'admin-badge admin-badge-info ms-2';
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
    }

    function escapeHtml(t) { return String(t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c])); }
  </script>
</body>
</html>
