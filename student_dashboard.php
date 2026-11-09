<?php
require 'auth.php';
check_auth('student');
require 'db.php';
require_once __DIR__ . '/includes/student_exams.php';

$student_id = (int) $_SESSION['student_id'];
$name = $_SESSION['student_name'];
$matric = $_SESSION['matric_no'];

auto_finalize_expired_attempts($db, null, $student_id);
$sd_cards = sd_student_exams($db, $student_id);

$requires_media = false;
foreach ($sd_cards as $c) {
  $e = $c['exam'];
  if (!empty($e['proctor_webcam']) || !empty($e['proctor_live_video']) || !empty($e['is_strict'])) {
    $requires_media = true;
  }
}
$first = explode(' ', trim($name))[0] ?: $name;
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$in_progress = array_filter($sd_cards, fn($c) => $c['state'] === 'progress');
$open_now = array_filter($sd_cards, fn($c) => in_array($c['state'], ['open', 'progress'], true) || ($c['primary']['kind'] ?? '') === 'reattempt');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>My Exams - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/vendor/fonts/fonts.css" rel="stylesheet">
  <link href="assets/css/student.css?v=2" rel="stylesheet">
</head>

<body class="sd">
  <div class="sd-layout">
    <?php include 'student_sidebar.php'; ?>

    <div class="sd-main">
      <header class="sd-top">
        <button type="button" class="sd-menu-btn" data-sd-menu aria-label="Menu">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
        </button>
        <h1>My exams</h1>
        <span class="sd-clock" id="sdClock"><?= date('D j M · g:i A') ?></span>
      </header>

      <main class="sd-content">
        <?php if (!empty($_GET['error'])): ?>
          <div class="sd-flash is-warn"><span><?= htmlspecialchars($_GET['error']) ?></span><button onclick="this.parentElement.remove()" aria-label="Close">&times;</button></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['auth_error'])): ?>
          <div class="sd-flash is-warn"><span><?= htmlspecialchars($_SESSION['auth_error']) ?></span><button onclick="this.parentElement.remove()" aria-label="Close">&times;</button></div>
          <?php unset($_SESSION['auth_error']); ?>
        <?php endif; ?>

        <h2 class="sd-hello"><?= $greeting ?>, <?= htmlspecialchars($first) ?>.</h2>
        <p class="sd-hello-sub">
          <?php if ($in_progress): ?>
            You have an exam in progress. Your remaining time has been kept — press <strong>Continue exam</strong> to carry on.
          <?php elseif ($open_now): ?>
            <?= count($open_now) ?> exam<?= count($open_now) === 1 ? ' is' : 's are' ?> open for you now.
          <?php elseif ($sd_cards): ?>
            No exam is open for you right now.
          <?php else: ?>
            You have not been registered for any exam yet.
          <?php endif; ?>
        </p>

        <?php if (!$sd_cards): ?>
          <div class="sd-empty">
            <strong>No exams yet</strong>
            When the examination office registers you for an exam, it will appear here.
          </div>
        <?php else: ?>
          <div class="sd-exams">
            <?php foreach ($sd_cards as $c):
              $e = $c['exam'];
              $p = $c['primary'];
              $s = $c['secondary'];
              $dur = (int) $e['duration_minutes'];
              $modal = [
                'name' => $e['name'],
                'href' => $p['href'] ?? '',
                'reattempt' => ($p['kind'] ?? '') === 'reattempt',
                'questions' => $c['questions'],
                'duration' => $dur > 0 ? $dur . ' min' : 'No limit',
                'attempt' => ($c['attempts_used'] + 1) . ' of ' . $c['attempts_max'],
                'fullscreen' => !empty($e['proctor_fullscreen']) || !empty($e['is_strict']),
                'webcam' => !empty($e['proctor_webcam']) || !empty($e['is_strict']),
                'closes' => !empty($e['end_time']) ? date('g:i A', strtotime($e['end_time'])) : null,
              ];
              ?>
              <article class="sd-exam is-<?= $c['state'] ?>" id="exam-<?= (int) $e['id'] ?>">
                <div>
                  <?php if (!empty($e['group_name'])): ?><div class="sd-exam-group"><?= htmlspecialchars($e['group_name']) ?></div><?php endif; ?>
                  <h3 class="sd-exam-name"><?= htmlspecialchars($e['name']) ?><span class="sd-state is-<?= $c['state'] ?>"><?= htmlspecialchars($c['label']) ?></span></h3>
                  <?php if (!empty($e['description'])): ?><p class="sd-exam-desc"><?= htmlspecialchars($e['description']) ?></p><?php endif; ?>
                  <div class="sd-facts">
                    <span><b><?= $c['questions'] ?></b> questions</span>
                    <span><b><?= $dur > 0 ? $dur . ' min' : 'No time limit' ?></b></span>
                    <span>Attempts <b><?= $c['attempts_used'] ?> / <?= $c['attempts_max'] ?></b></span>
                    <?php if (!empty($e['start_time'])): ?><span>Opens <b><?= date('j M, g:i A', strtotime($e['start_time'])) ?></b></span><?php endif; ?>
                    <?php if (!empty($e['end_time'])): ?><span>Closes <b><?= date('j M, g:i A', strtotime($e['end_time'])) ?></b></span><?php endif; ?>
                    <?php if ($c['state'] === 'progress' && $c['remaining'] !== null): ?><span>Time left <b><?= sd_fmt_minutes($c['remaining']) ?></b></span><?php endif; ?>
                  </div>
                </div>
                <div class="sd-actions">
                  <?php if ($c['score']): ?>
                    <div class="sd-score">Your score <b><?= round($c['score']['value'], 2) ?></b> / <?= round($c['score']['total'], 2) ?></div>
                  <?php endif; ?>
                  <?php if ($p): ?>
                    <?php if (!empty($p['disabled'])): ?>
                      <button class="sd-btn sd-btn-<?= $p['kind'] ?>" disabled><?= htmlspecialchars($p['label']) ?></button>
                    <?php elseif (!empty($p['confirm'])): ?>
                      <button type="button" class="sd-btn sd-btn-<?= $p['kind'] ?>" data-start='<?= htmlspecialchars(json_encode($modal), ENT_QUOTES) ?>'><?= htmlspecialchars($p['label']) ?></button>
                    <?php else: ?>
                      <a class="sd-btn sd-btn-<?= $p['kind'] ?>" href="<?= htmlspecialchars($p['href']) ?>"><?= htmlspecialchars($p['label']) ?></a>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if ($s): ?>
                    <a class="sd-btn sd-btn-ghost sd-btn-sm" href="<?= htmlspecialchars($s['href']) ?>"><?= htmlspecialchars($s['label']) ?></a>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </main>
    </div>
  </div>

  <div class="sd-modal" id="sdStart" hidden role="dialog" aria-modal="true" aria-labelledby="sdStartTitle">
    <div class="sd-modal-box">
      <h2 id="sdStartTitle">Start exam?</h2>
      <p class="sd-modal-sub" id="sdStartName"></p>
      <div class="sd-kv">
        <div><b id="sdStartQ"></b><span>Questions</span></div>
        <div><b id="sdStartD"></b><span>Time</span></div>
        <div><b id="sdStartA"></b><span>Attempt</span></div>
      </div>
      <ul class="sd-rules" id="sdStartRules"></ul>
      <div class="sd-modal-actions">
        <button type="button" class="sd-btn sd-btn-ghost" id="sdStartCancel">Not now</button>
        <a class="sd-btn sd-btn-start" id="sdStartGo" href="#">Start</a>
      </div>
    </div>
  </div>

  <script>
    (function () {
      var modal = document.getElementById('sdStart');
      function li(t) { var x = document.createElement('li'); x.textContent = t; return x; }
      document.querySelectorAll('[data-start]').forEach(function (b) {
        b.addEventListener('click', function () {
          var d = JSON.parse(b.getAttribute('data-start'));
          document.getElementById('sdStartTitle').textContent = d.reattempt ? 'Start a new attempt?' : 'Start exam?';
          document.getElementById('sdStartName').textContent = d.name;
          document.getElementById('sdStartQ').textContent = d.questions;
          document.getElementById('sdStartD').textContent = d.duration;
          document.getElementById('sdStartA').textContent = d.attempt;
          var rules = document.getElementById('sdStartRules');
          rules.innerHTML = '';
          rules.appendChild(li('The timer starts when you press ' + (d.reattempt ? 'Re-attempt' : 'Start') + '.'));
          if (d.reattempt) rules.appendChild(li('Your previous attempt is kept. The exam\'s grading rule decides which attempt counts.'));
          rules.appendChild(li('Answers save automatically. If the computer fails, sign in again — your remaining time is kept.'));
          rules.appendChild(li('Leaving the exam screen, switching windows or copying is recorded.'));
          if (d.fullscreen) rules.appendChild(li('The exam opens in full screen.'));
          if (d.webcam) rules.appendChild(li('Your camera will be used for proctoring.'));
          if (d.closes) rules.appendChild(li('The exam closes at ' + d.closes + '.'));
          var go = document.getElementById('sdStartGo');
          go.href = d.href;
          go.textContent = d.reattempt ? 'Re-attempt' : 'Start';
          go.className = 'sd-btn ' + (d.reattempt ? 'sd-btn-reattempt' : 'sd-btn-start');
          modal.hidden = false;
          go.focus();
        });
      });
      document.getElementById('sdStartCancel').onclick = function () { modal.hidden = true; };
      modal.addEventListener('click', function (e) { if (e.target === modal) modal.hidden = true; });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.hidden = true; });
      document.getElementById('sdStartGo').addEventListener('click', function () {
        this.textContent = 'Opening…';
        this.classList.add('is-disabled');
      });

      // highlight an exam picked from the sidebar
      function mark() {
        document.querySelectorAll('.sd-exam.is-target').forEach(function (x) { x.classList.remove('is-target'); });
        var t = location.hash && document.querySelector(location.hash);
        if (t) { t.classList.add('is-target'); t.scrollIntoView({ block: 'center' }); }
      }
      window.addEventListener('hashchange', mark);
      mark();

      var clock = document.getElementById('sdClock');
      setInterval(function () {
        var d = new Date();
        clock.textContent = d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' }) + ' · ' +
          d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
      }, 30000);
      // keep states (opening times, time left) current
      setTimeout(function () { if (document.getElementById('sdStart').hidden) location.reload(); }, 120000);
    })();
  </script>

  <?php if ($requires_media): ?>
    <script>
      // Ask for camera/microphone here, before the exam, so no permission pop-up
      // interrupts the student once the timer is running.
      (function () {
        if (sessionStorage.getItem('_portalSecurityReady') || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;
        setTimeout(function () {
          navigator.mediaDevices.getUserMedia({ video: true, audio: true }).then(function (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            sessionStorage.setItem('_portalSecurityReady', '1');
          }).catch(function () {});
        }, 1200);
      })();
    </script>
  <?php endif; ?>
</body>

</html>
