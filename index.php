<?php
// Temporarily enable error display for debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
require 'db.php';

// Auth Redirect
if (isset($_SESSION['role'])) {
    $r = $_SESSION['role'];
    if ($r === 'admin')
        header("Location: admin_dashboard");
    elseif ($r === 'examiner')
        header("Location: examiner_dashboard");
    elseif ($r === 'student')
        header("Location: student_dashboard");
    exit;
}

// Fetch dynamic settings
$settings = [];
try {
    $settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo "<!-- Settings loaded: " . count($settings) . " items -->\n";
} catch (Exception $e) {
    echo "<!-- Error loading settings: " . $e->getMessage() . " -->\n";
}

// Global branding details
$portal_title = $settings['portal_title'] ?? 'Veritas Mock Nursing Council Exam';
$portal_logo = $settings['portal_logo'] ?? '';
$portal_favicon = $settings['portal_favicon'] ?? 'assets/favicon.ico';
$contact_email = $settings['contact_email'] ?? 'support@veritas.edu.ng';
$contact_address = $settings['contact_address'] ?? 'Innovation Lab, Abuja';
$footer_text = $settings['footer_text'] ?? '&copy; 2026 BMXcode &bull; UNCOMPROMISED EXCELLENCE';

// Fetch basic stats
$exam_count = 0;
$student_count = 0;
try {
    $exam_count = $db->query("SELECT COUNT(*) FROM exams WHERE is_active = 1 AND deleted_at IS NULL")->fetchColumn();
    $student_count = $db->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL")->fetchColumn();
} catch (Exception $e) {
    echo "<!-- Stats error: " . $e->getMessage() . " -->\n";
}

// Helper to support simple markdown bold in bullets
function render_cms_markdown($text) {
    return preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', htmlspecialchars($text));
}
$student_count = 0;
try {
    $exam_count = $db->query("SELECT COUNT(*) FROM exams WHERE is_active = 1 AND deleted_at IS NULL")->fetchColumn();
    $student_count = $db->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL")->fetchColumn();
} catch (Exception $e) {
    echo "<!-- Stats error: " . $e->getMessage() . " -->\n";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($portal_title) ?> | Secure AI-Proctored Exam Portal</title>
    <link rel="icon" href="<?= htmlspecialchars($portal_favicon) ?>" type="image/x-icon">

    <!-- Premium Google Fonts -->
    <link
        href="assets/vendor/fonts/fonts.css"
        rel="stylesheet">

    <!-- UI Plugins -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">

    <!-- Choices.js for mobile-friendly multi-select -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/styles/choices.min.css">
    <style>
        /* Fix Choices.js dropdown z-index to appear above sidebar */
        .choices__list--dropdown {
            z-index: 9999 !important;
        }
        .choices__list--dropdown .choices__list {
            z-index: 9999 !important;
        }
        .choices__list--dropdown .choices__item {
            z-index: 9999 !important;
        }
        .choices__inner {
            z-index: auto !important;
        }
        .is-open .choices__list--dropdown {
            z-index: 9999 !important;
        }
    </style>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
            background: #fff;
            overflow-x: hidden;
            position: relative;
        }

        h1, h2, h3, h4, .outfit {
            font-family: 'Outfit', sans-serif;
        }

        .animated-bg-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
            z-index: -1;
        }

        .bg-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.12;
            animation: float-blob 25s infinite alternate ease-in-out;
        }

        .blob-green {
            background: radial-gradient(circle, #22c55e 0%, rgba(34, 197, 94, 0) 70%);
            width: 500px; height: 500px;
            top: -100px; right: -100px;
            animation-duration: 20s;
        }

        .blob-primary {
            background: radial-gradient(circle, #092c19 0%, rgba(9, 44, 25, 0) 70%);
            width: 600px; height: 600px;
            bottom: 10%; left: -200px;
            animation-duration: 30s;
            animation-delay: -5s;
        }

        .blob-purple {
            background: radial-gradient(circle, #6366f1 0%, rgba(99, 102, 241, 0) 70%);
            width: 450px; height: 450px;
            top: 40%; right: 15%;
            animation-duration: 25s;
            animation-delay: -10s;
        }

        @keyframes float-blob {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            50% { transform: translate(80px, 50px) rotate(180deg) scale(1.1); }
            100% { transform: translate(-40px, -80px) rotate(360deg) scale(0.9); }
        }

        .hover-shadow { transition: all 0.3s ease; }
        .hover-shadow:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.08) !important;
        }
        .hover-translate { transition: all 0.3s ease; }
        .hover-translate:hover { transform: translateY(-8px); }

        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* ── Navbar ── */
        .landing-navbar {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 95%;
            max-width: 1550px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(25px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 60px;
            padding: 12px 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
            z-index: 2000;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .landing-navbar.scrolled {
            top: 0;
            width: 100%;
            max-width: 100%;
            background: rgba(255, 255, 255, 0.98);
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            border-radius: 0;
            padding: 10px 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }

        .navbar-toggler:focus { box-shadow: none; }
        .navbar-toggler i { font-size: 1.6rem; transition: transform 0.3s; }
        .navbar-toggler[aria-expanded="true"] i { transform: rotate(90deg); }

        .step-card {
            background: #fff;
            border: 1px solid rgba(0,0,0,0.04);
            border-radius: 1.25rem;
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: all 0.3s ease;
        }
        .step-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            border-color: var(--primary-color);
        }
        .step-num {
            background: var(--primary-color);
            color: #fff;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .spec-pill {
            background: #f8fafc;
            border: 1px solid rgba(0,0,0,0.04);
            border-radius: 60px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .spec-pill:hover {
            border-color: var(--primary-color);
            background: #f0fdf4;
        }

        .landing-navbar .navbar-brand span {
            font-size: clamp(1rem, 2vw, 1.6rem);
            white-space: nowrap;
        }

        /* ── Hero ── */
        .hero-section {
            padding-top: 140px;
            padding-bottom: 80px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            background: #fff;
        }

        .hero-title {
            font-size: clamp(2rem, 6vw, 4.5rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .text-swap-container {
            color: var(--primary-color);
            display: inline-block;
        }

        .swap-text { display: none; }
        .swap-text.active {
            display: inline;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero-carousel .carousel-item img {
            border-radius: 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        }

        .floating-card {
            position: absolute;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 16px 20px;
            border-radius: 1.25rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            z-index: 20;
        }

        /* ── Section spacing ── */
        .section-padding { padding: 100px 0; }

        /* ── Stats ── */
        .stat-item {
            padding: 20px 0;
        }
        .stat-item .display-6 {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
        }
        .border-md-start {
            border-left: 1px solid rgba(0,0,0,0.08);
        }

        /* ── Buttons hero ── */
        .hero-content .btn-lg {
            padding: 14px 28px;
            font-size: clamp(0.85rem, 1.5vw, 1rem);
        }

        /* ── Responsive ── */
        @media (max-width: 1199.98px) {
            .landing-navbar { padding: 10px 24px; }
            .landing-navbar.scrolled { padding: 8px 30px; }
        }

        @media (max-width: 991.98px) {
            .landing-navbar {
                top: 12px;
                width: 94%;
                padding: 10px 18px;
                border-radius: 20px;
            }
            .landing-navbar.scrolled {
                top: 0;
                width: 100%;
                border-radius: 0;
                padding: 10px 16px;
            }
            .landing-navbar .navbar-collapse {
                background: rgba(255,255,255,0.98);
                backdrop-filter: blur(20px);
                border-radius: 16px;
                padding: 16px;
                margin-top: 10px;
                max-height: 70vh;
                overflow-y: auto;
                box-shadow: 0 20px 60px rgba(0,0,0,0.12);
            }
            .landing-navbar .navbar-nav { gap: 2px; }
            .landing-navbar .nav-link {
                padding: 10px 16px;
                border-radius: 10px;
                font-size: 0.95rem;
                text-align: center;
            }
            .landing-navbar .nav-link:hover { background: #f0fdf4; }
            .landing-navbar .d-flex.gap-3 { margin-top: 8px; }
            .landing-navbar .btn { width: 100%; justify-content: center; }

            .hero-section {
                padding-top: 120px;
                padding-bottom: 70px;
                min-height: auto;
                display: block;
                align-items: normal;
                overflow: visible;
            }
            .hero-content { text-align: center; }
            .hero-content .lead { padding-right: 0; }
            .hero-content .d-flex { justify-content: center; }
            .hero-content .btn-lg {
                padding: 12px 20px;
            }
            .floating-card {
                display: none;
            }
            .hero-badge i { display: none; }
            .hero-carousel { margin-top: 1.5rem; }

            .section-padding { padding: 60px 0; }
            .container-xxl.px-5 { padding-left: 20px; padding-right: 20px; }

            .border-md-start { border-left: none; }

            .stat-item { padding: 12px 0; }

            #about .display-5 { font-size: clamp(1.6rem, 5vw, 2.2rem); }
            #about .row.g-5 > div { text-align: center; }

            .feature-card { padding: 2rem 1.5rem; }
            .team-item { padding: 1.5rem 0.5rem; }
            .team-img-wrapper { width: 140px; height: 140px; }

            .step-card { padding: 1.25rem; }
            .spec-pill { width: 100%; text-align: center; justify-content: center; }

            .landing-navbar .navbar-brand img { max-height: 36px; }
        }

        @media (max-width: 767.98px) {
            .landing-navbar {
                top: 8px;
                width: 92%;
                padding: 8px 14px;
                border-radius: 16px;
            }
            .landing-navbar.scrolled {
                padding: 8px 12px;
                border-radius: 0;
                width: 100%;
                top: 0;
            }
            .landing-navbar .navbar-brand span {
                font-size: 0.95rem;
            }
            .landing-navbar .navbar-brand img {
                max-height: 30px;
                width: auto;
            }

            .hero-section { padding-top: 100px; padding-bottom: 60px; }
            .hero-title { font-size: clamp(1.6rem, 7vw, 2.4rem); }
            .hero-content .btn-lg { width: 100%; }

            .section-padding { padding: 45px 0; }

            #security .display-4,
            #features .display-4,
            #exam-formats .display-4,
            #roi .display-4,
            #workflow .display-4 {
                font-size: clamp(1.6rem, 5vw, 2rem);
            }
            #security .card { padding: 1.25rem; }

            .step-card .step-num {
                width: 40px; height: 40px;
                font-size: 1rem;
            }

            #exam-formats .card { padding: 1.25rem; }

            footer .row.g-5 > div { margin-bottom: 1.5rem; }
            footer .text-lg-start { text-align: center; }
            footer h3 { font-size: 1.3rem; }
        }

        @media (max-width: 575.98px) {
            .landing-navbar { padding: 6px 10px; }
            .landing-navbar .navbar-brand span { font-size: 0.82rem; }
            .landing-navbar .navbar-brand img { max-height: 26px; }

            .hero-section { padding-top: 80px; padding-bottom: 50px; }
            .hero-title { font-size: clamp(1.4rem, 8vw, 2rem); }
            .hero-content .btn-lg {
                font-size: 0.85rem;
                padding: 10px 16px;
            }

            .section-padding { padding: 35px 0; }

            .container-xxl.px-5 { padding-left: 14px; padding-right: 14px; }

            .feature-card { padding: 1.5rem 1rem; }
            .feature-icon-lg { width: 60px; height: 60px; font-size: 1.6rem; }

            .bg-blob { display: none; }

            .table { font-size: 0.75rem; }
            .table th,
            .table td { padding: 0.5rem; }

            footer .row.g-5 { gap: 0; }
        }

        /* ── Global responsive fixes ── */
        @media (max-width: 991.98px) {
            /* Table responsive */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .table-responsive > .table {
                min-width: 600px;
            }

            /* ROI table responsive */
            #roi .table-responsive {
                overflow-x: auto;
            }
            #roi table {
                min-width: 700px;
            }

            /* Hero carousel responsive */
            .hero-carousel .carousel-item img {
                max-height: 400px;
                object-fit: contain;
            }

            /* Feature cards */
            .feature-card {
                padding: 1.5rem;
            }

            /* ROI table */
            #roi .table td,
            #roi .table th {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 767.98px) {
            /* Navbar */
            .landing-navbar .navbar-brand span {
                font-size: 0.85rem;
            }
            .landing-navbar .navbar-brand img {
                max-height: 28px;
            }

            /* Hero section */
            .hero-section {
                padding-top: 90px;
                padding-bottom: 50px;
            }
            .hero-title {
                font-size: clamp(1.4rem, 8vw, 2.2rem);
                line-height: 1.2;
            }
            .hero-content .lead {
                font-size: 1rem;
            }
            .hero-content .btn-lg {
                padding: 12px 16px;
                font-size: 0.9rem;
            }

            /* Section spacing */
            .section-padding { padding: 40px 0; }

            /* Stats */
            .stat-item {
                padding: 10px 0;
            }
            .stat-item .display-6 {
                font-size: clamp(1.5rem, 4vw, 2rem);
            }

            /* About section */
            #about .display-5 {
                font-size: clamp(1.5rem, 5vw, 2rem);
            }
            #about .row.g-5 > div { text-align: center; }
            #about .col-md-4 {
                margin-bottom: 1rem;
            }

            /* Security cards */
            #security .card {
                padding: 1rem;
            }

            /* Feature cards */
            .feature-card {
                padding: 1.25rem;
            }
            .feature-icon-lg {
                width: 56px;
                height: 56px;
                font-size: 1.4rem;
            }

            /* ROI table */
            #roi .table {
                font-size: 0.75rem;
            }
            #roi .table td,
            #roi .table th {
                padding: 0.4rem 0.5rem;
            }

            /* Team */
            .team-img-wrapper {
                width: 120px;
                height: 120px;
            }

            /* Workflow */
            .step-card {
                padding: 1rem;
            }
            .step-num {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }

            /* Exam formats */
            #exam-formats .card {
                padding: 1rem;
            }

            /* Team section */
            .team-item {
                padding: 1rem 0.5rem;
            }
            .team-img-wrapper {
                width: 110px;
                height: 110px;
            }

            /* Footer */
            footer .row.g-5 > div {
                margin-bottom: 1rem;
            }
            footer .text-lg-start {
                text-align: center;
            }
            footer h3 {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 575.98px) {
            .landing-navbar { padding: 6px 10px; }
            .landing-navbar .navbar-brand span { font-size: 0.75rem; }
            .landing-navbar .navbar-brand img { max-height: 24px; }

            .hero-section { padding-top: 70px; padding-bottom: 40px; }
            .hero-title { font-size: clamp(1.2rem, 8vw, 1.8rem); }
            .hero-content .lead { font-size: 0.9rem; }
            .hero-content .btn-lg {
                font-size: 0.8rem;
                padding: 10px 12px;
            }

            .section-padding { padding: 30px 0; }

            .container-xxl.px-5 { padding-left: 12px; padding-right: 12px; }

            .feature-card { padding: 1rem; }
            .feature-icon-lg { width: 50px; height: 50px; font-size: 1.3rem; }

            .bg-blob { display: none; }

            .table { font-size: 0.7rem; }
            .table th,
            .table td { padding: 0.4rem; }

            footer .row.g-5 { gap: 0; }
            footer .col-md-4 { margin-bottom: 1.5rem; }
            footer h3 { font-size: 1rem; }

            /* Hero carousel */
            .hero-carousel .carousel-item img {
                max-height: 250px;
            }
        }

        /* ── Print styles ── */
        @media print {
            .landing-navbar,
            .hero-section .floating-card,
            .bg-blob,
            .animated-bg-container,
            footer,
            .btn {
                display: none !important;
            }
            .hero-section {
                min-height: auto !important;
                padding: 0 !important;
            }
            .section-padding { padding: 0 !important; }
            .container-xxl { max-width: 100% !important; padding: 0 !important; }
            .card { border: none !important; box-shadow: none !important; }
            .bg-dark, .bg-light { background: transparent !important; color: #000 !important; }
            a { text-decoration: none !important; color: #000 !important; }
        }
    </style>
    <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
</head>