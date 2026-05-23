<!-- Single Line Footer Component - BMXcode Initiator -->
<footer class="footer">
  <div class="footer-content">
    <div class="brand">Assessment Portal v2.0</div>
    <div class="developer-info">
      <span>Developed by <a href="https://wa.me/1234567890" target="_blank">BMXcode Initiator</a></span>
      <span>•</span>
      <span>Enhanced Edition</span>
      <span>•</span>
      <span>© <?= date('Y') ?> All rights reserved</span>
      <span class="footer-theme-wrapper">
        <button id="themeToggle" class="btn btn-sm btn-outline-secondary footer-theme-btn"><i class="bi bi-moon"></i> Dark
          Mode</button>
      </span>
    </div>
  </div>
</footer>

<!-- WhatsApp Chat Icon (now bottom left, replaces system assistant chat) -->
<a href="https://wa.me/+2348168438930?text=Hi%20BMXcode%20Initiator,%20I%20need%20help%20with%20the%20Assessment%20Portal"
  class="whatsapp-chat left" target="_blank" title="Contact Developer"></a>

<!-- User Chat Icon -->
<div class="user-chat-icon position-relative" id="userChatIcon" title="User Chat">
  <span id="userChatNotification" class="user-chat-notification-badge"></span>
</div>

<!-- User Chat Modal (fixed, improved, full-featured) -->
<div class="user-chat-modal" id="userChatModal">
  <div class="user-chat-content">
    <div class="user-chat-sidebar">
      <div class="user-chat-sidebar-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-people"></i> Chat</h5>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <button class="btn btn-sm btn-danger" id="clearChatsBtn" title="Clear All Chats"><i class="bi bi-trash"></i></button>
        <?php endif; ?>
      </div>
      <div class="user-chat-contacts" id="userChatContacts">
        <!-- Contacts will be loaded here -->
      </div>
    </div>
    <div class="user-chat-main" id="userChatMain">
      <div class="user-chat-header">
        <h5 id="userChatTitle">Select a contact to start chatting</h5>
        <button class="user-chat-close" id="userChatClose">&times;</button>
      </div>
      <div class="user-chat-messages" id="userChatMessages">
        <!-- Messages will be loaded here -->
      </div>
      <div class="user-chat-input">
        <input type="text" id="userChatInput" placeholder="Type a message..." maxlength="500">
        <button id="userChatSend"><i class="bi bi-send"></i></button>
      </div>
    </div>
  </div>
</div>

<!-- Fallback style for user chat icon visibility -->
<style>
  .user-chat-icon {
    position: fixed !important;
    right: 20px !important;
    bottom: 20px !important;
    z-index: 9999 !important;
    display: flex !important;
  }
  .user-chat-notification-badge {
    display: none;
    position: absolute;
    top: 8px;
    right: 8px;
    background: #dc3545;
    color: #fff;
    border-radius: 50%;
    padding: 2px 7px;
    font-size: 13px;
    z-index: 1100;
    min-width: 22px;
    text-align: center;
  }
  .navbar-chat-dropdown {
    display: none;
    position: absolute;
    top: 48px;
    right: 0;
    min-width: 320px;
    max-width: 400px;
    z-index: 99999;
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    padding: 0.5rem 0;
    overflow: hidden;
  }
  .navbar-chat-dropdown-footer {
    text-align: center;
    padding: 0.5rem 0;
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
    cursor: pointer;
    font-weight: 500;
    font-size: 15px;
  }
  .footer-theme-wrapper {
    margin-left: 2rem;
  }
  .footer-theme-btn {
    margin-left: 1rem;
  }
  .back-button-inline {
    margin: 0.5rem;
  }
  .navbar-chat-item {
    cursor: pointer;
  }
  .navbar-chat-avatar {
    background: var(--primary-color);
    color: #fff;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 15px;
    flex-shrink: 0;
  }
  .navbar-chat-content {
    flex: 1;
    min-width: 0;
  }
  .navbar-chat-name {
    font-weight: 600;
    font-size: 15px;
  }
  .navbar-chat-preview {
    font-size: 13px;
    color: #666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
  }
  .navbar-chat-time {
    font-size: 12px;
    color: #888;
    flex-shrink: 0;
  }
  .navbar-chat-unread {
    background: #f8f9fa;
  }
  .user-chat-contact-role {
    font-size: 12px;
    color: #888;
  }
</style>

<!-- Notification Dropdown for Navbar Bell -->
<div id="navbarChatDropdown" class="navbar-chat-dropdown">
  <div id="navbarChatDropdownContent"></div>
  <div id="navbarChatDropdownFooter" class="navbar-chat-dropdown-footer">Show all</div>
</div>

<script>
  // User Chat Functionality (full-featured, like chat.php)
  document.addEventListener('DOMContentLoaded', function () {
    const userChatIcon = document.getElementById('userChatIcon');
    const userChatModal = document.getElementById('userChatModal');
    const userChatClose = document.getElementById('userChatClose');
    const userChatContacts = document.getElementById('userChatContacts');
    const userChatMain = document.getElementById('userChatMain');
    const userChatTitle = document.getElementById('userChatTitle');
    const userChatMessages = document.getElementById('userChatMessages');
    const userChatInput = document.getElementById('userChatInput');
    const userChatSend = document.getElementById('userChatSend');
    const userChatNotification = document.getElementById('userChatNotification');
    const clearChatsBtn = document.getElementById('clearChatsBtn');

    let currentChatUser = null;
    let contacts = [];
    let messageInterval = null;
    let notificationInterval = null;

    // Open user chat modal
    userChatIcon.addEventListener('click', function () {
      userChatModal.classList.add('show');
      loadContacts();
      // Clear notification badge when opening
      userChatNotification.style.display = 'none';
    });

    // Close user chat modal
    userChatClose.addEventListener('click', function () {
      userChatModal.classList.remove('show');
      if (messageInterval) {
        clearInterval(messageInterval);
        messageInterval = null;
      }
    });

    // Close modal when clicking outside
    userChatModal.addEventListener('click', function (e) {
      if (e.target === userChatModal) {
        userChatModal.classList.remove('show');
        if (messageInterval) {
          clearInterval(messageInterval);
          messageInterval = null;
        }
      }
    });

    // Admin: Clear all chats
    if (clearChatsBtn) {
      clearChatsBtn.addEventListener('click', function () {
        Swal.fire({
          title: 'Clear All Chats',
          text: 'Are you sure you want to clear all chat messages? This cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          confirmButtonText: 'Yes, clear it'
        }).then((result) => {
          if (result.isConfirmed) {
            fetch('chat_backend?action=clear_chats', { method: 'POST' })
              .then(r => r.json())
              .then(data => {
                if (data.success) {
                  Swal.fire('Cleared!', 'All chat messages cleared!', 'success');
                  loadMessages();
                } else {
                  Swal.fire('Error', 'Failed to clear chats: ' + (data.error || 'Unknown error'), 'error');
                }
              });
          }
        });
      });
    }

    // Load contacts (all users in examiners except self)
    function loadContacts() {
      fetch('chat_backend?action=get_contacts')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            contacts = data.contacts || [];
            renderContacts();
          }
        })
        .catch(error => {
          userChatContacts.innerHTML = '<div class="text-danger">Failed to load contacts.</div>';
        });
    }

    // Render contacts (show role and avatar)
    function renderContacts() {
      userChatContacts.innerHTML = '';
      // Group Chat option
      const groupChat = document.createElement('div');
      groupChat.className = 'user-chat-contact' + (currentChatUser === 'group' ? ' active' : '');
      groupChat.innerHTML = `
      <div class="user-chat-contact-avatar"><i class="bi bi-people"></i></div>
      <div class="user-chat-contact-info">
        <div class="user-chat-contact-name">Group Chat</div>
        <div class="user-chat-contact-status online">Online</div>
      </div>
    `;
      groupChat.addEventListener('click', () => selectContact('group', 'Group Chat'));
      userChatContacts.appendChild(groupChat);
      // Individual contacts
      contacts.forEach(contact => {
        // Show role (admin/examiner) if available, fallback to 'Examiner'
        const role = contact.role ? contact.role.charAt(0).toUpperCase() + contact.role.slice(1) : 'Examiner';
        const contactDiv = document.createElement('div');
        contactDiv.className = 'user-chat-contact' + (currentChatUser == contact.id ? ' active' : '');
        contactDiv.innerHTML = `
        <div class="user-chat-contact-avatar">${contact.name.charAt(0).toUpperCase()}</div>
        <div class="user-chat-contact-info">
          <div class="user-chat-contact-name">${contact.name} <span class="user-chat-contact-role">(${role})</span></div>
          <div class="user-chat-contact-status ${contact.online ? 'online' : ''}">${contact.online ? 'Online' : 'Offline'}</div>
        </div>
      `;
        contactDiv.addEventListener('click', () => selectContact(contact.id, contact.name));
        userChatContacts.appendChild(contactDiv);
      });
    }

    // Select contact
    function selectContact(userId, userName) {
      currentChatUser = userId;
      userChatTitle.textContent = userId === 'group' ? 'Group Chat' : `Chat with ${userName}`;
      document.querySelectorAll('.user-chat-contact').forEach(contact => contact.classList.remove('active'));
      // Find and activate the clicked contact
      Array.from(userChatContacts.children).forEach(child => {
        if ((userId === 'group' && child.textContent.includes('Group Chat')) ||
          (child.textContent.includes(userName) && userId !== 'group')) {
          child.classList.add('active');
        }
      });
      // Show main chat area on mobile
      if (window.innerWidth <= 768) {
        userChatMain.classList.add('show');
      }
      loadMessages();
      if (messageInterval) clearInterval(messageInterval);
      messageInterval = setInterval(loadMessages, 3000);
    }

    // Load messages
    function loadMessages() {
      if (!currentChatUser) return;
      let params = currentChatUser === 'group' ? 'group_id=1' : `receiver_id=${currentChatUser}`;
      fetch('chat_backend?' + params)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            renderMessages(data.messages || []);
          }
        })
        .catch(error => {
          userChatMessages.innerHTML = '<div class="text-danger">Failed to load messages.</div>';
        });
    }

    // Render messages
    function renderMessages(messages) {
      userChatMessages.innerHTML = '';
      messages.forEach(msg => {
        const isMe = msg.sender_name === '<?= addslashes($_SESSION['user'] ?? '') ?>';
        const messageDiv = document.createElement('div');
        messageDiv.className = `user-chat-message ${isMe ? 'user' : 'other'}`;
        const time = msg.created_at ? new Date(msg.created_at.replace(' ', 'T')) : null;
        const timeStr = time ? time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        messageDiv.innerHTML = `
        <div class="user-chat-message-avatar">${isMe ? '<?= addslashes($_SESSION['user'] ?? '') ?>'.charAt(0).toUpperCase() : msg.sender_name.charAt(0).toUpperCase()}</div>
        <div class="user-chat-message-content">
          ${isMe ? '' : `<strong>${msg.sender_name}:</strong> `}${msg.message || ''}
          <div class="user-chat-message-time">${timeStr}</div>
        </div>
      `;
        userChatMessages.appendChild(messageDiv);
      });
      userChatMessages.scrollTop = userChatMessages.scrollHeight;
    }

    // Send message
    function sendMessage() {
      const message = userChatInput.value.trim();
      if (!message || !currentChatUser) return;
      const formData = new FormData();
      formData.append('message', message);
      if (currentChatUser === 'group') {
        formData.append('group_id', '1');
      } else {
        formData.append('receiver_id', currentChatUser);
      }
      fetch('chat_backend', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            userChatInput.value = '';
            loadMessages();
          }
        })
        .catch(error => {
          // Optionally show error
        });
    }

    // Send message on button click
    userChatSend.addEventListener('click', sendMessage);
    // Send message on Enter key
    userChatInput.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') sendMessage();
    });

    // Back button for mobile
    const backButton = document.createElement('button');
    backButton.className = 'btn btn-sm btn-outline-secondary d-md-none';
    backButton.innerHTML = '← Back';
    backButton.className += ' back-button-inline';
    backButton.addEventListener('click', () => {
      userChatMain.classList.remove('show');
      currentChatUser = null;
      if (messageInterval) {
        clearInterval(messageInterval);
        messageInterval = null;
      }
    });
    userChatMessages.parentNode.insertBefore(backButton, userChatMessages);
    backButton.style.display = 'none';
    const observer = new MutationObserver(() => {
      backButton.style.display = userChatMain.classList.contains('show') ? 'block' : 'none';
    });
    observer.observe(userChatMain, { attributes: true, attributeFilter: ['class'] });

    // --- Chat Notification Badge ---
    function pollNotifications() {
      fetch('chat_backend?action=unread_count')
        .then(r => r.json())
        .then(data => {
          if (data.success && data.unread > 0) {
            userChatNotification.textContent = data.unread;
            userChatNotification.style.display = 'block';
          } else {
            userChatNotification.style.display = 'none';
          }
        });
    }
    pollNotifications();
    notificationInterval = setInterval(pollNotifications, 5000);
  });

  // --- Navbar Chat Notification Bell with Dropdown (advanced) ---
  document.addEventListener('DOMContentLoaded', function () {
    var navbarChatBell = document.getElementById('navbarChatBell');
    var navbarChatBadge = document.getElementById('navbarChatBadge');
    var userChatIcon = document.getElementById('userChatIcon');
    var userChatModal = document.getElementById('userChatModal');
    var navbarChatDropdown = document.getElementById('navbarChatDropdown');
    var navbarChatDropdownContent = document.getElementById('navbarChatDropdownContent');
    var navbarChatDropdownFooter = document.getElementById('navbarChatDropdownFooter');

    // Position dropdown under bell
    function positionDropdown() {
      if (!navbarChatBell || !navbarChatDropdown) return;
      var rect = navbarChatBell.getBoundingClientRect();
      navbarChatDropdown.style.top = (rect.bottom + window.scrollY + 8) + 'px';
      navbarChatDropdown.style.right = (window.innerWidth - rect.right - 8) + 'px';
    }

    // Mark all messages as read (optionally for a sender)
    function markAllAsRead(senderId) {
      var form = new FormData();
      if (senderId) form.append('sender_id', senderId);
      fetch('chat_backend?action=mark_read', { method: 'POST', body: form })
        .then(() => {
          updateNavbarChatBadge();
          pollNotifications && pollNotifications();
        });
    }

    // Fetch and show recent unread messages
    function showDropdown() {
      positionDropdown();
      navbarChatDropdown.style.display = 'block';
      navbarChatDropdownContent.innerHTML = '<div class="text-center py-3 text-muted">Loading...</div>';
      fetch('chat_backend?action=recent_unread')
        .then(r => r.json())
        .then(data => {
          if (data.error) {
            navbarChatDropdownContent.innerHTML = '<div class="text-danger text-center py-3">Error: ' + data.error + '</div>';
            return;
          }
          if (data.success && data.messages.length > 0) {
            navbarChatDropdownContent.innerHTML = '';
            data.messages.forEach(function (msg) {
              var preview = msg.message.length > 40 ? msg.message.substring(0, 40) + '…' : msg.message;
              var time = new Date(msg.created_at.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
              var item = document.createElement('div');
              item.className = 'dropdown-item d-flex align-items-start gap-2 px-3 py-2 navbar-chat-item';
              item.innerHTML = `
              <div class="navbar-chat-avatar">${msg.sender_name.charAt(0).toUpperCase()}</div>
              <div class="navbar-chat-content">
                <div class="navbar-chat-name">${msg.sender_name}</div>
                <div class="navbar-chat-preview">${preview}</div>
              </div>
              <div class="navbar-chat-time">${time}</div>
            `;
              item.onclick = function () {
                markAllAsRead(msg.sender_id);
                if (userChatModal) userChatModal.classList.add('show');
                if (userChatIcon) userChatIcon.click();
                // Try to select the sender in the chat modal
                setTimeout(function () {
                  var contacts = document.querySelectorAll('.user-chat-contact');
                  contacts.forEach(function (c) {
                    if (c.textContent.includes(msg.sender_name)) c.click();
                  });
                }, 300);
                navbarChatDropdown.style.display = 'none';
              };
              navbarChatDropdownContent.appendChild(item);
            });
          } else if (data.success && (!data.messages || data.messages.length === 0)) {
            navbarChatDropdownContent.innerHTML = '<div class="text-center py-3 text-muted">No new messages</div>';
          } else {
            navbarChatDropdownContent.innerHTML = '<div class="text-danger text-center py-3">Malformed response</div>';
          }
        })
        .catch(error => {
          navbarChatDropdownContent.innerHTML = '<div class="text-danger text-center py-3">Fetch error: ' + error.message + '</div>';
        });
    }

    // Fetch and show all messages (read or unread)
    function showAllMessages() {
      positionDropdown();
      navbarChatDropdown.style.display = 'block';
      navbarChatDropdownContent.innerHTML = '<div class="text-center py-3 text-muted">Loading...</div>';
      fetch('chat_backend?action=all_messages_preview')
        .then(r => r.json())
        .then(data => {
          if (data.success && data.messages.length > 0) {
            navbarChatDropdownContent.innerHTML = '';
            data.messages.forEach(function (msg) {
              var preview = msg.message.length > 40 ? msg.message.substring(0, 40) + '…' : msg.message;
              var time = new Date(msg.created_at.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
              var item = document.createElement('div');
              item.className = 'dropdown-item d-flex align-items-start gap-2 px-3 py-2 navbar-chat-item';
              if (!msg.is_read || msg.is_read === '0') {
                item.classList.add('navbar-chat-unread');
              }
              item.innerHTML = `
              <div class="navbar-chat-avatar">${msg.sender_name.charAt(0).toUpperCase()}</div>
              <div class="navbar-chat-content">
                <div class="navbar-chat-name">${msg.sender_name}</div>
                <div class="navbar-chat-preview">${preview}</div>
              </div>
              <div class="navbar-chat-time">${time}</div>
            `;
              item.onclick = function () {
                markAllAsRead(msg.sender_id);
                if (userChatModal) userChatModal.classList.add('show');
                if (userChatIcon) userChatIcon.click();
                setTimeout(function () {
                  var contacts = document.querySelectorAll('.user-chat-contact');
                  contacts.forEach(function (c) {
                    if (c.textContent.includes(msg.sender_name)) c.click();
                  });
                }, 300);
                navbarChatDropdown.style.display = 'none';
              };
              navbarChatDropdownContent.appendChild(item);
            });
          } else {
            navbarChatDropdownContent.innerHTML = '<div class="text-center py-3 text-muted">No messages</div>';
          }
        });
      // Mark all as read when viewing all
      markAllAsRead();
    }

    // Hide dropdown on outside click
    document.addEventListener('mousedown', function (e) {
      if (navbarChatDropdown && !navbarChatDropdown.contains(e.target) && e.target !== navbarChatBell) {
        navbarChatDropdown.style.display = 'none';
      }
    });

    // Update badge as before
    function updateNavbarChatBadge() {
      fetch('chat_backend?action=unread_count')
        .then(r => r.json())
        .then(data => {
          if (data.success && data.unread > 0) {
            navbarChatBadge.textContent = data.unread;
            navbarChatBadge.style.display = 'block';
          } else {
            navbarChatBadge.style.display = 'none';
          }
        });
    }
    if (navbarChatBell && navbarChatBadge) {
      updateNavbarChatBadge();
      setInterval(updateNavbarChatBadge, 5000);
      navbarChatBell.onclick = function (e) {
        e.stopPropagation();
        if (navbarChatDropdown.style.display === 'block') {
          navbarChatDropdown.style.display = 'none';
        } else {
          showDropdown();
        }
        navbarChatBadge.style.display = 'none';
      };
      if (navbarChatDropdownFooter) {
        navbarChatDropdownFooter.onclick = function () {
          showAllMessages();
        };
      }
    }

    // Mark all as read when chat modal is opened
    if (userChatIcon) {
      userChatIcon.addEventListener('click', function () {
        markAllAsRead();
      });
    }
  });

  // Theme toggle logic
  function setTheme(dark) {
    if (dark) {
      document.body.classList.add('dark-mode');
      document.querySelectorAll('.footer').forEach(f => f.classList.add('dark-mode'));
      localStorage.setItem('theme', 'dark');
      document.getElementById('themeToggle').innerHTML = '<i class="bi bi-sun"></i> Light Mode';
    } else {
      document.body.classList.remove('dark-mode');
      document.querySelectorAll('.footer').forEach(f => f.classList.remove('dark-mode'));
      localStorage.setItem('theme', 'light');
      document.getElementById('themeToggle').innerHTML = '<i class="bi bi-moon"></i> Dark Mode';
    }
  }
  document.addEventListener('DOMContentLoaded', function () {
    const themeToggle = document.getElementById('themeToggle');
    // Set initial theme
    const savedTheme = localStorage.getItem('theme');
    setTheme(savedTheme === 'dark');
    themeToggle.addEventListener('click', function () {
      setTheme(!document.body.classList.contains('dark-mode'));
    });
  });
</script>