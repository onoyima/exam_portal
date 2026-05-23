<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
require 'db_exeat2.php';
$name = $_SESSION['user'] ?? 'Admin';
$back_url = 'admin_dashboard';

// Fetch exeat2 departments for filter
$exeat2_departments = [];
if ($exeat2_db) {
  try {
    $exeat2_departments = $exeat2_db->query("SELECT id, name FROM departments WHERE name IS NOT NULL AND name != '' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $exeat2_db = null;
  }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $db->prepare("INSERT INTO examiners (name, username, password, role) VALUES (?, ?, ?, 'examiner')");
        $stmt->execute([$_POST['name'], $_POST['username'], $_POST['password']]);
        $success = "Staff member added successfully!";
    } elseif (isset($_POST['delete'])) {
        $stmt = $db->prepare("UPDATE examiners SET deleted_at=NOW() WHERE id = ? AND role != 'admin'");
        $stmt->execute([$_POST['id']]);
        $success = "Staff member removed successfully!";
    } elseif (isset($_POST['edit'])) {
        $stmt = $db->prepare("UPDATE examiners SET name = ?, username = ? WHERE id = ?");
        $stmt->execute([$_POST['name'], $_POST['username'], $_POST['id']]);
        $success = "Staff information updated successfully!";
    } elseif (isset($_POST['reset_password'])) {
        $stmt = $db->prepare("UPDATE examiners SET password = '12345' WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $success = "Staff password reset to default (12345) successfully!";
    }
}

// Get all staff (excluding current admin if you wish, or just non-admin roles)
$staff = $db->query("SELECT * FROM examiners WHERE role = 'examiner' AND deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Staff - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <nav class="admin-navbar">
          <div class="admin-navbar-inner">
            <div class="admin-navbar-start">
              <button class="admin-navbar-toggle d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="bi bi-list"></i>
              </button>
              <a href="<?= $back_url ?>" class="admin-back-btn">
                <i class="bi bi-arrow-left"></i>
              </a>
              <h1 class="admin-navbar-title">Manage Staff / Examiners</h1>
            </div>
            <div class="admin-navbar-end">
              <div class="admin-user-badge">
                <span class="admin-user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></span>
                <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
              </div>
              <a href="logout" class="admin-logout-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
              </a>
            </div>
          </div>
        </nav>

        <div class="admin-content">

            <nav class="admin-breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
                    <li class="sep">/</li>
                    <li class="current" aria-current="page">Staff Management</li>
                </ol>
            </nav>

            <div class="admin-page-header">
                <h1 class="admin-page-title"><i class="bi bi-people"></i> Staff Management</h1>
                <div class="admin-page-actions">
                    <button class="admin-btn admin-btn-primary" onclick="document.getElementById('addStaffForm').scrollIntoView({behavior:'smooth'})">
                        <i class="bi bi-person-plus"></i> Add Staff
                    </button>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="admin-alert admin-alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                    <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Source Selector -->
            <div class="admin-filters mb-3">
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="fw-semibold small text-secondary">STAFF SOURCE:</span>
                <div class="btn-group btn-group-sm" role="group">
                  <input type="radio" class="btn-check" name="staff_source" id="staff_src_manual" value="manual" checked>
                  <label class="btn btn-outline-primary" for="staff_src_manual">Manual Entry</label>
                  <input type="radio" class="btn-check" name="staff_source" id="staff_src_exeat2" value="exeat2" <?= $exeat2_db ? '' : 'disabled' ?>>
                  <label class="btn btn-outline-primary" for="staff_src_exeat2">School Portal (exeat2)</label>
                </div>
                <?php if (!$exeat2_db): ?>
                  <span class="small text-danger"><i class="bi bi-exclamation-triangle"></i> School Portal DB not reachable</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- exeat2 Staff Section (hidden by default) -->
            <div id="exeat2StaffSection" class="d-none">
              <div class="admin-filters mb-3">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <span class="fw-semibold small text-secondary">FILTERS:</span>
                  <select id="staffFilterDepartment" class="admin-filter-input" style="width:auto;min-width:200px">
                    <option value="">All Departments</option>
                    <?php foreach ($exeat2_departments as $d): ?>
                      <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="button" id="refreshExeat2Staff" class="admin-btn admin-btn-primary admin-btn-sm">
                    <i class="bi bi-arrow-repeat"></i> Refresh
                  </button>
                  <span id="staffLoadMsg" class="small text-secondary d-none"><i class="bi bi-hourglass-split"></i> Loading...</span>
                </div>
              </div>

              <div class="admin-card">
                <div class="admin-card-header">
                  <div class="d-flex align-items-center gap-3">
                    <h3 class="admin-card-title mb-0"><i class="bi bi-database"></i> School Portal Staff</h3>
                    <span id="exeat2StaffCount" class="admin-badge admin-badge-neutral d-none">0</span>
                  </div>
                </div>
                <div class="admin-card-body-flush">
                  <div id="exeat2StaffEmpty" class="admin-empty d-none">
                    <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
                    <div class="admin-empty-title">No Staff Loaded</div>
                    <div class="admin-empty-desc">Select a department filter and click Refresh.</div>
                  </div>
                  <div id="exeat2StaffError" class="admin-empty d-none">
                    <div class="admin-empty-icon"><i class="bi bi-exclamation-triangle text-danger"></i></div>
                    <div class="admin-empty-title">Error</div>
                    <div id="exeat2StaffErrorMsg" class="admin-empty-desc"></div>
                  </div>
                  <div id="exeat2StaffTableWrapper" class="table-responsive d-none">
                    <table class="admin-table" id="exeat2StaffTable">
                      <thead>
                        <tr>
                          <th width="40"><input type="checkbox" id="staffMasterCheckbox" class="form-check-input"></th>
                          <th>Name</th>
                          <th>Email</th>
                          <th>Username</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody id="exeat2StaffTableBody"></tbody>
                    </table>
                  </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center p-3 border-top">
                  <span class="fw-semibold small text-secondary me-2">ACTIONS:</span>
                  <button type="button" id="importStaffSelectedBtn" class="admin-btn admin-btn-primary admin-btn-sm" disabled>
                    <i class="bi bi-person-check"></i> Import Selected
                  </button>
                  <button type="button" id="importStaffAllBtn" class="admin-btn admin-btn-success admin-btn-sm" disabled>
                    <i class="bi bi-person-plus-fill"></i> Import All Matching
                  </button>
                </div>
              </div>
            </div>

            <!-- Import Result Modal -->
            <div class="modal fade" id="staffImportResultModal" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle text-success"></i> Import Complete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body" id="staffImportResultBody"></div>
                  <div class="modal-footer">
                    <button type="button" class="admin-btn admin-btn-secondary" data-bs-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Manual Entry Section -->
            <div id="manualStaffSection">
              <!-- Add Staff Form -->
              <div class="admin-card" id="addStaffForm">
                  <div class="admin-card-header">
                      <h3 class="admin-card-title"><i class="bi bi-person-plus"></i> Add New Examiner</h3>
                  </div>
                  <div class="admin-card-body">
                      <form method="post" class="row g-3">
                          <div class="col-md-4">
                              <div class="admin-form-group">
                                  <label for="name" class="admin-form-label">Full Name</label>
                                  <input type="text" class="admin-input" id="name" name="name"
                                      placeholder="e.g. Dr. Smith" required>
                              </div>
                          </div>
                          <div class="col-md-4">
                              <div class="admin-form-group">
                                  <label for="username" class="admin-form-label">Username</label>
                                  <input type="text" class="admin-input" id="username" name="username"
                                      placeholder="e.g. smith_exam" required>
                              </div>
                          </div>
                          <div class="col-md-4">
                              <div class="admin-form-group">
                                  <label for="password" class="admin-form-label">Password</label>
                                  <input type="text" class="admin-input" id="password" name="password" value="12345"
                                      required>
                              </div>
                          </div>
                          <div class="col-12 text-end">
                              <button type="submit" name="add" class="admin-btn admin-btn-primary"
                                  data-swal-confirm="Create this examiner account?">
                                  <i class="bi bi-check-lg"></i> Create Account
                              </button>
                          </div>
                      </form>
                  </div>
              </div>

            <!-- Staff List -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">
                        <i class="bi bi-people"></i> Active Examiners
                        <span class="admin-badge admin-badge-neutral ms-2"><?= count($staff) ?></span>
                    </h3>
                </div>
                <?php if (empty($staff)): ?>
                    <div class="admin-card-body">
                        <div class="admin-empty">
                            <div class="admin-empty-icon"><i class="bi bi-person-x"></i></div>
                            <h4 class="admin-empty-title">No examiners found</h4>
                            <p class="admin-empty-desc">Add an examiner above to get started.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="admin-card-body-flush">
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th class="actions-cell">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($staff as $s): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <?= htmlspecialchars($s['name']) ?>
                                            </td>
                                            <td><code><?= htmlspecialchars($s['username']) ?></code></td>
                                            <td>
                                                <span class="admin-badge admin-badge-warning">
                                                    <i class="bi bi-person-badge"></i> <?= ucfirst($s['role']) ?>
                                                </span>
                                            </td>
                                            <td class="actions-cell">
                                                <div class="d-flex gap-1 justify-content-end">
                                                    <button class="admin-btn admin-btn-sm admin-btn-outline"
                                                        onclick="editStaff(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['username'], ENT_QUOTES) ?>')"
                                                        title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="post" class="d-inline"
                                                        data-swal="This will reset the password to 12345. Continue?">
                                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                        <button type="submit" name="reset_password"
                                                            class="admin-btn admin-btn-sm admin-btn-outline"
                                                            title="Reset to Default">Reset to Default</button>
                                                    </form>
                                                    <form method="post" class="d-inline"
                                                        data-swal="Are you sure you want to remove this staff member?">
                                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                        <button type="submit" name="delete"
                                                            class="admin-btn admin-btn-sm admin-btn-danger"
                                                            title="Remove"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

            </div> <!-- /manualStaffSection -->

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Examiner Info</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="admin-form-group">
                            <label for="edit_name" class="admin-form-label">Full Name</label>
                            <input type="text" class="admin-input" id="edit_name" name="name" required>
                        </div>
                        <div class="admin-form-group">
                            <label for="edit_username" class="admin-form-label">Username</label>
                            <input type="text" class="admin-input" id="edit_username" name="username" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
                        <button type="submit" name="edit" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin-common.js"></script>
    <script src="assets/js/admin-swal.js"></script>
    <script>
        function editStaff(id, name, username) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_username').value = username;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        // ─── Source selector toggle ───
        document.querySelectorAll('input[name="staff_source"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var showExeat2 = (this.value === 'exeat2');
                document.getElementById('manualStaffSection').classList.toggle('d-none', showExeat2);
                document.getElementById('exeat2StaffSection').classList.toggle('d-none', !showExeat2);
            });
        });

        // ─── exeat2 Staff: fetch and render ───
        var exeat2StaffData = [];

        document.getElementById('refreshExeat2Staff').addEventListener('click', function () {
            var dept = document.getElementById('staffFilterDepartment').value;

            var url = 'includes/fetch_exeat2_staff.php';
            if (dept) url += '?department_id=' + dept;

            var msgEl = document.getElementById('staffLoadMsg');
            msgEl.classList.remove('d-none');

            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    msgEl.classList.add('d-none');
                    if (data.error) {
                        showStaffError(data.error);
                        return;
                    }
                    exeat2StaffData = data;
                    renderStaffTable(data);
                })
                .catch(function (err) {
                    msgEl.classList.add('d-none');
                    showStaffError('Failed to fetch staff: ' + err.message);
                });
        });

        function showStaffError(msg) {
            document.getElementById('exeat2StaffEmpty').classList.add('d-none');
            document.getElementById('exeat2StaffTableWrapper').classList.add('d-none');
            document.getElementById('exeat2StaffErrorMsg').textContent = msg;
            document.getElementById('exeat2StaffError').classList.remove('d-none');
            document.getElementById('exeat2StaffCount').classList.add('d-none');
            document.getElementById('importStaffSelectedBtn').disabled = true;
            document.getElementById('importStaffAllBtn').disabled = true;
        }

        function renderStaffTable(data) {
            document.getElementById('exeat2StaffError').classList.add('d-none');
            var tbody = document.getElementById('exeat2StaffTableBody');
            tbody.innerHTML = '';

            if (!data || data.length === 0) {
                document.getElementById('exeat2StaffEmpty').classList.remove('d-none');
                document.getElementById('exeat2StaffTableWrapper').classList.add('d-none');
                document.getElementById('exeat2StaffCount').classList.add('d-none');
                document.getElementById('importStaffSelectedBtn').disabled = true;
                document.getElementById('importStaffAllBtn').disabled = true;
                return;
            }

            document.getElementById('exeat2StaffEmpty').classList.add('d-none');
            document.getElementById('exeat2StaffTableWrapper').classList.remove('d-none');
            document.getElementById('exeat2StaffCount').classList.remove('d-none');
            document.getElementById('exeat2StaffCount').textContent = data.length + ' staff';
            document.getElementById('importStaffSelectedBtn').disabled = false;
            document.getElementById('importStaffAllBtn').disabled = false;

            var labels = {
                'new': '<span class="admin-badge admin-badge-primary"><i class="bi bi-plus-circle"></i> New</span>',
                'exists_exeat2': '<span class="admin-badge admin-badge-info"><i class="bi bi-arrow-repeat"></i> Exists (exeat2)</span>',
                'exists_manual': '<span class="admin-badge admin-badge-warning"><i class="bi bi-exclamation-circle"></i> Exists (manual)</span>',
                'exists_upload': '<span class="admin-badge admin-badge-warning"><i class="bi bi-exclamation-circle"></i> Exists (upload)</span>'
            };

            data.forEach(function (s) {
                var tr = document.createElement('tr');
                var label = labels[s.status] || '<span class="admin-badge admin-badge-neutral">Unknown</span>';
                var canSelect = (s.status !== 'assigned');
                tr.innerHTML =
                    '<td><input type="checkbox" class="staff-exeat2-checkbox form-check-input" value="' + s.exeat2_staff_id + '"' + (canSelect ? '' : ' disabled') + '></td>' +
                    '<td>' + escHtml(s.name) + '</td>' +
                    '<td>' + escHtml(s.email || '') + '</td>' +
                    '<td><code>' + escHtml(s.username) + '</code></td>' +
                    '<td>' + label + '</td>';
                tbody.appendChild(tr);
            });
        }

        function escHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(s));
            return d.innerHTML;
        }

        // ─── Staff master checkbox ───
        document.getElementById('staffMasterCheckbox').addEventListener('change', function () {
            document.querySelectorAll('.staff-exeat2-checkbox:not(:disabled)').forEach(function (cb) {
                cb.checked = this.checked;
            }.bind(this));
        });

        // ─── Import Selected Staff ───
        document.getElementById('importStaffSelectedBtn').addEventListener('click', function () {
            var checked = document.querySelectorAll('.staff-exeat2-checkbox:checked');
            if (checked.length === 0) {
                Swal.fire({ title: 'No Selection', text: 'Select at least one staff member.', icon: 'warning', confirmButtonColor: '#198754' });
                return;
            }
            var ids = [];
            checked.forEach(function (cb) { ids.push(parseInt(cb.value)); });
            doStaffImport(ids);
        });

        // ─── Import All Matching Staff ───
        document.getElementById('importStaffAllBtn').addEventListener('click', function () {
            var ids = exeat2StaffData.map(function (s) { return s.exeat2_staff_id; });
            Swal.fire({
                title: 'Import All?',
                text: 'This will import ' + ids.length + ' staff member(s) from the school portal.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                confirmButtonText: 'Yes, import all'
            }).then(function (result) {
                if (result.isConfirmed) doStaffImport(ids);
            });
        });

        function doStaffImport(ids) {
            var btn = document.getElementById('importStaffSelectedBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="btn-spinner"></span> Importing...';
            document.getElementById('importStaffAllBtn').disabled = true;

            fetch('includes/import_exeat2_staff.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'staff_ids=' + encodeURIComponent(JSON.stringify(ids))
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-person-check"></i> Import Selected';
                document.getElementById('importStaffAllBtn').disabled = false;

                var html = '';
                html += '<div class="mb-3">';
                html += '<div class="d-flex justify-content-around text-center">';
                html += '<div><span class="fs-4 text-success">' + res.imported + '</span><br><small>Newly Imported</small></div>';
                html += '<div><span class="fs-4 text-info">' + res.updated + '</span><br><small>Updated</small></div>';
                html += '<div><span class="fs-4 text-warning">' + (res.skipped_manual + res.skipped_upload) + '</span><br><small>Skipped</small></div>';
                html += '</div></div>';
                html += '<p class="text-center fw-bold">Total: ' + res.total + ' staff processed</p>';

                if (res.details && res.details.length > 0) {
                    html += '<hr><ul class="small" style="max-height:200px;overflow-y:auto">';
                    res.details.forEach(function (d) {
                        var icon = d.status === 'imported' ? '✅' : d.status === 'updated' ? '🔄' : '⏭️';
                        html += '<li>' + icon + ' ' + escHtml(d.name) + ' (' + escHtml(d.username) + ') — ' + d.status + '</li>';
                    });
                    html += '</ul>';
                }

                document.getElementById('staffImportResultBody').innerHTML = html;
                var modal = new bootstrap.Modal(document.getElementById('staffImportResultModal'));
                modal.show();

                document.getElementById('refreshExeat2Staff').click();
            })
            .catch(function (err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-person-check"></i> Import Selected';
                document.getElementById('importStaffAllBtn').disabled = false;
                Swal.fire({ title: 'Import Error', text: err.message, icon: 'error', confirmButtonColor: '#198754' });
            });
        }
    </script>
</body>

</html>
