<?php
require 'auth.php';
// If already logged in as student, redirect to dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
  header("Location: student_dashboard.php");
  exit;
}
require 'db.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identity = trim($_POST['matric_no']);
  $password = trim($_POST['password']);

  // Clean identity to remove slashes, spaces, or dashes for flexible lookup
  $cleanIdentity = str_replace(['/', ' ', '-'], '', $identity);

  // Flexible lookup: Match against raw OR sanitized matric_no/username
  $stmt = $db->prepare("SELECT * FROM students WHERE 
                        REPLACE(matric_no, '/', '') = ? OR 
                        REPLACE(username, '/', '') = ? OR 
                        matric_no = ? OR 
                        username = ?");
  $stmt->execute([$cleanIdentity, $cleanIdentity, $identity, $identity]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($student && $student['password'] === $password) {
    // Refresh session to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['role'] = 'student';
    $_SESSION['student_id'] = $student['id'];
    $_SESSION['student_name'] = $student['name'];
    $_SESSION['matric_no'] = $student['matric_no'];
    $_SESSION['user'] = $student['name']; // Set common user variable for navbar

    // Log successful login
    $login_stmt = $db->prepare("INSERT INTO login_history (user_id, username, user_type, ip_address, user_agent, status) VALUES (?, ?, ?, ?, ?, ?)");
    $login_stmt->execute([
      $student['id'],
      $student['matric_no'],
      'student',
      $_SERVER['REMOTE_ADDR'],
      $_SERVER['HTTP_USER_AGENT'] ?? '',
      'success'
    ]);
    $_SESSION['login_id'] = $db->lastInsertId();

    header('Location: student_dashboard.php');
    exit;
  } else {
    // Log failed login attempt
    $login_stmt = $db->prepare("INSERT INTO login_history (username, user_type, ip_address, user_agent, status) VALUES (?, ?, ?, ?, ?)");
    $login_stmt->execute([
      $identity,
      'student',
      $_SERVER['REMOTE_ADDR'],
      $_SERVER['HTTP_USER_AGENT'] ?? '',
      'failed'
    ]);
    $error = 'Invalid identity (Matric No/Username) or password.';
  }
}

// Fetch instructions
$instruction_stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'login_instruction'");
$instruction_stmt->execute();
$instruction = $instruction_stmt->fetchColumn() ?: "Welcome to the Assessment Portal. Use your Matric Number to login.";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Student Login - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
    <div class="container-xl">
      <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
        <img src="assets/favicon.ico" alt="Logo" width="30" height="30">
        <span>Assessment Portal</span>
      </a>
    </div>
  </nav>

  <div class="container-xl py-5">
    <div class="row align-items-center justify-content-center">
      <!-- Instruction Card (Left) -->
      <div class="col-12 col-lg-5 mb-4 mb-lg-0 order-2 order-lg-1">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-4">
              <div class="bg-info-light text-info p-3 rounded-4">
                <i class="bi bi-info-circle-fill fs-3"></i>
              </div>
              <h4 class="fw-bold mb-0">Instructions</h4>
            </div>
            <div class="instruction-body">
              <p class="text-muted fs-6 mb-0"><?= nl2br(htmlspecialchars($instruction)) ?></p>
            </div>
          </div>
        </div>
      </div>

      <!-- Login Card (Right) -->
      <div class="col-12 col-md-8 col-lg-5 order-1 order-lg-2">
        <div class="card shadow-lg border-0 rounded-4">
          <div class="card-body p-4 p-md-5">
            <h2 class="fw-bold text-primary mb-1">Student Login</h2>
            <p class="text-muted small mb-4">Welcome back! Please login to your account.</p>

            <?php if ($error): ?>
              <div class="alert alert-danger rounded-3 border-0 shadow-sm"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
              <div class="mb-3">
                <label for="identity" class="form-label fw-bold small">Matric Number / Username</label>
                <input type="text" class="form-control rounded-pill border-light-subtle bg-light px-4 py-3"
                  id="identity" name="matric_no" placeholder="e.g. VUG/BNS/23/12345" required>
              </div>
              <div class="mb-4">
                <label for="password" class="form-label fw-bold small">Password</label>
                <input type="password" class="form-control rounded-pill border-light-subtle bg-light px-4 py-3"
                  id="password" name="password" required value="12345">
              </div>
              <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm">
                <i class="bi bi-box-arrow-in-right me-2"></i> Access My Dashboard
              </button>
            </form>

            <div class="text-center mt-5 pt-3 border-top">
              <p class="mb-0 small text-muted">Administrative Staff? <a href="login.php"
                  class="fw-bold text-decoration-none">Login to Staff Panel</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>