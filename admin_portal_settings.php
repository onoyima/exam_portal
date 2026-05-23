<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';
$success = '';
$error = '';
$back_url = 'admin_dashboard';

// Default values for seeding
$default_cms_settings = [
    'portal_title' => 'Veritas Mock Nursing Council Exam',
    'contact_email' => 'support@veritas.edu.ng',
    'contact_address' => 'Innovation Lab, Abuja',
    'footer_text' => '&copy; 2026 BMXcode &bull; UNCOMPROMISED EXCELLENCE',
    'student_login_content' => "Checklist for Nursing Candidates:\n- Ensure webcam is enabled and centered\n- Keep government ID ready for verification\n- Avoid closing or swapping browser tabs\n- Ensure a stable internet connection",
    'staff_login_content' => "Welcome to the Assessment Control Room.\n- Verify live proctoring stream feeds\n- Ensure grading rubrics are unlocked\n- Log out when session is completed",
    'hero_badge' => 'LEADING INNOVATION',
    'hero_title_swaps' => 'AI Proctoring|Remote Security|Live Proctored Chat|Global Integrity',
    'hero_lead' => 'The ultimate digital environment for institutions. Seamlessly integrated monitoring and automated performance tracking with zero-compromise security.',
    'hero_btn1_text' => 'STUDENT ENTRANCE',
    'hero_btn1_url' => 'student_login.php',
    'hero_btn2_text' => 'HOW IT WORKS',
    'hero_btn2_url' => '#workflow',
    'hero_floating_card_title' => 'Identity Sync',
    'hero_floating_card_desc' => '99.9% Match Accuracy',
    'hero_img1' => 'assets/img/hero.png',
    'hero_img2' => 'assets/img/proctoring.png',
    'stat1_label' => 'ACTIVE ASSESSMENTS',
    'stat2_label' => 'VERIFIED STUDENTS',
    'stat3_val' => '150ms',
    'stat3_label' => 'LIVE STREAM LATENCY',
    'about_img' => 'assets/img/about.png',
    'about_title' => 'Our Mission: <br><span class="text-success">Integrity at Scale</span>',
    'about_lead' => 'Founded on the principle that digital education requires uncompromising security, NextGen Assessment Portal bridges the gap between remote testing and physical invigilation.',
    'about_desc' => 'We provide institutions with a robust framework to conduct high-stakes examinations anywhere in the world, backed by real-time neural monitoring and automated behavioral analysis.',
    'about_bullet1_title' => 'Security First',
    'about_bullet1_desc' => 'End-to-end encrypted session stores.',
    'about_bullet2_title' => 'Rapid Results',
    'about_bullet2_desc' => 'Real-time grading and instant metrics.',
    'about_bullet3_title' => 'Proctored Chat',
    'about_bullet3_desc' => 'Direct intervention & student support.',
    'sec_badge' => 'Military-Grade Defense',
    'sec_title' => 'The <span class="text-success">7-Layer Security</span> Stack',
    'sec_lead' => 'Uncompromised academic integrity backed by active hardware-to-cloud security measures.',
    'sec_l7_title' => 'OS Kiosk (SEB)',
    'sec_l7_desc' => 'Locks the entire system at the operating system level via native Safe Exam Browser integration, completely disabling desktop swaps, background communication, or external displays.',
    'sec_l6_title' => 'Browser Lockdown',
    'sec_l6_desc' => 'Aggressive JavaScript restrictions. Intercepts and blocks F12 inspection tools, right-click context menus, Ctrl+U source printing, Alt+F4 closures, and cuts off clipboard paste capabilities.',
    'sec_l5_title' => 'Tab Blur Counters',
    'sec_l5_desc' => 'Active viewport focus monitoring. Triggers immediate audible warning sound alarms when browser focus is lost. Dynamically auto-submits assessments upon reaching the 5-strike threshold.',
    'sec_l4_title' => 'Webcam Proctoring',
    'sec_l4_desc' => 'Periodic frame-by-frame snapshot capture saved securely to archives. Provides live-feed synchronization using WebRTC so proctors can invigilate multiple rooms concurrently.',
    'sec_l3_title' => 'Device Whitelisting',
    'sec_l3_desc' => 'Centralized IP and device authorization gates. Intercepts initial handshakes, blocks unrecognized remote endpoints, and places new connections into a dynamic review queue.',
    'sec_l2_title' => 'Session Hardening',
    'sec_l2_desc' => 'Rigid session control. Generates and swaps session tokens at login to neutralize hijack attacks. Multi-device tracking prevents dual-login instances, securing individual credentials.',
    'sol_title' => 'Institutional <span class="text-success">Solutions</span>',
    'sol_lead' => 'A comprehensive toolset engineered for digital transformation.',
    'sol1_title' => 'Staff Control',
    'sol1_desc' => 'Distinct environments for Admin and Examiners with granular permissions.',
    'sol2_title' => 'Bulk Intake',
    'sol2_desc' => 'High-speed CSV ingestion for onboarding thousands of candidates in seconds.',
    'sol3_title' => 'Dynamic Pools',
    'sol3_desc' => 'Curate and randomize question banks with custom scoring logic.',
    'sol4_title' => 'Proctored Chat',
    'sol4_desc' => 'Real-time encrypted messaging for student guidance and security warnings.',
    'ai_badge' => 'Automated Invigilation',
    'ai_title' => 'Client-Side <span class="text-success">AI Proctoring</span> Packages',
    'ai_lead' => 'Scale remote surveillance effortlessly. Choose a surveillance tier matching your institution\'s integrity and computing constraints.',
    'ai_pkgA_title' => 'Package A: Basic AI',
    'ai_pkgA_desc' => 'Runs client-side in the student\'s browser. Extremely fast, requires **zero server GPU overhead**.',
    'ai_pkgA_bullets' => 'Face Presence Detection: Ensures candidate remains at screen.;;Multiple Face Alerts: Detects when third parties enter frame.;;Object Detection: Identifies phones, books, and devices.',
    'ai_pkgB_title' => 'Package B: Advanced AI',
    'ai_pkgB_desc' => 'Biometric profiling and gaze alignment. Integrates client face description mapping models.',
    'ai_pkgB_bullets' => 'Gaze & Attention Tracking: Flags when eyes wander away.;;Facial Verification: Confirms test-taker identity.;;Liveness Detection: Blink challenge to counter spoofing.;;Statistical Pattern Match: Outlier checking across portals.',
    'ai_pkgC_title' => 'Package C: Full AI Suite',
    'ai_pkgC_desc' => 'Max security integration. Complete cognitive and behavioral modeling dashboard.',
    'ai_pkgC_bullets' => 'LLM-Powered Chatbot: Seamless voice guidelines assistant.;;Behavioral Biometrics: Keyboard cadence & mouse patterns.;;Voice Detection: Acoustic analyzer flags whispers.;;Forensic Reports: Suspicion heatmaps and graphs.',
    'flow_title' => 'Operational <span class="text-success">Workflow</span>',
    'flow_lead' => 'Seamless scaling for institutions. Our 4-step workflow ensures your portal is running production-grade exams in minutes.',
    'flow_spec1' => 'PHP 8.1+ Core Foundation',
    'flow_spec2' => 'Distributed SQL Logic',
    'flow_step1_title' => 'Institutional Setup',
    'flow_step1_desc' => 'Synchronize your database schema and configure institutional branding.',
    'flow_step2_title' => 'Resource Curation',
    'flow_step2_desc' => 'Populate rosters and question banks with precision tools.',
    'flow_step3_title' => 'Live Session Access',
    'flow_step3_desc' => 'Activate exams and monitor real-time security alerts.',
    'fmt_badge' => 'Unparalleled Versatility',
    'fmt_title' => 'Versatile <span class="text-success">Examination Formats</span>',
    'fmt_lead' => 'A unified portal supporting traditional cognitive tests, structured clinical assessments, and academic defensibility.',
    'fmt1_title' => 'Objective MCQ Exams',
    'fmt1_desc' => 'Accelerate diagnostic feedback. Securely shuffle candidate pools, randomized lists, and answer sequence variables.',
    'fmt1_bullets' => 'Fully Automatic Grading;;Shuffled Pools & Options;;Time Limit & Auto-Submit',
    'fmt2_title' => 'OSCE Practical Nursing',
    'fmt2_desc' => 'Bespoke clinical rubrics designed for professional medical, engineering, and nursing certifications.',
    'fmt2_bullets' => 'Structured Examiner Rubrics;;1/2, 1/4, 1/8 Fractional Points;;Candidate Attempt Revision Logging',
    'fmt3_title' => 'VIVA / Oral Examinations',
    'fmt3_desc' => 'Maximize evaluation throughput. Interactive grids streamline multi-evaluator oral defences and feedback loops.',
    'fmt3_bullets' => '1-to-N Evaluation Matrix;;Quick-Select Interactive Roster;;Auto-Generated Scoring Sheets',
    'team_title' => 'Academic <span class="text-success">Visionaries</span>',
    'team_lead' => 'Pioneering secure assessment technology at Veritas University.',
    'team_m1_name' => 'BMX Initiator',
    'team_m1_role' => 'SYSTEM ARCHITECT',
    'team_m2_name' => 'Ariel Vance',
    'team_m2_role' => 'SECURITY LEAD',
    'roi_badge' => 'Measurable Institutional Impact',
    'roi_title' => 'The Veritas Advantage & <span class="text-success">ROI Summary</span>',
    'roi_lead' => 'Replacing paper-based logistics with automated, high-fidelity digital architectures yields direct financial and operational dividends.',
    'roi_savings_rows' => "Printing Logistics (Papers, booklets);;₦1.5 Million;;₦0;;₦1,500,000\nSecure Transit & Vaults;;₦800,000;;₦0;;₦800,000\nScript Storage & Archival;;₦500,000;;₦0;;₦500,000\nExaminer Labor Overtime;;₦3.0 Million;;₦100,000;;₦2,900,000\nTranscription & Data Entry;;₦700,000;;₦0;;₦700,000\nVerification & Audit;;₦500,000;;₦50,000;;₦450,000",
    'roi_total_label' => 'TOTAL DIRECT SAVINGS',
    'roi_total_paper' => '₦7.0 Million',
    'roi_total_portal' => '₦150,000',
    'roi_total_savings' => '₦6,850,000+',
    'roi_lms_title' => 'Why NextGen Trumps Standard LMS',
    'roi_lms1_title' => 'Fast Result Processing',
    'roi_lms1_desc' => 'Cuts downstream compilation pipelines from standard **4-to-8 weeks** down to **1-to-7 days** total with instantaneous auto-grading queues.',
    'roi_lms2_title' => 'Unlimited Concurrency',
    'roi_lms2_desc' => 'Run unlimited concurrent examinations without physical desk constraints or heavy invigilation rosters.',
    'roi_lms3_title' => 'NUC Accreditation Compliance',
    'roi_lms3_desc' => 'Maintains robust, immutable log tables of student, examiner, and administrator activities for seamless auditing.',
    'section_hero_active' => '1',
    'section_stats_active' => '1',
    'section_about_active' => '1',
    'section_security_active' => '1',
    'section_solutions_active' => '1',
    'section_ai_active' => '1',
    'section_workflow_active' => '1',
    'section_formats_active' => '1',
    'section_team_active' => '1',
    'section_roi_active' => '1'
];

$settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$seeded = false;
foreach ($default_cms_settings as $k => $v) {
    if (!isset($settings[$k])) {
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$k, $v]);
        $settings[$k] = $v;
        $seeded = true;
    }
}
if ($seeded) {
    $settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    if (isset($_POST['settings']) && is_array($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
    }
    $target_dir = "assets/img/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $images_to_upload = [
        'portal_image' => 'login_bg_',
        'portal_logo' => 'portal_logo_',
        'portal_favicon' => 'portal_favicon_',
        'hero_img1' => 'hero_img1_',
        'hero_img2' => 'hero_img2_',
        'about_img' => 'about_img_'
    ];
    foreach ($images_to_upload as $file_key => $prefix) {
        if (!empty($_FILES[$file_key]['name'])) {
            $file_ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
            $allowed_exts = ($file_key === 'portal_favicon') ? ['ico', 'png', 'jpg', 'jpeg'] : ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            if (in_array($file_ext, $allowed_exts)) {
                $new_name = $prefix . time() . "." . $file_ext;
                $target_file = $target_dir . $new_name;
                if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) {
                    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$file_key, $target_file, $target_file]);
                }
            } else {
                $error = 'Invalid file type for ' . str_replace('_', ' ', $file_key) . '. Allowed: ' . implode(', ', $allowed_exts);
            }
        }
    }
    if (!$error) {
        $success = 'CMS Content and Illustrations updated successfully!';
        $settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Landing Page CMS - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= htmlspecialchars($settings['portal_favicon'] ?? 'assets/favicon.ico') ?>" type="image/x-icon">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <style>
        .settings-card {
            border-radius: 20px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.02);
            background: #ffffff;
            margin-bottom: 1.5rem;
        }
        .preview-box {
            position: relative;
            border-radius: 16px;
            border: 2px dashed #cbd5e1;
            padding: 12px;
            text-align: center;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 140px;
        }
        .preview-box img {
            max-height: 100px;
            max-width: 100%;
            object-fit: contain;
            border-radius: 8px;
        }
        .preview-label {
            font-size: 0.68rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }
        .custom-upload-btn {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            font-weight: 600;
        }
        .custom-upload-btn input[type="file"] {
            position: absolute;
            font-size: 100px;
            opacity: 0;
            right: 0;
            top: 0;
            cursor: pointer;
        }
        .field-desc {
            font-size: 0.82rem;
            color: #64748b;
            margin-top: 4px;
        }
        .visibility-card {
            background: rgba(34, 197, 94, 0.03);
        }
        .favicon-preview-img {
            max-height: 50px; width: 50px;
        }
        .admin-tab-nav {
            background: #fff;
            border-radius: 16px;
            padding: 8px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 12px rgba(0,0,0,0.01);
            overflow-x: auto;
            white-space: nowrap;
            display: flex;
            gap: 6px;
        }
        .admin-tab-nav .nav-link {
            border-radius: 12px;
            color: #64748b;
            font-weight: 600;
            padding: 10px 18px;
            transition: all 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .admin-tab-nav .nav-link.active {
            background: #22c55e !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.25);
        }
        .admin-tab-nav .nav-link:hover:not(.active) {
            background: #f1f5f9;
            color: #0f172a;
        }
        .admin-section-chevron {
            transition: transform 0.3s ease;
        }
        .admin-section-toggle[aria-expanded="false"] .admin-section-chevron {
            transform: rotate(-90deg);
        }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <nav class="admin-navbar">
            <div class="admin-navbar-inner">
                <div class="admin-navbar-start">
                    <button class="admin-navbar-toggle d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
                    <?php if (isset($back_url)): ?><a href="<?= $back_url ?>" class="admin-back-btn"><i class="bi bi-arrow-left"></i></a><?php endif; ?>
                    <h1 class="admin-navbar-title">Portal CMS Settings</h1>
                </div>
                <div class="admin-navbar-end">
                    <div class="admin-user-badge">
                        <span class="admin-user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></span>
                        <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
                    </div>
                    <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                </div>
            </div>
        </nav>
        <div class="admin-content">
            <nav class="admin-breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
                    <li class="sep">/</li>
                    <li class="current" aria-current="page">Portal Settings</li>
                </ol>
            </nav>
            <div class="admin-page-header">
                <div>
                    <h2 class="admin-page-title">Portal CMS Settings</h2>
                    <p class="text-muted mb-0 small">Customize every heading, paragraph, card list, and illustration image of your landing page dynamically.</p>
                </div>
            </div>
<?php if (isset($success) && $success): ?>
            <div class="admin-alert admin-alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= htmlspecialchars($success) ?></span>
                <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
<?php endif; ?>
<?php if (isset($error) && $error): ?>
            <div class="admin-alert admin-alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
                <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
<?php endif; ?>
            <form method="post" enctype="multipart/form-data" id="cmsForm">
                <?php csrf_field(); ?>
                <div class="mb-4">
                    <nav class="nav nav-tabs admin-tab-nav border-0" id="cmsTab" role="tablist">
                        <button class="nav-link active" id="tab-brand" data-bs-toggle="tab" data-bs-target="#brand" type="button" role="tab"><i class="bi bi-palette"></i> Branding &amp; Login</button>
                        <button class="nav-link" id="tab-hero" data-bs-toggle="tab" data-bs-target="#hero" type="button" role="tab"><i class="bi bi-star"></i> Hero Banner</button>
                        <button class="nav-link" id="tab-stats" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab"><i class="bi bi-bar-chart"></i> Stats</button>
                        <button class="nav-link" id="tab-about" data-bs-toggle="tab" data-bs-target="#about" type="button" role="tab"><i class="bi bi-info-circle"></i> About Mission</button>
                        <button class="nav-link" id="tab-security" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab"><i class="bi bi-shield"></i> 7-Layer Security</button>
                        <button class="nav-link" id="tab-sol" data-bs-toggle="tab" data-bs-target="#solutions" type="button" role="tab"><i class="bi bi-grid"></i> Solutions</button>
                        <button class="nav-link" id="tab-ai" data-bs-toggle="tab" data-bs-target="#ai" type="button" role="tab"><i class="bi bi-robot"></i> AI Tiers</button>
                        <button class="nav-link" id="tab-flow" data-bs-toggle="tab" data-bs-target="#flow" type="button" role="tab"><i class="bi bi-arrow-repeat"></i> Workflow</button>
                        <button class="nav-link" id="tab-fmt" data-bs-toggle="tab" data-bs-target="#formats" type="button" role="tab"><i class="bi bi-file-text"></i> Formats</button>
                        <button class="nav-link" id="tab-team" data-bs-toggle="tab" data-bs-target="#team" type="button" role="tab"><i class="bi bi-people"></i> Team</button>
                        <button class="nav-link" id="tab-roi" data-bs-toggle="tab" data-bs-target="#roi" type="button" role="tab"><i class="bi bi-wallet"></i> Advantage &amp; ROI</button>
                    </nav>
                </div>
                <div class="tab-content" id="cmsTabContent">
                    <!-- Tab 1: Branding & Login -->
                    <div class="tab-pane fade show active" id="brand" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-lg-7">
                                <div class="card settings-card">
                                    <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#brandIdentity" aria-expanded="true">
                                        <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-box fs-4"></i> Identity &amp; Metadata</h5>
                                        <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                                    </div>
                                    <div class="collapse show p-4 pt-3" id="brandIdentity">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Portal Global Title</label>
                                            <input type="text" name="settings[portal_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['portal_title']) ?>" required>
                                            <div class="field-desc">Shown in the header, navbar and browser tab.</div>
                                        </div>
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Support/Contact Email</label>
                                                <input type="email" name="settings[contact_email]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['contact_email']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Innovation Lab Location</label>
                                                <input type="text" name="settings[contact_address]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['contact_address']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Footer Copyright Notice</label>
                                            <input type="text" name="settings[footer_text]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['footer_text']) ?>" required>
                                            <div class="field-desc">Supports HTML character entities like &amp;copy; and &amp;bull;.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card settings-card">
                                    <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#brandLogin" aria-expanded="true">
                                        <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-box-arrow-in-right fs-4"></i> Login Guidelines</h5>
                                        <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                                    </div>
                                    <div class="collapse show p-4 pt-3" id="brandLogin">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Student Portal Entry Messages</label>
                                            <textarea name="settings[student_login_content]" class="form-control rounded-3" rows="3"><?= htmlspecialchars($settings['student_login_content']) ?></textarea>
                                        </div>
                                        <div>
                                            <label class="form-label fw-bold small text-muted text-uppercase">Staff &amp; Examiner Login Messages</label>
                                            <textarea name="settings[staff_login_content]" class="form-control rounded-3" rows="3"><?= htmlspecialchars($settings['staff_login_content']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="card settings-card p-4">
                                    <h5 class="fw-bold mb-4 outfit text-success"><i class="bi bi-image fs-4"></i> Branding Assets</h5>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Institutional Logo</label>
                                        <div class="preview-box mb-2">
                                            <span class="preview-label">Current Logo</span>
                                            <img id="logo-preview" src="<?= htmlspecialchars($settings['portal_logo'] ?? 'assets/favicon.ico') ?>" alt="Logo">
                                        </div>
                                        <div class="btn btn-outline-success custom-upload-btn btn-sm w-100 text-center py-2">
                                            <i class="bi bi-upload me-1"></i> Choose New Logo Image
                                            <input type="file" name="portal_logo" onchange="previewImage(this, 'logo-preview')" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Portal Favicon (.ico / .png)</label>
                                        <div class="preview-box mb-2">
                                            <span class="preview-label">Current Favicon</span>
                                            <img id="favicon-preview" src="<?= htmlspecialchars($settings['portal_favicon']) ?>" alt="Favicon" class="favicon-preview-img">
                                        </div>
                                        <div class="btn btn-outline-success custom-upload-btn btn-sm w-100 text-center py-2">
                                            <i class="bi bi-upload me-1"></i> Choose New Favicon
                                            <input type="file" name="portal_favicon" onchange="previewImage(this, 'favicon-preview')" accept="image/x-icon,image/png,image/jpeg">
                                        </div>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Student Login Background</label>
                                        <div class="preview-box mb-2">
                                            <span class="preview-label">Current Login Background</span>
                                            <img id="bg-preview" src="<?= htmlspecialchars($settings['portal_image'] ?? 'assets/img/login_bg_1776438145.png') ?>" alt="Background">
                                        </div>
                                        <div class="btn btn-outline-success custom-upload-btn btn-sm w-100 text-center py-2">
                                            <i class="bi bi-upload me-1"></i> Choose New Background Image
                                            <input type="file" name="portal_image" onchange="previewImage(this, 'bg-preview')" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Hero Banner -->
                    <div class="tab-pane fade" id="hero" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-lg-7">
                                <div class="card settings-card p-3 border-success border-opacity-25 mb-4 visibility-card">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-eye text-success fs-4"></i>
                                            <div>
                                                <h6 class="fw-bold mb-0 outfit text-success">Section Visibility</h6>
                                                <p class="text-muted mb-0 small">Publish/unpublish the Hero Banner section.</p>
                                            </div>
                                        </div>
                                        <div>
                                            <select name="settings[section_hero_active]" class="form-select rounded-pill px-3 fw-bold bg-white text-success border-success">
                                                <option value="1" <?= ($settings['section_hero_active'] ?? '1') === '1' ? 'selected' : '' ?>>Published</option>
                                                <option value="0" <?= ($settings['section_hero_active'] ?? '1') === '0' ? 'selected' : '' ?>>Unpublished</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="card settings-card">
                                    <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#heroCopy" aria-expanded="true">
                                        <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-star fs-4"></i> Hero Copy Content</h5>
                                        <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                                    </div>
                                    <div class="collapse show p-4 pt-3" id="heroCopy">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Top Badge Label</label>
                                            <input type="text" name="settings[hero_badge]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['hero_badge']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Dynamic Word Swappers (Pipe '|' Separated)</label>
                                            <input type="text" name="settings[hero_title_swaps]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['hero_title_swaps']) ?>" required>
                                            <div class="field-desc">These rotate dynamically with fade-in-up animations. Example: AI Proctoring|Remote Security|Global Integrity</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Lead Paragraph Text</label>
                                            <textarea name="settings[hero_lead]" class="form-control rounded-3" rows="3" required><?= htmlspecialchars($settings['hero_lead']) ?></textarea>
                                        </div>
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Button 1 (Primary) Label</label>
                                                <input type="text" name="settings[hero_btn1_text]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['hero_btn1_text']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Button 1 URL Redirect</label>
                                                <input type="text" name="settings[hero_btn1_url]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['hero_btn1_url']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Button 2 (Secondary) Label</label>
                                                <input type="text" name="settings[hero_btn2_text]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['hero_btn2_text']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Button 2 URL Redirect</label>
                                                <input type="text" name="settings[hero_btn2_url]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['hero_btn2_url']) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card settings-card">
                                    <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#heroHud" aria-expanded="true">
                                        <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-card-text fs-4"></i> Floating HUD Card</h5>
                                        <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                                    </div>
                                    <div class="collapse show p-4 pt-3" id="heroHud">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">HUD Title</label>
                                                <input type="text" name="settings[hero_floating_card_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['hero_floating_card_title']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">HUD Description</label>
                                                <input type="text" name="settings[hero_floating_card_desc]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['hero_floating_card_desc']) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="card settings-card p-4">
                                    <h5 class="fw-bold mb-4 outfit text-success"><i class="bi bi-images fs-4"></i> Hero Carousel Slides</h5>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Carousel Illustration 1</label>
                                        <div class="preview-box mb-2">
                                            <span class="preview-label">Slide 1 Image</span>
                                            <img id="hero1-preview" src="<?= htmlspecialchars($settings['hero_img1']) ?>" alt="Slide 1">
                                        </div>
                                        <div class="btn btn-outline-success custom-upload-btn btn-sm w-100 text-center py-2">
                                            <i class="bi bi-upload me-1"></i> Upload Image 1
                                            <input type="file" name="hero_img1" onchange="previewImage(this, 'hero1-preview')" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Carousel Illustration 2</label>
                                        <div class="preview-box mb-2">
                                            <span class="preview-label">Slide 2 Image</span>
                                            <img id="hero2-preview" src="<?= htmlspecialchars($settings['hero_img2']) ?>" alt="Slide 2">
                                        </div>
                                        <div class="btn btn-outline-success custom-upload-btn btn-sm w-100 text-center py-2">
                                            <i class="bi bi-upload me-1"></i> Upload Image 2
                                            <input type="file" name="hero_img2" onchange="previewImage(this, 'hero2-preview')" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 3: Quick Stats -->
                    <div class="tab-pane fade" id="stats" role="tabpanel">
                        <div class="card settings-card p-3 border-success border-opacity-25 mb-4 visibility-card">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-eye text-success fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 outfit text-success">Section Visibility</h6>
                                        <p class="text-muted mb-0 small">Publish/unpublish the Quick Stats section.</p>
                                    </div>
                                </div>
                                <div>
                                    <select name="settings[section_stats_active]" class="form-select rounded-pill px-3 fw-bold bg-white text-success border-success">
                                        <option value="1" <?= ($settings['section_stats_active'] ?? '1') === '1' ? 'selected' : '' ?>>Published</option>
                                        <option value="0" <?= ($settings['section_stats_active'] ?? '1') === '0' ? 'selected' : '' ?>>Unpublished</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#statsContent" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-bar-chart fs-4"></i> Public Stats Layout</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="statsContent">
                                <div class="alert alert-info border-0 rounded-4 p-3 small mb-4">
                                    <i class="bi bi-info-circle-fill me-2 text-info"></i> Statistics for <strong>Active Assessments</strong> and <strong>Verified Students</strong> are loaded automatically from the active exam lists and candidate databases in real-time. Only the labels and 3rd static box statistics are customizable.
                                </div>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Stat Box 1 label (Active Assessments)</label>
                                        <input type="text" name="settings[stat1_label]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['stat1_label']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Stat Box 2 label (Verified Students)</label>
                                        <input type="text" name="settings[stat2_label]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['stat2_label']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Stat Box 3 Value (e.g. 150ms)</label>
                                        <input type="text" name="settings[stat3_val]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['stat3_val']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Stat Box 3 label (e.g. Latency)</label>
                                        <input type="text" name="settings[stat3_label]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['stat3_label']) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 4: About Mission -->
                    <div class="tab-pane fade" id="about" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-lg-7">
                                <div class="card settings-card p-3 border-success border-opacity-25 mb-4 visibility-card">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-eye text-success fs-4"></i>
                                            <div>
                                                <h6 class="fw-bold mb-0 outfit text-success">Section Visibility</h6>
                                                <p class="text-muted mb-0 small">Publish/unpublish the About Mission section.</p>
                                            </div>
                                        </div>
                                        <div>
                                            <select name="settings[section_about_active]" class="form-select rounded-pill px-3 fw-bold bg-white text-success border-success">
                                                <option value="1" <?= ($settings['section_about_active'] ?? '1') === '1' ? 'selected' : '' ?>>Published</option>
                                                <option value="0" <?= ($settings['section_about_active'] ?? '1') === '0' ? 'selected' : '' ?>>Unpublished</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="card settings-card">
                                    <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#aboutContent" aria-expanded="true">
                                        <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-info-circle fs-4"></i> About Content & Mission</h5>
                                        <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                                    </div>
                                    <div class="collapse show p-4 pt-3" id="aboutContent">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Mission Title (HTML Allowed)</label>
                                            <input type="text" name="settings[about_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['about_title']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Lead Mission Statement</label>
                                            <textarea name="settings[about_lead]" class="form-control rounded-3" rows="2" required><?= htmlspecialchars($settings['about_lead']) ?></textarea>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Main Descriptive Paragraph</label>
                                            <textarea name="settings[about_desc]" class="form-control rounded-3" rows="3" required><?= htmlspecialchars($settings['about_desc']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="card settings-card">
                                    <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#aboutValues" aria-expanded="true">
                                        <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-list-check fs-4"></i> Value Highlights</h5>
                                        <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                                    </div>
                                    <div class="collapse show p-4 pt-3" id="aboutValues">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Highlight 1 Title</label>
                                                <input type="text" name="settings[about_bullet1_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['about_bullet1_title']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Highlight 1 Desc</label>
                                                <input type="text" name="settings[about_bullet1_desc]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['about_bullet1_desc']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Highlight 2 Title</label>
                                                <input type="text" name="settings[about_bullet2_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['about_bullet2_title']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Highlight 2 Desc</label>
                                                <input type="text" name="settings[about_bullet2_desc]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['about_bullet2_desc']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Highlight 3 Title</label>
                                                <input type="text" name="settings[about_bullet3_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['about_bullet3_title']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted text-uppercase">Highlight 3 Desc</label>
                                                <input type="text" name="settings[about_bullet3_desc]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['about_bullet3_desc']) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="card settings-card p-4">
                                    <h5 class="fw-bold mb-4 outfit text-success"><i class="bi bi-image fs-4"></i> About Illustration</h5>
                                    <div class="mb-0">
                                        <label class="form-label fw-bold small text-muted text-uppercase">About Page Image</label>
                                        <div class="preview-box mb-2">
                                            <span class="preview-label">Illustration preview</span>
                                            <img id="about-preview" src="<?= htmlspecialchars($settings['about_img']) ?>" alt="About">
                                        </div>
                                        <div class="btn btn-outline-success custom-upload-btn btn-sm w-100 text-center py-2">
                                            <i class="bi bi-upload me-1"></i> Upload Illustration Image
                                            <input type="file" name="about_img" onchange="previewImage(this, 'about-preview')" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 5: 7-Layer Security -->
                    <div class="tab-pane fade" id="security" role="tabpanel">
                        <div class="card settings-card p-3 border-success border-opacity-25 mb-4 visibility-card">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-eye text-success fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 outfit text-success">Section Visibility</h6>
                                        <p class="text-muted mb-0 small">Publish/unpublish the 7-Layer Security section.</p>
                                    </div>
                                </div>
                                <div>
                                    <select name="settings[section_security_active]" class="form-select rounded-pill px-3 fw-bold bg-white text-success border-success">
                                        <option value="1" <?= ($settings['section_security_active'] ?? '1') === '1' ? 'selected' : '' ?>>Published</option>
                                        <option value="0" <?= ($settings['section_security_active'] ?? '1') === '0' ? 'selected' : '' ?>>Unpublished</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#securityCopy" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-shield fs-4"></i> 7-Layer Security Stack Copy</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="securityCopy">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Security Section Badge</label>
                                        <input type="text" name="settings[sec_badge]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['sec_badge']) ?>" required>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Title (HTML Allowed)</label>
                                        <input type="text" name="settings[sec_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['sec_title']) ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Lead Paragraph</label>
                                        <input type="text" name="settings[sec_lead]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['sec_lead']) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#securityLayers" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-layers fs-4"></i> Defense Layers Description</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="securityLayers">
                                <div class="row g-4">
                                    <div class="col-md-6 col-lg-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <span class="badge bg-success text-white fw-bold outfit mb-2">Layer 7</span>
                                            <input type="text" name="settings[sec_l7_title]" class="form-control rounded-3 fw-bold mb-2 small" value="<?= htmlspecialchars($settings['sec_l7_title']) ?>" required>
                                            <textarea name="settings[sec_l7_desc]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['sec_l7_desc']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <span class="badge bg-success text-white fw-bold outfit mb-2">Layer 6</span>
                                            <input type="text" name="settings[sec_l6_title]" class="form-control rounded-3 fw-bold mb-2 small" value="<?= htmlspecialchars($settings['sec_l6_title']) ?>" required>
                                            <textarea name="settings[sec_l6_desc]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['sec_l6_desc']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <span class="badge bg-success text-white fw-bold outfit mb-2">Layer 5</span>
                                            <input type="text" name="settings[sec_l5_title]" class="form-control rounded-3 fw-bold mb-2 small" value="<?= htmlspecialchars($settings['sec_l5_title']) ?>" required>
                                            <textarea name="settings[sec_l5_desc]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['sec_l5_desc']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <span class="badge bg-success text-white fw-bold outfit mb-2">Layer 4</span>
                                            <input type="text" name="settings[sec_l4_title]" class="form-control rounded-3 fw-bold mb-2 small" value="<?= htmlspecialchars($settings['sec_l4_title']) ?>" required>
                                            <textarea name="settings[sec_l4_desc]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['sec_l4_desc']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <span class="badge bg-success text-white fw-bold outfit mb-2">Layer 3</span>
                                            <input type="text" name="settings[sec_l3_title]" class="form-control rounded-3 fw-bold mb-2 small" value="<?= htmlspecialchars($settings['sec_l3_title']) ?>" required>
                                            <textarea name="settings[sec_l3_desc]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['sec_l3_desc']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <span class="badge bg-success text-white fw-bold outfit mb-2">Layer 2</span>
                                            <input type="text" name="settings[sec_l2_title]" class="form-control rounded-3 fw-bold mb-2 small" value="<?= htmlspecialchars($settings['sec_l2_title']) ?>" required>
                                            <textarea name="settings[sec_l2_desc]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['sec_l2_desc']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 6: Solutions -->
                    <div class="tab-pane fade" id="solutions" role="tabpanel">
                        <div class="card settings-card p-3 border-success border-opacity-25 mb-4 visibility-card">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-eye text-success fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 outfit text-success">Section Visibility</h6>
                                        <p class="text-muted mb-0 small">Publish/unpublish the Institutional Solutions section.</p>
                                    </div>
                                </div>
                                <div>
                                    <select name="settings[section_solutions_active]" class="form-select rounded-pill px-3 fw-bold bg-white text-success border-success">
                                        <option value="1" <?= ($settings['section_solutions_active'] ?? '1') === '1' ? 'selected' : '' ?>>Published</option>
                                        <option value="0" <?= ($settings['section_solutions_active'] ?? '1') === '0' ? 'selected' : '' ?>>Unpublished</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#solutionsContent" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-grid fs-4"></i> Institutional Solutions</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="solutionsContent">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-5">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Title (HTML Allowed)</label>
                                        <input type="text" name="settings[sol_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['sol_title']) ?>" required>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Lead Description</label>
                                        <input type="text" name="settings[sol_lead]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['sol_lead']) ?>" required>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 bg-light">
                                            <label class="form-label fw-bold text-success small">Solution Card 1</label>
                                            <input type="text" name="settings[sol1_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['sol1_title']) ?>" required>
                                            <textarea name="settings[sol1_desc]" class="form-control rounded-3 small" rows="2" required><?= htmlspecialchars($settings['sol1_desc']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 bg-light">
                                            <label class="form-label fw-bold text-success small">Solution Card 2</label>
                                            <input type="text" name="settings[sol2_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['sol2_title']) ?>" required>
                                            <textarea name="settings[sol2_desc]" class="form-control rounded-3 small" rows="2" required><?= htmlspecialchars($settings['sol2_desc']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 bg-light">
                                            <label class="form-label fw-bold text-success small">Solution Card 3</label>
                                            <input type="text" name="settings[sol3_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['sol3_title']) ?>" required>
                                            <textarea name="settings[sol3_desc]" class="form-control rounded-3 small" rows="2" required><?= htmlspecialchars($settings['sol3_desc']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 bg-light">
                                            <label class="form-label fw-bold text-success small">Solution Card 4</label>
                                            <input type="text" name="settings[sol4_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['sol4_title']) ?>" required>
                                            <textarea name="settings[sol4_desc]" class="form-control rounded-3 small" rows="2" required><?= htmlspecialchars($settings['sol4_desc']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 7: AI Tiers -->
                    <div class="tab-pane fade" id="ai" role="tabpanel">
                        <div class="card settings-card p-3 border-success border-opacity-25 mb-4 visibility-card">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-eye text-success fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 outfit text-success">Section Visibility</h6>
                                        <p class="text-muted mb-0 small">Publish/unpublish the AI Proctoring Packages section.</p>
                                    </div>
                                </div>
                                <div>
                                    <select name="settings[section_ai_active]" class="form-select rounded-pill px-3 fw-bold bg-white text-success border-success">
                                        <option value="1" <?= ($settings['section_ai_active'] ?? '1') === '1' ? 'selected' : '' ?>>Published</option>
                                        <option value="0" <?= ($settings['section_ai_active'] ?? '1') === '0' ? 'selected' : '' ?>>Unpublished</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#aiIntro" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-robot fs-4"></i> Client-Side AI Surveillance Packages</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="aiIntro">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Badge</label>
                                        <input type="text" name="settings[ai_badge]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['ai_badge']) ?>" required>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Title (HTML Allowed)</label>
                                        <input type="text" name="settings[ai_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['ai_title']) ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Lead Description</label>
                                        <input type="text" name="settings[ai_lead]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['ai_lead']) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#aiPackages" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-list-check fs-4"></i> Packages Specifications</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="aiPackages">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <label class="form-label fw-bold text-success small">Surveillance Tier A</label>
                                            <input type="text" name="settings[ai_pkgA_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['ai_pkgA_title']) ?>" required>
                                            <textarea name="settings[ai_pkgA_desc]" class="form-control rounded-3 small mb-2" rows="2" required><?= htmlspecialchars($settings['ai_pkgA_desc']) ?></textarea>
                                            <label class="form-label small text-muted text-uppercase mb-1">Checklist Bullets (Separated by ';;')</label>
                                            <textarea name="settings[ai_pkgA_bullets]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['ai_pkgA_bullets']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <label class="form-label fw-bold text-success small">Surveillance Tier B</label>
                                            <input type="text" name="settings[ai_pkgB_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['ai_pkgB_title']) ?>" required>
                                            <textarea name="settings[ai_pkgB_desc]" class="form-control rounded-3 small mb-2" rows="2" required><?= htmlspecialchars($settings['ai_pkgB_desc']) ?></textarea>
                                            <label class="form-label small text-muted text-uppercase mb-1">Checklist Bullets (Separated by ';;')</label>
                                            <textarea name="settings[ai_pkgB_bullets]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['ai_pkgB_bullets']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <label class="form-label fw-bold text-success small">Surveillance Tier C</label>
                                            <input type="text" name="settings[ai_pkgC_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['ai_pkgC_title']) ?>" required>
                                            <textarea name="settings[ai_pkgC_desc]" class="form-control rounded-3 small mb-2" rows="2" required><?= htmlspecialchars($settings['ai_pkgC_desc']) ?></textarea>
                                            <label class="form-label small text-muted text-uppercase mb-1">Checklist Bullets (Separated by ';;')</label>
                                            <textarea name="settings[ai_pkgC_bullets]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['ai_pkgC_bullets']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 8: Workflow -->
                    <div class="tab-pane fade" id="flow" role="tabpanel">
                        <div class="card settings-card p-3 border-success border-opacity-25 mb-4 visibility-card">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-eye text-success fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 outfit text-success">Section Visibility</h6>
                                        <p class="text-muted mb-0 small">Publish/unpublish the Operational Workflow section.</p>
                                    </div>
                                </div>
                                <div>
                                    <select name="settings[section_workflow_active]" class="form-select rounded-pill px-3 fw-bold bg-white text-success border-success">
                                        <option value="1" <?= ($settings['section_workflow_active'] ?? '1') === '1' ? 'selected' : '' ?>>Published</option>
                                        <option value="0" <?= ($settings['section_workflow_active'] ?? '1') === '0' ? 'selected' : '' ?>>Unpublished</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#flowIntro" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-arrow-repeat fs-4"></i> Operational Workflow Steps</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="flowIntro">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-5">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Title (HTML Allowed)</label>
                                        <input type="text" name="settings[flow_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['flow_title']) ?>" required>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Lead Paragraph</label>
                                        <input type="text" name="settings[flow_lead]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['flow_lead']) ?>" required>
                                    </div>
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Tech Spec Core 1</label>
                                        <input type="text" name="settings[flow_spec1]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['flow_spec1']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Tech Spec Core 2</label>
                                        <input type="text" name="settings[flow_spec2]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['flow_spec2']) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#flowSteps" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-list-ol fs-4"></i> Workflow Steps Details</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="flowSteps">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded-3 bg-light">
                                            <span class="badge bg-success text-white fw-bold outfit mb-2">Step 1</span>
                                            <input type="text" name="settings[flow_step1_title]" class="form-control rounded-3 fw-bold mb-2 small" value="<?= htmlspecialchars($settings['flow_step1_title']) ?>" required>
                                            <textarea name="settings[flow_step1_desc]" class="form-control rounded-3 small" rows="3" required><?= htmlspecialchars($settings['flow_step1_desc']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded-3 bg-light">
                                            <span class="badge bg-success text-white fw-bold outfit mb-2">Step 2</span>
                                            <input type="text" name="settings[flow_step2_title]" class="form-control rounded-3 fw-bold mb-2 small" value="<?= htmlspecialchars($settings['flow_step2_title']) ?>" required>
                                            <textarea name="settings[flow_step2_desc]" class="form-control rounded-3 small" rows="3" required><?= htmlspecialchars($settings['flow_step2_desc']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded-3 bg-light">
                                            <span class="badge bg-success text-white fw-bold outfit mb-2">Step 3</span>
                                            <input type="text" name="settings[flow_step3_title]" class="form-control rounded-3 fw-bold mb-2 small" value="<?= htmlspecialchars($settings['flow_step3_title']) ?>" required>
                                            <textarea name="settings[flow_step3_desc]" class="form-control rounded-3 small" rows="3" required><?= htmlspecialchars($settings['flow_step3_desc']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 9: Formats -->
                    <div class="tab-pane fade" id="formats" role="tabpanel">
                        <div class="card settings-card p-3 border-success border-opacity-25 mb-4 visibility-card">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-eye text-success fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 outfit text-success">Section Visibility</h6>
                                        <p class="text-muted mb-0 small">Publish/unpublish the Versatile Formats section.</p>
                                    </div>
                                </div>
                                <div>
                                    <select name="settings[section_formats_active]" class="form-select rounded-pill px-3 fw-bold bg-white text-success border-success">
                                        <option value="1" <?= ($settings['section_formats_active'] ?? '1') === '1' ? 'selected' : '' ?>>Published</option>
                                        <option value="0" <?= ($settings['section_formats_active'] ?? '1') === '0' ? 'selected' : '' ?>>Unpublished</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#fmtIntro" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-file-text fs-4"></i> Examination Formats Supported</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="fmtIntro">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Badge</label>
                                        <input type="text" name="settings[fmt_badge]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['fmt_badge']) ?>" required>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Title (HTML Allowed)</label>
                                        <input type="text" name="settings[fmt_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['fmt_title']) ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Lead Paragraph</label>
                                        <input type="text" name="settings[fmt_lead]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['fmt_lead']) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#fmtCategories" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-detail fs-4"></i> Format Categories</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="fmtCategories">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <label class="form-label fw-bold text-success small">Format Category 1</label>
                                            <input type="text" name="settings[fmt1_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['fmt1_title']) ?>" required>
                                            <textarea name="settings[fmt1_desc]" class="form-control rounded-3 small mb-2" rows="3" required><?= htmlspecialchars($settings['fmt1_desc']) ?></textarea>
                                            <label class="form-label small text-muted text-uppercase mb-1">Checklist Highlights (Separated by ';;')</label>
                                            <textarea name="settings[fmt1_bullets]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['fmt1_bullets']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <label class="form-label fw-bold text-success small">Format Category 2</label>
                                            <input type="text" name="settings[fmt2_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['fmt2_title']) ?>" required>
                                            <textarea name="settings[fmt2_desc]" class="form-control rounded-3 small mb-2" rows="3" required><?= htmlspecialchars($settings['fmt2_desc']) ?></textarea>
                                            <label class="form-label small text-muted text-uppercase mb-1">Checklist Highlights (Separated by ';;')</label>
                                            <textarea name="settings[fmt2_bullets]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['fmt2_bullets']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded-3 bg-light h-100">
                                            <label class="form-label fw-bold text-success small">Format Category 3</label>
                                            <input type="text" name="settings[fmt3_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['fmt3_title']) ?>" required>
                                            <textarea name="settings[fmt3_desc]" class="form-control rounded-3 small mb-2" rows="3" required><?= htmlspecialchars($settings['fmt3_desc']) ?></textarea>
                                            <label class="form-label small text-muted text-uppercase mb-1">Checklist Highlights (Separated by ';;')</label>
                                            <textarea name="settings[fmt3_bullets]" class="form-control rounded-3 small" rows="4" required><?= htmlspecialchars($settings['fmt3_bullets']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 10: Team -->
                    <div class="tab-pane fade" id="team" role="tabpanel">
                        <div class="card settings-card p-3 border-success border-opacity-25 mb-4 visibility-card">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-eye text-success fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 outfit text-success">Section Visibility</h6>
                                        <p class="text-muted mb-0 small">Publish/unpublish the Academic Visionaries (Team) section.</p>
                                    </div>
                                </div>
                                <div>
                                    <select name="settings[section_team_active]" class="form-select rounded-pill px-3 fw-bold bg-white text-success border-success">
                                        <option value="1" <?= ($settings['section_team_active'] ?? '1') === '1' ? 'selected' : '' ?>>Published</option>
                                        <option value="0" <?= ($settings['section_team_active'] ?? '1') === '0' ? 'selected' : '' ?>>Unpublished</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#teamIntro" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-people fs-4"></i> Academic Visionaries (Team)</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="teamIntro">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-5">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Title (HTML Allowed)</label>
                                        <input type="text" name="settings[team_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['team_title']) ?>" required>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Lead description</label>
                                        <input type="text" name="settings[team_lead]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['team_lead']) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#teamProfiles" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-person fs-4"></i> Team Member Profiles</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="teamProfiles">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 bg-light">
                                            <label class="form-label fw-bold text-success small">Team Member 1</label>
                                            <div class="mb-2">
                                                <label class="form-label small text-muted">Full Name</label>
                                                <input type="text" name="settings[team_m1_name]" class="form-control rounded-3 fw-bold small" value="<?= htmlspecialchars($settings['team_m1_name']) ?>" required>
                                            </div>
                                            <div>
                                                <label class="form-label small text-muted">Official Designation Role</label>
                                                <input type="text" name="settings[team_m1_role]" class="form-control rounded-3 small text-success fw-bold" value="<?= htmlspecialchars($settings['team_m1_role']) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 bg-light">
                                            <label class="form-label fw-bold text-success small">Team Member 2</label>
                                            <div class="mb-2">
                                                <label class="form-label small text-muted">Full Name</label>
                                                <input type="text" name="settings[team_m2_name]" class="form-control rounded-3 fw-bold small" value="<?= htmlspecialchars($settings['team_m2_name']) ?>" required>
                                            </div>
                                            <div>
                                                <label class="form-label small text-muted">Official Designation Role</label>
                                                <input type="text" name="settings[team_m2_role]" class="form-control rounded-3 small text-success fw-bold" value="<?= htmlspecialchars($settings['team_m2_role']) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 11: Advantage & ROI -->
                    <div class="tab-pane fade" id="roi" role="tabpanel">
                        <div class="card settings-card p-3 border-success border-opacity-25 mb-4 visibility-card">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-eye text-success fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 outfit text-success">Section Visibility</h6>
                                        <p class="text-muted mb-0 small">Publish/unpublish the Veritas Advantage & ROI section.</p>
                                    </div>
                                </div>
                                <div>
                                    <select name="settings[section_roi_active]" class="form-select rounded-pill px-3 fw-bold bg-white text-success border-success">
                                        <option value="1" <?= ($settings['section_roi_active'] ?? '1') === '1' ? 'selected' : '' ?>>Published</option>
                                        <option value="0" <?= ($settings['section_roi_active'] ?? '1') === '0' ? 'selected' : '' ?>>Unpublished</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#roiIntro" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-wallet fs-4"></i> Measurable Advantage & ROI</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="roiIntro">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Badge</label>
                                        <input type="text" name="settings[roi_badge]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['roi_badge']) ?>" required>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Title (HTML Allowed)</label>
                                        <input type="text" name="settings[roi_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['roi_title']) ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Section Lead Paragraph</label>
                                        <input type="text" name="settings[roi_lead]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['roi_lead']) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card settings-card">
                            <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#roiMatrix" aria-expanded="true">
                                <h5 class="fw-bold mb-0 outfit text-success"><i class="bi bi-table fs-4"></i> ROI Matrix & Advantage</h5>
                                <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
                            </div>
                            <div class="collapse show p-4 pt-3" id="roiMatrix">
                                <div class="row g-4">
                                    <div class="col-lg-6">
                                        <h6 class="fw-bold text-success mb-2 outfit"><i class="bi bi-table"></i> ROI Matrix Table Rows</h6>
                                        <div class="alert alert-warning border-0 rounded-4 p-2.5 small mb-3">
                                            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Each row must follow this exact format separated by <code>;;</code>:<br>
                                            <code>Category Title;;Paper Cost;;Portal Cost;;Savings Amount</code>
                                        </div>
                                        <textarea name="settings[roi_savings_rows]" class="form-control rounded-3 small" rows="8" required><?= htmlspecialchars($settings['roi_savings_rows']) ?></textarea>
                                        <hr class="my-3 opacity-10">
                                        <h6 class="fw-bold text-success mb-2 outfit"><i class="bi bi-calculator"></i> Summary Row Details</h6>
                                        <div class="row g-2 mb-2">
                                            <div class="col-md-6">
                                                <label class="form-label small text-muted">Summary Label</label>
                                                <input type="text" name="settings[roi_total_label]" class="form-control rounded-3 small" value="<?= htmlspecialchars($settings['roi_total_label']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small text-muted">Total Paper Cost</label>
                                                <input type="text" name="settings[roi_total_paper]" class="form-control rounded-3 small" value="<?= htmlspecialchars($settings['roi_total_paper']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="form-label small text-muted">Total Portal Cost</label>
                                                <input type="text" name="settings[roi_total_portal]" class="form-control rounded-3 small" value="<?= htmlspecialchars($settings['roi_total_portal']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small text-muted">Total Annual Savings</label>
                                                <input type="text" name="settings[roi_total_savings]" class="form-control rounded-3 small" value="<?= htmlspecialchars($settings['roi_total_savings']) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <h6 class="fw-bold text-success mb-2 outfit"><i class="bi bi-infinity"></i> Advantage vs Standard LMS</h6>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Section Side Header</label>
                                            <input type="text" name="settings[roi_lms_title]" class="form-control rounded-3" value="<?= htmlspecialchars($settings['roi_lms_title']) ?>" required>
                                        </div>
                                        <div class="p-3 border rounded-3 bg-light mb-3">
                                            <label class="form-label small text-muted fw-bold">Advantage Pillar 1</label>
                                            <input type="text" name="settings[roi_lms1_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['roi_lms1_title']) ?>" required>
                                            <textarea name="settings[roi_lms1_desc]" class="form-control rounded-3 small" rows="2" required><?= htmlspecialchars($settings['roi_lms1_desc']) ?></textarea>
                                        </div>
                                        <div class="p-3 border rounded-3 bg-light mb-3">
                                            <label class="form-label small text-muted fw-bold">Advantage Pillar 2</label>
                                            <input type="text" name="settings[roi_lms2_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['roi_lms2_title']) ?>" required>
                                            <textarea name="settings[roi_lms2_desc]" class="form-control rounded-3 small" rows="2" required><?= htmlspecialchars($settings['roi_lms2_desc']) ?></textarea>
                                        </div>
                                        <div class="p-3 border rounded-3 bg-light">
                                            <label class="form-label small text-muted fw-bold">Advantage Pillar 3</label>
                                            <input type="text" name="settings[roi_lms3_title]" class="form-control rounded-3 mb-2 small fw-bold" value="<?= htmlspecialchars($settings['roi_lms3_title']) ?>" required>
                                            <textarea name="settings[roi_lms3_desc]" class="form-control rounded-3 small" rows="2" required><?= htmlspecialchars($settings['roi_lms3_desc']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-sticky-save">
        <span class="admin-unsaved-dot d-none" id="unsavedDot"></span>
        <span class="small text-muted" id="saveStatus">All changes saved</span>
        <button type="submit" class="admin-btn admin-btn-primary" id="saveBtn" form="cmsForm">
            <i class="bi bi-cloud-upload"></i> Publish Live Changes
        </button>
    </div>

    <?php include 'footer.php'; ?>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin-common.js"></script>
    <script src="assets/js/admin-swal.js"></script>
    <script>
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById(previewId).src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        (function() {
            var form = document.getElementById('cmsForm');
            var saveBtn = document.getElementById('saveBtn');
            var unsavedDot = document.getElementById('unsavedDot');
            var saveStatus = document.getElementById('saveStatus');
            var formDirty = false;

            function markDirty() {
                if (!formDirty) {
                    formDirty = true;
                    if (unsavedDot) unsavedDot.style.display = '';
                    if (saveStatus) saveStatus.textContent = 'Unsaved changes';
                }
            }

            if (form) {
                form.addEventListener('change', markDirty);
                form.addEventListener('input', markDirty);
                form.addEventListener('submit', function() {
                    formDirty = false;
                    if (unsavedDot) unsavedDot.style.display = 'none';
                    if (saveStatus) saveStatus.textContent = 'Saving...';
                });
            }

            window.addEventListener('beforeunload', function(e) {
                if (formDirty) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        })();
    </script>
</body>
</html>
