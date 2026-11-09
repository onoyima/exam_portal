<?php
require 'auth.php';
check_auth(['admin', 'examiner']);
require 'db.php';

$name = $_SESSION['user'] ?? 'Staff';
$role = $_SESSION['role'] ?? 'examiner';
$dashboard = ($role == 'admin') ? 'admin_dashboard.php' : 'assess.php';

// Fetch all examiners/staff for private chat
$examiners = $db->query("SELECT id, name, username, role FROM examiners WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch current sender's ID
$stmt = $db->prepare("SELECT id FROM examiners WHERE username=?");
$stmt->execute([$name]);
$sender_id = $stmt->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Staff Chat - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <style>
    /* Chat Custom Premium Styles */
    .chat-card {
      height: calc(100vh - 180px);
      min-height: 520px;
      max-height: 800px;
      background: #ffffff;
      display: flex;
      flex-direction: column;
    }

    .chat-contacts-panel {
      background: #ffffff;
      border-right: 1px solid #e5e7eb;
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .chat-list-container {
      flex-grow: 1;
      overflow-y: auto;
    }

    .chat-contact-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      cursor: pointer;
      transition: all 0.2s ease;
      border-bottom: 1px solid #f3f4f6;
      text-decoration: none;
      color: inherit;
    }

    .chat-contact-item:hover {
      background-color: #f9fafb;
      text-decoration: none;
      color: inherit;
    }

    .chat-contact-item.active {
      background-color: #ecfdf5;
      border-left: 4px solid #10b981;
      text-decoration: none;
      color: inherit;
    }

    .contact-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #3b82f6;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 0.95rem;
      position: relative;
      flex-shrink: 0;
    }

    .contact-avatar.group-avatar {
      background-color: #10b981;
    }

    .contact-avatar .status-badge {
      position: absolute;
      bottom: 0;
      right: 0;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      border: 2px solid white;
      background-color: #9ca3af;
      transition: background-color 0.2s ease;
    }

    .contact-avatar .status-badge.online {
      background-color: #10b981;
    }

    .chat-header-avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background-color: #10b981;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 1rem;
      flex-shrink: 0;
    }

    .chat-header-avatar.user-avatar {
      background-color: #3b82f6;
    }

    .chat-messages-container {
      flex-grow: 1;
      overflow-y: auto;
      background-color: #f3f4f6;
      background-image: radial-gradient(#e5e7eb 1.5px, transparent 1.5px);
      background-size: 24px 24px;
      padding: 20px;
      scroll-behavior: smooth;
    }

    .chat-bubble-row {
      display: flex;
      margin-bottom: 14px;
      animation: bubbleFadeIn 0.25s ease-out;
    }

    @keyframes bubbleFadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .chat-bubble {
      max-width: 70%;
      padding: 10px 14px;
      border-radius: 18px;
      position: relative;
      font-size: 0.9rem;
      line-height: 1.45;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .chat-bubble.me {
      background-color: #d1fae5;
      color: #065f46;
      border-bottom-right-radius: 4px;
    }

    .chat-bubble.other {
      background-color: #ffffff;
      color: #1f2937;
      border-bottom-left-radius: 4px;
    }

    .chat-time {
      display: block;
      font-size: 0.7rem;
      color: #9ca3af;
      text-align: right;
      margin-top: 4px;
    }

    .chat-bubble.me .chat-time {
      color: #047857;
      opacity: 0.8;
    }

    .chat-media {
      max-width: 100%;
      border-radius: 8px;
      margin-top: 8px;
      display: block;
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .chat-input {
      box-shadow: none !important;
      border: 1px solid #d1d5db;
    }

    .btn-icon {
      padding: 0;
      flex-shrink: 0;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .btn-icon:hover {
      transform: scale(1.05);
    }

    .media-preview-banner {
      background-color: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 8px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.05);
      position: absolute;
      bottom: 75px;
      left: 15px;
      right: 15px;
      z-index: 100;
    }

    .contacts-search-box {
      border-radius: 20px !important;
      padding-left: 36px !important;
      background-color: #f3f4f6 !important;
      border: none !important;
    }

    .contacts-search-container {
      position: relative;
    }

    .contacts-search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      font-size: 1.1rem;
      z-index: 5;
    }
  </style>
</head>

<body class="bg-light">
  <?php include ($_SESSION['role'] ?? '') === 'examiner' ? 'examiner_sidebar.php' : 'admin_sidebar.php'; ?>
  
  <div class="main-content">
    <!-- Premium Header Navbar -->
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid px-4">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
          <a href="<?= $dashboard ?>" class="btn btn-outline-secondary rounded-pill px-3 me-2">&larr; Back</a>
          <span class="navbar-brand fw-bold">Communication Hub</span>
        </div>
        
        <!-- Premium Profile Badge with Dropdown -->
        <div class="dropdown">
          <div class="d-flex align-items-center gap-3 chat-clickable" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="text-end d-none d-md-block">
              <div class="fw-bold small mb-0 text-dark"><?= htmlspecialchars($name) ?></div>
              <div class="text-muted small chat-timestamp"><?= strtoupper($role) ?></div>
            </div>
            <span class="profile-icon me-0"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var c=p.previousElementSibling;if(c){var t=c.textContent.trim();p.textContent=t.charAt(0).toUpperCase()||&quot;?&quot;}}"></span>
          </div>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2 animate slideIn" aria-labelledby="profileDropdown">
            <li>
              <div class="px-3 py-2">
                <span class="fw-bold text-dark d-block chat-name"><?= htmlspecialchars($name) ?></span>
                <span class="text-muted small d-block text-truncate chat-last-msg"><?= htmlspecialchars($_SESSION['user'] ?? '') ?></span>
              </div>
            </li>
            <li><hr class="dropdown-divider my-1"></li>
            <li>
              <a class="dropdown-item text-danger d-flex align-items-center gap-2 py-2" href="logout">
                <i class="bx bx-log-out fs-5"></i>
                <span class="chat-logout-text">Logout</span>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- Sub-page Chat Application Panel -->
    <div class="container-xl px-4">
      <div class="card chat-card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="row g-0 flex-nowrap chat-full-height">
          
          <!-- Column 1: Sub-panel Contacts List -->
          <div class="col-12 col-md-4 col-lg-3 chat-contacts-panel d-flex flex-column">
            <div class="p-3 border-bottom bg-white">
              <h5 class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                <span>Conversations</span>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 chat-badge-text" id="onlineCounter">Staff List</span>
              </h5>
              <div class="contacts-search-container">
                <i class="bx bx-search contacts-search-icon"></i>
                <input type="text" id="contactSearch" class="form-control contacts-search-box shadow-none" placeholder="Search staff members...">
              </div>
            </div>
            
            <div class="chat-list-container" id="chatList">
              <!-- Group Chat Row -->
              <a href="#" class="chat-contact-item active" id="groupLink" data-group="1">
                <div class="contact-avatar group-avatar">
                  <i class="bi bi-people-fill"></i>
                  <span class="status-badge online"></span>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark text-truncate">Group Channel</span>
                  </div>
                  <small class="text-muted text-truncate d-block">Public broadcast stream</small>
                </div>
              </a>

              <!-- Examiners Rows -->
              <?php foreach ($examiners as $ex):
                if ($ex['username'] === $name) continue; ?>
                <a href="#" class="chat-contact-item contact-staff-row" data-user="<?= $ex['id'] ?>" data-username="<?= htmlspecialchars($ex['username']) ?>">
                  <div class="contact-avatar">
                    <?= strtoupper(substr($ex['name'], 0, 1)) ?>
                    <span class="status-badge offline" id="status-<?= $ex['id'] ?>"></span>
                  </div>
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center">
                      <span class="fw-bold text-dark text-truncate"><?= htmlspecialchars($ex['name']) ?></span>
                    </div>
                    <small class="text-muted text-truncate d-block chat-small"><?= strtoupper($ex['role'] ?? 'Examiner') ?></small>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Column 2: Chat Discussion Area -->
          <div class="col col-md-8 col-lg-9 d-flex flex-column chat-area-panel position-relative h-100">
            
            <!-- Conversation Header -->
            <div class="p-3 border-bottom bg-white d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center gap-3">
                <div class="chat-header-avatar" id="activeChatAvatar">G</div>
                <div>
                  <h6 class="fw-bold mb-0 text-dark" id="activeChatName">Group Channel</h6>
                  <small class="text-muted d-flex align-items-center gap-1" id="activeChatStatus">
                    <span class="status-dot online chat-status-dot"></span> Active Group
                  </small>
                </div>
              </div>
            </div>

            <!-- Messages Stream Area -->
            <div class="chat-messages-container" id="chatMessages">
              <!-- Bubbles populated dynamically via JS -->
            </div>

            <!-- Pre-upload File Preview Notification (hidden by default) -->
            <div class="media-preview-banner d-none" id="mediaPreviewBanner">
              <div class="d-flex align-items-center gap-2 overflow-hidden">
                <i class="bx bx-paperclip text-primary fs-4"></i>
                <span class="small text-dark text-truncate fw-semibold" id="mediaPreviewName">Attached file.png</span>
              </div>
              <button type="button" class="btn-close chat-btn-sm" id="clearMediaPreview" title="Remove Attachment"></button>
            </div>

            <!-- Message Typing Form Footer -->
            <div class="p-3 bg-white border-top">
              <form id="chatForm" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                <!-- Attachment Button -->
                <label class="btn btn-outline-secondary btn-icon rounded-circle mb-0 chat-avatar-label" title="Attach diagram (Image, Audio, Video)">
                  <i class="bx bx-paperclip fs-5"></i>
                  <input type="file" name="media" id="mediaInput" accept="image/*,audio/*,video/*" class="d-none">
                </label>

                <!-- Input Text Box -->
                <input type="text" name="message" id="messageInput" autocomplete="off" class="form-control rounded-pill p-2 px-3 chat-input" placeholder="Write your message here...">

                <!-- Send Button -->
                <button type="submit" class="btn btn-primary rounded-circle btn-icon chat-avatar-btn">
                  <i class="bx bxs-send fs-5"></i>
                </button>
              </form>
            </div>

          </div>

        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    let currentUser = null;
    let currentGroup = 1;
    const senderId = <?= intval($sender_id) ?>;

    // Elements
    const chatMessages = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const mediaInput = document.getElementById('mediaInput');
    const mediaPreviewBanner = document.getElementById('mediaPreviewBanner');
    const mediaPreviewName = document.getElementById('mediaPreviewName');
    const clearMediaPreview = document.getElementById('clearMediaPreview');
    const activeChatAvatar = document.getElementById('activeChatAvatar');
    const activeChatName = document.getElementById('activeChatName');
    const activeChatStatus = document.getElementById('activeChatStatus');
    const contactSearch = document.getElementById('contactSearch');

    // Attach Media File Listener
    mediaInput.onchange = function() {
      if (this.files && this.files[0]) {
        mediaPreviewName.textContent = this.files[0].name;
        mediaPreviewBanner.classList.remove('d-none');
        messageInput.focus();
      }
    };

    // Remove Attached File
    clearMediaPreview.onclick = function() {
      mediaInput.value = '';
      mediaPreviewBanner.classList.add('d-none');
    };

    // Filter staff list in real-time
    contactSearch.oninput = function() {
      const q = this.value.toLowerCase().trim();
      document.querySelectorAll('.contact-staff-row').forEach(row => {
        const staffName = row.querySelector('.fw-bold').textContent.toLowerCase();
        if (staffName.includes(q)) {
          row.style.setProperty('display', 'flex', 'important');
        } else {
          row.style.setProperty('display', 'none', 'important');
        }
      });
    };

    // Fetch and draw messages
    function fetchMessages() {
      let params = currentUser ? `receiver_id=${currentUser}` : `group_id=${currentGroup}`;
      fetch('chat_backend?' + params)
        .then(r => r.json())
        .then(data => {
          // Track scroll height to scroll down if near bottom
          const isAtBottom = chatMessages.scrollHeight - chatMessages.clientHeight <= chatMessages.scrollTop + 60;
          
          chatMessages.innerHTML = '';
          (data.messages || []).forEach(msg => {
            const isMe = parseInt(msg.sender_id) === senderId;
            
            const row = document.createElement('div');
            row.className = 'chat-bubble-row ' + (isMe ? 'justify-content-end' : 'justify-content-start');
            
            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble ' + (isMe ? 'me' : 'other');
            
            // Format time
            let timeStr = '';
            if (msg.created_at) {
              const parts = msg.created_at.split(' ');
              if (parts[1]) {
                const tParts = parts[1].split(':');
                timeStr = tParts[0] + ':' + tParts[1];
              }
            }
            
            let html = '';
            if (!isMe) {
              html += `<b class="text-primary small d-block mb-1">${msg.sender_name}</b>`;
            }
            html += `<span>${msg.message || ''}</span>`;
            
            if (msg.media_base64) {
              if (msg.media_type.startsWith('image')) {
                html += `<img src="${msg.media_base64}" class="chat-media" />`;
              } else if (msg.media_type.startsWith('audio')) {
                html += `<audio controls class="chat-media w-100 mt-2"><source src="${msg.media_base64}"></audio>`;
              } else if (msg.media_type.startsWith('video')) {
                html += `<video controls class="chat-media w-100 mt-2"><source src="${msg.media_base64}"></video>`;
              }
            }
            
            html += `<span class="chat-time">${timeStr}</span>`;
            bubble.innerHTML = html;
            row.appendChild(bubble);
            chatMessages.appendChild(row);
          });
          
          if (isAtBottom || chatMessages.innerHTML === '') {
            chatMessages.scrollTop = chatMessages.scrollHeight;
          }
        });
    }

    // Mark messages as read on click
    function markAsRead(senderStaffId) {
      if (!senderStaffId) return;
      const form = new FormData();
      form.append('sender_id', senderStaffId);
      fetch('chat_backend?action=mark_read', { method: 'POST', body: form });
    }

    // Submit Message Form
    chatForm.onsubmit = function(e) {
      e.preventDefault();
      
      const msgVal = messageInput.value.trim();
      const hasFile = mediaInput.files.length > 0;
      
      if (!msgVal && !hasFile) return;

      const form = new FormData(this);
      if (currentUser) form.append('receiver_id', currentUser);
      if (!currentUser) form.append('group_id', currentGroup);

      fetch('chat_backend', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
          this.reset();
          mediaPreviewBanner.classList.add('d-none');
          fetchMessages();
          if (currentUser) {
            markAsRead(currentUser);
          }
        });
    };

    // Contacts navigation
    document.querySelectorAll('.chat-contact-item').forEach(link => {
      link.onclick = function(e) {
        e.preventDefault();
        
        document.querySelectorAll('.chat-contact-item').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        
        const avatarText = this.querySelector('.contact-avatar').textContent.trim();
        const contactName = this.querySelector('.fw-bold').textContent.trim();
        
        if (this.dataset.user) {
          currentUser = this.dataset.user;
          currentGroup = null;
          
          activeChatAvatar.className = 'chat-header-avatar user-avatar';
          activeChatAvatar.textContent = avatarText || contactName.charAt(0).toUpperCase();
          activeChatName.textContent = contactName;
          activeChatStatus.innerHTML = '<span class="status-dot offline chat-status-dot" id="headerStatusDot"></span> Checking Status...';
          
          markAsRead(currentUser);
        } else {
          currentUser = null;
          currentGroup = 1;
          
          activeChatAvatar.className = 'chat-header-avatar group-avatar';
          activeChatAvatar.innerHTML = '<i class="bi bi-people-fill"></i>';
          activeChatName.textContent = 'Group Channel';
          activeChatStatus.innerHTML = '<span class="status-dot online chat-status-dot"></span> Active Group';
        }
        
        chatMessages.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading messages...</div>';
        fetchMessages();
        fetchStatus();
      };
    });

    // Online status polling
    function fetchStatus() {
      fetch('chat_status')
        .then(r => r.json())
        .then(data => {
          let hasActiveUserOnline = false;
          (data.statuses || []).forEach(s => {
            const dot = document.getElementById('status-' + s.id);
            if (dot) {
              dot.className = 'status-badge ' + (s.online ? 'online' : 'offline');
            }
            if (currentUser && parseInt(currentUser) === s.id && s.online) {
              hasActiveUserOnline = true;
            }
          });
          
          if (currentUser) {
            const headerDot = document.getElementById('headerStatusDot');
            if (headerDot) {
              headerDot.className = 'status-dot ' + (hasActiveUserOnline ? 'online' : 'offline');
            }
            activeChatStatus.innerHTML = `<span class="status-dot ${hasActiveUserOnline ? 'online' : 'offline'} chat-status-dot"></span> ${hasActiveUserOnline ? 'Online' : 'Offline'}`;
          }
        });
    }

    // Initial Fetch & Intervals
    fetchMessages();
    fetchStatus();
    setInterval(fetchMessages, 3000);
    setInterval(fetchStatus, 5000);
  </script>

  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>

</html>
