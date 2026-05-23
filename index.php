<?php
session_start();
require 'db.php';

// Auth Redirect
if (isset($_SESSION['role'])) {
    $r = $_SESSION['role'];
    if ($r === 'admin')
        header("Location: admin_dashboard");
    elseif ($r === 'examiner')
        header("Location: assess");
    elseif ($r === 'student')
        header("Location: student_dashboard");
    exit;
}

// Fetch dynamic settings
$settings = [];
try {
    $settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}

// Global branding details
$portal_title = $settings['portal_title'] ?? 'Veritas Mock Nursing Council Exam';
$portal_logo = $settings['portal_logo'] ?? '';
$portal_favicon = $settings['portal_favicon'] ?? 'assets/favicon.ico';
$contact_email = $settings['contact_email'] ?? 'support@veritas.edu.ng';
$contact_address = $settings['contact_address'] ?? 'Innovation Lab, Abuja';
$footer_text = $settings['footer_text'] ?? '&copy; 2026 BMXcode &bull; UNCOMPROMISED EXCELLENCE';

// Helper to support simple markdown bold in bullets
function render_cms_markdown($text) {
    return preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', htmlspecialchars($text));
}

// Fetch basic stats
$exam_count = 0;
$student_count = 0;
try {
    $exam_count = $db->query("SELECT COUNT(*) FROM exams WHERE is_active = 1 AND deleted_at IS NULL")->fetchColumn();
    $student_count = $db->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL")->fetchColumn();
} catch (Exception $e) {
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- UI Plugins -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
            background: #fff;
            overflow-x: hidden;
            position: relative;
        }

        h1,
        h2,
        h3,
        h4,
        .outfit {
            font-family: 'Outfit', sans-serif;
        }

        /* Premium Floating Animated Background blobs */
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
            width: 500px;
            height: 500px;
            top: -100px;
            right: -100px;
            animation-duration: 20s;
        }

        .blob-primary {
            background: radial-gradient(circle, #092c19 0%, rgba(9, 44, 25, 0) 70%);
            width: 600px;
            height: 600px;
            bottom: 10%;
            left: -200px;
            animation-duration: 30s;
            animation-delay: -5s;
        }

        .blob-purple {
            background: radial-gradient(circle, #6366f1 0%, rgba(99, 102, 241, 0) 70%);
            width: 450px;
            height: 450px;
            top: 40%;
            right: 15%;
            animation-duration: 25s;
            animation-delay: -10s;
        }

        @keyframes float-blob {
            0% {
                transform: translate(0, 0) rotate(0deg) scale(1);
            }
            50% {
                transform: translate(80px, 50px) rotate(180deg) scale(1.1);
            }
            100% {
                transform: translate(-40px, -80px) rotate(360deg) scale(0.9);
            }
        }

        .hover-shadow {
            transition: all 0.3s ease;
        }
        .hover-shadow:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.08) !important;
        }
        .hover-translate {
            transition: all 0.3s ease;
        }
        .hover-translate:hover {
            transform: translateY(-8px);
        }

        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Hero Refinements */
        .hero-section {
            padding-top: 160px;
            padding-bottom: 100px;
        }

        .hero-title {
            font-size: clamp(2rem, 8vw, 4.5rem);
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -0.04em;
            min-height: 3em;
        }

        @media (min-width: 768px) {
            .hero-title {
                min-height: 2.2em;
                line-height: 1.05;
            }
        }

        @media (max-width: 991px) {
            .hero-section {
                padding-top: 150px !important;
                padding-bottom: 60px !important;
                align-items: flex-start !important;
                min-height: auto !important;
            }

            .hero-badge i {
                display: none;
            }
        }

        /* Elegant Glass Navbar Transition */
        .landing-navbar {
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(25px) saturate(180%) !important;
            border: 1px solid rgba(255, 255, 255, 0.4) !important;
            border-radius: 60px !important;
            top: 25px;
            width: 95%;
            max-width: 1550px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 35px !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05) !important;
            z-index: 2000;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .landing-navbar.scrolled {
            top: 0;
            width: 100% !important;
            max-width: 100% !important;
            background: rgba(255, 255, 255, 0.15) !important;
            backdrop-filter: blur(30px) saturate(150%) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 12px 60px !important;
        }

        /* Responsive Navbar Tweaks */
        @media (max-width: 991px) {
            .landing-navbar {
                background: white !important;
                top: 0;
                width: 100% !important;
                border-radius: 0 !important;
                padding: 15px 20px !important;
            }
        }

        /* Animated Text Swapper */
        .text-swap-container {
            color: var(--primary-color);
            display: inline-block;
        }

        .swap-text {
            display: none;
        }

        .swap-text.active {
            display: inline;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Hero Image Slider */
        .hero-carousel .carousel-item img {
            border-radius: 3rem;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.15);
            transition: transform 0.5s ease;
        }

        .hero-carousel .carousel-item.active img {
            transform: scale(1);
        }

        /* ── Responsive Navbar ── */
        .navbar-toggler:focus { box-shadow: none; }
        .navbar-toggler i { font-size: 1.5rem; transition: transform 0.2s; }
        .navbar-toggler[aria-expanded="true"] i { transform: rotate(90deg); }

        @media (max-width: 991.98px) {
            .landing-navbar {
                padding: 10px 16px !important;
                width: 96%;
                border-radius: 20px !important;
                top: 12px;
            }
            .landing-navbar .navbar-collapse {
                background: rgba(255,255,255,0.98);
                backdrop-filter: blur(20px);
                border-radius: 16px;
                padding: 16px;
                margin-top: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            }
            .landing-navbar .navbar-nav { gap: 4px; }
            .landing-navbar .nav-link {
                padding: 10px 16px !important;
                border-radius: 10px;
                font-size: 0.95rem;
            }
            .landing-navbar .nav-link:hover { background: #f0fdf4; }
            .landing-navbar .navbar-brand span { font-size: calc(1rem + 1vw) !important; }
            .landing-navbar .d-flex.gap-3 { margin-top: 8px; }
            .landing-navbar .btn { width: 100%; }
        }

        @media (max-width: 576px) {
            .landing-navbar { padding: 8px 12px !important; top: 8px; }
            .landing-navbar .navbar-brand img { max-height: 32px !important; }
            .landing-navbar .navbar-brand span { font-size: 0.9rem !important; }
        }
    </style>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
    <!-- Animated Background Blobs -->
    <div class="animated-bg-container">
        <div class="bg-blob blob-green"></div>
        <div class="bg-blob blob-primary"></div>
        <div class="bg-blob blob-purple"></div>
    </div>

    <!-- Dynamic Transparent Navbar -->
    <nav class="navbar navbar-expand-lg landing-navbar" id="mainNav">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-3" href="#">
                <?php if (!empty($portal_logo) && file_exists($portal_logo)): ?>
                    <img src="<?= htmlspecialchars($portal_logo) ?>" alt="Logo" style="max-height: 45px; width: auto; border-radius: 4px;">
                <?php else: ?>
                    <img src="assets/favicon.ico" alt="Logo" width="45" height="45">
                <?php endif; ?>
                <span class="outfit fs-3 fw-bold text-success"><?= htmlspecialchars($portal_title) ?></span>
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarContent">
                <i class="bi bi-list"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav mx-auto mb-3 mb-lg-0 text-center text-lg-start">
                    <?php if (($settings['section_about_active'] ?? '1') === '1'): ?>
                        <li class="nav-item"><a class="nav-link fw-bold" href="#about">About</a></li>
                    <?php endif; ?>
                    <?php if (($settings['section_solutions_active'] ?? '1') === '1'): ?>
                        <li class="nav-item"><a class="nav-link fw-bold" href="#features">Services</a></li>
                    <?php endif; ?>
                    <?php if (($settings['section_workflow_active'] ?? '1') === '1'): ?>
                        <li class="nav-item"><a class="nav-link fw-bold" href="#workflow">Workflow</a></li>
                    <?php endif; ?>
                    <?php if (($settings['section_team_active'] ?? '1') === '1'): ?>
                        <li class="nav-item"><a class="nav-link fw-bold" href="#team">Team</a></li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-3">
                    <a href="login" class="btn btn-outline-dark rounded-pill px-4 fw-bold">Staff Login</a>
                    <a href="student_login" class="btn btn-success rounded-pill px-5 fw-bold shadow-lg">Portal Access</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Master Hero Section -->
    <?php if (($settings['section_hero_active'] ?? '1') === '1'): ?>
    <section class="hero-section bg-dots">
        <div class="container-xxl hero-content px-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-6" data-aos="fade-right">
                    <span class="hero-badge"><i class="bi bi-stars me-2"></i> <?= htmlspecialchars($settings['hero_badge'] ?? 'LEADING INNOVATION') ?></span>
                    <h1 class="hero-title mb-4">
                        Intelligent <br>
                        <span class="text-swap-container">
                            <?php 
                            $swaps = explode('|', $settings['hero_title_swaps'] ?? 'AI Proctoring|Remote Security|Live Proctored Chat|Global Integrity');
                            foreach ($swaps as $idx => $swap): 
                                if (empty(trim($swap))) continue;
                            ?>
                                <span class="swap-text <?= $idx === 0 ? 'active' : '' ?>"><?= htmlspecialchars(trim($swap)) ?></span>
                            <?php endforeach; ?>
                        </span>
                    </h1>
                    <p class="lead text-muted mb-5 pe-lg-5 fw-normal"><?= htmlspecialchars($settings['hero_lead'] ?? 'The ultimate digital environment for institutions. Seamlessly integrated monitoring and automated performance tracking with zero-compromise security.') ?></p>
                    <div class="d-grid d-sm-flex flex-wrap gap-3">
                        <a href="<?= htmlspecialchars($settings['hero_btn1_url'] ?? 'student_login.php') ?>"
                            class="btn btn-success btn-lg rounded-pill px-5 py-3 fw-bold shadow-lg w-100 w-sm-auto"><?= htmlspecialchars($settings['hero_btn1_text'] ?? 'STUDENT ENTRANCE') ?></a>
                        <a href="<?= htmlspecialchars($settings['hero_btn2_url'] ?? '#workflow') ?>"
                            class="btn btn-outline-dark btn-lg rounded-pill px-5 py-3 fw-bold w-100 w-sm-auto"><?= htmlspecialchars($settings['hero_btn2_text'] ?? 'HOW IT WORKS') ?></a>
                        <a href="downloads/seb/VeritasSEB-1.0.0-win.exe" class="btn btn-outline-success btn-lg rounded-pill px-5 py-3 fw-bold w-100 w-sm-auto" target="_blank"><i class="bi bi-download me-2"></i>Download SEB Browser</a>
                    </div>
                </div>

                <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
                    <!-- Hero Image Interchange Slider -->
                    <div id="heroCarousel" class="carousel slide hero-carousel carousel-fade" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active" data-bs-interval="4000">
                                <img src="<?= htmlspecialchars($settings['hero_img1'] ?? 'assets/img/hero.png') ?>" class="d-block w-100" alt="Proctoring Tech">
                            </div>
                            <div class="carousel-item" data-bs-interval="4000">
                                <img src="<?= htmlspecialchars($settings['hero_img2'] ?? 'assets/img/proctoring.png') ?>" class="d-block w-100" alt="Advanced HUD">
                            </div>
                            <div class="carousel-item" data-bs-interval="4000">
                                <img src="<?= htmlspecialchars($settings['about_img'] ?? 'assets/img/about.png') ?>" class="d-block w-100" alt="Collaboration">
                            </div>
                        </div>
                        <!-- Float Overlay Cards for Interactivity -->
                        <div class="floating-card shadow-lg" style="bottom: 40px; left: -20px; z-index: 10;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary-light p-2 rounded-circle"><i
                                        class="bi bi-shield-fill-check text-success fs-4"></i></div>
                                <div>
                                    <div class="fw-bold small"><?= htmlspecialchars($settings['hero_floating_card_title'] ?? 'Identity Sync') ?></div>
                                    <div class="text-muted smaller"><?= htmlspecialchars($settings['hero_floating_card_desc'] ?? '99.9% Match Accuracy') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Quick Stats -->
    <?php if (($settings['section_stats_active'] ?? '1') === '1'): ?>
    <section class="py-5 border-bottom">
        <div class="container-xxl px-5">
            <div class="row text-center py-4 g-4 px-md-5">
                <div class="col-md-4 stat-item">
                    <div class="display-6 fw-bold outfit text-success"><?= $exam_count ?>+</div>
                    <div class="text-muted fw-bold small uppercase tracking-widest mt-2"><?= htmlspecialchars($settings['stat1_label'] ?? 'ACTIVE ASSESSMENTS') ?></div>
                </div>
                <div class="col-md-4 stat-item border-md-start">
                    <div class="display-6 fw-bold outfit text-dark"><?= $student_count ?>+</div>
                    <div class="text-muted fw-bold small uppercase tracking-widest mt-2"><?= htmlspecialchars($settings['stat2_label'] ?? 'VERIFIED STUDENTS') ?></div>
                </div>
                <div class="col-md-4 stat-item border-md-start">
                    <div class="display-6 fw-bold outfit text-primary"><?= htmlspecialchars($settings['stat3_val'] ?? '150ms') ?></div>
                    <div class="text-muted fw-bold small uppercase tracking-widest mt-2"><?= htmlspecialchars($settings['stat3_label'] ?? 'LIVE STREAM LATENCY') ?></div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- About Section -->
    <?php if (($settings['section_about_active'] ?? '1') === '1'): ?>
    <section id="about" class="section-padding">
        <div class="container-xxl px-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 order-2 order-lg-1" data-aos="fade-right">
                    <img src="<?= htmlspecialchars($settings['about_img'] ?? 'assets/img/about.png') ?>" class="img-fluid rounded-5 shadow-lg" alt="About NextGen">
                </div>
                <div class="col-lg-6 order-1 order-lg-2" data-aos="fade-left">
                    <h2 class="display-5 fw-bold mb-4"><?= $settings['about_title'] ?? 'Our Mission: <br><span class="text-success">Integrity at Scale</span>' ?></h2>
                    <p class="text-muted fs-5 mb-4"><?= htmlspecialchars($settings['about_lead'] ?? 'Founded on the principle that digital education requires uncompromising security, NextGen Assessment Portal bridges the gap between remote testing and physical invigilation.') ?></p>
                    <p class="text-muted mb-5"><?= htmlspecialchars($settings['about_desc'] ?? 'We provide institutions with a robust framework to conduct high-stakes examinations anywhere in the world, backed by real-time neural monitoring and automated behavioral analysis.') ?></p>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <h5 class="fw-bold"><i class="bi bi-patch-check-fill text-success me-2"></i> <?= htmlspecialchars($settings['about_bullet1_title'] ?? 'Security First') ?></h5>
                            <p class="small text-muted"><?= htmlspecialchars($settings['about_bullet1_desc'] ?? 'End-to-end encrypted session stores.') ?></p>
                        </div>
                        <div class="col-md-4">
                            <h5 class="fw-bold"><i class="bi bi-lightning-fill text-warning me-2"></i> <?= htmlspecialchars($settings['about_bullet2_title'] ?? 'Rapid Results') ?></h5>
                            <p class="small text-muted"><?= htmlspecialchars($settings['about_bullet2_desc'] ?? 'Real-time grading and instant metrics.') ?></p>
                        </div>
                        <div class="col-md-4">
                            <h5 class="fw-bold"><i class="bi bi-chat-left-dots-fill text-primary me-2"></i> <?= htmlspecialchars($settings['about_bullet3_title'] ?? 'Proctored Chat') ?></h5>
                            <p class="small text-muted"><?= htmlspecialchars($settings['about_bullet3_desc'] ?? 'Direct intervention & student support.') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Security Framework Section (7-Layer Security Stack) -->
    <?php if (($settings['section_security_active'] ?? '1') === '1'): ?>
    <section id="security" class="section-padding bg-dark text-white position-relative">
        <div class="animated-bg-container">
            <div class="bg-blob blob-green" style="opacity: 0.08; width: 400px; height: 400px; top: 10%; left: 10%;"></div>
            <div class="bg-blob blob-purple" style="opacity: 0.05; width: 500px; height: 500px; bottom: 10%; right: 10%;"></div>
        </div>
        
        <div class="container-xxl px-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-3 py-2 uppercase small tracking-wider mb-2"><?= htmlspecialchars($settings['sec_badge'] ?? 'Military-Grade Defense') ?></span>
                <h2 class="display-4 fw-bold outfit text-white"><?= $settings['sec_title'] ?? 'The <span class="text-success">7-Layer Security</span> Stack' ?></h2>
                <p class="text-muted fs-5"><?= htmlspecialchars($settings['sec_lead'] ?? 'Uncompromised academic integrity backed by active hardware-to-cloud security measures.') ?></p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card h-100 border border-secondary border-opacity-25 p-4 rounded-4 hover-shadow" style="background: rgba(255,255,255,0.02); backdrop-filter: blur(10px); color: #fff;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 45px; height: 45px; font-size: 1.1rem; flex-shrink:0;">L7</div>
                            <h4 class="fw-bold m-0 outfit text-white fs-5"><?= htmlspecialchars($settings['sec_l7_title'] ?? 'OS Kiosk (SEB)') ?></h4>
                        </div>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($settings['sec_l7_desc'] ?? '') ?></p>
                        <a href="downloads/seb/VeritasSEB-1.0.0-win.exe" class="btn btn-sm btn-outline-success rounded-pill mt-3 px-4" target="_blank"><i class="bi bi-download me-1"></i> Download SEB</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card h-100 border border-secondary border-opacity-25 p-4 rounded-4 hover-shadow" style="background: rgba(255,255,255,0.02); backdrop-filter: blur(10px); color: #fff;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 45px; height: 45px; font-size: 1.1rem; flex-shrink:0;">L6</div>
                            <h4 class="fw-bold m-0 outfit text-white fs-5"><?= htmlspecialchars($settings['sec_l6_title'] ?? 'Browser Lockdown') ?></h4>
                        </div>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($settings['sec_l6_desc'] ?? '') ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="card h-100 border border-secondary border-opacity-25 p-4 rounded-4 hover-shadow" style="background: rgba(255,255,255,0.02); backdrop-filter: blur(10px); color: #fff;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 45px; height: 45px; font-size: 1.1rem; flex-shrink:0;">L5</div>
                            <h4 class="fw-bold m-0 outfit text-white fs-5"><?= htmlspecialchars($settings['sec_l5_title'] ?? 'Tab Blur Counters') ?></h4>
                        </div>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($settings['sec_l5_desc'] ?? '') ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="card h-100 border border-secondary border-opacity-25 p-4 rounded-4 hover-shadow" style="background: rgba(255,255,255,0.02); backdrop-filter: blur(10px); color: #fff;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 45px; height: 45px; font-size: 1.1rem; flex-shrink:0;">L4</div>
                            <h4 class="fw-bold m-0 outfit text-white fs-5"><?= htmlspecialchars($settings['sec_l4_title'] ?? 'Webcam Proctoring') ?></h4>
                        </div>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($settings['sec_l4_desc'] ?? '') ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="500">
                    <div class="card h-100 border border-secondary border-opacity-25 p-4 rounded-4 hover-shadow" style="background: rgba(255,255,255,0.02); backdrop-filter: blur(10px); color: #fff;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 45px; height: 45px; font-size: 1.1rem; flex-shrink:0;">L3</div>
                            <h4 class="fw-bold m-0 outfit text-white fs-5"><?= htmlspecialchars($settings['sec_l3_title'] ?? 'Device Whitelisting') ?></h4>
                        </div>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($settings['sec_l3_desc'] ?? '') ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="card h-100 border border-secondary border-opacity-25 p-4 rounded-4 hover-shadow" style="background: rgba(255,255,255,0.02); backdrop-filter: blur(10px); color: #fff;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 45px; height: 45px; font-size: 1.1rem; flex-shrink:0;">L2</div>
                            <h4 class="fw-bold m-0 outfit text-white fs-5"><?= htmlspecialchars($settings['sec_l2_title'] ?? 'Session Hardening') ?></h4>
                        </div>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($settings['sec_l2_desc'] ?? '') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Services Section -->
    <?php if (($settings['section_solutions_active'] ?? '1') === '1'): ?>
    <section id="features" class="section-padding bg-light">
        <div class="container-xxl px-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-4 fw-bold outfit"><?= $settings['sol_title'] ?? 'Institutional <span class="text-success">Solutions</span>' ?></h2>
                <p class="text-muted fs-5"><?= htmlspecialchars($settings['sol_lead'] ?? 'A comprehensive toolset engineered for digital transformation.') ?></p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon-lg"><i class="bi bi-people-fill"></i></div>
                        <h4 class="fw-bold"><?= htmlspecialchars($settings['sol1_title'] ?? 'Staff Control') ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($settings['sol1_desc'] ?? '') ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon-lg"><i class="bi bi-database-fill-check"></i></div>
                        <h4 class="fw-bold"><?= htmlspecialchars($settings['sol2_title'] ?? 'Bulk Intake') ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($settings['sol2_desc'] ?? '') ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon-lg"><i class="bi bi-question-diamond-fill"></i></div>
                        <h4 class="fw-bold"><?= htmlspecialchars($settings['sol3_title'] ?? 'Dynamic Pools') ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($settings['sol3_desc'] ?? '') ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon-lg"><i class="bi bi-chat-left-text-fill"></i></div>
                        <h4 class="fw-bold"><?= htmlspecialchars($settings['sol4_title'] ?? 'Proctored Chat') ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($settings['sol4_desc'] ?? '') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- AI Proctoring Packages Section -->
    <?php if (($settings['section_ai_active'] ?? '1') === '1'): ?>
    <section id="ai-proctoring" class="section-padding bg-light position-relative">
        <div class="container-xxl px-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 uppercase small tracking-wider mb-2"><?= htmlspecialchars($settings['ai_badge'] ?? 'Automated Invigilation') ?></span>
                <h2 class="display-4 fw-bold outfit text-dark"><?= $settings['ai_title'] ?? 'Client-Side <span class="text-success">AI Proctoring</span> Packages' ?></h2>
                <p class="text-muted fs-5"><?= htmlspecialchars($settings['ai_lead'] ?? 'Scale remote remote surveillance effortlessly.') ?></p>
            </div>
            
            <div class="row g-4 justify-content-center">
                <!-- Package A -->
                <div class="col-md-6 col-lg-4" data-aos="zoom-in" data-aos-delay="100">
                    <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-white hover-shadow transition">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 d-inline-block mb-3" style="width: fit-content;">
                            <i class="bi bi-cpu fs-3"></i>
                        </div>
                        <h4 class="fw-bold outfit"><?= htmlspecialchars($settings['ai_pkgA_title'] ?? 'Package A') ?></h4>
                        <p class="text-muted small"><?= htmlspecialchars($settings['ai_pkgA_desc'] ?? '') ?></p>
                        <hr class="my-3 opacity-25">
                        <ul class="list-unstyled d-flex flex-column gap-2.5 small text-muted p-0">
                            <?php 
                            $bulletsA = explode(';;', $settings['ai_pkgA_bullets'] ?? '');
                            foreach ($bulletsA as $b): 
                                if (empty(trim($b))) continue;
                            ?>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> <?= render_cms_markdown($b) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="mt-auto pt-4 text-center">
                            <span class="badge bg-success text-white px-3 py-2 rounded-pill small">Active Integration</span>
                        </div>
                    </div>
                </div>
                
                <!-- Package B -->
                <div class="col-md-6 col-lg-4" data-aos="zoom-in" data-aos-delay="200">
                    <div class="card border-0 shadow-lg rounded-4 h-100 p-4 bg-white border border-success border-2 position-relative hover-shadow transition">
                        <div class="position-absolute top-0 end-0 bg-success text-white px-3 py-1 rounded-bl-4 rounded-tr-4 small fw-bold" style="border-top-right-radius: 14px; border-bottom-left-radius: 14px;">RECOMMENDED</div>
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 d-inline-block mb-3" style="width: fit-content;">
                            <i class="bi bi-eye fs-3"></i>
                        </div>
                        <h4 class="fw-bold outfit"><?= htmlspecialchars($settings['ai_pkgB_title'] ?? 'Package B') ?></h4>
                        <p class="text-muted small"><?= htmlspecialchars($settings['ai_pkgB_desc'] ?? '') ?></p>
                        <hr class="my-3 opacity-25">
                        <ul class="list-unstyled d-flex flex-column gap-2.5 small text-muted p-0">
                            <?php 
                            $bulletsB = explode(';;', $settings['ai_pkgB_bullets'] ?? '');
                            foreach ($bulletsB as $b): 
                                if (empty(trim($b))) continue;
                            ?>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> <?= render_cms_markdown($b) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="mt-auto pt-4 text-center">
                            <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill small">Available on Request</span>
                        </div>
                    </div>
                </div>
                
                <!-- Package C -->
                <div class="col-md-6 col-lg-4" data-aos="zoom-in" data-aos-delay="300">
                    <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-white hover-shadow transition">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 d-inline-block mb-3" style="width: fit-content;">
                            <i class="bi bi-server fs-3"></i>
                        </div>
                        <h4 class="fw-bold outfit"><?= htmlspecialchars($settings['ai_pkgC_title'] ?? 'Package C') ?></h4>
                        <p class="text-muted small"><?= htmlspecialchars($settings['ai_pkgC_desc'] ?? '') ?></p>
                        <hr class="my-3 opacity-25">
                        <ul class="list-unstyled d-flex flex-column gap-2.5 small text-muted p-0">
                            <?php 
                            $bulletsC = explode(';;', $settings['ai_pkgC_bullets'] ?? '');
                            foreach ($bulletsC as $b): 
                                if (empty(trim($b))) continue;
                            ?>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> <?= render_cms_markdown($b) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="mt-auto pt-4 text-center">
                            <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill small">Available on Request</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Workflow Section -->
    <?php if (($settings['section_workflow_active'] ?? '1') === '1'): ?>
    <section id="workflow" class="section-padding">
        <div class="container-xxl px-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-5" data-aos="fade-right">
                    <h2 class="display-4 fw-bold outfit mb-4"><?= $settings['flow_title'] ?? 'Operational <span class="text-success">Workflow</span>' ?></h2>
                    <p class="text-muted fs-5 mb-5"><?= htmlspecialchars($settings['flow_lead'] ?? 'Seamless scaling for institutions.') ?></p>
                    <div class="d-flex flex-column gap-3">
                        <div class="spec-pill"><i class="bi bi-cpu text-primary"></i> <?= htmlspecialchars($settings['flow_spec1'] ?? 'PHP 8.1+ Core') ?></div>
                        <div class="spec-pill"><i class="bi bi-database text-success"></i> <?= htmlspecialchars($settings['flow_spec2'] ?? 'Distributed SQL') ?></div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="vstack gap-4">
                        <div class="step-card" data-aos="fade-left" data-aos-delay="100">
                            <div class="step-num">1</div>
                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($settings['flow_step1_title'] ?? 'Institutional Setup') ?></h5>
                            <p class="text-muted mb-0"><?= htmlspecialchars($settings['flow_step1_desc'] ?? '') ?></p>
                        </div>
                        <div class="step-card" data-aos="fade-left" data-aos-delay="200">
                            <div class="step-num">2</div>
                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($settings['flow_step2_title'] ?? 'Resource Curation') ?></h5>
                            <p class="text-muted mb-0"><?= htmlspecialchars($settings['flow_step2_desc'] ?? '') ?></p>
                        </div>
                        <div class="step-card" data-aos="fade-left" data-aos-delay="300">
                            <div class="step-num">3</div>
                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($settings['flow_step3_title'] ?? 'Live Session Access') ?></h5>
                            <p class="text-muted mb-0"><?= htmlspecialchars($settings['flow_step3_desc'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Examination Formats Supported -->
    <?php if (($settings['section_formats_active'] ?? '1') === '1'): ?>
    <section id="exam-formats" class="section-padding bg-white position-relative">
        <div class="container-xxl px-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 uppercase small tracking-wider mb-2"><?= htmlspecialchars($settings['fmt_badge'] ?? 'Unparalleled Versatility') ?></span>
                <h2 class="display-4 fw-bold outfit text-dark"><?= $settings['fmt_title'] ?? 'Versatile <span class="text-success">Examination Formats</span>' ?></h2>
                <p class="text-muted fs-5"><?= htmlspecialchars($settings['fmt_lead'] ?? 'A unified portal supporting traditional cognitive tests.') ?></p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card border-0 bg-light rounded-4 h-100 p-4 hover-shadow transition">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-4 d-inline-block mb-4" style="width: fit-content;">
                            <i class="bi bi-ui-checks-grid fs-3"></i>
                        </div>
                        <h4 class="fw-bold outfit"><?= htmlspecialchars($settings['fmt1_title'] ?? 'MCQ Exams') ?></h4>
                        <p class="text-muted mb-4 small"><?= htmlspecialchars($settings['fmt1_desc'] ?? '') ?></p>
                        <div class="vstack gap-2 small text-muted font-weight-medium">
                            <?php 
                            $bullets1 = explode(';;', $settings['fmt1_bullets'] ?? '');
                            foreach ($bullets1 as $bullet): 
                                if (empty(trim($bullet))) continue;
                            ?>
                                <div class="d-flex align-items-center gap-2"><i class="bi bi-check2 text-success"></i> <?= htmlspecialchars(trim($bullet)) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card border-0 bg-light rounded-4 h-100 p-4 hover-shadow transition">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-4 d-inline-block mb-4" style="width: fit-content;">
                            <i class="bi bi-heart-pulse-fill fs-3"></i>
                        </div>
                        <h4 class="fw-bold outfit"><?= htmlspecialchars($settings['fmt2_title'] ?? 'OSCE Practical') ?></h4>
                        <p class="text-muted mb-4 small"><?= htmlspecialchars($settings['fmt2_desc'] ?? '') ?></p>
                        <div class="vstack gap-2 small text-muted font-weight-medium">
                            <?php 
                            $bullets2 = explode(';;', $settings['fmt2_bullets'] ?? '');
                            foreach ($bullets2 as $bullet): 
                                if (empty(trim($bullet))) continue;
                            ?>
                                <div class="d-flex align-items-center gap-2"><i class="bi bi-check2 text-success"></i> <?= htmlspecialchars(trim($bullet)) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="card border-0 bg-light rounded-4 h-100 p-4 hover-shadow transition">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-4 d-inline-block mb-4" style="width: fit-content;">
                            <i class="bi bi-mic-fill fs-3"></i>
                        </div>
                        <h4 class="fw-bold outfit"><?= htmlspecialchars($settings['fmt3_title'] ?? 'VIVA / Oral') ?></h4>
                        <p class="text-muted mb-4 small"><?= htmlspecialchars($settings['fmt3_desc'] ?? '') ?></p>
                        <div class="vstack gap-2 small text-muted font-weight-medium">
                            <?php 
                            $bullets3 = explode(';;', $settings['fmt3_bullets'] ?? '');
                            foreach ($bullets3 as $bullet): 
                                if (empty(trim($bullet))) continue;
                            ?>
                                <div class="d-flex align-items-center gap-2"><i class="bi bi-check2 text-success"></i> <?= htmlspecialchars(trim($bullet)) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Team Section -->
    <?php if (($settings['section_team_active'] ?? '1') === '1'): ?>
    <section id="team" class="section-padding bg-light">
        <div class="container-xxl px-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold outfit"><?= $settings['team_title'] ?? 'Academic <span class="text-success">Visionaries</span>' ?></h2>
                <p class="text-muted fs-5"><?= htmlspecialchars($settings['team_lead'] ?? '') ?></p>
            </div>
            <div class="row g-5 justify-content-center">
                <div class="col-md-4 col-lg-3" data-aos="fade-up">
                    <div class="team-item">
                        <div class="team-img-wrapper"><img src="assets/img/secure.png" alt="Team"></div>
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($settings['team_m1_name'] ?? 'BMX Initiator') ?></h4>
                        <p class="text-success fw-bold small mb-3"><?= htmlspecialchars($settings['team_m1_role'] ?? 'SYSTEM ARCHITECT') ?></p>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="team-item">
                        <div class="team-img-wrapper"><img src="assets/img/icon.png" alt="Team"></div>
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($settings['team_m2_name'] ?? 'Ariel Vance') ?></h4>
                        <p class="text-success fw-bold small mb-3"><?= htmlspecialchars($settings['team_m2_role'] ?? 'SECURITY LEAD') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Veritas Advantage & ROI Section -->
    <?php if (($settings['section_roi_active'] ?? '1') === '1'): ?>
    <section id="roi" class="section-padding bg-dark text-white position-relative">
        <div class="animated-bg-container">
            <div class="bg-blob blob-primary" style="opacity: 0.1; width: 600px; height: 600px; top: -100px; left: -100px;"></div>
        </div>
        <div class="container-xxl px-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-3 py-2 uppercase small tracking-wider mb-2"><?= htmlspecialchars($settings['roi_badge'] ?? 'Measurable Institutional Impact') ?></span>
                <h2 class="display-4 fw-bold outfit text-white font-weight-bold"><?= $settings['roi_title'] ?? 'The Veritas Advantage & <span class="text-success">ROI Summary</span>' ?></h2>
                <p class="text-muted fs-5"><?= htmlspecialchars($settings['roi_lead'] ?? '') ?></p>
            </div>
            
            <div class="row g-5 align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h3 class="fw-bold outfit text-white mb-4 fs-4">Annual Cost Savings Analysis</h3>
                    <div class="table-responsive rounded-4 overflow-hidden border border-secondary border-opacity-25">
                        <table class="table table-dark table-hover m-0 align-middle small">
                            <thead>
                                <tr class="bg-success text-white">
                                    <th>Cost Category</th>
                                    <th>Paper-Based</th>
                                    <th>NextGen Portal</th>
                                    <th class="text-success">Annual Savings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rows = explode("\n", $settings['roi_savings_rows'] ?? '');
                                foreach ($rows as $row): 
                                    $cols = explode(';;', $row);
                                    if (count($cols) < 4) continue;
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars(trim($cols[0])) ?></td>
                                        <td><?= htmlspecialchars(trim($cols[1])) ?></td>
                                        <td><?= htmlspecialchars(trim($cols[2])) ?></td>
                                        <td class="text-success font-monospace fw-bold"><?= htmlspecialchars(trim($cols[3])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-active fw-bold border-top border-secondary">
                                    <td><?= htmlspecialchars($settings['roi_total_label'] ?? 'TOTAL DIRECT SAVINGS') ?></td>
                                    <td><?= htmlspecialchars($settings['roi_total_paper'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($settings['roi_total_portal'] ?? '') ?></td>
                                    <td class="text-success font-monospace fs-5"><?= htmlspecialchars($settings['roi_total_savings'] ?? '') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
                    <h3 class="fw-bold outfit text-white mb-4 fs-4"><?= htmlspecialchars($settings['roi_lms_title'] ?? 'Why NextGen Trumps Standard LMS') ?></h3>
                    <div class="vstack gap-4">
                        <div class="d-flex gap-3">
                            <div class="p-3 bg-success bg-opacity-20 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; flex-shrink: 0;"><i class="bi bi-graph-up-arrow fs-4"></i></div>
                            <div>
                                <h5 class="fw-bold text-white mb-1"><?= htmlspecialchars($settings['roi_lms1_title'] ?? '') ?></h5>
                                <p class="text-muted small mb-0"><?= render_cms_markdown($settings['roi_lms1_desc'] ?? '') ?></p>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="p-3 bg-success bg-opacity-20 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; flex-shrink: 0;"><i class="bi bi-infinity fs-4"></i></div>
                            <div>
                                <h5 class="fw-bold text-white mb-1"><?= htmlspecialchars($settings['roi_lms2_title'] ?? '') ?></h5>
                                <p class="text-muted small mb-0"><?= render_cms_markdown($settings['roi_lms2_desc'] ?? '') ?></p>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="p-3 bg-success bg-opacity-20 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; flex-shrink: 0;"><i class="bi bi-award-fill fs-4"></i></div>
                            <div>
                                <h5 class="fw-bold text-white mb-1"><?= htmlspecialchars($settings['roi_lms3_title'] ?? '') ?></h5>
                                <p class="text-muted small mb-0"><?= render_cms_markdown($settings['roi_lms3_desc'] ?? '') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="py-5 bg-dark text-white border-top border-secondary border-opacity-25">
        <div class="container-xxl px-5">
            <div class="row g-5">
                <div class="col-lg-4 text-center text-lg-start">
                    <h3 class="fw-bold text-success mb-4 outfit"><?= htmlspecialchars($portal_title) ?></h3>
                    <p class="opacity-50">Empowering education through secure technological landscapes.</p>
                </div>
                <div class="col-lg-8">
                    <div class="row g-4 text-center text-lg-start">
                        <?php if ((($settings['section_about_active'] ?? '1') === '1') || (($settings['section_solutions_active'] ?? '1') === '1')): ?>
                        <div class="col-md-4">
                            <h6 class="fw-bold text-uppercase small opacity-25 mb-4">Navigation</h6>
                            <ul class="list-unstyled opacity-75 small text-white p-0">
                                <?php if (($settings['section_about_active'] ?? '1') === '1'): ?>
                                    <li class="mb-2"><a href="#about" class="link-light text-decoration-none">About</a></li>
                                <?php endif; ?>
                                <?php if (($settings['section_solutions_active'] ?? '1') === '1'): ?>
                                    <li class="mb-2"><a href="#features" class="link-light text-decoration-none">Services</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <h6 class="fw-bold text-uppercase small opacity-25 mb-4">Download SEB Browser</h6>
                            <ul class="list-unstyled opacity-75 small p-0">
                                <li class="mb-2"><a href="downloads/seb/VeritasSEB-1.0.0-win.exe" class="link-light text-decoration-none" target="_blank"><i class="bi bi-download me-1"></i> Windows (.exe)</a></li>
                                <li class="mb-2"><span class="text-secondary"><i class="bi bi-clock me-1"></i> macOS — Coming Soon</span></li>
                                <li class="mb-2"><span class="text-secondary"><i class="bi bi-clock me-1"></i> Linux — Coming Soon</span></li>
                                <li class="mt-3"><a href="seb-client/README.md" class="link-light text-decoration-none small" target="_blank"><i class="bi bi-info-circle me-1"></i> Setup Guide</a></li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="fw-bold text-uppercase small opacity-25 mb-4">Official Contact</h6>
                            <p class="opacity-75 small mb-1"><?= htmlspecialchars($contact_email) ?></p>
                            <p class="opacity-75 small mb-0"><?= htmlspecialchars($contact_address) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="my-5 opacity-10">
            <div class="text-center opacity-50 small uppercase tracking-widest">
                <?= $footer_text ?>
            </div>
        </div>
    </footer>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true });

        // Transparent to Solid Navbar Transition
        window.addEventListener('scroll', function () {
            const nav = document.getElementById('mainNav');
            if (window.scrollY > 80) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        });

        // Text Swapper Logic
        let currentText = 0;
        const texts = document.querySelectorAll('.swap-text');
        if (texts.length > 0) {
            setInterval(() => {
                texts[currentText].classList.remove('active');
                currentText = (currentText + 1) % texts.length;
                texts[currentText].classList.add('active');
            }, 3000);
        }

        // Responsive Scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({ behavior: 'smooth' });
            });
        });
    </script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>

</html>