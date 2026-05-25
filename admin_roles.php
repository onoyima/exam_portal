<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';
$success = '';
$error = '';

// Handle role update
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <nav class="admin-navbar">
            <div class="admin-navbar-inner">
                <div class="admin-navbar-start">
                    <button class="admin-navbar-toggle d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
                    <h1 class="admin-navbar-title">Role Assignment</h1>
                </div>
                <div class="admin-navbar-end">
                    <div class="admin-user-badge">
                        <span class="admin-user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></span>
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
                    <p class="text-muted mb-0 small">Assign <strong>admin</strong> or <strong>examiner</strong> roles to staff members.</p>
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
                    <h3 class="admin-card-title"><i class="bi bi-people"></i> All Staff</h3>
                    <span class="admin-badge admin-badge-neutral"><?= count($examiners) ?> staff</span>
                </div>
                <div class="admin-card-body-flush">
                    <?php if (empty($examiners)): ?>
                        <div class="admin-empty">
                            <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
                            <div class="admin-empty-title">No Staff Found</div>
                            <div class="admin-empty-desc">Import or add staff members first.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table" id="rolesTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Current Role</th>
                                        <th>Source</th>
                                        <th width="200">Change Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($examiners as $staff): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($staff['name']) ?></td>
                                            <td><code><?= htmlspecialchars($staff['username']) ?></code></td>
                                            <td><?= htmlspecialchars($staff['email'] ?? '—') ?></td>
                                            <td>
                                                <?php if ($staff['role'] === 'admin'): ?>
                                                    <span class="admin-badge admin-badge-danger"><i class="bi bi-shield-fill"></i> Admin</span>
                                                <?php else: ?>
                                                    <span class="admin-badge admin-badge-primary"><i class="bi bi-person"></i> Examiner</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="small text-secondary"><?= htmlspecialchars($staff['source'] ?? '—') ?></span></td>
                                            <td>
                                                <form method="POST" class="d-flex gap-2" onsubmit="return confirmRoleChange(this);">
                                                    <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                                    <select name="new_role" class="admin-filter-input" style="width:auto;min-width:120px">
                                                        <option value="examiner" <?= $staff['role'] === 'examiner' ? 'selected' : '' ?>>Examiner</option>
                                                        <option value="admin" <?= $staff['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    </select>
                                                    <button type="submit" name="update_role" class="admin-btn admin-btn-sm admin-btn-primary">
                                                        <i class="bi bi-check-lg"></i> Update
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
            var current = select.options[select.selectedIndex].text;
            var staffName = form.closest('tr').querySelector('td:first-child').textContent.trim();
            return Swal.fire({
                title: 'Change Role?',
                html: 'Set <strong>' + staffName + '</strong> to <strong>' + current + '</strong>?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                confirmButtonText: 'Yes, update role'
            }).then(function (result) {
                return result.isConfirmed;
            });
        }
    </script>
</body>
</html>
