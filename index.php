<?php
session_start();
require 'db.php';

// Redirect based on user status
if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];

    switch ($role) {
        case 'admin':
            header("Location: admin_dashboard.php");
            break;
        case 'examiner':
            header("Location: assess.php");
            break;
        case 'student':
            header("Location: student_dashboard.php");
            break;
        default:
            // Unknown role, clear session and go to login
            session_destroy();
            header("Location: services.php");
            break;
    }
    exit;
} else {
    // User is not logged in, redirect to services page
    header("Location: services.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Assessment Portal - Welcome</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
    <div class="container-xl text-center py-5">
        <div class="mb-4">
            <img src="assets/favicon.ico" alt="Logo" width="120" class="mb-3">
        </div>
        <h1 class="display-4 fw-bold text-primary mb-4">Assessment Portal</h1>
        <p class="lead mb-4">Welcome to the comprehensive student assessment system</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="services.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">System Overview</a>
            <a href="student_login.php" class="btn btn-primary btn-lg rounded-pill px-5">Student Entrance</a>
            <a href="login.php" class="btn btn-dark btn-lg rounded-pill px-4">Staff Portal</a>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>

</html>