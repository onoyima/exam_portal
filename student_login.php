<?php
require 'auth.php';
check_device_auth(); // Phase 3: device authorization gate
// If already logged in as student, redirect to dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
  header("Location: student_dashboard");
  exit;
}
require 'db.php';

// Fetch settings early — needed by AJAX handlers below
$settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$require_password = ($settings['require_student_password'] ?? 'false') === 'true';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validate_csrf();
}

// -------------------------------------------------------
// AJAX STEP 1: Matric lookup — returns student info for identity confirmation modal
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lookup') {
  header('Content-Type: application/json');
  $identity = trim($_POST['matric_no'] ?? '');
  $cleanIdentity = preg_replace('/[\\/\\s\\-]/', '', strtoupper($identity));

  $stmt = $db->prepare("SELECT id, name, matric_no, password FROM students WHERE deleted_at IS NULL AND (
                        REPLACE(UPPER(matric_no), '/', '') = ? OR 
                        REPLACE(UPPER(username), '/', '') = ? OR 
                        matric_no = ? OR username = ?)");
  $stmt->execute([$cleanIdentity, $cleanIdentity, $identity, $identity]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($student) {
    echo json_encode(['found' => true, 'name' => $student['name'], 'matric_no' => $student['matric_no'], 'require_password' => $require_password]);
  } else {
    echo json_encode(['found' => false]);
  }
  exit;
}

// -------------------------------------------------------
// AJAX STEP 2: Confirmed login — set session after identity modal confirmed
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_login') {
  header('Content-Type: application/json');
  $identity = trim($_POST['matric_no'] ?? '');
  $cleanIdentity = preg_replace('/[\\/\\s\\-]/', '', strtoupper($identity));

  $stmt = $db->prepare("SELECT * FROM students WHERE deleted_at IS NULL AND (
                        REPLACE(UPPER(matric_no), '/', '') = ? OR 
                        REPLACE(UPPER(username), '/', '') = ? OR 
                        matric_no = ? OR username = ?)");
  $stmt->execute([$cleanIdentity, $cleanIdentity, $identity, $identity]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student not found.']);
    exit;
  }

  if ($require_password) {
    $input_password = $_POST['password'] ?? '';
    if (!password_verify($input_password, $student['password'])) {
      if ($input_password === $student['password']) {
        $new_hash = password_hash($input_password, PASSWORD_DEFAULT);
        $db->prepare("UPDATE students SET password=? WHERE id=?")->execute([$new_hash, $student['id']]);
      } else {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials.']);
        exit;
      }
    }
  }

    // Someone is actively writing an exam with this account on another
    // computer: do not let a second computer take the seat. If that computer
    // has failed, its heartbeat stops and the login is allowed after a minute.
    if (!empty($student['current_session_id']) && $student['current_session_id'] !== session_id()) {
      $busy = $db->prepare("SELECT 1 FROM objective_attempts WHERE student_id = ? AND finished_at IS NULL
          AND last_active > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
      $busy->execute([$student['id']]);
      if ($busy->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => 'This account is writing an exam on another computer right now. If that computer has stopped working, wait one minute and try again, or ask the invigilator.']);
        exit;
      }
    }
    session_regenerate_id(true);
    $newSessionId = session_id();

    $db->prepare("UPDATE students SET current_session_id = ? WHERE id = ?")->execute([$newSessionId, $student['id']]);

    $_SESSION['role']           = 'student';
    $_SESSION['student_id']     = $student['id'];
    $_SESSION['student_name']   = $student['name'];
    $_SESSION['matric_no']      = $student['matric_no'];
    $_SESSION['user']           = $student['name'];
    $_SESSION['php_session_id'] = $newSessionId;

    // Log login
    try {
      $db->prepare("INSERT INTO login_history (user_id, username, user_type, ip_address, user_agent, status) VALUES (?,?,?,?,?,?)")
         ->execute([$student['id'], $student['matric_no'], 'student', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '', 'success']);
    } catch (Exception $e) { /* login_history may not exist yet */ }

    echo json_encode(['success' => true]);
  exit;
}

// -------------------------------------------------------
// AJAX: Power off — destroys session and responds
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'power_off') {
  header('Content-Type: application/json');
  session_destroy();
  echo json_encode(['success' => true]);
  exit;
}
$portal_title = $settings['portal_title'] ?? 'Veritas Exam Portal';
$portal_logo = $settings['portal_logo'] ?? '';
$portal_favicon = $settings['portal_favicon'] ?? 'assets/favicon.ico';
$portal_image = $settings['portal_image'] ?? 'assets/img/login_bg.jpg';
$student_content = $settings['student_login_content'] ?? 'Welcome to the Secure Assessment Portal. Please enter your credentials to begin your session.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Student Login - <?= htmlspecialchars($portal_title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Secure student examination login portal">
  <link rel="icon" href="<?= htmlspecialchars($portal_favicon) ?>" type="image/x-icon">
  <link href="assets/vendor/fonts/fonts.css" rel="stylesheet">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --primary: #0f5132;
      --primary-light: #198754;
      --accent: #22c55e;
      --bg: #060d16;
      --surface: rgba(255,255,255,0.04);
      --border: rgba(255,255,255,0.08);
      --text: #e8f0e9;
      --muted: rgba(232,240,233,0.5);
    }

    html, body {
      height: 100%;
      width: 100%;
      overflow: hidden;
      font-family: 'Outfit', sans-serif;
      background: var(--bg);
      color: var(--text);
      user-select: none;
      -webkit-user-select: none;
    }

    /* Animated background */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background: linear-gradient(to top, rgba(6,13,22,0.95), rgba(6,13,22,0.7)), url('<?= htmlspecialchars($portal_image) ?>') no-repeat center center;
      background-size: cover;
      animation: bgShift 8s ease-in-out infinite alternate;
      pointer-events: none;
      z-index: 0;
    }

    @keyframes bgShift {
      0% { opacity: 0.8; }
      100% { opacity: 1; }
    }

    /* Grid overlay */
    body::after {
      content: '';
      position: fixed;
      inset: 0;
      background-image: linear-gradient(rgba(0,230,118,0.03) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(0,230,118,0.03) 1px, transparent 1px);
      background-size: 50px 50px;
      pointer-events: none;
      z-index: 0;
    }

    /* ── Power Off Button ── */
    /* ── Header Bar ── */
    .secure-header {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: 60px;
      background: rgba(6,13,22,0.85);
      border-bottom: 1px solid var(--border);
      backdrop-filter: blur(20px);
      display: flex;
      align-items: center;
      padding: 0 24px;
      z-index: 100;
      gap: 12px;
    }
    .secure-header .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 700;
      font-size: 1rem;
      color: var(--text);
    }
    .secure-header .shield-icon {
      width: 32px; height: 32px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem;
      color: white;
    }
    .secure-badge {
      margin-left: auto;
      margin-right: 60px;
      background: rgba(0,230,118,0.1);
      border: 1px solid rgba(0,230,118,0.3);
      color: #00e676;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.1em;
      animation: pulse-badge 2s ease-in-out infinite;
    }
    @keyframes pulse-badge {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.6; }
    }

    /* ── Main layout ── */
    .login-screen {
      position: fixed;
      inset: 0;
      padding-top: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
    }

    .login-container {
      display: flex;
      gap: 32px;
      width: 100%;
      max-width: 960px;
      padding: 0 24px;
      align-items: stretch;
    }

    /* ── Instructions Panel ── */
    .instructions-panel {
      flex: 1;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 36px 32px;
      backdrop-filter: blur(20px);
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .instructions-panel .panel-title {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 1rem;
      font-weight: 700;
      color: var(--text);
      border-bottom: 1px solid var(--border);
      padding-bottom: 16px;
    }
    .instructions-panel .panel-title i {
      color: #00e676;
      font-size: 1.2rem;
    }
    .instructions-panel .instruction-text {
      font-size: 0.875rem;
      color: var(--muted);
      line-height: 1.8;
    }
    .tip-item {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      font-size: 0.82rem;
      color: var(--muted);
      padding: 8px 0;
      border-bottom: 1px solid var(--border);
    }
    .tip-item:last-child { border-bottom: none; }
    .tip-item i { color: var(--accent); margin-top: 2px; flex-shrink: 0; }

    /* ── Login Card ── */
    .login-card {
      width: 420px;
      flex-shrink: 0;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 40px 36px;
      backdrop-filter: blur(20px);
      position: relative;
      overflow: hidden;
    }
    .login-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--primary), var(--accent), var(--primary));
      background-size: 200% 100%;
      animation: shimmer 3s linear infinite;
    }
    @keyframes shimmer {
      0% { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }

    .login-card .card-logo {
      width: 56px; height: 56px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: white;
      margin-bottom: 20px;
      box-shadow: 0 8px 24px rgba(15,81,50,0.4);
    }
    .card-title {
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 4px;
    }
    .card-subtitle {
      font-size: 0.82rem;
      color: var(--muted);
      margin-bottom: 32px;
    }

    /* ── Form elements ── */
    .form-label {
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 8px;
      display: block;
    }
    .secure-input {
      width: 100%;
      background: rgba(255,255,255,0.06);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 14px 18px;
      font-size: 1rem;
      font-family: 'Inter', sans-serif;
      color: var(--text);
      outline: none;
      transition: all 0.3s ease;
      caret-color: var(--accent);
    }
    .secure-input::placeholder { color: rgba(232,240,233,0.25); }
    .secure-input:focus {
      border-color: rgba(0,230,118,0.5);
      background: rgba(255,255,255,0.08);
      box-shadow: 0 0 0 3px rgba(0,230,118,0.1);
    }

    .btn-login {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      border: none;
      border-radius: 12px;
      font-size: 0.95rem;
      font-weight: 700;
      color: white;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 24px;
      letter-spacing: 0.02em;
    }
    .btn-login:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 24px rgba(15,81,50,0.5);
    }
    .btn-login:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }

    .error-box {
      background: rgba(220,53,69,0.1);
      border: 1px solid rgba(220,53,69,0.3);
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 0.82rem;
      color: #f8a7ae;
      margin-bottom: 20px;
      display: none;
    }
    .error-box.show { display: flex; align-items: center; gap: 10px; }

    .admin-link {
      text-align: center;
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
      font-size: 0.78rem;
      color: var(--muted);
    }
    .admin-link a {
      color: var(--accent);
      text-decoration: none;
      font-weight: 600;
    }

    /* ── Overlay Modals ── */
    .secure-overlay {
      position: fixed;
      inset: 0;
      background: rgba(6,13,22,0.92);
      backdrop-filter: blur(16px);
      z-index: 2000;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
    }
    .secure-overlay.active {
      opacity: 1;
      pointer-events: all;
    }
    .overlay-card {
      background: #0e1a14;
      border: 1px solid rgba(0,230,118,0.2);
      border-radius: 24px;
      padding: 48px 44px;
      width: 100%;
      max-width: 480px;
      text-align: center;
      transform: scale(0.95);
      transition: transform 0.3s ease;
    }
    .secure-overlay.active .overlay-card {
      transform: scale(1);
    }

    /* Identity Confirmation */
    .identity-avatar {
      width: 80px; height: 80px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: white;
      margin: 0 auto 20px;
      box-shadow: 0 0 30px rgba(15,81,50,0.4);
    }
    .identity-name {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 4px;
    }
    .identity-matric {
      font-size: 0.9rem;
      color: var(--accent);
      font-weight: 600;
      margin-bottom: 8px;
      letter-spacing: 0.05em;
    }
    .identity-question {
      font-size: 0.9rem;
      color: var(--muted);
      margin-bottom: 32px;
    }
    .btn-confirm {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #0f5132, #198754);
      border: none;
      border-radius: 12px;
      font-size: 0.95rem;
      font-weight: 700;
      color: white;
      cursor: pointer;
      margin-bottom: 12px;
      transition: all 0.2s;
    }
    .btn-confirm:hover { opacity: 0.9; transform: translateY(-1px); }
    .btn-cancel-identity {
      width: 100%;
      padding: 12px;
      background: transparent;
      border: 1px solid var(--border);
      border-radius: 12px;
      font-size: 0.85rem;
      color: var(--muted);
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-cancel-identity:hover { border-color: rgba(220,53,69,0.5); color: #f8a7ae; }

    /* Power Off Overlay */
    #powerOverlay .overlay-card { border-color: rgba(220,53,69,0.3); }
    .power-icon-large {
      width: 72px; height: 72px;
      background: rgba(220,53,69,0.15);
      border: 2px solid rgba(220,53,69,0.5);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem;
      color: #dc3545;
      margin: 0 auto 20px;
    }
    .btn-poweroff {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #842029, #dc3545);
      border: none;
      border-radius: 12px;
      font-size: 0.95rem;
      font-weight: 700;
      color: white;
      cursor: pointer;
      margin-bottom: 12px;
      transition: all 0.2s;
    }
    .btn-poweroff:hover { opacity: 0.9; }

    /* Loading Spinner */
    .spinner-sm {
      width: 18px; height: 18px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      display: none;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Responsive */
    @media (max-width: 768px) {
      .login-container { flex-direction: column; padding: 0 16px; }
      .instructions-panel { display: none; }
      .login-card { width: 100%; }
      .overlay-card { margin: 16px; padding: 32px 24px; }
    }
  </style>
</head>
<body>

  <!-- Secure Header -->
  <header class="secure-header">
    <div class="brand">
      <?php if (!empty($portal_logo) && file_exists($portal_logo)): ?>
        <img src="<?= htmlspecialchars($portal_logo) ?>" alt="Logo" style="max-height: 35px; width: auto; border-radius: 4px;">
      <?php else: ?>
        <div class="shield-icon"><i class="bi bi-shield-lock-fill"></i></div>
      <?php endif; ?>
      <span><?= htmlspecialchars($portal_title) ?></span>
    </div>
    <span class="secure-badge"><i class="bi bi-shield-lock-fill"></i> SECURE ENVIRONMENT</span>
  </header>

  <!-- Main Login Screen -->
  <div class="login-screen">
    <div class="login-container">

      <!-- Instructions Panel -->
      <div class="instructions-panel">
        <div class="panel-title">
          <i class="bi bi-info-circle-fill"></i>
          <span>Exam Instructions</span>
        </div>
        <p class="instruction-text"><?= nl2br(htmlspecialchars($student_content)) ?></p>
        <div style="margin-top: auto;">
          <div class="tip-item">
            <i class="bi bi-shield-check"></i>
            <span>Only your Matric Number is required. Password is pre-configured.</span>
          </div>
          <div class="tip-item">
            <i class="bi bi-fullscreen"></i>
            <span>This portal operates in full-screen secure mode. Do not attempt to exit.</span>
          </div>
          <div class="tip-item">
            <i class="bi bi-camera-video"></i>
            <span>Your webcam will be activated during the examination for proctoring.</span>
          </div>
          <div class="tip-item">
            <i class="bi bi-exclamation-triangle"></i>
            <span>Violations are logged and may result in disqualification.</span>
          </div>
        </div>
      </div>

      <!-- Login Card -->
      <div class="login-card">
        <div class="card-logo"><i class="bi bi-mortarboard-fill"></i></div>
        <h1 class="card-title">Student Login</h1>
        <p class="card-subtitle">Enter your Matric Number to proceed</p>

        <?php if (isset($_GET['submitted'])): ?>
          <div class="error-box show" style="background:#e8f5ee;border-color:#9fd3b6;color:#0f5132;">
            <i class="bi bi-check-circle-fill"></i>
            <span>Your exam has been submitted successfully. You may leave the computer.</span>
          </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['auth_error'])): ?>
          <div class="error-box show">
            <i class="bi bi-info-circle-fill"></i>
            <span><?= htmlspecialchars($_SESSION['auth_error']) ?></span>
          </div>
          <?php unset($_SESSION['auth_error']); ?>
        <?php endif; ?>

        <div class="error-box" id="errorBox">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span id="errorText">Matric number not found. Please check and try again.</span>
        </div>

        <div>
          <label class="form-label" for="matricInput">Matric Number</label>
          <input
            type="text"
            id="matricInput"
            class="secure-input"
            placeholder="e.g. vug/csc/11/1313 or vugcsc111313"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="characters"
            spellcheck="false"
            autofocus
          >
        </div>

        <button class="btn-login" id="loginBtn" onclick="doLookup()">
          <div class="spinner-sm" id="loginSpinner"></div>
          <i class="bi bi-arrow-right-circle-fill" id="loginIcon"></i>
          <span id="loginBtnText">Verify Identity</span>
        </button>

        <div class="admin-link">
          Administrative Staff? <a href="login">Staff Login Panel</a>
        </div>
      </div>

    </div>
  </div>

  <!-- ── Identity Confirmation Overlay ── -->
  <div class="secure-overlay" id="identityOverlay">
    <div class="overlay-card">
      <div class="identity-avatar"><i class="bi bi-person-fill"></i></div>
      <div class="identity-name" id="confirmName">Student Name</div>
      <div class="identity-matric" id="confirmMatric">MATRIC/NUMBER</div>
      <div id="passwordFieldWrapper" class="d-none mt-3" style="width:100%;max-width:320px">
        <div class="position-relative">
          <input type="password" id="confirmPassword" class="form-control form-control-lg text-center rounded-3" placeholder="Enter your password" autocomplete="off" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#fff;font-size:1rem;padding-right:2.5rem">
          <button type="button" id="togglePassword" class="btn p-0 position-absolute top-50 end-0 translate-middle-y me-2" style="background:none;border:none;color:rgba(255,255,255,0.5);font-size:1.1rem;line-height:1" tabindex="-1" onclick="togglePasswordVisibility()">
            <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
          </button>
        </div>
      </div>
      <p class="identity-question mt-3">Is this your identity? Please confirm before proceeding to the examination portal.</p>
      <button class="btn-confirm" id="confirmYesBtn" onclick="confirmLogin()">
        <i class="bi bi-check-circle-fill me-2"></i> Yes, this is me — Continue
      </button>
      <button class="btn-cancel-identity" onclick="cancelIdentity()">
        <i class="bi bi-x-circle me-1"></i> No, go back
      </button>
    </div>
  </div>

  <!-- ── Power Off Confirmation Overlay ── -->
  <div class="secure-overlay" id="powerOverlay">
    <div class="overlay-card">
      <div class="power-icon-large"><i class="bi bi-power"></i></div>
      <h3 style="font-size:1.2rem; font-weight:800; color: #f8a7ae; margin-bottom: 8px;">Close Exam Environment?</h3>
      <p style="font-size: 0.85rem; color: var(--muted); margin-bottom: 28px;">
        This will close the secure examination environment and end your session. Are you sure?
      </p>
      <button class="btn-poweroff" onclick="executePowerOff()">
        <i class="bi bi-power me-2"></i> Yes, Close Environment
      </button>
      <button class="btn-cancel-identity" onclick="hidePowerModal()">
        <i class="bi bi-arrow-left me-1"></i> Cancel — Stay in exam
      </button>
    </div>
  </div>

  <script>
    // ── Store pending matric for confirm step ──
    let pendingMatric = '';
    let requirePassword = false;
    let csrfToken = '<?= generate_csrf_token() ?>';

    // Posts to the login handler. If the page sat idle long enough for the
    // session to expire, the server returns a fresh token and we retry once,
    // so the student never has to refresh the page.
    async function postLogin(fields, retried) {
      const fd = new FormData();
      Object.keys(fields).forEach(k => fd.append(k, fields[k]));
      fd.append('csrf_token', csrfToken);
      const res = await fetch('student_login', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
      let data;
      try { data = await res.json(); } catch (e) {
        if (!retried) { await refreshToken(); return postLogin(fields, true); }
        throw e;
      }
      if (data && data.csrf_expired && !retried) {
        csrfToken = data.csrf_token;
        return postLogin(fields, true);
      }
      return data;
    }
    async function refreshToken() {
      try {
        const html = await (await fetch('student_login', { credentials: 'same-origin', cache: 'no-store' })).text();
        const m = html.match(/let csrfToken = '([a-f0-9]+)'/);
        if (m) csrfToken = m[1];
      } catch (e) { /* offline: the retry will report it */ }
    }
    // Keep the session warm while the page is open.
    setInterval(refreshToken, 10 * 60 * 1000);

    // ── Lookup Matric Number ──
    async function doLookup() {
      const input = document.getElementById('matricInput');
      const matric = input.value.trim();
      if (!matric) { showError('Please enter your Matric Number.'); return; }

      setLoading(true);
      hideError();

      try {
        const data = await postLogin({ action: 'lookup', matric_no: matric });

        if (data.found) {
          pendingMatric = matric;
          requirePassword = data.require_password || false;
          document.getElementById('confirmName').textContent = data.name;
          document.getElementById('confirmMatric').textContent = data.matric_no;
          var pwdWrapper = document.getElementById('passwordFieldWrapper');
          var pwdInput = document.getElementById('confirmPassword');
          if (requirePassword) {
            pwdWrapper.classList.remove('d-none');
            pwdInput.value = '';
            pwdInput.focus();
          } else {
            pwdWrapper.classList.add('d-none');
          }
          showIdentityOverlay();
        } else {
          showError('Matric number not found. Please check and try again.');
        }
      } catch (e) {
        showError('Cannot reach the exam server. Check the network cable or Wi-Fi, then try again.');
      } finally {
        setLoading(false);
      }
    }

    // ── Confirm login after identity modal ──
    async function confirmLogin() {
      const btn = document.getElementById('confirmYesBtn');
      btn.disabled = true;
      btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.4);border-top-color:white;border-radius:50%;animation:spin 0.7s linear infinite;"></span> Logging in...';

      const fields = { action: 'confirm_login', matric_no: pendingMatric };
      if (requirePassword) {
        fields.password = document.getElementById('confirmPassword').value;
      }

      try {
        const data = await postLogin(fields);
        if (data.success) {
          window.location.href = 'student_dashboard';
        } else {
          hideIdentityOverlay();
          showError(data.error || 'Login failed. Please try again.');
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-check-circle-fill" style="margin-right:8px;"></i> Yes, this is me — Continue';
        }
      } catch (e) {
        hideIdentityOverlay();
        showError('Cannot reach the exam server. Check the network cable or Wi-Fi, then try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill" style="margin-right:8px;"></i> Yes, this is me — Continue';
      }
    }

    function togglePasswordVisibility() {
      var pwd = document.getElementById('confirmPassword');
      var icon = document.getElementById('togglePasswordIcon');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye';
      } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye-slash';
      }
    }

    // Allow Enter key to submit from password field
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && document.getElementById('identityOverlay').classList.contains('active')) {
        e.preventDefault();
        confirmLogin();
      }
    });

    function cancelIdentity() {
      hideIdentityOverlay();
      pendingMatric = '';
      document.getElementById('confirmPassword').value = '';
    }

    // ── Overlay helpers ──
    function showIdentityOverlay() { document.getElementById('identityOverlay').classList.add('active'); }
    function hideIdentityOverlay() { document.getElementById('identityOverlay').classList.remove('active'); }
    function showPowerModal()      { document.getElementById('powerOverlay').classList.add('active'); }
    function hidePowerModal()      { document.getElementById('powerOverlay').classList.remove('active'); }

    // ── Power off ──
    async function executePowerOff() {
      try { await postLogin({ action: 'power_off' }); } catch (e) { /* closing anyway */ }
      document.exitFullscreen?.();
      window.close();
      // Fallback if window.close() is blocked
      document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;background:#060d16;color:#e8f0e9;font-family:Inter,sans-serif;text-align:center;"><div><i class="bi bi-power" style="font-size:3rem;color:#dc3545;"></i><h2 style="margin-top:16px;">Session Ended</h2><p style="color:rgba(232,240,233,0.5);">You may now close this window.</p></div></div>';
    }

    // ── UI Helpers ──
    function setLoading(state) {
      const btn = document.getElementById('loginBtn');
      const spinner = document.getElementById('loginSpinner');
      const icon = document.getElementById('loginIcon');
      const txt = document.getElementById('loginBtnText');
      btn.disabled = state;
      spinner.style.display = state ? 'block' : 'none';
      icon.style.display = state ? 'none' : 'inline';
      txt.textContent = state ? 'Verifying...' : 'Verify Identity';
    }
    function showError(msg) {
      const box = document.getElementById('errorBox');
      document.getElementById('errorText').textContent = msg;
      box.classList.add('show');
    }
    function hideError() { document.getElementById('errorBox').classList.remove('show'); }

    // ── Allow Enter key to submit ──
    document.getElementById('matricInput').addEventListener('keydown', e => {
      if (e.key === 'Enter') doLookup();
    });
  </script>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <!-- Aggressive Global Lockdown -->
  <script src="assets/js/student_lockdown.js"></script>
    <script src="assets/js/admin-common.js"></script>
    <script src="assets/js/admin-swal.js"></script>
</body>
</html>
