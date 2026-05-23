<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
$name = $_SESSION['user'] ?? 'Admin';
$back_url = 'admin_dashboard';

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
    </script>
</body>

</html>
