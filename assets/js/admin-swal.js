// ── SweetAlert2 global injection ──
(function() {
  'use strict';

  // Only run once
  if (window.__adminSwalLoaded) return;
  window.__adminSwalLoaded = true;

  // Inject SweetAlert2 CDN if not present
  if (typeof Swal === 'undefined') {
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    s.onload = init;
    document.head.appendChild(s);
  } else {
    init();
  }

  function init() {
    // Override global alert
    window.alert = function(msg) {
      Swal.fire({
        title: 'Message',
        text: msg,
        icon: 'info',
        confirmButtonColor: '#198754'
      });
    };

    // Intercept data-swal-confirm elements
    document.addEventListener('click', function(e) {
      var el = e.target.closest('[data-swal-confirm]');
      if (!el) return;
      e.preventDefault();
      var msg = el.getAttribute('data-swal-confirm') || 'Are you sure?';
      var form = el.closest('form');
      Swal.fire({
        title: 'Are you sure?',
        text: msg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, proceed'
      }).then(function(result) {
        if (result.isConfirmed) {
          if (form) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = el.name || el.getAttribute('data-name') || 'submit';
            hidden.value = el.value || '1';
            form.appendChild(hidden);
            HTMLFormElement.prototype.submit.call(form);
          } else if (el.tagName === 'A' && el.href) {
            window.location.href = el.href;
          } else {
            el.click();
          }
        }
      });
    });

    // Intercept forms with data-confirm via SweetAlert (enhancement over native confirm)
    document.addEventListener('submit', function(e) {
      var form = e.target;
      if (form.hasAttribute('data-swal')) {
        e.preventDefault();
        var msg = form.getAttribute('data-swal') || 'Are you sure?';
        Swal.fire({
          title: 'Are you sure?',
          text: msg,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#198754',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, proceed'
        }).then(function(result) {
          if (result.isConfirmed) {
            HTMLFormElement.prototype.submit.call(form);
          }
        });
      }
    });
  }

})();
