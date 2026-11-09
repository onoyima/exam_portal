/* Live monitor wall. Polls admin_live_proctor?action=wall and keeps the tiles current. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var wall = $('lmWall');
  var state = { exam: $('lmExam').value, view: 'live', search: '', tiles: [], open: null };
  var STATE_LABEL = { writing: 'Writing', away: 'Away', offline: 'No signal', locked: 'Locked', submitted: 'Submitted' };
  var rank = function (t) { return t.alert ? 0 : t.state === 'locked' ? 1 : t.state === 'away' ? 2 : t.state === 'offline' ? 3 : t.state === 'writing' ? 4 : 5; };

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function mins(s) { if (s == null) return ''; var m = Math.ceil(s / 60); return m >= 60 ? Math.floor(m / 60) + 'h ' + (m % 60) + 'm' : m + ' min'; }
  function initials(n) { return String(n).split(/\s+/).filter(Boolean).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase(); }

  function toast(msg) {
    var t = $('lmToast'); t.textContent = msg; t.hidden = false;
    clearTimeout(toast.h); toast.h = setTimeout(function () { t.hidden = true; }, 2600);
  }

  function post(action, data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fd.append('csrf_token', LM.csrf);
    return fetch('admin_live_proctor?action=' + action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.csrf_expired) { LM.csrf = j.csrf_token; return post(action, data); } return j; });
  }

  /* ---------- wall ---------- */

  function tileHtml(t) {
    var media = t.snap ? '<img src="' + esc(t.snap) + '" alt="" loading="lazy">' :
      '<div class="lm-nocam"><span>' + esc(initials(t.name)) + '</span>No camera image yet</div>';
    var pct = t.total ? Math.round(t.answered / t.total * 100) : 0;
    var right = t.state === 'submitted' ? 'Submitted' : (t.left != null ? mins(t.left) + ' left' : '');
    return media +
      '<div class="lm-tile-top"><span class="lm-state"><i></i>' + STATE_LABEL[t.state] + (t.live ? ' · live' : '') + '</span>' +
      '<span><span class="lm-strikes' + (t.violations ? '' : ' is-zero') + '" title="Security warnings">' + t.violations + ' ⚑</span>' + (t.rec ? '<span class="lm-rec" title="Recording saved">●</span>' : '') + '</span></div>' +
      (t.alert && t.last_violation ? '<div class="lm-alert-text">' + esc(t.last_violation) + '</div>' : '') +
      '<div class="lm-tile-foot"><div class="lm-tile-name">' + esc(t.name) + '</div>' +
      '<div class="lm-tile-sub"><span class="lm-mat">' + esc(t.matric) + '</span><span>' + esc(right) + '</span></div>' +
      (t.state !== 'submitted' && t.total ? '<div class="lm-bar-progress" title="' + t.answered + ' of ' + t.total + ' answered"><b style="width:' + pct + '%"></b></div>' : '') +
      '</div>';
  }

  function render() {
    var tiles = state.tiles.slice();
    if ($('lmAlertsFirst').checked) tiles.sort(function (a, b) { return rank(a) - rank(b) || a.name.localeCompare(b.name); });
    var seen = {};
    tiles.forEach(function (t, i) {
      seen[t.id] = true;
      var el = document.getElementById('tile-' + t.id);
      var key = JSON.stringify(t);
      if (!el) {
        el = document.createElement('div');
        el.id = 'tile-' + t.id;
        el.tabIndex = 0;
        el.addEventListener('click', function () { openSpot(t.id); });
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter') openSpot(t.id); });
      }
      if (el.getAttribute('data-key') !== key) {
        el.className = 'lm-tile is-' + t.state + (t.alert ? ' is-alert' : '');
        el.innerHTML = tileHtml(t);
        el.setAttribute('data-key', key);
        el.title = t.name + ' — ' + t.exam + ' (attempt ' + t.attempt + ')';
      }
      if (wall.children[i] !== el) wall.insertBefore(el, wall.children[i] || null);
    });
    Array.prototype.slice.call(wall.children).forEach(function (el) {
      if (!seen[el.id.replace('tile-', '')]) el.remove();
    });
    $('lmEmpty').hidden = tiles.length > 0;

    var c = { writing: 0, away: 0, offline: 0, locked: 0, submitted: 0, alert: 0 };
    state.tiles.forEach(function (t) { c[t.state]++; if (t.alert) c.alert++; });
    $('lmCounts').innerHTML =
      '<span class="lm-count"><b>' + c.writing + '</b>writing</span>' +
      (c.alert ? '<span class="lm-count is-alert"><b>' + c.alert + '</b>new alerts</span>' : '') +
      (c.away ? '<span class="lm-count is-away"><b>' + c.away + '</b>away</span>' : '') +
      (c.offline ? '<span class="lm-count"><b>' + c.offline + '</b>no signal</span>' : '') +
      (c.locked ? '<span class="lm-count is-alert"><b>' + c.locked + '</b>locked</span>' : '') +
      (c.submitted ? '<span class="lm-count"><b>' + c.submitted + '</b>submitted</span>' : '');
    if (state.open) fillSpot();
  }

  function load() {
    var q = 'admin_live_proctor?action=wall&exam_id=' + state.exam + '&view=' + state.view + '&search=' + encodeURIComponent(state.search);
    return fetch(q, { headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.success) return;
        state.tiles = d.tiles;
        $('lmClock').textContent = 'Updated ' + d.time;
        render();
      }).catch(function () { $('lmClock').textContent = 'Connection lost — retrying'; });
  }

  function syncTools() {
    var on = state.exam !== '0';
    $('lmSubmitAll').hidden = !on || state.view === 'finished';
    $('lmDownloadAll').hidden = !on;
    $('lmDownloadAll').href = 'admin_live_proctor?action=download_all_recordings&exam_id=' + state.exam;
    var url = new URL(location.href);
    url.searchParams.set('exam_id', state.exam);
    history.replaceState(null, '', url);
  }

  $('lmExam').addEventListener('change', function () { state.exam = this.value; wall.innerHTML = ''; syncTools(); load(); });
  document.querySelectorAll('#lmView button').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('#lmView button').forEach(function (x) { x.classList.remove('is-on'); });
      b.classList.add('is-on'); state.view = b.getAttribute('data-view'); wall.innerHTML = ''; syncTools(); load();
    });
  });
  document.querySelectorAll('#lmSize button').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('#lmSize button').forEach(function (x) { x.classList.remove('is-on'); });
      b.classList.add('is-on');
      wall.className = 'lm-wall lm-size-' + b.getAttribute('data-size');
      try { localStorage.setItem('lmSize', b.getAttribute('data-size')); } catch (e) {}
    });
  });
  try { var sz = localStorage.getItem('lmSize'); if (sz) document.querySelector('#lmSize [data-size="' + sz + '"]').click(); } catch (e) {}
  var searchTimer;
  $('lmSearch').addEventListener('input', function () { var v = this.value; clearTimeout(searchTimer); searchTimer = setTimeout(function () { state.search = v; load(); }, 300); });
  $('lmAlertsFirst').addEventListener('change', render);
  $('lmSubmitAll').addEventListener('click', function () {
    var n = state.tiles.filter(function (t) { return t.state !== 'submitted'; }).length;
    Swal.fire({ title: 'Submit for everyone?', text: 'This submits the exam for all ' + n + ' students still writing it.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#b3261e', confirmButtonText: 'Submit all' })
      .then(function (r) {
        if (!r.isConfirmed) return;
        post('force_submit_all', { exam_id: state.exam }).then(function (d) { toast((d.count || 0) + ' attempts submitted'); load(); });
      });
  });

  /* ---------- spotlight ---------- */

  function current() { return state.tiles.filter(function (t) { return t.id === state.open; })[0]; }

  function openSpot(id) {
    state.open = id;
    $('lmSpot').hidden = false;
    $('spPlayer').hidden = true;
    $('spPlayer').removeAttribute('src');
    fillSpot();
    loadRecs();
  }
  function closeSpot() {
    if (state.open && peers[state.open]) stopLive(state.open);
    state.open = null;
    $('lmSpot').hidden = true;
    $('spPlayer').pause();
  }
  $('spClose').onclick = closeSpot;
  $('lmSpot').addEventListener('click', function (e) { if (e.target === this) closeSpot(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('lmSpot').hidden) closeSpot(); });

  function fillSpot() {
    var t = current();
    if (!t) return;
    $('spName').textContent = t.name;
    $('spMatric').textContent = t.matric;
    if (!peers[t.id]) {
      $('spImg').hidden = !t.snap;
      if (t.snap && $('spImg').getAttribute('src') !== t.snap) $('spImg').src = t.snap;
    }
    var rows = [
      ['Exam', t.exam], ['Attempt', '#' + t.attempt], ['Status', STATE_LABEL[t.state] + (t.state === 'away' || t.state === 'offline' ? ' (' + t.idle + 's since last signal)' : '')],
      ['Started', t.started], ['Answered', t.total ? t.answered + ' of ' + t.total : '—'],
      ['Time left', t.state === 'submitted' ? 'Submitted' : (t.left != null ? mins(t.left) : 'No limit')],
      ['Warnings', t.violations + (t.last_violation ? ' (last: ' + t.last_violation + ')' : '')]
    ];
    $('spFacts').innerHTML = rows.map(function (r) { return '<dt>' + r[0] + '</dt><dd>' + esc(r[1]) + '</dd>'; }).join('');
    var done = t.state === 'submitted';
    ['spStream', 'spWarn', 'spTag', 'spSubmit', 'spRelease'].forEach(function (id) { $(id).disabled = done; });
    $('spUnlock').disabled = t.state !== 'locked';
    $('spStream').innerHTML = peers[t.id] ? '<i class="bi bi-stop-circle"></i> Stop live view' : '<i class="bi bi-camera-video"></i> Live view';
  }

  function act(action, confirmText, extra, done) {
    var t = current(); if (!t) return;
    var go = function (more) {
      post(action, Object.assign({ attempt_id: t.id }, extra || {}, more || {})).then(function (d) {
        if (d.success) { toast(done); load(); } else toast(d.error || 'Could not complete that');
      });
    };
    if (!confirmText) return go();
    Swal.fire({ title: confirmText, icon: 'warning', showCancelButton: true, confirmButtonColor: '#1f6b4a', confirmButtonText: 'Yes' })
      .then(function (r) { if (r.isConfirmed) go(); });
  }
  $('spWarn').onclick = function () {
    var t = current();
    Swal.fire({ title: 'Warn ' + t.name, input: 'textarea', inputValue: 'Please keep your eyes on your own screen.', showCancelButton: true, confirmButtonText: 'Send warning', confirmButtonColor: '#1f6b4a' })
      .then(function (r) { if (r.isConfirmed) post('warn', { attempt_id: t.id, message: r.value }).then(function () { toast('Warning sent to ' + t.name); }); });
  };
  $('spTag').onclick = function () {
    var t = current();
    Swal.fire({ title: 'Tag a violation', input: 'text', inputPlaceholder: 'What did you see?', showCancelButton: true, confirmButtonText: 'Record', confirmButtonColor: '#b3261e' })
      .then(function (r) { if (r.isConfirmed) post('violation', { attempt_id: t.id, reason: r.value || '' }).then(function () { toast('Violation recorded'); load(); }); });
  };
  $('spUnlock').onclick = function () { act('unlock', 'Unlock this exam and reset the warnings?', null, 'Unlocked — the student can continue'); };
  $('spRelease').onclick = function () { act('release', 'Let this student sign in on another computer now? Their remaining time is kept.', null, 'The student can now sign in on another computer'); };
  $('spSubmit').onclick = function () { act('force_submit', 'Submit this exam for the student now?', null, 'Exam submitted'); };

  /* ---------- recordings ---------- */

  function loadRecs() {
    var t = current(); if (!t) return;
    $('spRecs').innerHTML = '<span style="color:#7d9489">Loading…</span>';
    fetch('admin_live_proctor?action=recordings&attempt_id=' + t.id, { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.files || !d.files.length) { $('spRecs').innerHTML = '<span style="color:#7d9489">No recording yet.</span>'; return; }
        $('spRecs').innerHTML = d.files.map(function (f, i) {
          return '<div class="lm-rec-row"><span><b>Part ' + (i + 1) + '</b> · ' + esc(f.who) + '<br><small>' + esc(f.started) + ' – ' + esc(f.ended) + ' · ' + f.minutes + ' min · ' + f.size + ' MB</small></span>' +
            '<button type="button" class="lm-btn" data-play="' + esc(f.file) + '"><i class="bi bi-play-fill"></i> Play</button></div>';
        }).join('') + '<a class="lm-btn" style="margin-top:4px" href="admin_live_proctor?action=download_recording&attempt_id=' + t.id + '"><i class="bi bi-download"></i> Download all parts</a>';
      });
  }
  $('spRecs').addEventListener('click', function (e) {
    var b = e.target.closest('[data-play]'); if (!b) return;
    var p = $('spPlayer');
    p.hidden = false;
    p.src = 'admin_live_proctor?action=recording_file&attempt_id=' + state.open + '&file=' + encodeURIComponent(b.getAttribute('data-play'));
    p.play().catch(function () {});
  });

  /* ---------- live view (WebRTC) ---------- */

  var peers = {}, polls = {}, lastSig = {}, recorders = {}, mic = {};
  var rtc = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };

  function signal(id, type, payload) {
    var fd = new FormData();
    fd.append('attempt_id', id); fd.append('type', type); fd.append('payload', payload); fd.append('csrf_token', LM.csrf);
    return fetch('webrtc_api?action=send', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
  }

  function startLive(id) {
    $('spStream').disabled = true;
    $('spStream').textContent = 'Connecting…';
    fetch('webrtc_api?action=clear&attempt_id=' + id).then(function () {
      return fetch('webrtc_api?action=request_live&attempt_id=' + id);
    }).then(function () {
      var pc = new RTCPeerConnection(rtc);
      peers[id] = pc;
      lastSig[id] = 0;
      pc.addTransceiver('video', { direction: 'recvonly' });
      pc.addTransceiver('audio', { direction: 'recvonly' });
      pc.onicecandidate = function (e) { if (e.candidate) signal(id, 'ice', JSON.stringify(e.candidate)); };
      pc.ontrack = function (e) {
        if (state.open !== id) return;
        var v = $('spVideo');
        v.srcObject = e.streams[0];
        v.hidden = false;
        $('spImg').hidden = true;
        $('spLive').hidden = false;
        $('spSpeak').disabled = false;
        $('spStream').disabled = false;
        fillSpot();
        recordLive(id, e.streams[0]);
      };
      return pc.createOffer().then(function (o) { return pc.setLocalDescription(o).then(function () { return signal(id, 'offer', JSON.stringify(o)); }); });
    }).then(function () {
      var tries = 0;
      polls[id] = setInterval(function () {
        if (++tries > 40 && $('spLive').hidden) { stopLive(id); toast('The student\'s computer did not answer. Is the camera allowed?'); return; }
        fetch('webrtc_api?action=get&attempt_id=' + id + '&last_id=' + lastSig[id]).then(function (r) { return r.json(); }).then(function (d) {
          (d.signals || []).forEach(function (s) {
            lastSig[id] = s.id;
            var p = JSON.parse(s.payload), pc = peers[id];
            if (!pc) return;
            if (s.type === 'answer') pc.setRemoteDescription(new RTCSessionDescription(p)).catch(function () {});
            else if (s.type === 'ice') pc.addIceCandidate(new RTCIceCandidate(p)).catch(function () {});
          });
        }).catch(function () {});
      }, 1000);
    }).catch(function () { stopLive(id); toast('Could not start live view'); });
  }

  function stopLive(id) {
    clearInterval(polls[id]);
    if (recorders[id]) { try { recorders[id].stop(); } catch (e) {} delete recorders[id]; }
    if (mic[id]) { mic[id].getTracks().forEach(function (t) { t.stop(); }); delete mic[id]; }
    if (peers[id]) { peers[id].close(); delete peers[id]; }
    fetch('webrtc_api?action=stop_live&attempt_id=' + id);
    var v = $('spVideo'); v.srcObject = null; v.hidden = true;
    $('spLive').hidden = true;
    $('spImg').hidden = false;
    $('spSpeak').disabled = true;
    $('spSpeak').innerHTML = '<i class="bi bi-mic"></i> Speak';
    $('spStream').disabled = false;
    fillSpot();
  }

  // the proctor's own copy of what they watched, saved alongside the student's recording
  function recordLive(id, stream) {
    if (!window.MediaRecorder) return;
    try {
      var r = new MediaRecorder(stream, { mimeType: 'video/webm' });
      var segment = String(Date.now()), seq = 0, queue = Promise.resolve();
      r.ondataavailable = function (e) {
        if (!e.data || !e.data.size) return;
        var blob = e.data, n = seq++;
        queue = queue.then(function () {
          var fd = new FormData();
          fd.append('attempt_id', id); fd.append('segment', segment); fd.append('seq', n); fd.append('chunk', blob, 'part.webm');
          return fetch('upload_recording?action=chunk', { method: 'POST', body: fd }).catch(function () {});
        });
      };
      r.start(5000);
      recorders[id] = r;
    } catch (e) {}
  }

  $('spStream').onclick = function () { var id = state.open; if (peers[id]) stopLive(id); else startLive(id); };
  $('spSpeak').onclick = function () {
    var id = state.open, pc = peers[id];
    if (!pc) return;
    if (mic[id]) {
      mic[id].getTracks().forEach(function (t) { t.stop(); }); delete mic[id];
      $('spSpeak').innerHTML = '<i class="bi bi-mic"></i> Speak';
      return;
    }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
      mic[id] = s;
      s.getAudioTracks().forEach(function (tr) { pc.addTrack(tr, s); });
      return pc.createOffer().then(function (o) { return pc.setLocalDescription(o).then(function () { return signal(id, 'offer', JSON.stringify(o)); }); });
    }).then(function () { $('spSpeak').innerHTML = '<i class="bi bi-mic-mute"></i> Stop speaking'; })
      .catch(function () { toast('Microphone not available'); });
  };

  syncTools();
  load();
  setInterval(function () { if (!document.hidden) load(); }, 5000);
})();
