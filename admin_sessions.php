<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$success = '';
$error = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_session'])) {
        $name = trim($_POST['session_name']);
        if (!empty($name)) {
            $stmt = $db->prepare("INSERT INTO academic_sessions (session_name, is_active) VALUES (?, 0)");
            $stmt->execute([$name]);
            $success = "Session '$name' created successfully.";
        } else {
            $error = "Session name cannot be empty.";
        }
    } elseif (isset($_POST['set_active'])) {
        $id = intval($_POST['session_id']);
        // Deactivate all others first
        $db->query("UPDATE academic_sessions SET is_active = 0");
        $stmt = $db->prepare("UPDATE academic_sessions SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Active session updated.";
    } elseif (isset($_POST['delete_session'])) {
        $id = intval($_POST['session_id']);
        // Check if exams are linked
        $check = $db->prepare("SELECT COUNT(*) FROM exams WHERE session_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $error = "Cannot delete session with linked exams. Reassign exams first.";
        } else {
            $stmt = $db->prepare("DELETE FROM academic_sessions WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Session deleted.";
        }
    }
}

$sessions = $db->query("SELECT * FROM academic_sessions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Academic Sessions - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content">
        <nav class="navbar navbar-light bg-white shadow-sm mb-4">
            <div class="container-fluid">
                <span class="navbar-brand fw-bold text-primary"><i class="bi bi-calendar-event me-2"></i>Academic Sessions</span>
            </div>
        </nav>

        <div class="container-xl">
            <?php if ($success): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4"><?= $error ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Create Session -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <h5 class="fw-bold mb-3">Add New Session</h5>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Session Name</label>
                                <input type="text" name="session_name" class="form-control rounded-3" placeholder="e.g. 2023/2024 First Semester" required>
                            </div>
                            <button type="submit" name="create_session" class="btn btn-primary w-100 rounded-pill fw-bold">
                                <i class="bi bi-plus-lg me-1"></i> Create Session
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Session List -->
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white border-0 p-4">
                            <h5 class="mb-0 fw-bold">All Academic Sessions</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-muted small uppercase">
                                    <tr>
                                        <th class="ps-4">Session Name</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $s): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold"><?= htmlspecialchars($s['session_name']) ?></td>
                                        <td>
                                            <?php if ($s['is_active']): ?>
                                                <span class="badge bg-success-subtle text-success rounded-pill px-3">ACTIVE</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted rounded-pill px-3">INACTIVE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end gap-2">
                                                <?php if (!$s['is_active']): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                                    <button type="submit" name="set_active" class="btn btn-sm btn-outline-success rounded-pill px-3">Make Active</button>
                                                </form>
                                                <?php endif; ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this session?')">
                                                    <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                                    <button type="submit" name="delete_session" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
