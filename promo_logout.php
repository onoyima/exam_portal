<?php
/**
 * promo_logout.php — Staff Promotional Examination logout
 *
 * Clears the promo staff session and returns to the promo login.
 * Does not auto-submit any in-progress attempt.
 */
require 'auth.php';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'promo_staff') {
    session_unset();
    session_destroy();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}
header("Location: promo_login");
exit;
