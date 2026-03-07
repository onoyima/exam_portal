<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
$name = $_SESSION['user'] ?? 'Admin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $db->prepare("INSERT INTO examiners (name, username, password, role) VALUES (?, ?, ?, 'examiner')");
        $stmt->execute([$_POST['name'], $_POST['username'], $_POST['password']]);
        $success = "Staff member added successfully!";
    } elseif (isset($_POST['delete'])) {
        $stmt = $db->prepare("DELETE FROM examiners WHERE id = ? AND role != 'admin'");
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
$staff = $db->query("SELECT * FROM examiners WHERE role = 'examiner' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Manage Staff - Assessment Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <nav class="navbar navbar-light bg-white shadow-sm mb-4">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><span
                            class="navbar-toggler-icon"></span></button>
                    <a href="admin_dashboard" class="btn btn-outline-secondary me-2">Back</a>
                    <span class="navbar-brand fw-bold">Manage Staff / Examiners</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="profile-icon">
                        <?= strtoupper(substr($name, 0, 1)) ?>
                    </span>
                    <span class="fw-semibold">
                        <?= htmlspecialchars($name) ?>
                    </span>
                    <a href="logout" class="btn btn-outline-danger ms-2">Logout</a>
                </div>
            </div>
        </nav>

        <div class="container-xl">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Add Staff Form -->
            <div class="card p-4 shadow-sm mb-4 border-0 rounded-4">
                <h4 class="mb-3 fw-bold text-primary">Add New Examiner</h4>
                <form method="post" class="row g-3">
                    <div class="col-md-4">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control rounded-pill" id="name" name="name"
                            placeholder="e.g. Dr. Smith" required>
                    </div>
                    <div class="col-md-4">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control rounded-pill" id="username" name="username"
                            placeholder="e.g. smith_exam" required>
                    </div>
                    <div class="col-md-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="text" class="form-control rounded-pill" id="password" name="password" value="12345"
                            required>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" name="add" class="btn btn-primary px-4 rounded-pill">Create
                            Account</button>
                    </div>
                </form>
            </div>

            <!-- Staff List -->
            <div class="card p-4 shadow-sm border-0 rounded-4">
                <h4 class="mb-3 fw-bold">Active Examiners (
                    <?= count($staff) ?>)
                </h4>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff as $s): ?>
                                <tr>
                                    <td class="fw-bold">
                                        <?= htmlspecialchars($s['name']) ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($s['username']) ?></code></td>
                                    <td><span class="badge bg-secondary rounded-pill">
                                            <?= ucfirst($s['role']) ?>
                                        </span></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary"
                                                onclick="editStaff(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['username'], ENT_QUOTES) ?>')">
                                                Edit
                                            </button>
                                            <form method="post" class="d-inline"
                                                onsubmit="return confirm('Reset password to 12345 for this examiner?')">
                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                <button type="submit" name="reset_password"
                                                    class="btn btn-sm btn-outline-warning">Reset PW</button>
                                            </form>
                                            <form method="post" class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to remove this staff member?')">
                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                <button type="submit" name="delete"
                                                    class="btn btn-sm btn-outline-danger">Remove</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($staff)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No examiners found. Add one above.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Examiner Info</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control rounded-pill" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control rounded-pill" id="edit_username" name="username"
                                required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit" class="btn btn-primary rounded-pill px-4">Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        const sidebar = document.getElementById('sidebarMenu');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.onclick = function () {
                sidebar.classList.toggle('show');
                sidebarBackdrop.classList.toggle('show');
            };
            sidebarBackdrop.onclick = function () {
                sidebar.classList.remove('show');
                sidebarBackdrop.classList.remove('show');
            };
        }

        // Edit staff function
        function editStaff(id, name, username) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_username').value = username;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
    </script>
<!-- sweetalert_custom_injected_v1 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Override global alert
        window.alert = function(msg) {
            Swal.fire({
                title: 'Message',
                text: msg,
                icon: 'info',
                confirmButtonColor: '#092c19'
            });
        };

        // Intercept inline onsubmit confirms
        document.querySelectorAll('form[onsubmit*="return confirm"]').forEach(el => {
            let match = el.getAttribute('onsubmit').match(/return confirm\(['"`](.*?)['"`]\)/);
            if(match) {
                let msg = match[1];
                el.removeAttribute('onsubmit');
                el.addEventListener('submit', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: msg,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#092c19',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, proceed'
                    }).then((result) => {
                        if(result.isConfirmed) {
                            let btn = el.querySelector('button[type="submit"]:hover, input[type="submit"]:hover') 
                                      || el.querySelector('button[type="submit"], input[type="submit"]');
                            if(btn && btn.name) {
                                let hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = btn.name;
                                hidden.value = btn.value || '1';
                                el.appendChild(hidden);
                            }
                            HTMLFormElement.prototype.submit.call(el);
                        }
                    });
                });
            }
        });

        // Intercept inline onclick confirms for anchors
        document.querySelectorAll('a[onclick*="return confirm"], button[onclick*="return confirm"]').forEach(el => {
            let match = el.getAttribute('onclick').match(/return confirm\(['"`](.*?)['"`]\)/);
            if(match) {
                let msg = match[1];
                el.removeAttribute('onclick');
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: msg,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#092c19',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, proceed'
                    }).then((result) => {
                        if(result.isConfirmed) {
                            if(el.tagName === 'A' && el.href) {
                                window.location.href = el.href;
                            } else if(el.tagName === 'BUTTON') {
                                // if it's a submit button inside a form
                                if(el.type === 'submit' && el.form) {
                                    if(el.name) {
                                        let hidden = document.createElement('input');
                                        hidden.type = 'hidden';
                                        hidden.name = el.name;
                                        hidden.value = el.value || '1';
                                        el.form.appendChild(hidden);
                                    }
                                    HTMLFormElement.prototype.submit.call(el.form);
                                }
                            }
                        }
                    });
                });
            }
        });
    });
</script>
</body>


</html>
