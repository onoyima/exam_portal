// ── SweetAlert2 global injection ──
(function() {
  'use strict';

  // Only run once
  if (window.__adminSwalLoaded) return;
  window.__adminSwalLoaded = true;

  // Load the bundled copy first (exam halls often have no internet), then the CDN.
  if (typeof Swal === 'undefined') {
    var here = (document.currentScript && document.currentScript.src) || '';
    var local = here ? here.replace(/admin-swal\.js(\?.*)?$/, 'sweetalert2.all.min.js') : 'assets/js/sweetalert2.all.min.js';
    var s = document.createElement('script');
    s.src = local;
    s.onload = init;
    s.onerror = function () {
      var cdn = document.createElement('script');
      cdn.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
      cdn.onload = init;
      document.head.appendChild(cdn);
    };
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
        // form.submit() does not send the clicked button, and most handlers
        // check for it (isset($_POST['delete'])), so carry it across by hand.
        var btn = e.submitter;
        var old = form.querySelector('input[data-swal-submitter]');
        if (old) old.remove();
        if (btn && btn.name) {
          var carry = document.createElement('input');
          carry.type = 'hidden';
          carry.name = btn.name;
          carry.value = btn.value || '1';
          carry.setAttribute('data-swal-submitter', '1');
          form.appendChild(carry);
        }
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
