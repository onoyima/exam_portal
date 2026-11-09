<?php
require '../../db.php';
$settings = $db->query("SELECT * FROM seb_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Gate | Veritas Exam Portal</title>
    <link href="../../assets/vendor/fonts/fonts.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: #060d16;
            color: #e8f0e9;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gate-card {
            max-width: 500px;
            width: 100%;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 48px;
            backdrop-filter: blur(20px);
            text-align: center;
        }
        .icon-box {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #198754, #0f5132);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; color: white;
            margin: 0 auto 24px;
            box-shadow: 0 10px 30px rgba(25,135,84,0.3);
        }
        .btn-download {
            background: linear-gradient(135deg, #198754, #0f5132);
            border: none; padding: 16px 32px;
            border-radius: 14px; color: white;
            font-weight: 700; text-decoration: none;
            display: inline-flex; align-items: center; gap: 10px;
            transition: all 0.3s;
            margin-top: 24px;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(25,135,84,0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="gate-card">
        <div class="icon-box">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h1 class="fw-800 mb-2">Secure Hub Activation</h1>
        <p class="text-muted mb-4 small">Select your device type to establish a system-level secure workspace.</p>
        
        <ul class="nav nav-pills nav-justified mb-4 bg-dark rounded-pill p-1 border border-secondary border-opacity-25" id="platformTabs" role="tablist">
          <li class="nav-item"><a class="nav-link active rounded-pill small py-1" data-bs-toggle="pill" href="#win">Windows / Mac</a></li>
          <li class="nav-item"><a class="nav-link rounded-pill small py-1" data-bs-toggle="pill" href="#ios">iOS (iPad)</a></li>
        </ul>

        <div class="tab-content text-start small">
          <div class="tab-pane fade show active" id="win">
            <ol class="mb-4 text-muted">
                <li>Install <b>Safe Exam Browser</b> client (<a href="https://safeexambrowser.org/download_en.html" target="_blank" class="text-accent">Download</a>).</li>
                <li>Download your <b>Veritas Config</b> below.</li>
                <li>Double-click the file to lock your system and start.</li>
            </ol>
            <a href="config_generator.php" class="btn-download w-100">
                <i class="bi bi-pc-display"></i>
                Download Desktop Hub Config
            </a>
          </div>
          <div class="tab-pane fade" id="ios">
            <ol class="mb-4 text-muted">
                <li>Install <b>Safe Exam Browser</b> from App Store.</li>
                <li>Tap the "Launch Now" link below.</li>
                <li>The SEB app will open and secure your iPad/iPhone.</li>
            </ol>
            <?php 
              $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "sebs" : "seb") . "://$_SERVER[HTTP_HOST]" . str_replace("index.php", "config_generator.php", $_SERVER['REQUEST_URI']);
            ?>
            <a href="<?= $link ?>" class="btn-download w-100" style="background: linear-gradient(135deg, #0d6efd, #0a58ca);">
                <i class="bi bi-phone-fill"></i>
                Launch Now (iOS Only)
            </a>
          </div>
        </div>

        <p class="mt-4 mb-0 text-muted" style="font-size: 0.7rem;">
            <i class="bi bi-lock-fill me-1"></i> System-Level Encryption Active
        </p>
    </div>
    
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
