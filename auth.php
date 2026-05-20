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
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
            </body></html>');
        }

        if ($device && $device['status'] === 'pending') {
            http_response_code(403);
            die('<!DOCTYPE html><html><head><title>Awaiting Approval</title>
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <link href="assets/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"></head>
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            die("CSRF token validation failed. Unauthorized request.");
        }
    }
}
?>
