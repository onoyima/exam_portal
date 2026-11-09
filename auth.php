<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Session Inactivity Timeout (Component 1.4) ───────────────────────
if (isset($_SESSION['role'])) {
    if (!defined('SESSION_LIFETIME')) {
        require_once __DIR__ . '/config.php';
    }
    $lifetime = defined('SESSION_LIFETIME') ? (int)SESSION_LIFETIME : 1800;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $lifetime)) {
        $role = $_SESSION['role'];
        session_unset();
        session_destroy();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['auth_error'] = "Your session has expired due to inactivity.";
        if ($role === 'student') {
            header("Location: student_login");
        } else {
            header("Location: login");
        }
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Device Authorization Gate — runs for student-facing pages only.
 * Auto-registers unknown IPs as 'pending', blocks 'rejected' devices.
 */
function check_device_auth() {
    // Only enforce for student role pages
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return; // the server's own browser never needs approval
    }

    try {
        // Use shared config constants instead of hardcoded credentials
        if (!defined('DB_HOST')) { require_once __DIR__ . '/config.php'; }
        $db_dev = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS
        );
        $db_dev->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Upsert: register device if new, update last_seen if known
        $db_dev->prepare("INSERT INTO approved_devices (ip_address, device_info, status, last_seen)
            VALUES (?, ?, 'pending', NOW())
            ON DUPLICATE KEY UPDATE device_info = ?, last_seen = NOW()")
            ->execute([$ip, $ua, $ua]);

        // Fetch current status
        $stmt = $db_dev->prepare("SELECT status FROM approved_devices WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($device && $device['status'] === 'rejected') {
            http_response_code(403);
            die('<!DOCTYPE html><html><head><title>Access Denied</title>
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <link href="assets/css/bootstrap.min.css" rel="stylesheet"></head>
            <body class="bg-dark d-flex align-items-center justify-content-center" style="min-height:100vh;">
            <div class="text-center text-white p-5">
              <i class="bi bi-shield-x" style="font-size:5rem;color:#ef4444;"></i>
              <h2 class="mt-4 fw-bold">Device Not Authorized</h2>
              <p class="text-white-50">This device has been blocked from accessing the examination portal.<br>
                Please contact the exam administrator.</p>
              <div class="mt-3 p-3 bg-dark rounded-3 border border-danger border-opacity-25 d-inline-block">
                <small class="text-danger-emphasis font-monospace">IP: ' . htmlspecialchars($ip) . '</small>
              </div>
            </div>
            <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
            </body></html>');
        }

        if ($device && $device['status'] === 'pending') {
            http_response_code(403);
            die('<!DOCTYPE html><html><head><title>Awaiting Approval</title>
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <link href="assets/css/bootstrap.min.css" rel="stylesheet">
            <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet"></head>
            <body class="bg-dark d-flex align-items-center justify-content-center" style="min-height:100vh;">
            <div class="text-center text-white p-5">
              <div style="width:80px;height:80px;border:3px solid #f59e0b;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 24px;"></div>
              <h2 class="fw-bold">Device Pending Authorization</h2>
              <p class="text-white-50">This device has been registered and is awaiting approval from the administrator.<br>
                Please wait or notify your exam supervisor.</p>
              <div class="mt-3 p-3 bg-dark rounded-3 border border-warning border-opacity-25 d-inline-block">
                <small class="text-warning font-monospace">IP: ' . htmlspecialchars($ip) . '</small>
              </div>
              <p class="text-white-50 mt-4 small">This page will auto-refresh every 15 seconds.</p>
            </div>
            <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
            <script>setTimeout(()=>location.reload(),15000);</script>
            </body></html>');
        }
    } catch (Exception $e) {
        // If device auth DB fails, fail open (don't block students due to DB error)
        error_log("Device auth check failed: " . $e->getMessage());
    }
}


/**
 * Checks if the user is authorized to access a page.
 * @param array|string $allowed_roles Roles allowed to access the page ('admin', 'examiner', 'student')
 */
function check_auth($allowed_roles)
{
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    if (!isset($_SESSION['role'])) {
        // Not logged in. Determine where to send them.
        redirect_to_login($allowed_roles);
    }

    if (!in_array($_SESSION['role'], $allowed_roles)) {
        // Logged in but not authorized for this specific area.
        // Instead of 'die', we redirect to their own dashboard with an error message
        $role = $_SESSION['role'];
        $target = 'index.php'; // index.php will route them correctly

        // Optionally set a flash message
        $_SESSION['auth_error'] = "You do not have permission to access that page.";
        header("Location: index");
        exit;
    }
}

/**
 * Redirects to the appropriate login page based on the requested access.
 */
function redirect_to_login($target_roles)
{
    if (in_array('student', $target_roles) && count($target_roles) === 1) {
        header("Location: student_login");
    } else {
        header("Location: login");
    }
    exit;
}

/**
 * Helper to display authorization errors if any.
 */
function display_auth_errors()
{
    if (isset($_SESSION['auth_error'])) {
        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>' . htmlspecialchars($_SESSION['auth_error']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['auth_error']);
    }
}

/**
 * Generate CSRF token if not set (Component 1.3)
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden input element containing the CSRF token
 */
function csrf_field() {
    $token = generate_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validate CSRF token in post request
 */
function validate_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return;
    }

    // The usual cause is a page left open until its session expired. Hand the
    // page a fresh token instead of a dead end, so a retry simply works.
    $fresh = generate_csrf_token();
    if (is_ajax_request()) {
        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'csrf_expired' => true,
            'csrf_token' => $fresh,
            'error' => 'Your page had expired. Please try again.',
        ]);
        exit;
    }
    $_SESSION['auth_error'] = 'This page had been open for a long time and was refreshed. Please try again.';
    $back = strtok($_SERVER['REQUEST_URI'] ?? 'index', '#');
    header('Location: ' . $back);
    exit;
}

function is_ajax_request(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
}

/**
 * One live session per student. A newer login replaces the older one; the
 * older browser is signed out on its next request.
 */
function enforce_single_student_session(PDO $db): void
{
    if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['student_id'])) {
        return;
    }
    $st = $db->prepare("SELECT current_session_id FROM students WHERE id = ?");
    $st->execute([$_SESSION['student_id']]);
    $current = $st->fetchColumn();
    if ($current === null || $current === false || $current === session_id()) {
        return;
    }
    session_unset();
    session_destroy();
    if (is_ajax_request() || strpos($_SERVER['HTTP_CONTENT_TYPE'] ?? ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data') === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'status' => 'FORCED_LOGOUT', 'message' => 'This account has been signed in on another computer.']);
        exit;
    }
    session_start();
    $_SESSION['auth_error'] = 'You were signed out because this account was signed in on another computer.';
    header('Location: student_login');
    exit;
}

/* ---------- examiners created for one grouped exam ---------- */

/** Why a group examiner may not work now, or null when they may. */
function group_examiner_problem(PDO $db, array $examiner): ?string
{
    if (empty($examiner['group_id'])) {
        return null;
    }
    $st = $db->prepare("SELECT name, exam_ends, ended_at, deleted_at FROM exam_groups WHERE id = ?");
    $st->execute([(int) $examiner['group_id']]);
    $g = $st->fetch(PDO::FETCH_ASSOC);
    if (!$g || $g['deleted_at']) {
        return 'This examiner account belongs to an exam group that no longer exists on this server.';
    }
    if ($g['ended_at'] || ($g['exam_ends'] && strtotime($g['exam_ends']) < time())) {
        return 'The ' . $g['name'] . ' is over, so examiner accounts created for it are closed.';
    }
    return null;
}

/** Signed in somewhere else within the idle time? */
function examiner_session_busy(array $examiner): bool
{
    $idle = defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME : 1800;
    return !empty($examiner['session_token']) && !empty($examiner['last_seen_at'])
        && strtotime($examiner['last_seen_at']) > time() - $idle;
}

/**
 * Runs on every request of a group examiner: one computer at a time, only
 * while the group exam lasts, and only on the pages examiners need for it.
 */
function enforce_single_examiner_session(PDO $db): void
{
    if (($_SESSION['role'] ?? '') !== 'examiner' || empty($_SESSION['group_examiner']) || empty($_SESSION['examiner_id'])) {
        return;
    }
    $st = $db->prepare("SELECT id, group_id, session_token, last_seen_at, deleted_at FROM examiners WHERE id = ?");
    $st->execute([(int) $_SESSION['examiner_id']]);
    $x = $st->fetch(PDO::FETCH_ASSOC);

    $why = null;
    if (!$x || $x['deleted_at']) {
        $why = 'Your examiner account was removed by the centre manager.';
    } elseif ($x['session_token'] !== session_id()) {
        $why = 'You were signed out: this account was signed in on another computer, or the centre manager reset your session.';
    } else {
        $why = group_examiner_problem($db, $x);
    }
    if ($why !== null) {
        if ($x && $x['session_token'] === session_id()) {
            $db->prepare("UPDATE examiners SET session_token = NULL WHERE id = ?")->execute([$x['id']]);
        }
        session_unset();
        session_destroy();
        if (is_ajax_request()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'status' => 'FORCED_LOGOUT', 'error' => $why]);
            exit;
        }
        session_start();
        $_SESSION['auth_error'] = $why;
        header('Location: login');
        exit;
    }
    if (!$x['last_seen_at'] || strtotime($x['last_seen_at']) < time() - 60) {
        $db->prepare("UPDATE examiners SET last_seen_at = NOW() WHERE id = ?")->execute([$x['id']]);
    }

    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $allowed = ['examiner_dashboard.php', 'assess.php', 'exam_overview.php', 'admin_live_proctor.php', 'examiner_objective_results.php',
        'logout.php', 'login.php', 'passport_image.php', 'webrtc_api.php', 'upload_recording.php', 'search_students.php', 'index.php'];
    if (!in_array($page, $allowed, true)) {
        header('Location: examiner_dashboard');
        exit;
    }
}

/** Clears the one-computer lock when a group examiner signs out. */
function release_examiner_session(PDO $db): void
{
    if (!empty($_SESSION['group_examiner']) && !empty($_SESSION['examiner_id'])) {
        $db->prepare("UPDATE examiners SET session_token = NULL WHERE id = ? AND session_token = ?")->execute([(int) $_SESSION['examiner_id'], session_id()]);
    }
}

