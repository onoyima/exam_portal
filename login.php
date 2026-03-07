<?php
require 'auth.php';
// If already logged in as admin or examiner, redirect to dashboard
if (isset($_SESSION['role'])) {
  if ($_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit;
  } elseif ($_SESSION['role'] === 'examiner') {
    header("Location: assess.php");
    exit;
  }
}
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'];
  $password = $_POST['password'];

  // Check examiner table (includes both admin and examiner users)
  $stmt = $db->prepare("SELECT * FROM examiners WHERE username = ? AND password = ?");
  $stmt->execute([$username, $password]);
  $user = $stmt->fetch();

  if ($user) {
    $_SESSION['user'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $user_type = $user['role'];
    $redirect_url = ($user['role'] === 'admin') ? 'admin_dashboard.php' : 'assess.php';

    // Log successful login
    $db->exec("
            CREATE TABLE IF NOT EXISTS login_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                username VARCHAR(100) NOT NULL,
                user_type ENUM('admin', 'examiner', 'student') NOT NULL,
                login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                logout_time DATETIME NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                status ENUM('success', 'failed') DEFAULT 'success',
                INDEX idx_user_login (user_id, login_time)
            )
        ");

    $stmt = $db->prepare("INSERT INTO login_history (username, user_type, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $user_type, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);

    header("Location: " . $redirect_url);
    exit;
  }

  // Login failed
  $error = "Invalid credentials";

  // Log failed login attempt
  $db->exec("
        CREATE TABLE IF NOT EXISTS login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            username VARCHAR(100) NOT NULL,
            user_type ENUM('admin', 'examiner', 'student') NOT NULL,
            login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            logout_time DATETIME NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            status ENUM('success', 'failed') DEFAULT 'success',
            INDEX idx_user_login (user_id, login_time)
        )
    ");

  $stmt = $db->prepare("INSERT INTO login_history (username, user_type, ip_address, user_agent, status) VALUES (?, ?, ?, ?, 'failed')");
  $stmt->execute([$username, 'unknown', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Login - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-xl">
      <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
        <img src="assets/favicon.ico" alt="Logo" width="30" height="30" class="d-inline-block align-top">
        <span>Assessment Portal</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="services.php">Services</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="login.php">Login</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container-xl">
    <div class="row justify-content-center align-items-center min-vh-100">
      <div class="col-sm-10 col-md-8 col-lg-6 col-xl-4">
        <div class="card shadow-lg">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <h2 class="fw-bold text-primary mb-2">Assessment Portal</h2>
              <p class="text-muted mb-0">Sign in to your account</p>
            </div>

            <?php if (isset($error)): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
              <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
              </div>

              <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
              </div>

              <button type="submit" class="btn btn-primary w-100">Sign In</button>
            </form>

            <div class="text-center mt-4">
              <p class="mb-0">Are you a student? <a href="student_login.php" class="fw-bold text-decoration-none">Go to
                  Student Portal</a></p>
            </div>

            <div class="text-center mt-4">
              <div class="card bg-light">
                <div class="card-body p-3">
                  <small class="text-muted">
                    <strong>Demo Credentials:</strong><br>
                    <div class="mt-2">
                      <div class="mb-1"><strong>Admin:</strong> admin / admin123</div>
                      <div><strong>Examiner:</strong> examiner1 / examiner123</div>
                    </div>
                  </small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>