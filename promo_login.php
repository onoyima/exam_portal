<?php
/**
 * promo_login.php — Staff Promotional Examination login
 *
 * Staff authenticate with their staff email only (no password). The email must
 * end with @veritas.edu.ng, exist in promo_staff (active), and be enrolled in
 * at least one active promo exam. On success a session with role `promo_staff`
 * is created and the user is sent to promo_dashboard.
 *
 * Isolated from the admin/examiner/student login systems.
 */

require 'auth.php';
require 'db.php';
require_once __DIR__ . '/includes/promo_schema.php';
promo_ensure_schema($db);

// Already logged in as promo staff → dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'promo_staff') {
    header("Location: promo_dashboard");
    exit;
}

// Other logged-in roles cannot reach the promo login
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'examiner', 'student'], true)) {
    $_SESSION['auth_error'] = "Please sign out before opening the staff promotional portal.";
    header("Location: index");
    exit;
}

$error = '';
$settings = [];
try {
    $settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}
$portal_title = $settings['portal_title'] ?? 'Veritas Exam Portal';
$portal_logo = $settings['portal_logo'] ?? '';
$portal_favicon = $settings['portal_favicon'] ?? 'assets/favicon.ico';
$portal_image = isset($settings['portal_image']) ? $settings['portal_image'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No CSRF on login: a page left open must not refuse correct credentials.
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your staff email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid staff email address.';
    } elseif (stripos($email, '@veritas.edu.ng') === false || substr($email, -strlen('@veritas.edu.ng')) !== '@veritas.edu.ng') {
        $error = 'Only @veritas.edu.ng staff emails are accepted for promotional exams.';
    } else {
        $emailLower = strtolower($email);
        $stmt = $db->prepare("SELECT * FROM promo_staff WHERE LOWER(email) = ? AND is_active = 1");
        $stmt->execute([$emailLower]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$staff) {
            $error = 'No active promotional exam account found for this email. Contact your administrator.';
        } else {
            // Staff must be enrolled in at least one active exam
            $enrolled = $db->prepare("
                SELECT pe.id FROM promo_exams pe
                JOIN promo_exam_staff pes ON pes.exam_id = pe.id AND pes.staff_id = ?
                WHERE pe.is_active = 1
                  AND (pe.start_time IS NULL OR pe.start_time <= NOW())
                  AND (pe.end_time IS NULL OR pe.end_time >= NOW())
                LIMIT 1
            ");
            $enrolled->execute([(int) $staff['id']]);
            if (!$enrolled->fetchColumn()) {
                $error = 'Your account is active but you are not enrolled in any running promotional exam.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = $staff['name'];
                $_SESSION['role'] = 'promo_staff';
                $_SESSION['promo_staff_id'] = (int) $staff['id'];
                $_SESSION['promo_staff_email'] = $staff['email'];

                try {
                    $db->prepare("INSERT INTO login_history (username, user_type, ip_address, user_agent, status)
                        VALUES (?, 'promo_staff', ?, ?, 'success')")
                        ->execute([$staff['email'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                } catch (Exception $e) {
                    error_log("Promo login history insert failed: " . $e->getMessage());
                }

                header("Location: promo_dashboard");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Staff Promotional Login - <?= htmlspecialchars($portal_title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= htmlspecialchars($portal_favicon ?? 'assets/favicon.ico') ?>" type="image/x-icon">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/vendor/fonts/fonts.css" rel="stylesheet">
    <style>
        :root { --primary: #092c19; --accent: #22c55e; --dark: #060d16; }
        html, body { height: 100%; margin: 0; font-family: 'Outfit', sans-serif; background: var(--dark); color: #fff; overflow: hidden; }
        .login-wrap { display: flex; height: 100vh; width: 100%; }
        .login-info { flex: 1.2; position: relative; background: url('<?= !empty($portal_image) ? htmlspecialchars($portal_image) : 'assets/favicon.ico' ?>') no-repeat center center; background-size: cover; display: flex; align-items: flex-end; padding: 60px; overflow: hidden; display: none; }
        @media (min-width: 992px) { .login-info { display: flex; } }
        .login-info::before { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(6,13,22,0.9) 0%, rgba(6,13,22,0.4) 50%, transparent 100%); }
        .info-content { position: relative; z-index: 10; max-width: 600px; }
        .info-content h1 { font-size: 3rem; font-weight: 800; color: #fff; margin-bottom: 16px; text-transform: uppercase; letter-spacing: -1px; line-height: 1; }
        .info-content p { font-size: 1.1rem; color: rgba(255,255,255,0.7); line-height: 1.6; border-left: 4px solid var(--accent); padding-left: 20px; }
        .login-form-area { flex: 1; background: var(--dark); display: flex; align-items: center; justify-content: center; padding: 40px; }
        .form-container { width: 100%; max-width: 400px; }
        .brand-logo { margin-bottom: 32px; display: flex; align-items: center; gap: 10px; }
        .brand-logo img { width: 40px; height: 40px; }
        .brand-logo span { font-weight: 700; font-size: 1.1rem; color: #fff; letter-spacing: 1px; }
        .card-login { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 36px; backdrop-filter: blur(20px); }
        .form-label { color: rgba(255,255,255,0.5); font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 6px; }
        .form-control { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 10px; padding: 12px 16px; width: 100%; box-sizing: border-box; }
        .form-control:focus { background: rgba(255,255,255,0.08); border-color: var(--accent); outline: none; }
        .btn-login { background: var(--accent); border: none; border-radius: 10px; padding: 14px; width: 100%; font-weight: 700; color: var(--dark); cursor: pointer; margin-top: 16px; }
        .btn-login:hover { background: #4ade80; }
        .footer-links { margin-top: 24px; text-align: center; font-size: 0.85rem; color: rgba(255,255,255,0.4); }
        .footer-links a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .alert-danger { background: rgba(220,53,69,0.12); border: 1px solid rgba(220,53,69,0.3); color: #f8a7ae; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem; }
        .hint { font-size: 0.8rem; color: rgba(255,255,255,0.45); margin-top: 8px; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-info">
        <div class="info-content">
            <h1><?= htmlspecialchars($portal_title) ?></h1>
            <p>Staff Promotional Examination Portal — sign in with your staff email only.</p>
        </div>
    </div>

    <div class="login-form-area">
        <div class="form-container">
            <div class="brand-logo">
                <img src="assets/favicon.ico" alt="Logo">
                <span>STAFF PROMO PORTAL</span>
            </div>

            <div class="card-login">
                <h3 class="fw-bold text-white mb-2">Welcome Back</h3>
                <p class="text-muted small mb-4">Enter your staff email to open your promotional exam.</p>

                <?php if ($error): ?>
                    <div class="alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label" for="email">Staff Email</label>
                        <input type="email" name="email" id="email" class="form-control" placeholder="name@veritas.edu.ng" required autofocus>
                        <div class="hint">Only @veritas.edu.ng emails are accepted.</div>
                    </div>
                    <button type="submit" class="btn-login">Open My Promotional Exam</button>
                </form>

                <div class="footer-links">
                    Administrative Staff? <a href="login">Staff Login Panel</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
