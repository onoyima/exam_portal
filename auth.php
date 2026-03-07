<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        header("Location: index.php");
        exit;
    }
}

/**
 * Redirects to the appropriate login page based on the requested access.
 */
function redirect_to_login($target_roles)
{
    if (in_array('student', $target_roles) && count($target_roles) === 1) {
        header("Location: student_login.php");
    } else {
        header("Location: login.php");
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
?>