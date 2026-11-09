<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $staff_id = intval($_POST['staff_id']);
    $new_role = $_POST['new_role'] ?? '';
    if (!in_array($new_role, ['admin', 'examiner'])) {
        $error = 'Invalid role specified.';
    } else {
        try {
            $stmt = $db->prepare("UPDATE examiners SET role = ? WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$new_role, $staff_id]);
            if ($stmt->rowCount()) {
                $success = 'Role updated successfully.';
            } else {
                $error = 'Staff not found or no change needed.';
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$examiners = $db->query("SELECT id, name, username, email, role, source FROM examiners WHERE deleted_at IS NULL ORDER BY role ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Role Assignment - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <style>
        .role-badge-admin {
            background: rgba(220, 38, 38, 0.12);
            color: #dc2626;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .role-badge-examiner {
            background: rgba(37, 99, 235, 0.1);
            color: #2563eb;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .role-select {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 30px 6px 12px;
            font-size: 13px;
            font-weight: 500;
            background: #fff;
            cursor: pointer;
            transition: all 0.15s ease;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }
        .role-select:hover {
            border-color: #22c55e;
        }
        .role-select:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
        }
        .source-tag {
            font-size: 12px;
            color: #94a3b8;
            background: #f8fafc;
            padding: 2px 10px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <nav class="admin-navbar">
            <div class="admin-navbar-inner">
                <div class="admin-navbar-start">
                    <button class="admin-navbar-toggle d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
                    <a href="admin_dashboard" class="admin-back-btn"><i class="bi bi-arrow-left"></i></a>
                    <h1 class="admin-navbar-title">Role Assignment</h1>
                </div>
                <div class="admin-navbar-end">
                    <div class="admin-user-badge">
                        <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():&quot;?&quot;}}"></span>
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
                    <li class="current" aria-current="page">Role Assignment</li>
                </ol>
            </nav>
            <div class="admin-page-header">
                <div>
                    <h2 class="admin-page-title"><i class="bi bi-shield-lock"></i> Role Assignment</h2>
                    <p class="text-muted mb-0 small">Manage staff permissions — assign <strong>Admin</strong> or <strong>Examiner</strong> roles.</p>
                </div>
                <div>
                    <span class="admin-badge admin-badge-neutral px-3 py-2"><i class="bi bi-people"></i> <?= count($examiners) ?> staff</span>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="admin-alert admin-alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                    <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="admin-alert admin-alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                    <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title"><i class="bi bi-shield-check"></i> Staff Permissions</h3>
                    <div class="d-flex gap-2">
                        <input type="text" id="roleSearch" class="admin-filter-input" style="width:240px" placeholder="Search staff...">
                    </div>
                </div>
                <div class="admin-card-body-flush">
                    <?php if (empty($examiners)): ?>
                        <div class="admin-empty">
                            <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
                            <div class="admin-empty-title">No Staff Found</div>
                            <div class="admin-empty-desc"><a href="admin_staff" class="text-success">Add or import staff</a> to assign roles.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table" id="rolesTable">
                                <thead>
                                    <tr>
                                        <th width="50">Photo</th>
                                        <th>Staff Member</th>
                                        <th>Current Role</th>
                                        <th>Source</th>
                                        <th>Change Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($examiners as $staff): ?>
                                        <tr>
                                            <td class="text-center">
                                                <img src="passport_image.php?type=examiner&id=<?= $staff['id'] ?>"
                                                     class="rounded-circle" style="width:36px;height:36px;object-fit:cover"
                                                     alt="" onerror="this.style.display='none'">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($staff['name']) ?></div>
                                                        <div class="small text-secondary">
                                                            <code><?= htmlspecialchars($staff['username']) ?></code>
                                                            <?php if ($staff['email']): ?>
                                                                &middot; <?= htmlspecialchars($staff['email']) ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($staff['role'] === 'admin'): ?>
                                                    <span class="role-badge-admin"><i class="bi bi-shield-fill"></i> Admin</span>
                                                    <?php else: ?>
                                                    <span class="role-badge-examiner"><i class="bi bi-person-badge"></i> Examiner</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="source-tag"><?= htmlspecialchars($staff['source'] ?? '—') ?></span></td>
                                            <td>
                                                <form method="POST" class="d-flex align-items-center gap-2" onsubmit="return confirmRoleChange(this);">
                                                    <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                                    <select name="new_role" class="role-select">
                                                        <option value="examiner" <?= $staff['role'] === 'examiner' ? 'selected' : '' ?>>Examiner</option>
                                                        <option value="admin" <?= $staff['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    </select>
                                                    <button type="submit" name="update_role" class="admin-btn admin-btn-sm admin-btn-primary" title="Save">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
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
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin-common.js"></script>
    <script src="assets/js/admin-swal.js"></script>
    <script>
        function confirmRoleChange(form) {
            var select = form.querySelector('select[name="new_role"]');
            var role = select.options[select.selectedIndex].text;
            var row = form.closest('tr');
            var name = row.querySelector('.fw-semibold').textContent.trim();
            return Swal.fire({
                title: 'Change Role?',
                html: 'Set <strong>' + name + '</strong> to <strong>' + role + '</strong>?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                confirmButtonText: 'Yes, update'
            }).then(function (result) {
                return result.isConfirmed;
            });
        }

        document.getElementById('roleSearch').addEventListener('keyup', function () {
            var q = this.value.toLowerCase();
            document.querySelectorAll('#rolesTable tbody tr').forEach(function (tr) {
                tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
            });
        });
    </script>
</body>
</html>
