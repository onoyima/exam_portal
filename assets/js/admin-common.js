(function() {
  'use strict';

  // ── Sidebar toggle (handles both desktop collapse and mobile slide) ──
  function initSidebar() {
    var sidebar = document.getElementById('sidebarMenu');
    var backdrop = document.getElementById('sidebarBackdrop');
    var mainContent = document.querySelector('.main-content');
    var toggle = document.querySelector('.admin-navbar-toggle, .sidebar-navbar-toggle, #sidebarToggle');
    if (!sidebar) return;
    if (toggle) {
      toggle.addEventListener('click', function() {
        if (window.innerWidth < 992) {
          sidebar.classList.toggle('show');
          if (backdrop) backdrop.classList.toggle('show');
          document.body.classList.toggle('sidebar-open');
        } else {
          sidebar.classList.toggle('collapsed');
          if (mainContent) mainContent.classList.toggle('sidebar-collapsed');
          try { localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed')); } catch(e) {}
        }
      });
    }
    if (backdrop) {
      backdrop.addEventListener('click', function() {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
        document.body.classList.remove('sidebar-open');
      });
    }
    // Restore desktop collapsed state
    if (window.innerWidth >= 992) {
      try {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
          sidebar.classList.add('collapsed');
          if (mainContent) mainContent.classList.add('sidebar-collapsed');
        }
      } catch(e) {}
    }
  }

  // ── data-confirm handler ──
  function initConfirmHandler() {
    document.addEventListener('submit', function(e) {
      var form = e.target;
      if (form.hasAttribute('data-confirm')) {
        e.preventDefault();
        var msg = form.getAttribute('data-confirm') || 'Are you sure?';
        if (confirm(msg)) {
          HTMLFormElement.prototype.submit.call(form);
        }
      }
    });
  }

  // ── Intercept inline onsubmit="return confirm(...)" patterns ──
  function initLegacyConfirmInterceptor() {
    document.querySelectorAll('form[onsubmit*="return confirm"]').forEach(function(el) {
      var match = el.getAttribute('onsubmit').match(/return confirm\(['"`](.*?)['"`]\)/);
      if (match) {
        var msg = match[1];
        el.removeAttribute('onsubmit');
        el.addEventListener('submit', function(e) {
          e.preventDefault();
          Swal.fire({
            title: 'Are you sure?',
            text: msg,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#092c19',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, proceed'
          }).then(function(result) {
            if (result.isConfirmed) {
              var btn = el.querySelector('button[type="submit"]:hover, input[type="submit"]:hover') || el.querySelector('button[type="submit"], input[type="submit"]');
              if (btn && btn.name) {
                var hidden = document.createElement('input');
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
    document.querySelectorAll('a[onclick*="return confirm"], button[onclick*="return confirm"]').forEach(function(el) {
      var match = el.getAttribute('onclick').match(/return confirm\(['"`](.*?)['"`]\)/);
      if (match) {
        var msg = match[1];
        el.removeAttribute('onclick');
        el.addEventListener('click', function(e) {
          e.preventDefault();
          Swal.fire({
            title: 'Are you sure?',
            text: msg,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#092c19',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, proceed'
          }).then(function(result) {
            if (result.isConfirmed) {
              if (el.tagName === 'A' && el.href) {
                window.location.href = el.href;
              } else if (el.tagName === 'BUTTON') {
                if (el.type === 'submit' && el.form) {
                  if (el.name) {
                    var hidden = document.createElement('input');
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
  }

  // ── Loading spinner on form submit ──
  function initSpinner() {
    document.addEventListener('submit', function(e) {
      var form = e.target;
      var btn = form.querySelector('button[type="submit"]');
      if (!btn || btn.disabled) return;
      if (btn.innerHTML.indexOf('btn-spinner') > -1) return;
      if (form.hasAttribute('data-confirm')) return;
      if (btn.name) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = btn.name;
        hidden.value = btn.value || '1';
        form.appendChild(hidden);
      }
      btn.disabled = true;
      btn.classList.add('btn-loading');
      btn.innerHTML = '<span class="btn-spinner"></span> ' + (btn.textContent || btn.value || 'Processing...');
    });
  }

  // ── Init on DOM ready ──
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initSidebar();
      initConfirmHandler();
      initSpinner();
      initLegacyConfirmInterceptor();
    });
  } else {
    initSidebar();
    initConfirmHandler();
    initSpinner();
    initLegacyConfirmInterceptor();
  }

})();
