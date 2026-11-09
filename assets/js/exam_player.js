/*
 * Objective exam player.
 *
 * Reads window.EXAM_CONFIG (built by student_objective_exam.php) and drives:
 *   - rendering of single / multiple answer, true-false and multiple true-false questions
 *   - fitting each question inside the viewport so the student never scrolls
 *   - answer autosave with an offline queue (localStorage) and batched requests
 *   - the countdown, re-synced with the server's used-time counter
 *   - browser lockdown, violation reporting, webcam evidence and live view
 */
(function () {
  'use strict';

  var C = window.EXAM_CONFIG;
  if (!C) return;

  var Q = C.questions;
  var N = Q.length;
  var REVIEW = !!C.review;
  var policy = C.policy || {};
  var $ = function (id) { return document.getElementById(id); };

  var TYPE_LABEL = {
    mcq_single: 'Single answer',
    mcq_multi: 'Multiple answer',
    true_false: 'True / False',
    mtf: 'Multiple True/False'
  };
  var INSTRUCTION = {
    mcq_single: 'Choose <strong>one</strong> answer.',
    mcq_multi: 'Choose <strong>all</strong> the answers that apply.',
    true_false: 'Choose True or False.',
    mtf: 'Choose True or False for <strong>each</strong> statement.'
  };

  // Globals other scripts already rely on (ai_proctor.js)
  window.examId = C.examId;
  window.currentAttemptNo = C.attemptNo;
  window.isReviewMode = REVIEW;
  window.pWebcam = !!policy.webcam;
  window.isLocked = false;
  window.isWebcamReady = false;

  /* ------------------------------------------------------------------ *
   *  local storage (per attempt) — survives refresh, crash, power cut
   * ------------------------------------------------------------------ */

  var STORE_KEY = 'xp:' + C.attemptId;
  var store = {
    read: function () {
      try { return JSON.parse(localStorage.getItem(STORE_KEY) || '{}') || {}; } catch (e) { return {}; }
    },
    write: function (data) {
      try { localStorage.setItem(STORE_KEY, JSON.stringify(data)); } catch (e) { /* private mode / full */ }
    }
  };

  var saved = store.read();
  var answers = {};
  Object.keys(C.answers || {}).forEach(function (k) { answers[k] = String(C.answers[k] || ''); });
  var pending = REVIEW ? {} : (saved.pending || {});
  Object.keys(pending).forEach(function (k) { answers[k] = pending[k]; });
  var flags = {};
  (saved.flags || []).forEach(function (id) { flags[id] = true; });
  var index = Math.min(Math.max(parseInt(saved.index, 10) || 0, 0), N - 1);

  var ZOOM_STEPS = [0.85, 1, 1.15, 1.3, 1.5, 1.75];
  var zoom = 1;
  try { zoom = parseFloat(localStorage.getItem(C.studentKey || 'xpz')) || 1; } catch (e) {}
  if (ZOOM_STEPS.indexOf(zoom) === -1) zoom = 1;

  function persist() {
    if (REVIEW) return;
    store.write({ pending: pending, flags: Object.keys(flags), index: index });
  }

  /* ------------------------------------------------------------------ *
   *  answer helpers
   * ------------------------------------------------------------------ */

  function blankMtf(q) { return new Array(q.count + 1).join('-'); }

  function answerState(q) {
    var a = answers[q.id] || '';
    if (q.type === 'mtf') {
      var marked = (a.match(/[TF]/g) || []).length;
      if (!marked) return 'empty';
      return marked < q.count ? 'partial' : 'answered';
    }
    return a ? 'answered' : 'empty';
  }

  function readAnswerFromDom(q) {
    var box = $('xpContent');
    if (q.type === 'mtf') {
      var chars = blankMtf(q).split('');
      box.querySelectorAll('input[data-stmt]:checked').forEach(function (i) {
        chars[parseInt(i.getAttribute('data-stmt'), 10)] = i.value;
      });
      var s = chars.join('');
      return /^-*$/.test(s) ? '' : s;
    }
    if (q.type === 'mcq_multi') {
      var picked = [];
      box.querySelectorAll('input[type=checkbox]:checked').forEach(function (i) { picked.push(i.value); });
      return picked.sort().join(',');
    }
    var one = box.querySelector('input[type=radio]:checked');
    return one ? one.value : '';
  }

  function setAnswer(q, value) {
    if (REVIEW || window.isLocked) return;
    answers[q.id] = value;
    pending[q.id] = value;
    persist();
    scheduleFlush();
    paintChoices(q);
    paintPalette();
  }

  /* ------------------------------------------------------------------ *
   *  rendering
   * ------------------------------------------------------------------ */

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function fmtMark(m) {
    var n = Math.round(m * 100) / 100;
    return n + (n === 1 ? ' mark' : ' marks');
  }

  function render(i) {
    if (i < 0 || i >= N) return;
    index = i;
    var q = Q[i];
    persist();

    $('xpQno').textContent = 'Question ' + (i + 1) + ' of ' + N;
    $('xpCounter').textContent = 'Question ' + (i + 1) + ' of ' + N;
    $('xpQtype').textContent = TYPE_LABEL[q.type] || '';
    $('xpQtype').className = 'xp-qtype is-' + q.type;
    $('xpQmark').textContent = fmtMark(q.mark);
    $('xpProgress').style.width = ((i + 1) / N * 100) + '%';

    var html = '<div class="xp-stem">' + q.text + '</div>';
    if (!REVIEW) {
      html += '<div class="xp-instr is-' + q.type + '">' + (INSTRUCTION[q.type] || '') +
        (q.type === 'mcq_multi' ? '<span class="xp-pickcount" id="xpPickCount"></span>' : '') +
        '</div>';
    }
    if (q.image) html += '<div class="xp-figure"><img src="' + esc(q.image) + '" alt=""></div>';

    var name = 'q' + q.id;
    if (q.type === 'mtf') {
      html += '<div class="xp-mtf" role="group">';
      html += '<div class="xp-mtf-head"><span></span><span>Statement</span><span class="xp-mtf-pick"><span>True</span><span>False</span></span></div>';
      q.statements.forEach(function (s, n) {
        var label = String.fromCharCode(97 + n);
        var gname = name + '_' + s.key;
        html += '<div class="xp-mtf-row" data-row="' + s.key + '">' +
          '<span class="xp-mtf-label">' + label + '</span>' +
          '<div class="xp-mtf-text">' + s.text + '<div class="xp-mtf-key" hidden></div></div>' +
          '<span class="xp-mtf-pick">' +
          '<label class="xp-tf is-true"><input type="radio" name="' + gname + '" value="T" data-stmt="' + s.key + '" aria-label="Statement ' + label + ' true"' + (REVIEW ? ' disabled' : '') + '>T</label>' +
          '<label class="xp-tf is-false"><input type="radio" name="' + gname + '" value="F" data-stmt="' + s.key + '" aria-label="Statement ' + label + ' false"' + (REVIEW ? ' disabled' : '') + '>F</label>' +
          '</span></div>';
      });
      html += '</div>';
    } else {
      var multi = q.type === 'mcq_multi';
      var tf = q.type === 'true_false';
      html += '<div class="xp-options' + (multi ? ' is-multi' : '') + (tf ? ' is-tf' : '') + '" role="' + (multi ? 'group' : 'radiogroup') + '">';
      q.options.forEach(function (o, n) {
        var letter = String.fromCharCode(65 + n);
        html += '<label class="xp-opt" data-key="' + o.key + '">' +
          '<input type="' + (multi ? 'checkbox' : 'radio') + '" name="' + name + '" value="' + o.key + '"' + (REVIEW ? ' disabled' : '') + '>' +
          (tf ? '' : '<span class="xp-opt-ctl" aria-hidden="true"></span><span class="xp-opt-key">' + letter + '</span>') +
          '<span class="xp-opt-text">' + o.text + (o.image ? '<img src="' + esc(o.image) + '" alt="">' : '') + '</span>' +
          '</label>';
      });
      html += '</div>';
    }
    if (REVIEW && C.result && C.result[q.id]) {
      var r = C.result[q.id];
      var words = { correct: 'Correct', partial: 'Partly correct', wrong: 'Incorrect', unanswered: 'Not answered' };
      html += '<div class="xp-verdict is-' + r.status + '">' + words[r.status] + ' · ' + (Math.round(r.awarded * 100) / 100) + ' of ' + fmtMark(q.mark) + '</div>';
    }

    var content = $('xpContent');
    content.innerHTML = html;
    paintChoices(q);
    if (REVIEW) paintReview(q);

    $('xpPrev').disabled = i === 0;
    var last = i === N - 1;
    $('xpNext').disabled = last;
    $('xpNext').innerHTML = last && !REVIEW ? 'Last question' : 'Next <span aria-hidden="true">&rarr;</span>';
    if ($('xpFlag')) {
      $('xpFlag').setAttribute('aria-pressed', flags[q.id] ? 'true' : 'false');
      $('xpFlag').textContent = flags[q.id] ? 'Flagged' : 'Flag';
    }

    paintPalette();
    fit();
    content.querySelectorAll('img').forEach(function (img) {
      if (!img.complete) img.addEventListener('load', fit, { once: true });
    });
    if (window.MathJax && MathJax.typesetPromise && /\$|\\\(|\\\[/.test(content.textContent)) {
      MathJax.typesetPromise([content]).then(fit).catch(function () {});
    }
  }

  function paintChoices(q) {
    var box = $('xpContent');
    var a = answers[q.id] || '';
    if (q.type === 'mtf') {
      box.querySelectorAll('input[data-stmt]').forEach(function (inp) {
        var on = a.charAt(parseInt(inp.getAttribute('data-stmt'), 10)) === inp.value;
        inp.checked = on;
        inp.parentNode.classList.toggle('is-checked', on);
      });
      box.querySelectorAll('.xp-mtf-row').forEach(function (row) {
        var ch = a.charAt(parseInt(row.getAttribute('data-row'), 10));
        row.classList.toggle('is-set', ch === 'T' || ch === 'F');
      });
      return;
    }
    var picked = q.type === 'mcq_multi' ? a.split(',') : [a];
    box.querySelectorAll('.xp-opt').forEach(function (lab) {
      var on = picked.indexOf(lab.getAttribute('data-key')) !== -1 && a !== '';
      lab.classList.toggle('is-on', on);
      lab.querySelector('input').checked = on;
    });
    var counter = $('xpPickCount');
    if (counter) {
      var n = picked.filter(function (k) { return k && a !== ''; }).length;
      counter.textContent = n + ' selected';
      counter.classList.toggle('is-set', n > 0);
    }
  }

  function paintReview(q) {
    var r = C.result && C.result[q.id];
    if (!r) return;
    var box = $('xpContent');
    if (q.type === 'mtf') {
      box.querySelectorAll('.xp-mtf-row').forEach(function (row) {
        var k = parseInt(row.getAttribute('data-row'), 10);
        var res = r.statements ? r.statements[k] : null;
        row.classList.add(res === true ? 'is-right' : 'is-wrong');
        var keyEl = row.querySelector('.xp-mtf-key');
        keyEl.textContent = 'Answer: ' + (r.key.charAt(k) === 'T' ? 'True' : 'False');
        keyEl.hidden = false;
      });
      return;
    }
    var keys = r.key.split(',');
    var mine = (answers[q.id] || '').split(',');
    box.querySelectorAll('.xp-opt').forEach(function (lab) {
      var k = lab.getAttribute('data-key');
      if (keys.indexOf(k) !== -1) lab.classList.add('is-key');
      else if (mine.indexOf(k) !== -1) lab.classList.add('is-miss');
    });
  }

  /* Shrink the question until it fits in the visible box. */
  var fitTimer = null;
  function fit() {
    var body = $('xpBody');
    var content = $('xpContent');
    var cs = getComputedStyle(body);
    var room = body.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom) - 2;
    content.style.setProperty('--zoom', zoom);
    var scale = 1;
    content.style.setProperty('--fit', scale);
    body.classList.remove('is-overflowing');
    var guard = 0;
    // A student who enlarged the text wants it large: scroll instead of shrinking.
    var floor = zoom > 1 ? 1 : 0.5;
    while (content.scrollHeight > room && scale > floor && guard++ < 25) {
      scale = Math.round((scale - 0.04) * 100) / 100;
      content.style.setProperty('--fit', scale);
    }
    if (content.scrollHeight > room) body.classList.add('is-overflowing');
  }
  document.addEventListener('xp-mathjax', function () { render(index); });
  window.addEventListener('resize', function () {
    clearTimeout(fitTimer);
    fitTimer = setTimeout(fit, 120);
  });

  function paintPalette() {
    var grid = $('xpGrid');
    if (!grid.children.length) {
      Q.forEach(function (q, i) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'xp-cell';
        b.textContent = i + 1;
        b.setAttribute('data-i', i);
        grid.appendChild(b);
      });
    }
    var answered = 0;
    Q.forEach(function (q, i) {
      var cell = grid.children[i];
      var st = answerState(q);
      if (st === 'answered') answered++;
      var cls = 'xp-cell';
      if (REVIEW && C.result && C.result[q.id]) {
        var rs = C.result[q.id].status;
        cls += rs === 'correct' ? ' is-correct' : rs === 'partial' ? ' is-partial' : ' is-wrong';
      } else if (st === 'answered') cls += ' is-answered';
      else if (st === 'partial') cls += ' is-partial';
      if (flags[q.id]) cls += ' is-flagged';
      if (i === index) cls += ' is-current';
      cell.className = cls;
      cell.title = 'Question ' + (i + 1) + (st === 'partial' ? ' (incomplete)' : '');
    });
    $('xpAnsweredCount').textContent = answered + ' / ' + N + ' answered';
  }

  /* ------------------------------------------------------------------ *
   *  saving
   * ------------------------------------------------------------------ */

  var flushTimer = null;
  var inFlight = false;
  var retryDelay = 2000;

  function setSync(state) {
    var el = $('xpSync');
    el.setAttribute('data-state', state);
    el.querySelector('span').textContent = state === 'saving' ? 'Saving…' : state === 'offline' ? 'Offline — answers kept on this computer' : 'All answers saved';
  }

  function post(url, data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fd.append('csrf_token', C.csrf);
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) {
        return r.text().then(function (t) {
          var j;
          try { j = JSON.parse(t); } catch (e) { throw new Error('bad-response'); }
          if (j && j.csrf_expired && j.csrf_token) {
            // session was renewed under us: take the new token and resend once
            C.csrf = j.csrf_token;
            var again = new FormData();
            Object.keys(data).forEach(function (k) { again.append(k, data[k]); });
            again.append('csrf_token', C.csrf);
            return fetch(url, { method: 'POST', body: again, credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'fetch' } })
              .then(function (r2) { return r2.json(); });
          }
          if (j && j.status === 'FORCED_LOGOUT') { handlePing(j); throw new Error('signed-out'); }
          return j;
        });
      });
  }

  function scheduleFlush(delay) {
    clearTimeout(flushTimer);
    flushTimer = setTimeout(flush, delay == null ? 600 : delay);
  }

  function flush() {
    if (REVIEW || inFlight) return Promise.resolve();
    var ids = Object.keys(pending);
    if (!ids.length) { setSync('ok'); return Promise.resolve(); }
    inFlight = true;
    setSync('saving');
    var batch = {};
    ids.forEach(function (k) { batch[k] = pending[k]; });
    return post('objective_exam_api?action=save_answers', {
      exam_id: C.examId,
      attempt_no: C.attemptNo,
      answers: JSON.stringify(batch)
    }).then(function (res) {
      inFlight = false;
      if (res.success) {
        retryDelay = 2000;
        Object.keys(batch).forEach(function (k) {
          if (pending[k] === batch[k]) delete pending[k];
        });
        persist();
        if (Object.keys(pending).length) scheduleFlush(200); else setSync('ok');
        return;
      }
      if (res.finished || res.time_up || res.closed) return endFromServer('Your exam has been submitted.');
      if (res.locked) return showLocked(C.maxViolations);
      // anything else: keep the answers and try again a bit later
      setSync('offline');
      scheduleFlush(retryDelay);
      retryDelay = Math.min(retryDelay * 2, 15000);
    }).catch(function () {
      inFlight = false;
      setSync('offline');
      scheduleFlush(retryDelay);
      retryDelay = Math.min(retryDelay * 2, 15000);
    });
  }

  window.addEventListener('online', function () { retryDelay = 2000; scheduleFlush(100); });
  window.addEventListener('offline', function () { setSync('offline'); });

  /* ------------------------------------------------------------------ *
   *  timer
   * ------------------------------------------------------------------ */

  var remaining = C.remaining;  // null = untimed
  var lastPerf = performance.now();
  var lastAckPerf = performance.now();
  var timerHandle = null;

  function paintTimer() {
    var el = $('xpTimerText');
    var box = $('xpTimer');
    if (REVIEW) { el.textContent = 'Review'; return; }
    if (remaining === null) { el.textContent = 'No limit'; return; }
    var s = Math.max(0, Math.ceil(remaining));
    var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
    el.textContent = (h ? h + ':' + String(m).padStart(2, '0') : String(m).padStart(2, '0')) + ':' + String(sec).padStart(2, '0');
    var total = C.duration || 0;
    var half = total > 0 && s <= total / 2;
    box.classList.toggle('is-half', half && s > 300);
    box.classList.toggle('is-low', s <= 300 && s > 60);
    box.classList.toggle('is-critical', s <= 60);
    if (half && !paintTimer.halfShown && total > 600) {
      paintTimer.halfShown = true;
      if (s < total / 2 - 5) return;   // page opened after half time: no announcement
      toast('Half of your time has gone. ' + Math.round(s / 60) + ' minutes left.');
    }
  }

  function setZoom(z) {
    zoom = z;
    try { localStorage.setItem(C.studentKey || 'xpz', String(z)); } catch (e) {}
    var i = ZOOM_STEPS.indexOf(z);
    document.querySelectorAll('[data-zoom]').forEach(function (b) {
      b.disabled = b.getAttribute('data-zoom') === '-1' ? i === 0 : i === ZOOM_STEPS.length - 1;
    });
    fit();
  }
  document.querySelectorAll('[data-zoom]').forEach(function (b) {
    b.onclick = function () {
      var i = ZOOM_STEPS.indexOf(zoom) + parseInt(b.getAttribute('data-zoom'), 10);
      if (i >= 0 && i < ZOOM_STEPS.length) setZoom(ZOOM_STEPS[i]);
    };
  });

  function startTimer() {
    paintTimer();
    if (REVIEW || remaining === null) return;
    timerHandle = setInterval(function () {
      var now = performance.now();
      var dt = (now - lastPerf) / 1000;
      lastPerf = now;
      if (window.isLocked) return;
      remaining -= dt;
      paintTimer();
      if (remaining <= 0) {
        clearInterval(timerHandle);
        submit(true, 'Time is up. Submitting your answers…');
      } else if (Math.abs(remaining - 600) < dt && (C.duration || 0) > 1200) {
        toast('10 minutes left.');
      } else if (Math.abs(remaining - 300) < dt) {
        toast('5 minutes left. Check your answers.', true);
      } else if (Math.abs(remaining - 60) < dt) {
        toast('1 minute left.', true);
      }
    }, 500);
  }

  /* ------------------------------------------------------------------ *
   *  heartbeat and proctoring
   * ------------------------------------------------------------------ */

  var violations = C.violations || 0;
  var lastViolationAt = 0;
  var graceUntil = Date.now() + 4000;
  var mediaPrompt = false;
  var monitoring = false;
  var submitting = false;
  var webcamVideo = null;
  var snapCanvas = document.createElement('canvas');

  function sendPing(status, violation, details) {
    if (REVIEW || submitting) return Promise.resolve();
    var data = {
      exam_id: C.examId,
      attempt_no: C.attemptNo,
      status: status || 'active',
      client_elapsed: Math.round((performance.now() - lastAckPerf) / 1000)
    };
    if (violation) { data.violation_type = violation; data.details = details || ''; }
    if (window.isWebcamReady && webcamVideo && webcamVideo.readyState >= 2) {
      try {
        snapCanvas.width = 160; snapCanvas.height = 120;
        snapCanvas.getContext('2d').drawImage(webcamVideo, 0, 0, 160, 120);
        data.webcam_snapshot = snapCanvas.toDataURL('image/jpeg', 0.5);
      } catch (e) { /* camera not ready */ }
    }
    return post('proctor_ping', data).then(function (res) {
      lastAckPerf = performance.now();
      handlePing(res);
    }).catch(function () { /* next beat will try again */ });
  }
  window.sendPing = sendPing;

  function handlePing(res) {
    if (!res || !res.status) return;
    if (res.status === 'FORCED_LOGOUT') {
      window.isLocked = true;
      submitting = true;
      return dialog('Signed in elsewhere', esc(res.message || 'This account was opened on another device.'), { ok: 'OK', cancel: false })
        .then(function () { leave('student_logout'); });
    }
    if (res.status === 'SUBMITTED') return endFromServer(res.message);
    if (res.status === 'LOCKED') return showLocked(res.count);
    if (res.status === 'TIME_UP') { remaining = 0; return submit(true, 'Time is up. Submitting your answers…'); }
    if (res.status !== 'OK') return;
    if (!$('xpLocked').hidden) {
      // the invigilator unlocked the attempt
      $('xpLocked').hidden = true;
      window.isLocked = false;
      violations = res.violation_count || 0;
      lastPerf = performance.now();
      toast('Your exam has been unlocked. You can continue.');
    }

    if (typeof res.remaining_seconds === 'number' && remaining !== null) {
      if (Math.abs(res.remaining_seconds - remaining) > 2) remaining = res.remaining_seconds;
    }
    if (typeof res.violation_count === 'number') violations = Math.max(violations, res.violation_count);
    if (policy.autoSubmitStrikes && violations >= policy.autoSubmitStrikes && !submitting) {
      submit(true, 'Too many security warnings. Your exam is being submitted.');
      return;
    }
    if (res.is_live_requested) startSignaling();
    if (res.warning) showWarning(res.warning);
  }

  function beat() {
    sendPing(document.hidden ? 'blur' : 'active');
    setTimeout(beat, 12000 + Math.random() * 6000);   // spread load across the hall
  }

  function violation(type, details) {
    if (REVIEW || !monitoring || submitting || window.isLocked || mediaPrompt) return;
    var now = Date.now();
    if (now < graceUntil || now - lastViolationAt < 2500) return;
    lastViolationAt = now;
    violations++;
    $('xpStrikeCount').textContent = Math.min(violations, C.maxViolations);
    $('xpStrikeMax').textContent = C.maxViolations;
    var left = C.maxViolations - violations;
    var note = left > 0 ? left + ' more and the exam will be locked.' : 'The exam is being locked.';
    if (policy.autoSubmitStrikes) {
      var toSubmit = policy.autoSubmitStrikes - violations;
      note = toSubmit > 0 ? toSubmit + ' more and your exam will be submitted automatically.' : 'Your exam is being submitted.';
    }
    $('xpStrikeNote').textContent = note;
    $('xpViolation').hidden = false;
    alarm();
    sendPing('blur', type, details);
  }

  function returnToExam() {
    $('xpViolation').hidden = true;
    graceUntil = Date.now() + 1500;
    if (policy.fullscreen) enterFullscreen();
    sendPing('active');
  }

  function showLocked(count) {
    window.isLocked = true;
    $('xpViolation').hidden = true;
    $('xpLockCount').textContent = count || C.maxViolations;
    $('xpLocked').hidden = false;
    flush();
  }

  function showWarning(msg) {
    alarm();
    dialog('Message from the invigilator', esc(msg), { ok: 'I understand', cancel: false });
  }

  function alarm() {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      [[880, 0, 0.12], [880, 0.18, 0.12], [1200, 0.36, 0.3]].forEach(function (b) {
        var osc = ctx.createOscillator(), g = ctx.createGain();
        osc.connect(g); g.connect(ctx.destination);
        osc.type = 'square';
        osc.frequency.setValueAtTime(b[0], ctx.currentTime + b[1]);
        g.gain.setValueAtTime(0.25, ctx.currentTime + b[1]);
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + b[1] + b[2]);
        osc.start(ctx.currentTime + b[1]);
        osc.stop(ctx.currentTime + b[1] + b[2] + 0.05);
      });
    } catch (e) { /* no audio */ }
  }

  function enterFullscreen() {
    var el = document.documentElement;
    var fn = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
    if (fn && !isFullscreen()) {
      try {
        var p = fn.call(el);
        if (p && p.catch) p.catch(function () {});
      } catch (e) { /* not allowed here */ }
    }
  }
  function isFullscreen() {
    return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
  }

  /* ------------------------------------------------------------------ *
   *  browser lockdown
   * ------------------------------------------------------------------ */

  function toast(msg, warn) {
    var t = $('xpToast');
    t.textContent = msg;
    t.className = 'xp-toast' + (warn ? ' is-warn' : '');
    t.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(function () { t.hidden = true; }, 2600);
  }

  function lockdown() {
    ['copy', 'cut', 'paste'].forEach(function (ev) {
      document.addEventListener(ev, function (e) {
        e.preventDefault();
        try { if (e.clipboardData) e.clipboardData.setData('text/plain', ''); } catch (x) {}
        if (!REVIEW) toast('Copying and pasting are disabled during the exam.', true);
      });
    });
    ['contextmenu', 'dragstart', 'drop', 'selectstart'].forEach(function (ev) {
      document.addEventListener(ev, function (e) { e.preventDefault(); });
    });

    var ctrlBlocked = 'acvxpsufghjklnortwdeiyb';
    document.addEventListener('keydown', function (e) {
      var k = (e.key || '').toLowerCase();
      var mod = e.ctrlKey || e.metaKey;
      if (k === 'printscreen' || k === 'snapshot') {
        e.preventDefault();
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText('').catch(function () {});
        violation('SCREENSHOT', 'Print Screen pressed');
        return;
      }
      if (/^f([1-9]|1[0-2])$/.test(k)) { e.preventDefault(); return; }
      if (mod && (ctrlBlocked.indexOf(k) !== -1 || e.shiftKey)) {
        e.preventDefault();
        if (k === 'c' || k === 'x' || k === 'v') toast('Copying and pasting are disabled during the exam.', true);
        if (e.shiftKey && (k === 'i' || k === 'j' || k === 'c')) violation('DEVTOOLS', 'Developer tools shortcut');
        return;
      }
      if (e.altKey && (k === 'arrowleft' || k === 'arrowright' || k === 'home')) { e.preventDefault(); return; }

      // arrow keys move between questions unless a radio/checkbox has focus
      var tag = e.target && e.target.tagName;
      if (!mod && !e.altKey && tag !== 'INPUT' && tag !== 'TEXTAREA' && $('xpDialog').hidden) {
        if (k === 'arrowright') { e.preventDefault(); go(1); }
        if (k === 'arrowleft') { e.preventDefault(); go(-1); }
      }
    }, true);
    document.addEventListener('keyup', function (e) {
      if ((e.key || '').toLowerCase() === 'printscreen' && navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText('').catch(function () {});
      }
    });

    if (REVIEW) return;

    // back button stays on the exam
    try {
      history.pushState({ xp: 1 }, '', location.href);
      window.addEventListener('popstate', function () {
        history.pushState({ xp: 1 }, '', location.href);
        toast('Use the Submit button at the top when you are done.');
      });
    } catch (e) { /* ignore */ }

    window.addEventListener('beforeunload', beforeUnload);

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) violation('TAB_SWITCH', 'Exam tab hidden');
    });
    window.addEventListener('blur', function () {
      // focus moving into an iframe of our own page is not leaving
      setTimeout(function () { if (!document.hasFocus()) violation('WINDOW_BLUR', 'Exam window lost focus'); }, 150);
    });
    var fsEvents = ['fullscreenchange', 'webkitfullscreenchange'];
    fsEvents.forEach(function (ev) {
      document.addEventListener(ev, function () {
        setTimeout(fit, 60);
        if (policy.fullscreen && !isFullscreen()) violation('EXIT_FS', 'Left full screen');
      });
    });
  }

  function beforeUnload(e) {
    if (submitting) return;
    flush();
    e.preventDefault();
    e.returnValue = '';
    return '';
  }

  function leave(url) {
    submitting = true;
    window.removeEventListener('beforeunload', beforeUnload);
    location.href = url;
  }

  /* ------------------------------------------------------------------ *
   *  dialog
   * ------------------------------------------------------------------ */

  function dialog(title, bodyHtml, opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      $('xpDialogTitle').textContent = title;
      $('xpDialogBody').innerHTML = bodyHtml;
      var ok = $('xpDialogOk'), cancel = $('xpDialogCancel');
      ok.textContent = opts.ok || 'OK';
      ok.hidden = opts.ok === false;
      cancel.textContent = opts.cancel || 'Cancel';
      cancel.hidden = opts.cancel === false;
      $('xpDialog').hidden = false;
      function done(v) {
        $('xpDialog').hidden = true;
        ok.onclick = cancel.onclick = null;
        resolve(v);
      }
      ok.onclick = function () { done(true); };
      cancel.onclick = function () { done(false); };
      if (!ok.hidden) ok.focus();
    });
  }

  /* ------------------------------------------------------------------ *
   *  submission
   * ------------------------------------------------------------------ */

  function summaryHtml() {
    var empty = [], partial = [], flagged = [];
    Q.forEach(function (q, i) {
      var st = answerState(q);
      if (st === 'empty') empty.push(i);
      if (st === 'partial') partial.push(i);
      if (flags[q.id]) flagged.push(i);
    });
    var done = N - empty.length - partial.length;
    var chips = function (arr) {
      return arr.map(function (i) { return '<button type="button" class="xp-chip" data-go="' + i + '">' + (i + 1) + '</button>'; }).join('');
    };
    var h = '<div class="xp-sum">' +
      '<div class="is-ok"><b>' + done + '</b><span>Answered</span></div>' +
      '<div class="' + (empty.length ? 'is-bad' : '') + '"><b>' + empty.length + '</b><span>Not answered</span></div>' +
      '<div class="' + (partial.length ? 'is-warn' : '') + '"><b>' + partial.length + '</b><span>Incomplete</span></div>' +
      '<div><b>' + flagged.length + '</b><span>Flagged</span></div>' +
      '</div>';
    if (empty.length) h += '<p class="xp-sum-label">Not answered — tap a number to go back to it</p><div class="xp-chips">' + chips(empty) + '</div>';
    if (partial.length) h += '<p class="xp-sum-label">Some statements left blank</p><div class="xp-chips">' + chips(partial) + '</div>';
    if (flagged.length) h += '<p class="xp-sum-label">Flagged for review</p><div class="xp-chips">' + chips(flagged) + '</div>';
    if (remaining !== null) h += '<p class="xp-sum-time">Time left: <strong>' + $('xpTimerText').textContent + '</strong></p>';
    h += '<p class="xp-sum-note">After you submit, you cannot change any answer.</p>';
    return h;
  }

  $('xpDialogBody').addEventListener('click', function (e) {
    var b = e.target.closest('[data-go]');
    if (!b) return;
    $('xpDialogCancel').click();
    render(parseInt(b.getAttribute('data-go'), 10));
  });

  function submit(auto, message) {
    if (REVIEW || submitting) return;
    if (!auto) {
      dialog('Submit your exam?', summaryHtml(), { ok: 'Yes, submit', cancel: 'Keep working' }).then(function (yes) {
        if (yes) doSubmit('Submitting your answers…');
      });
      return;
    }
    doSubmit(message || 'Submitting your answers…');
  }

  function doSubmit(message) {
    submitting = true;
    window.isLocked = true;
    $('xpViolation').hidden = true;
    stopRecording();
    dialog('Please wait', '<p>' + esc(message) + '</p>', { ok: false, cancel: false });

    var timeout = new Promise(function (r) { setTimeout(r, 6000); });
    Promise.race([flush(), timeout]).then(function () {
      var send = function () {
        var form = $('xpSubmitForm');
        var all = {};
        Q.forEach(function (q) { if (answers[q.id] !== undefined) all[q.id] = answers[q.id]; });
        form.elements.answers_json.value = JSON.stringify(all);
        form.elements.csrf_token.value = C.csrf;
        window.removeEventListener('beforeunload', beforeUnload);
        form.submit();
      };
      if (navigator.onLine === false) {
        $('xpDialogBody').innerHTML = '<p>The connection to the exam server is down. Your answers are safe on this computer and will be submitted as soon as it comes back. Do not close this window.</p>';
        window.addEventListener('online', send, { once: true });
      } else {
        send();
      }
    });
  }

  function endFromServer(message) {
    if (submitting && window.__xpEnded) return;
    window.__xpEnded = true;
    submitting = true;
    window.isLocked = true;
    stopRecording();
    try { localStorage.removeItem(STORE_KEY); } catch (e) {}
    dialog('Exam finished', '<p>' + esc(message || 'Your exam has been submitted.') + '</p>', { ok: 'OK', cancel: false })
      .then(function () { leave(C.afterSubmitUrl || 'student_logout'); });
  }

  /* ------------------------------------------------------------------ *
   *  webcam evidence and live view
   * ------------------------------------------------------------------ */

  var recorder = null;
  function initWebcam() {
    if (!policy.webcam || REVIEW || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;
    mediaPrompt = true;
    navigator.mediaDevices.getUserMedia({ video: true, audio: true }).then(function (stream) {
      mediaPrompt = false;
      graceUntil = Date.now() + 1500;
      window.webcamStream = stream;
      window.isWebcamReady = true;
      webcamVideo = document.createElement('video');
      webcamVideo.muted = true;
      webcamVideo.playsInline = true;
      webcamVideo.autoplay = true;
      webcamVideo.style.cssText = 'position:fixed;left:-2px;top:-2px;width:1px;height:1px;opacity:0;pointer-events:none';
      webcamVideo.srcObject = stream;
      document.body.appendChild(webcamVideo);
      webcamVideo.play().catch(function () {});
      if (window.MediaRecorder) {
        try {
          var mime = MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('video/webm;codecs=vp8') ? 'video/webm;codecs=vp8' : 'video/webm';
          recorder = new MediaRecorder(stream, { mimeType: mime, videoBitsPerSecond: 250000 });
          // One continuous file per sitting: slices are sent strictly in order
          // and the server appends them. A reload starts a new segment.
          var segment = String(Date.now());
          var seq = 0;
          var queue = Promise.resolve();
          recorder.ondataavailable = function (e) {
            if (!e.data || !e.data.size) return;
            var blob = e.data, n = seq++;
            queue = queue.then(function send(tries) {
              tries = tries || 0;
              var fd = new FormData();
              fd.append('attempt_id', C.attemptId);
              fd.append('segment', segment);
              fd.append('seq', n);
              fd.append('chunk', blob, 'part.webm');
              return fetch('upload_recording?action=student_chunk', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
                .then(function (r) { return r.json(); })
                .catch(function () {
                  if (tries < 20) return new Promise(function (ok) { setTimeout(ok, 3000); }).then(function () { return send(tries + 1); });
                });
            });
          };
          recorder.start(10000);
        } catch (e) { recorder = null; }
      }
    }).catch(function () {
      mediaPrompt = false;
      graceUntil = Date.now() + 1500;
      window.isWebcamReady = false;
    });
  }
  function stopRecording() {
    if (recorder && recorder.state !== 'inactive') {
      try { recorder.stop(); } catch (e) {}
    }
  }

  var pc = null, signalTimer = null, lastSignal = 0;
  function startSignaling() {
    if (signalTimer || REVIEW) return;
    signalTimer = setInterval(function () {
      fetch('webrtc_api?action=get&attempt_id=' + C.attemptId + '&last_id=' + lastSignal, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          (d.signals || []).forEach(function (s) {
            lastSignal = s.id;
            onSignal(s).catch(function () {});
          });
        }).catch(function () {});
    }, 1500);
  }
  function sendSignal(type, payload) {
    post('webrtc_api?action=send', { attempt_id: C.attemptId, type: type, payload: payload }).catch(function () {});
  }
  function onSignal(s) {
    var payload = JSON.parse(s.payload);
    if (s.type === 'offer') {
      if (!pc) {
        pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
        if (window.webcamStream) window.webcamStream.getTracks().forEach(function (t) { pc.addTrack(t, window.webcamStream); });
        pc.onicecandidate = function (e) { if (e.candidate) sendSignal('ice', JSON.stringify(e.candidate)); };
        pc.ontrack = function (e) {
          if (e.track.kind !== 'audio') return;
          var a = $('xpProctorAudio') || document.body.appendChild(Object.assign(document.createElement('audio'), { id: 'xpProctorAudio', autoplay: true }));
          a.srcObject = e.streams[0] || new MediaStream([e.track]);
        };
      }
      return pc.setRemoteDescription(new RTCSessionDescription(payload))
        .then(function () { return pc.createAnswer(); })
        .then(function (ans) { return pc.setLocalDescription(ans).then(function () { sendSignal('answer', JSON.stringify(ans)); }); });
    }
    if (s.type === 'ice' && pc) return pc.addIceCandidate(new RTCIceCandidate(payload));
    return Promise.resolve();
  }

  /* ------------------------------------------------------------------ *
   *  wiring
   * ------------------------------------------------------------------ */

  function go(step) {
    var next = index + step;
    if (next >= 0 && next < N) render(next);
  }

  function openPalette(open) {
    $('xpPalette').classList.toggle('is-open', open);
    $('xpScrim').hidden = !open;
  }

  $('xpContent').addEventListener('change', function (e) {
    if (!e.target.matches('input')) return;
    var q = Q[index];
    setAnswer(q, readAnswerFromDom(q));
  });
  // A second click on a chosen true/false circle clears that one statement.
  $('xpContent').addEventListener('click', function (e) {
    var inp = e.target.closest('.xp-tf') && e.target.closest('.xp-tf').querySelector('input');
    if (!inp || REVIEW || e.target !== inp) return;
    var was = inp.getAttribute('data-was') === '1';
    inp.removeAttribute('data-was');
    if (was) {
      inp.checked = false;
      setAnswer(Q[index], readAnswerFromDom(Q[index]));
    }
  });
  $('xpContent').addEventListener('pointerdown', function (e) {
    var lab = e.target.closest('.xp-tf');
    if (lab) {
      var inp = lab.querySelector('input');
      inp.setAttribute('data-was', inp.checked ? '1' : '0');
    }
  });

  $('xpPrev').onclick = function () { go(-1); };
  $('xpNext').onclick = function () { go(1); };
  if ($('xpFlag')) $('xpFlag').onclick = function () {
    var q = Q[index];
    if (flags[q.id]) delete flags[q.id]; else flags[q.id] = true;
    persist();
    render(index);
  };
  if ($('xpClear')) $('xpClear').onclick = function () {
    var q = Q[index];
    if (!answers[q.id]) return;
    setAnswer(q, '');
    render(index);
  };
  document.querySelectorAll('[data-finish]').forEach(function (b) {
    b.onclick = function () { openPalette(false); submit(false); };
  });
  $('xpGrid').addEventListener('click', function (e) {
    var cell = e.target.closest('.xp-cell');
    if (!cell) return;
    render(parseInt(cell.getAttribute('data-i'), 10));
    openPalette(false);
  });
  $('xpPaletteToggle').onclick = function () { openPalette(true); };
  $('xpPaletteClose').onclick = function () { openPalette(false); };
  $('xpScrim').onclick = function () { openPalette(false); };
  $('xpReturn').onclick = returnToExam;

  function begin() {
    monitoring = true;
    graceUntil = Date.now() + 3000;
    initWebcam();
    sendPing('active');
  }

  lockdown();
  setZoom(zoom);
  render(index);
  startTimer();

  if (!REVIEW) {
    if (Object.keys(pending).length) scheduleFlush(300);
    setInterval(function () { if (Object.keys(pending).length && !inFlight) flush(); }, 5000);
    // the heartbeat (and so the server-side clock) runs from page load
    setTimeout(beat, 6000 + Math.random() * 6000);
    if (policy.fullscreen) {
      $('xpGate').hidden = false;
      $('xpGateBtn').onclick = function () {
        enterFullscreen();
        $('xpGate').hidden = true;
        setTimeout(fit, 150);
        begin();
      };
    } else {
      begin();
    }
  }
})();
