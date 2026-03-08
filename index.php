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

// Fetch basic stats
$exam_count = 0;
$student_count = 0;
try {
    $exam_count = $db->query("SELECT COUNT(*) FROM exams WHERE is_active = 1")->fetchColumn();
    $student_count = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextGen Assessment | Secure AI-Proctored Exam Portal</title>

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
        }

        h1,
        h2,
        h3,
        h4,
        .outfit {
            font-family: 'Outfit', sans-serif;
        }

        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Hero Refinements */
        .hero-section {
            padding-top: 160px;
            /* Increased to prevent overlap */
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
            /* Increased Transparency as requested */
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
    </style>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>

    <!-- Dynamic Transparent Navbar -->
    <nav class="navbar navbar-expand-lg landing-navbar" id="mainNav">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-3" href="#">
                <img src="assets/favicon.ico" alt="Logo" width="45" height="45">
                <span class="outfit fs-3 fw-bold text-success">NextGen <span class="text-dark">Analytics</span></span>
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav mx-auto mb-3 mb-lg-0 text-center text-lg-start">
                    <li class="nav-item"><a class="nav-link fw-bold" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link fw-bold" href="#features">Services</a></li>
                    <li class="nav-item"><a class="nav-link fw-bold" href="#workflow">Workflow</a></li>
                    <li class="nav-item"><a class="nav-link fw-bold" href="#team">Team</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-3">
                    <a href="login" class="btn btn-outline-dark rounded-pill px-4 fw-bold">Staff Login</a>
                    <a href="student_login" class="btn btn-success rounded-pill px-5 fw-bold shadow-lg">Portal
                        Access</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Master Hero Section -->
    <section class="hero-section bg-dots">
        <div class="container-xxl hero-content px-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-6" data-aos="fade-right">
                    <span class="hero-badge"><i class="bi bi-stars me-2"></i> LEADING INNOVATION</span>
                    <h1 class="hero-title mb-4">
                        Intelligent <br>
                        <span class="text-swap-container">
                            <span class="swap-text active">AI Proctoring</span>
                            <span class="swap-text">Remote Security</span>
                            <span class="swap-text">Live Proctored Chat</span>
                            <span class="swap-text">Global Integrity</span>
                        </span>
                    </h1>
                    <p class="lead text-muted mb-5 pe-lg-5 fw-normal">The ultimate digital environment for institutions.
                        Seamlessly integrated monitoring and automated performance tracking with zero-compromise
                        security.</p>
                    <div class="d-grid d-sm-flex flex-wrap gap-3">
                        <a href="student_login"
                            class="btn btn-success btn-lg rounded-pill px-5 py-3 fw-bold shadow-lg w-100 w-sm-auto">STUDENT
                            ENTRANCE</a>
                        <a href="#workflow"
                            class="btn btn-outline-dark btn-lg rounded-pill px-5 py-3 fw-bold w-100 w-sm-auto">HOW IT
                            WORKS</a>
                    </div>
                </div>

                <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
                    <!-- Hero Image Interchange Slider -->
                    <div id="heroCarousel" class="carousel slide hero-carousel carousel-fade" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active" data-bs-interval="4000">
                                <img src="assets/img/hero.png" class="d-block w-100" alt="Proctoring Tech">
                            </div>
                            <div class="carousel-item" data-bs-interval="4000">
                                <img src="assets/img/proctoring.png" class="d-block w-100" alt="Advanced HUD">
                            </div>
                            <div class="carousel-item" data-bs-interval="4000">
                                <img src="assets/img/about.png" class="d-block w-100" alt="Collaboration">
                            </div>
                        </div>
                        <!-- Float Overlay Cards for Interactivity -->
                        <div class="floating-card shadow-lg" style="bottom: 40px; left: -20px; z-index: 10;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary-light p-2 rounded-circle"><i
                                        class="bi bi-shield-fill-check text-success fs-4"></i></div>
                                <div>
                                    <div class="fw-bold small">Identity Sync</div>
                                    <div class="text-muted smaller">99.9% Match Accuracy</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Stats -->
    <section class="py-5 border-bottom">
        <div class="container-xxl px-5">
            <div class="row text-center py-4 g-4 px-md-5">
                <div class="col-md-4 stat-item">
                    <div class="display-6 fw-bold outfit text-success"><?= $exam_count ?>+</div>
                    <div class="text-muted fw-bold small uppercase tracking-widest mt-2">ACTIVE ASSESSMENTS</div>
                </div>
                <div class="col-md-4 stat-item border-md-start">
                    <div class="display-6 fw-bold outfit text-dark"><?= $student_count ?>+</div>
                    <div class="text-muted fw-bold small uppercase tracking-widest mt-2">VERIFIED STUDENTS</div>
                </div>
                <div class="col-md-4 stat-item border-md-start">
                    <div class="display-6 fw-bold outfit text-primary">150ms</div>
                    <div class="text-muted fw-bold small uppercase tracking-widest mt-2">LIVE STREAM LATENCY</div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section-padding">
        <div class="container-xxl px-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 order-2 order-lg-1" data-aos="fade-right">
                    <img src="assets/img/about.png" class="img-fluid rounded-5 shadow-lg" alt="About NextGen">
                </div>
                <div class="col-lg-6 order-1 order-lg-2" data-aos="fade-left">
                    <h2 class="display-5 fw-bold mb-4">Our Mission: <br><span class="text-success">Integrity at
                            Scale</span></h2>
                    <p class="text-muted fs-5 mb-4">Founded on the principle that digital education requires
                        uncompromising security, NextGen Assessment Portal bridges the gap between remote testing and
                        physical invigilation.</p>
                    <p class="text-muted mb-5">We provide institutions with a robust framework to conduct high-stakes
                        examinations anywhere in the world, backed by real-time neural monitoring and automated
                        behavioral analysis.</p>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <h5 class="fw-bold"><i class="bi bi-patch-check-fill text-success me-2"></i> Security First
                            </h5>
                            <p class="small text-muted">End-to-end encrypted session stores.</p>
                        </div>
                        <div class="col-md-4">
                            <h5 class="fw-bold"><i class="bi bi-lightning-fill text-warning me-2"></i> Rapid Results
                            </h5>
                            <p class="small text-muted">Real-time grading and instant metrics.</p>
                        </div>
                        <div class="col-md-4">
                            <h5 class="fw-bold"><i class="bi bi-chat-left-dots-fill text-primary me-2"></i> Proctored
                                Chat</h5>
                            <p class="small text-muted">Direct intervention & student support.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="features" class="section-padding bg-light">
        <div class="container-xxl px-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-4 fw-bold outfit">Institutional <span class="text-success">Solutions</span></h2>
                <p class="text-muted fs-5">A comprehensive toolset engineered for digital transformation.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon-lg"><i class="bi bi-people-fill"></i></div>
                        <h4 class="fw-bold">Staff Control</h4>
                        <p class="text-muted">Distinct environments for Admin and Examiners with granular permissions.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon-lg"><i class="bi bi-database-fill-check"></i></div>
                        <h4 class="fw-bold">Bulk Intake</h4>
                        <p class="text-muted">High-speed CSV ingestion for onboarding thousands of candidates in
                            seconds.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon-lg"><i class="bi bi-question-diamond-fill"></i></div>
                        <h4 class="fw-bold">Dynamic Pools</h4>
                        <p class="text-muted">Curate and randomize question banks with custom scoring logic.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon-lg"><i class="bi bi-chat-left-text-fill"></i></div>
                        <h4 class="fw-bold">Proctored Chat</h4>
                        <p class="text-muted">Real-time encrypted messaging for student guidance and security warnings.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Workflow Section -->
    <section id="workflow" class="section-padding">
        <div class="container-xxl px-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-5" data-aos="fade-right">
                    <h2 class="display-4 fw-bold outfit mb-4">Operational <span class="text-success">Workflow</span>
                    </h2>
                    <p class="text-muted fs-5 mb-5">Seamless scaling for institutions. Our 4-step workflow ensures your
                        portal is running production-grade exams in minutes.</p>
                    <div class="d-flex flex-column gap-3">
                        <div class="spec-pill"><i class="bi bi-cpu text-primary"></i> PHP 8.1+ Core Foundation</div>
                        <div class="spec-pill"><i class="bi bi-database text-success"></i> Distributed SQL Logic</div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="vstack gap-4">
                        <div class="step-card" data-aos="fade-left" data-aos-delay="100">
                            <div class="step-num">1</div>
                            <h5 class="fw-bold mb-2">Institutional Setup</h5>
                            <p class="text-muted mb-0">Synchronize your database schema and configure institutional
                                branding.</p>
                        </div>
                        <div class="step-card" data-aos="fade-left" data-aos-delay="200">
                            <div class="step-num">2</div>
                            <h5 class="fw-bold mb-2">Resource Curation</h5>
                            <p class="text-muted mb-0">Populate rosters and question banks with precision tools.</p>
                        </div>
                        <div class="step-card" data-aos="fade-left" data-aos-delay="300">
                            <div class="step-num">3</div>
                            <h5 class="fw-bold mb-2">Live Session Access</h5>
                            <p class="text-muted mb-0">Activate exams and monitor real-time security alerts.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section id="team" class="section-padding bg-light">
        <div class="container-xxl px-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold outfit">Academic <span class="text-success">Visionaries</span></h2>
                <p class="text-muted fs-5">Pioneering secure assessment technology at Veritas University.</p>
            </div>
            <div class="row g-5 justify-content-center">
                <div class="col-md-4 col-lg-3" data-aos="fade-up">
                    <div class="team-item">
                        <div class="team-img-wrapper"><img src="assets/img/secure.png" alt="Team"></div>
                        <h4 class="fw-bold mb-1">BMX Initiator</h4>
                        <p class="text-success fw-bold small mb-3">SYSTEM ARCHITECT</p>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="team-item">
                        <div class="team-img-wrapper"><img src="assets/img/icon.png" alt="Team"></div>
                        <h4 class="fw-bold mb-1">Ariel Vance</h4>
                        <p class="text-success fw-bold small mb-3">SECURITY LEAD</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-5 bg-dark text-white border-top border-secondary border-opacity-25">
        <div class="container-xxl px-5">
            <div class="row g-5">
                <div class="col-lg-4 text-center text-lg-start">
                    <h3 class="fw-bold text-success mb-4 outfit">NextGen Portal</h3>
                    <p class="opacity-50">Empowering education through secure technological landscapes.</p>
                </div>
                <div class="col-lg-8">
                    <div class="row g-4 text-center text-lg-start">
                        <div class="col-md-4">
                            <h6 class="fw-bold text-uppercase small opacity-25 mb-4">Navigation</h6>
                            <ul class="list-unstyled opacity-75 small text-white">
                                <li class="mb-2"><a href="#about" class="link-light text-decoration-none">About</a></li>
                                <li class="mb-2"><a href="#features"
                                        class="link-light text-decoration-none">Services</a></li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="fw-bold text-uppercase small opacity-25 mb-4">Official Contact</h6>
                            <p class="opacity-75 small">support@veritas.edu.ng</p>
                            <p class="opacity-75 small">Innovation Lab &bull; Abuja</p>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="my-5 opacity-10">
            <div class="text-center opacity-50 small uppercase tracking-widest">
                &copy; 2026 BMXcode &bull; UNCOMPROMISED EXCELLENCE
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
        setInterval(() => {
            texts[currentText].classList.remove('active');
            currentText = (currentText + 1) % texts.length;
            texts[currentText].classList.add('active');
        }, 3000);

        // Responsive Scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({ behavior: 'smooth' });
            });
        });
    </script>
    <!-- sweetalert_custom_injected_v1 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Override global alert
            window.alert = function (msg) {
                Swal.fire({
                    title: 'Message',
                    text: msg,
                    icon: 'info',
                    confirmButtonColor: '#092c19'
                });
            };

            // Intercept inline onsubmit confirms
            document.querySelectorAll('form[onsubmit*="return confirm"]').forEach(el => {
                let match = el.getAttribute('onsubmit').match(/return confirm\(['"`](.*?)['"`]\)/);
                if (match) {
                    let msg = match[1];
                    el.removeAttribute('onsubmit');
                    el.addEventListener('submit', function (e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Are you sure?',
                            text: msg,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#092c19',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, proceed'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                let btn = el.querySelector('button[type="submit"]:hover, input[type="submit"]:hover')
                                    || el.querySelector('button[type="submit"], input[type="submit"]');
                                if (btn && btn.name) {
                                    let hidden = document.createElement('input');
                                    hidden.type = 'hidden';
                                    hidden.name = btn.name;
                                    hidden.value = btn.value || '1';
                                    el.appendChild(hidden);
                                }
                                HTMLFormElement.prototype.submit.call(el);
                            }
                        });
                    });
                }
            });

            // Intercept inline onclick confirms for anchors
            document.querySelectorAll('a[onclick*="return confirm"], button[onclick*="return confirm"]').forEach(el => {
                let match = el.getAttribute('onclick').match(/return confirm\(['"`](.*?)['"`]\)/);
                if (match) {
                    let msg = match[1];
                    el.removeAttribute('onclick');
                    el.addEventListener('click', function (e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Are you sure?',
                            text: msg,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#092c19',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, proceed'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                if (el.tagName === 'A' && el.href) {
                                    window.location.href = el.href;
                                } else if (el.tagName === 'BUTTON') {
                                    // if it's a submit button inside a form
                                    if (el.type === 'submit' && el.form) {
                                        if (el.name) {
                                            let hidden = document.createElement('input');
                                            hidden.type = 'hidden';
                                            hidden.name = el.name;
                                            hidden.value = el.value || '1';
                                            el.form.appendChild(hidden);
                                        }
                                        HTMLFormElement.prototype.submit.call(el.form);
                                    }
                                }
                            }
                        });
                    });
                }
            });
        });
    </script>
</body>


</html>