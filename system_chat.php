<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

$user = $_SESSION['user'];
$role = $_SESSION['role'] ?? 'user';
$name = $_SESSION['user'] ?? 'User';

// ── POST: Chatbot API ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $responses = [
        'hello' => 'Hello! How can I help you with the Assessment Portal today?',
        'help' => 'I can help you with:<br>• Exam management<br>• Student management<br>• Question management<br>• Analytics and reports<br>• System navigation<br><br>What would you like to know?',
        'exam' => 'To manage exams:<br>1. Go to "Manage Exams"<br>2. Create a new exam<br>3. Add students and questions<br>4. Activate when ready',
        'student' => 'To manage students:<br>1. Go to "Manage Students"<br>2. Add individual students or bulk upload<br>3. Edit or delete as needed',
        'question' => 'To manage questions:<br>1. Go to "Manage Questions"<br>2. Add questions with score values<br>3. Use bulk upload for multiple questions',
        'analytics' => 'View analytics in the "Analytics" section to see:<br>• Student performance<br>• Exam statistics<br>• Score distributions',
        'report' => 'Generate reports in the "Reports" section to export exam results and student scores.',
        'chat' => 'Use the "Chat" feature to communicate with other users in real-time.',
        'logout' => 'Click "Logout" in the sidebar to safely exit the system.',
        'default' => 'I\'m here to help! Try asking about:<br>• Exams<br>• Students<br>• Questions<br>• Analytics<br>• Reports<br>• Chat<br>• Help'
    ];

    $input = strtolower(trim($_POST['message'] ?? ''));
    $response = $responses['default'];

    if (strpos($input, 'hello') !== false || strpos($input, 'hi') !== false) {
        $response = $responses['hello'];
    } elseif (strpos($input, 'help') !== false) {
        $response = $responses['help'];
    } elseif (strpos($input, 'exam') !== false) {
        $response = $responses['exam'];
    } elseif (strpos($input, 'student') !== false) {
        $response = $responses['student'];
    } elseif (strpos($input, 'question') !== false) {
        $response = $responses['question'];
    } elseif (strpos($input, 'analytics') !== false || strpos($input, 'stat') !== false) {
        $response = $responses['analytics'];
    } elseif (strpos($input, 'report') !== false) {
        $response = $responses['report'];
    } elseif (strpos($input, 'chat') !== false) {
        $response = $responses['chat'];
    } elseif (strpos($input, 'logout') !== false || strpos($input, 'exit') !== false) {
        $response = $responses['logout'];
    }

    $db->exec("CREATE TABLE IF NOT EXISTS system_chat_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(100) NOT NULL,
        user_role VARCHAR(20) NOT NULL,
        user_message TEXT NOT NULL,
        bot_response TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $db->prepare("INSERT INTO system_chat_log (user_id, user_role, user_message, bot_response) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user, $role, $input, $response]);

    echo json_encode([
        'success' => true,
        'response' => $response,
        'timestamp' => date('H:i')
    ]);
    exit;
}

// ── GET: Render chatbot page ──
$back_url = $role === 'admin' ? 'admin_dashboard' : ($role === 'examiner' ? 'assess' : 'student_dashboard');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>System Assistant - Assessment Portal</title>
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
<?php if ($role === 'admin'): ?>
  <?php include 'admin_sidebar.php'; ?>
<?php elseif ($role === 'examiner'): ?>
  <?php include 'examiner_sidebar.php'; ?>
<?php endif; ?>
<div class="main-content">
  <nav class="admin-navbar">
    <div class="admin-navbar-inner">
      <div class="admin-navbar-start">
        <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
        <a href="<?= $back_url ?>" class="admin-back-btn"><i class="bi bi-arrow-left"></i></a>
        <h1 class="admin-navbar-title">System Assistant</h1>
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
    <nav class="admin-breadcrumb">
      <ol>
        <li><a href="<?= $back_url ?>"><i class="bi bi-house"></i></a></li>
        <li class="sep">/</li>
        <li class="current" aria-current="page">System Assistant</li>
      </ol>
    </nav>

    <div class="admin-page-header">
      <h2 class="admin-page-title"><i class="bi bi-robot"></i> System Assistant</h2>
    </div>

    <div class="admin-card">
      <div class="admin-card-body p-0">
        <div id="chatMessages" class="p-3" style="height:450px;overflow-y:auto;background:#f8fafc;">
          <div class="d-flex mb-3">
            <div class="flex-shrink-0 me-2"><span class="badge bg-primary rounded-circle p-2"><i class="bi bi-robot"></i></span></div>
            <div class="bg-white rounded-3 p-3 shadow-sm" style="max-width:75%;">
              <div class="fw-bold small text-primary mb-1">System Assistant</div>
              <div>Hello! I'm your Assessment Portal assistant. Ask me anything about managing exams, students, questions, or reports.</div>
              <div class="small text-muted mt-1"><?= date('H:i') ?></div>
            </div>
          </div>
        </div>
        <div class="border-top p-3 bg-white">
          <form id="chatForm" class="d-flex gap-2" autocomplete="off">
            <input type="text" id="chatInput" class="admin-input flex-grow-1" placeholder="Type your question..." required>
            <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-send"></i></button>
          </form>
          <div class="small text-muted mt-2">Try asking: "help", "exams", "students", "questions", "analytics", "reports"</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-common.js"></script>
<script>
document.getElementById('chatForm').addEventListener('submit', function(e) {
  e.preventDefault();
  var input = document.getElementById('chatInput');
  var msg = input.value.trim();
  if (!msg) return;

  var container = document.getElementById('chatMessages');

  // User message
  var userDiv = document.createElement('div');
  userDiv.className = 'd-flex mb-3 justify-content-end';
  userDiv.innerHTML = '<div class="bg-primary text-white rounded-3 p-3 shadow-sm" style="max-width:75%;"><div>' + escHtml(msg) + '</div><div class="small text-white-50 mt-1">' + new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) + '</div></div><div class="flex-shrink-0 ms-2"><span class="badge bg-secondary rounded-circle p-2"><i class="bi bi-person"></i></span></div>';
  container.appendChild(userDiv);
  container.scrollTop = container.scrollHeight;
  input.value = '';
  input.focus();

  // Bot thinking
  var botDiv = document.createElement('div');
  botDiv.className = 'd-flex mb-3';
  botDiv.innerHTML = '<div class="flex-shrink-0 me-2"><span class="badge bg-primary rounded-circle p-2"><i class="bi bi-robot"></i></span></div><div class="bg-white rounded-3 p-3 shadow-sm" style="max-width:75%;"><div class="fw-bold small text-primary mb-1">System Assistant</div><div><span class="spinner-border spinner-border-sm"></span> Thinking...</div></div>';
  container.appendChild(botDiv);
  container.scrollTop = container.scrollHeight;

  var fd = new FormData();
  fd.append('message', msg);
  fetch('system_chat', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      botDiv.querySelector('div:last-child').innerHTML = data.response + '<div class="small text-muted mt-1">' + data.timestamp + '</div>';
      container.scrollTop = container.scrollHeight;
    })
    .catch(function() {
      botDiv.querySelector('div:last-child').innerHTML = 'Sorry, something went wrong. Please try again.';
    });
});

function escHtml(str) {
  var d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}
</script>
</body>
</html>
