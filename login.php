<?php
require 'auth.php';
// If already logged in as admin or examiner, redirect to dashboard
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard");
        exit;
    } elseif ($_SESSION['role'] === 'examiner') {
        header("Location: assess");
        exit;
    }
}
require 'db.php';

// Fetch dynamic settings
$settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$portal_title = $settings['portal_title'] ?? 'Veritas Exam Portal';
$portal_image = $settings['portal_image'] ?? 'assets/img/login_bg.jpg';
$staff_content = $settings['staff_login_content'] ?? 'Authorized Staff only. Enter your administrative credentials to manage sessions and grades.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM examiners WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        $db->prepare("INSERT INTO login_history (username, user_type, ip_address, user_agent, status) VALUES (?, ?, ?, ?, 'success')")
           ->execute([$username, $user['role'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);

        header("Location: " . ($user['role'] === 'admin' ? 'admin_dashboard' : 'assess'));
        exit;
    }
    $error = "Invalid username or password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Staff Login - <?= htmlspecialchars($portal_title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #092c19;
            --accent: #22c55e;
            --dark: #060d16;
        }
        body, html { height: 100%; margin: 0; font-family: 'Outfit', sans-serif; overflow: hidden; background: var(--dark); }
        
        .login-wrap { display: flex; height: 100vh; width: 100%; }
        
        /* Left Column: Image and Title */
        .login-info {
            flex: 1.2;
            position: relative;
            background: url('<?= htmlspecialchars($portal_image) ?>') no-repeat center center;
            background-size: cover;
            display: flex;
            align-items: flex-end;
            padding: 60px;
            overflow: hidden;
            display: none; /* Hidden on mobile */
        }
        @media (min-width: 992px) { .login-info { display: flex; } }
        
        .login-info::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(6,13,22,0.9) 0%, rgba(6,13,22,0.4) 50%, transparent 100%);
        }
        
        .info-content { position: relative; z-index: 10; max-width: 600px; }
        .info-content h1 { font-size: 3.5rem; font-weight: 800; color: #fff; margin-bottom: 20px; text-transform: uppercase; letter-spacing: -1px; line-height: 1; }
        .info-content p { font-size: 1.2rem; color: rgba(255,255,255,0.7); line-height: 1.6; border-left: 4px solid var(--accent); padding-left: 20px; }
        
        /* Right Column: Form */
        .login-form-area {
            flex: 1;
            background: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
        }
        
        .form-container { width: 100%; max-width: 420px; }
        .brand-logo { margin-bottom: 40px; display: flex; align-items: center; gap: 12px; }
        .brand-logo img { width: 45px; height: 45px; }
        .brand-logo span { font-weight: 700; font-size: 1.2rem; color: #fff; letter-spacing: 1px; }
        
        .card-login { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 40px; backdrop-filter: blur(20px); }
        .form-label { color: rgba(255,255,255,0.5); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
        .form-control { 
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); 
            color: #fff; border-radius: 12px; padding: 12px 16px; transition: all 0.3s;
        }
        .form-control:focus { background: rgba(255,255,255,0.08); border-color: var(--accent); box-shadow: 0 0 0 4px rgba(34,197,94,0.1); color: #fff; }
        
        .btn-login { 
            background: var(--accent); border: none; border-radius: 12px; padding: 14px; 
            font-weight: 700; color: var(--dark); transition: all 0.3s; margin-top: 10px;
        }
        .btn-login:hover { background: #4ade80; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(34,197,94,0.3); }
        
        .footer-links { margin-top: 30px; text-align: center; font-size: 0.9rem; color: rgba(255,255,255,0.4); }
        .footer-links a { color: var(--accent); text-decoration: none; font-weight: 600; }
        
        /* Decorative elements */
        .blob {
            position: absolute; width: 300px; height: 300px; background: var(--accent);
            filter: blur(120px); opacity: 0.05; border-radius: 50%; z-index: 0;
        }
        .blob-1 { top: -100px; right: -100px; }
        .blob-2 { bottom: -100px; left: -100px; }
    </style>
</head>
<body>

    <div class="login-wrap">
        <div class="login-info">
            <div class="info-content">
                <h1><?= htmlspecialchars($portal_title) ?></h1>
                <p><?= htmlspecialchars($staff_content) ?></p>
            </div>
        </div>
        
        <div class="login-form-area">
            <div class="blob blob-1"></div>
            <div class="blob blob-2"></div>
            
            <div class="form-container">
                <div class="brand-logo">
                    <img src="assets/favicon.ico" alt="Logo">
                    <span>STAFF PORTAL</span>
                </div>
                
                <div class="card-login">
                    <h3 class="fw-bold text-white mb-2">Welcome Back</h3>
                    <p class="text-muted small mb-4">Please enter your staff credentials</p>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2">
                            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="staff_id" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <button type="submit" class="btn btn-login w-100">SIGN IN TO PORTAL</button>
                    </form>
                </div>
                
                <div class="footer-links">
                    Are you a student? <a href="student_login">Switch to Student Portal</a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
