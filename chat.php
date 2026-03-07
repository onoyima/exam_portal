<?php
require 'auth.php';
check_auth(['admin', 'examiner']);
require 'db.php';
$name = $_SESSION['user'];
$dashboard = (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') ? 'admin_dashboard.php' : 'assess.php';
// Get all examiners for private chat
$examiners = $db->query("SELECT id, name, username FROM examiners ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Chat - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
  <div class="chat-sidebar d-flex flex-column p-3" id="chatSidebar">
    <h4 class="fw-bold mb-4 text-center">Chat</h4>
    <ul class="nav nav-pills flex-column mb-auto" id="chatList">
      <li><a href="#" class="nav-link active" data-group="1"><span class="status-dot online"
            id="groupStatus"></span>Group Chat</a></li>
      <?php foreach ($examiners as $ex):
        if ($ex['username'] == $_SESSION['user'])
          continue; ?>
        <li><a href="#" class="nav-link" data-user="<?= $ex['id'] ?>"><span class="status-dot offline"
              id="status-<?= $ex['id'] ?>"></span><?= htmlspecialchars($ex['name']) ?></a></li>
      <?php endforeach; ?>
      <li class="mt-3"><a href="logout.php" class="nav-link text-danger">Logout</a></li>
    </ul>
  </div>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="main-content">
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><span
              class="navbar-toggler-icon"></span></button>
          <a href="<?= $dashboard ?>" class="btn btn-outline-secondary me-2">&larr; Back</a>
          <span class="navbar-brand fw-bold">Chat</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="fw-semibold">Logged in as <?= htmlspecialchars($name) ?></span>
        </div>
      </div>
    </nav>

    <div class="container-xl">
      <div class="chat-messages mb-3" id="chatMessages"></div>
      <form id="chatForm" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
        <input type="text" name="message" class="form-control chat-input" placeholder="Type a message...">
        <input type="file" name="media" accept="image/*,audio/*,video/*" class="form-control">
        <button type="submit" class="btn btn-primary">Send</button>
      </form>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar toggle
    const sidebar = document.getElementById('chatSidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
      sidebarToggle.onclick = function () {
        sidebar.classList.toggle('show');
        sidebarBackdrop.classList.toggle('show');
      };
      sidebarBackdrop.onclick = function () {
        sidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
      };
    }

    let currentUser = null, currentGroup = 1;
    function fetchMessages() {
      let params = currentUser ? `receiver_id=${currentUser}` : `group_id=${currentGroup}`;
      fetch('chat_backend.php?' + params)
        .then(r => r.json())
        .then(data => {
          const box = document.getElementById('chatMessages');
          box.innerHTML = '';
          (data.messages || []).forEach(msg => {
            let isMe = msg.sender_name === '<?= addslashes($name) ?>';
            let row = document.createElement('div');
            row.className = 'chat-bubble-row ' + (isMe ? 'justify-content-end' : 'justify-content-start');
            let bubble = document.createElement('div');
            bubble.className = 'chat-bubble ' + (isMe ? 'me' : 'other');
            let time = msg.created_at ? new Date(msg.created_at.replace(' ', 'T')) : null;
            let timeStr = time ? time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
            if (isMe) {
              bubble.innerHTML = `${msg.message || ''}<span class='chat-time'>${timeStr}</span>`;
            } else {
              bubble.innerHTML = `<b>${msg.sender_name}:</b> ${msg.message || ''}<span class='chat-time'>${timeStr}</span>`;
            }
            if (msg.media_base64) {
              if (msg.media_type.startsWith('image')) {
                bubble.innerHTML += `<img src="${msg.media_base64}" class="chat-media" />`;
              } else if (msg.media_type.startsWith('audio')) {
                bubble.innerHTML += `<audio controls class="chat-media"><source src="${msg.media_base64}"></audio>`;
              } else if (msg.media_type.startsWith('video')) {
                bubble.innerHTML += `<video controls class="chat-media"><source src="${msg.media_base64}"></video>`;
              }
            }
            row.appendChild(bubble);
            box.appendChild(row);
          });
          box.scrollTop = box.scrollHeight;
        });
    }
    document.getElementById('chatForm').onsubmit = function (e) {
      e.preventDefault();
      let form = new FormData(this);
      if (currentUser) form.append('receiver_id', currentUser);
      if (!currentUser) form.append('group_id', currentGroup);
      fetch('chat_backend.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(() => {
          this.reset();
          fetchMessages();
        });
    };
    document.querySelectorAll('#chatList .nav-link').forEach(link => {
      link.onclick = function () {
        document.querySelectorAll('#chatList .nav-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        if (this.dataset.user) {
          currentUser = this.dataset.user;
          currentGroup = null;
        } else {
          currentUser = null;
          currentGroup = 1;
        }
        fetchMessages();
      };
    });
    fetchMessages();
    setInterval(fetchMessages, 3000);

    // Online status polling
    function fetchStatus() {
      fetch('chat_status.php')
        .then(r => r.json())
        .then(data => {
          (data.statuses || []).forEach(s => {
            let dot = document.getElementById('status-' + s.id);
            if (dot) dot.className = 'status-dot ' + (s.online ? 'online' : 'offline');
          });
        });
    }
    fetchStatus();
    setInterval(fetchStatus, 5000);
  </script>
</body>

</html>