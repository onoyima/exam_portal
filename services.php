<?php
session_start();
require 'db.php';
$name = $_SESSION['user'] ?? 'Guest';
$role = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Services & Features - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    .hero-section {
      background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
      color: white;
      padding: 4rem 0;
      margin-bottom: 3rem;
    }

    .feature-card {
      border: none;
      border-radius: 1.5rem;
      transition: all 0.3s ease;
      height: 100%;
    }

    .feature-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    .feature-icon {
      width: 80px;
      height: 80px;
      background: var(--secondary-color);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      margin: 0 auto 1.5rem;
      color: var(--primary-color);
    }

    .service-section {
      padding: 3rem 0;
    }

    .service-section:nth-child(even) {
      background: var(--light-color);
    }

    .usage-step {
      background: white;
      border-radius: 1rem;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: var(--shadow);
      border-left: 4px solid var(--primary-color);
    }

    .step-number {
      width: 40px;
      height: 40px;
      background: var(--primary-color);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      margin-bottom: 1rem;
    }

    .benefit-item {
      display: flex;
      align-items: center;
      margin-bottom: 1rem;
      padding: 1rem;
      background: white;
      border-radius: 0.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .benefit-icon {
      width: 40px;
      height: 40px;
      background: var(--success-color);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      margin-right: 1rem;
      flex-shrink: 0;
    }
  </style>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-xl">
      <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index">
        <img src="assets/favicon.ico" alt="Logo" width="30" height="30">
        <span>Assessment Portal</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <i class="bi bi-list"></i>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link active" href="services">Services</a>
          </li>
          <?php if ($role): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= $role == 'admin' ? 'admin_dashboard.php' : 'assess' ?>">Dashboard</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="logout">Logout</a>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="login">Login</a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="bg-primary text-white py-5">
    <div class="container-xl text-center">
      <img src="assets/favicon.ico" alt="Logo" width="80" class="mb-4">
      <h1 class="display-4 fw-bold mb-3">Assessment Portal Services</h1>
      <p class="lead mb-4">Comprehensive student assessment and examination management system</p>
      <p class="mb-0">Streamlined evaluation process for educational institutions</p>
    </div>
  </section>

  <!-- Features Section -->
  <section class="py-5">
    <div class="container-xl">
      <div class="text-center mb-5">
        <h2 class="fw-bold mb-3">Core Features</h2>
        <p class="text-muted">Discover the powerful features that make our assessment portal stand out</p>
      </div>

      <div class="row g-4">
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body text-center p-4">
              <div class="display-4 mb-3">👥</div>
              <h5 class="card-title fw-bold">Multi-Role System</h5>
              <p class="card-text text-muted">Admin and Examiner roles with distinct permissions and access levels for
                secure management.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body text-center p-4">
              <div class="display-4 mb-3">📊</div>
              <h5 class="card-title fw-bold">Student Management</h5>
              <p class="card-text text-muted">Add, edit, delete, and bulk upload students via CSV/TXT files for
                efficient data management.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body text-center p-4">
              <div class="display-4 mb-3">❓</div>
              <h5 class="card-title fw-bold">Question Management</h5>
              <p class="card-text text-muted">Create and manage assessment questions with customizable scoring systems.
              </p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body text-center p-4">
              <div class="display-4 mb-3">📝</div>
              <h5 class="card-title fw-bold">Exam Management</h5>
              <p class="card-text text-muted">Create exams, assign students and questions, set active/inactive status.
              </p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body text-center p-4">
              <div class="display-4 mb-3">💬</div>
              <h5 class="card-title fw-bold">Real-time Chat</h5>
              <p class="card-text text-muted">Built-in messaging system between admin and examiners with media support.
              </p>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body text-center p-4">
              <div class="display-4 mb-3">📈</div>
              <h5 class="card-title fw-bold">Advanced Analytics</h5>
              <p class="card-text text-muted">Interactive charts and comprehensive reporting for data-driven decisions.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Usage Guide Section -->
  <section class="py-5 bg-light">
    <div class="container-xl">
      <div class="text-center mb-5">
        <h2 class="fw-bold mb-3">How to Use</h2>
        <p class="text-muted">Step-by-step guide to get started with the Assessment Portal</p>
      </div>

      <div class="row">
        <div class="col-lg-8 mx-auto">
          <div class="card mb-3">
            <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <span class="badge bg-primary me-3">1</span>
                <h5 class="fw-bold mb-0">System Setup</h5>
              </div>
              <p class="text-muted">Import the database schema and configure your web server. Ensure PHP 7.4+ and MySQL
                5.7+ are installed.</p>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <span class="badge bg-primary me-3">2</span>
                <h5 class="fw-bold mb-0">Admin Configuration</h5>
              </div>
              <p class="text-muted">Login as admin and set up your institution's data: add students, create questions,
                and configure exams.</p>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <span class="badge bg-primary me-3">3</span>
                <h5 class="fw-bold mb-0">Examiner Assignment</h5>
              </div>
              <p class="text-muted">Assign examiners to specific exams and provide them with login credentials to begin
                assessments.</p>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <span class="badge bg-primary me-3">4</span>
                <h5 class="fw-bold mb-0">Assessment Process</h5>
              </div>
              <p class="text-muted">Examiners log in, select assigned exams, search for students, and submit assessment
                scores.</p>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <span class="badge bg-primary me-3">5</span>
                <h5 class="fw-bold mb-0">Results & Analytics</h5>
              </div>
              <p class="text-muted">Generate reports, view analytics, export data, and track student performance over
                time.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Benefits Section -->
  <section class="py-5">
    <div class="container-xl">
      <div class="text-center mb-5">
        <h2 class="fw-bold mb-3">Key Benefits</h2>
        <p class="text-muted">Why choose our Assessment Portal for your institution</p>
      </div>

      <div class="row g-4">
        <div class="col-md-6">
          <div class="d-flex align-items-start p-3 bg-light rounded">
            <span class="badge bg-success me-3 mt-1">⚡</span>
            <div>
              <h6 class="fw-bold mb-1">Efficiency</h6>
              <p class="text-muted mb-0">Streamlined assessment process saves time and reduces administrative workload
              </p>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="d-flex align-items-start p-3 bg-light rounded">
            <span class="badge bg-success me-3 mt-1">📱</span>
            <div>
              <h6 class="fw-bold mb-1">Responsive Design</h6>
              <p class="text-muted mb-0">Works seamlessly on all devices - desktop, tablet, and mobile</p>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="d-flex align-items-start p-3 bg-light rounded">
            <span class="badge bg-success me-3 mt-1">🔒</span>
            <div>
              <h6 class="fw-bold mb-1">Security</h6>
              <p class="text-muted mb-0">Secure user sessions, input validation, and complete audit trail</p>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="d-flex align-items-start p-3 bg-light rounded">
            <span class="badge bg-success me-3 mt-1">📊</span>
            <div>
              <h6 class="fw-bold mb-1">Analytics</h6>
              <p class="text-muted mb-0">Comprehensive reporting and data visualization for informed decisions</p>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="d-flex align-items-start p-3 bg-light rounded">
            <span class="badge bg-success me-3 mt-1">💬</span>
            <div>
              <h6 class="fw-bold mb-1">Communication</h6>
              <p class="text-muted mb-0">Built-in chat system for seamless communication between users</p>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="d-flex align-items-start p-3 bg-light rounded">
            <span class="badge bg-success me-3 mt-1">🔄</span>
            <div>
              <h6 class="fw-bold mb-1">Scalability</h6>
              <p class="text-muted mb-0">Designed to handle growing institutions and increasing data volumes</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Technical Specifications -->
  <section class="py-5 bg-light">
    <div class="container-xl">
      <div class="text-center mb-5">
        <h2 class="fw-bold mb-3">Technical Specifications</h2>
        <p class="text-muted">System requirements and technical details</p>
      </div>

      <div class="row g-4">
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title fw-bold">Minimum Requirements</h5>
              <ul class="list-unstyled">
                <li class="mb-2">• PHP 7.4 or higher</li>
                <li class="mb-2">• MySQL 5.7 or higher</li>
                <li class="mb-2">• 128MB RAM</li>
                <li class="mb-2">• 50MB free storage</li>
                <li class="mb-2">• Web server (Apache/Nginx)</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title fw-bold">Recommended Requirements</h5>
              <ul class="list-unstyled">
                <li class="mb-2">• PHP 8.0 or higher</li>
                <li class="mb-2">• MySQL 8.0 or higher</li>
                <li class="mb-2">• 256MB RAM</li>
                <li class="mb-2">• 100MB free storage</li>
                <li class="mb-2">• SSL certificate for security</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Call to Action -->
  <section class="py-5 bg-primary text-white">
    <div class="container-xl text-center">
      <h2 class="fw-bold mb-3">Ready to Get Started?</h2>
      <p class="lead mb-4">Transform your assessment process with our comprehensive portal</p>
      <div class="d-flex justify-content-center gap-3 flex-wrap">
        <a href="login" class="btn btn-light btn-lg">Login Now</a>
        <a href="https://wa.me/+2348168438930?text=Hi%20BMXcode%20Initiator,%20I%20want%20to%20learn%20more%20about%20the%20Assessment%20Portal"
          class="btn btn-outline-light btn-lg" target="_blank">Contact Developer</a>
      </div>
    </div>
  </section>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>



</html>
