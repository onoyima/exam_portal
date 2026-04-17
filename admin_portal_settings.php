<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$success = '';
$error = '';

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    
    // Handle File Upload for portal_image
    if (!empty($_FILES['portal_image']['name'])) {
        $target_dir = "assets/img/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES['portal_image']['name'], PATHINFO_EXTENSION));
        $new_name = "login_bg_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_name;
        
        if (move_uploaded_file($_FILES['portal_image']['tmp_name'], $target_file)) {
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('portal_image', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$target_file, $target_file]);
        }
    }
    
    $success = 'Settings updated successfully.';
}

// Fetch current settings
$settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Portal Customization - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <style>
        .preview-img { width: 100%; max-width: 300px; border-radius: 12px; border: 1px solid #ddd; margin-top: 10px; }
        .settings-card { border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid px-lg-5">
            <div class="d-flex align-items-center justify-content-between mb-4 mt-3">
                <div>
                    <h2 class="fw-bold mb-0">Portal Customization</h2>
                    <p class="text-muted">Manage branding, images, and onboarding instructions.</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="row g-4">
                    <!-- Basic Branding -->
                    <div class="col-lg-7">
                        <div class="card settings-card p-4">
                            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                                <i class="bx bx-palette text-primary"></i> Branding & Title
                            </h5>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">Portal Title (Main Heading)</label>
                                <input type="text" name="settings[portal_title]" class="form-control rounded-3 py-2" 
                                       value="<?= htmlspecialchars($settings['portal_title'] ?? '') ?>" required>
                                <div class="form-text">This title appears on both Staff and Student login pages.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">Login Background Image</label>
                                <input type="file" name="portal_image" class="form-control rounded-3" accept="image/*">
                                <?php if (!empty($settings['portal_image'])): ?>
                                    <div class="mt-3">
                                        <div class="small text-muted mb-1">Current Preview:</div>
                                        <img src="<?= htmlspecialchars($settings['portal_image']) ?>" class="preview-img" alt="Current BG">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions / Content -->
                    <div class="col-lg-5">
                        <div class="card settings-card p-4 h-100">
                            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                                <i class="bx bx-message-dots text-primary"></i> Page Content
                            </h5>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">Student Login Description</label>
                                <textarea name="settings[student_login_content]" class="form-control rounded-3" rows="4"><?= htmlspecialchars($settings['student_login_content'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">Staff Login Description</label>
                                <textarea name="settings[staff_login_content]" class="form-control rounded-3" rows="4"><?= htmlspecialchars($settings['staff_login_content'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-4">
                        <div class="card settings-card bg-primary text-white p-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bx bx-save fs-1"></i>
                                    <div>
                                        <h5 class="mb-0 fw-bold">Publish Changes</h5>
                                        <p class="mb-0 opacity-75 small">All updates will be instantly visible on public login pages.</p>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-light btn-lg px-5 fw-bold rounded-pill shadow">Save All Settings</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
