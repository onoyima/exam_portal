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

// Filter to only show active/relevant exams for the student
$sd_cards = array_filter($sd_cards, function($c) {
    $state = $c['state'];
    // Show: in progress, locked, open, wait (upcoming), reattempt available
    // Hide: done/closed unless they have a score to view
    if (in_array($c['state'], ['progress', 'locked', 'open', 'wait'], true)) {
        return true;
    }
    if ($c['state'] === 'done' && $c['score']) {
        return true;
    }
    // Allow reattempt if available
    if (($c['primary']['kind'] ?? '') === 'reattempt') {
        return true;
    }
    return false;
});

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
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/student.css?v=2" rel="stylesheet">
    <style>
        /* Exam card styles */
        .exam-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .exam-card:hover {
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
            transform: translateY(-2px);
        }
        .exam-card.is-progress { border-left: 4px solid #22c55e; }
        .exam-card.is-locked { border-left: 4px solid #f59e0b; }
        .exam-card.is-open { border-left: 4px solid #22c55e; }
        .exam-card.is-wait { border-left: 4px solid #3b82f6; }
        .exam-card.is-done { border-left: 4px solid #64748b; }
        .exam-card.is-closed { border-left: 4px solid #ef4444; }
        .exam-card.is-locked { opacity: 0.7; }

        .exam-card-header {
            padding: 1.25rem 1.25rem 0.75rem;
        }
        .exam-card-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }
        .exam-card.is-progress .exam-card-badge { background: #dcfce7; color: #166534; }
        .exam-card.is-locked .exam-card-badge { background: #fef3c7; color: #92400e; }
        .exam-card.is-open .exam-card-badge { background: #dcfce7; color: #166534; }
        .exam-card.is-wait .exam-card-badge { background: #dbeafe; color: #1e40af; }
        .exam-card.is-done .exam-card-badge { background: #f1f5f9; color: #475569; }
        .exam-card.is-closed .exam-card-badge { background: #fef2f2; color: #991b1b; }

        .exam-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        .exam-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: #64748b;
        }
        .exam-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
            background: #f8fafc;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        .exam-meta-item i { font-size: 0.85rem; }
        .exam-desc {
            color: #64748b;
            font-size: 0.875rem;
            line-height: 1.5;
            margin-top: 0.5rem;
        }
        .exam-card-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid #e2e8f0;
            background: #fafafa;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: space-between;
        }
        .exam-score {
            font-size: 0.85rem;
            font-weight: 600;
            color: #0f172a;
        }
        .exam-score .score-value { color: #22c55e; font-weight: 700; }
        .exam-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .exam-card.is-locked .exam-actions { opacity: 0.5; }

        /* Exam type badges */
        .exam-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .exam-type-objective { background: #dcfce7; color: #166534; }
        .exam-type-practical { background: #fef3c7; color: #92400e; }
        .exam-type-viva { background: #e0e7ff; color: #3730a3; }
        .exam-type-research { background: #fce7f3; color: #9d174d; }

        /* Exam image */
        .exam-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: #f1f5f9;
        }
        .exam-image-placeholder {
            width: 100%;
            height: 160px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }

        /* Responsive grid */
        .exams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.25rem;
        }
        @media (max-width: 640px) {
            .exams-grid { grid-template-columns: 1fr; }
        }

        /* Empty state */
        .exams-empty {
            text-align: center;
            padding: 4rem 2rem;
            background: #fafafa;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
        }
        .exams-empty-icon { font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; }
        .exams-empty-title { font-size: 1.25rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem; }
        .exams-empty-text { color: #94a3b8; }

        /* Progress ring for time remaining */
        .time-remaining {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: #fef3c7;
            border-radius: 8px;
            color: #92400e;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .time-remaining.critical { background: #fef2f2; color: #991b1b; }
    </style>
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
                    <div class="exams-empty">
                        <div class="exams-empty-icon"><i class="bi bi-journal-x"></i></div>
                        <h3 class="exams-empty-title">No exams available</h3>
                        <p class="exams-empty-text">When the examination office registers you for an exam, it will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="exams-grid">
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
                            // Determine exam image
                            $examImage = null;
                            if (!empty($e['image'])) {
                                $examImage = $e['image'];
                            }
                            // Determine exam type for badge
                            $examType = $e['type'] ?? 'objective';
                            $typeLabels = ['objective' => 'Objective', 'practical' => 'Practical', 'viva' => 'Viva', 'research' => 'Research'];
                            $typeIcons = ['objective' => 'bi-ui-checks', 'practical' => 'bi-clipboard2-pulse', 'viva' => 'bi-mic', 'research' => 'bi-journal-richtext'];
                            ?>
                            <article class="exam-card is-<?= $c['state'] ?>" id="exam-<?= (int) $e['id'] ?>">
                                <?php if ($examImage): ?>
                                    <img src="<?= htmlspecialchars($examImage) ?>" alt="" class="exam-image" loading="lazy">
                                <?php else: ?>
                                    <div class="exam-image-placeholder">
                                        <i class="bi bi-<?= $typeIcons[$examType] ?? 'journal-text' ?> fs-1"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="exam-card-header">
                                    <?php if (!empty($e['group_name'])): ?>
                                        <div class="exam-type-badge exam-type-<?= $examType ?>"><i class="bi bi-collection"></i> <?= htmlspecialchars($e['group_name']) ?></div>
                                    <?php endif; ?>
                                    <span class="exam-type-badge exam-type-<?= $examType ?>"><i class="bi bi-<?= $typeIcons[$examType] ?? 'journal-text' ?>"></i> <?= $typeLabels[$examType] ?? ucfirst($examType) ?></span>
                                    <h3 class="exam-card-title"><?= htmlspecialchars($e['name']) ?></h3>
                                    <?php if (!empty($e['description'])): ?><p class="exam-desc"><?= htmlspecialchars($e['description']) ?></p><?php endif; ?>
                                    <div class="exam-meta">
                                        <span class="exam-meta-item"><i class="bi bi-question-circle"></i> <b><?= $c['questions'] ?></b> questions</span>
                                        <span class="exam-meta-item"><i class="bi bi-clock"></i> <b><?= $dur > 0 ? $dur . ' min' : 'No limit' ?></b></span>
                                        <span class="exam-meta-item"><i class="bi bi-arrow-repeat"></i> Attempts <b><?= $c['attempts_used'] ?> / <?= $c['attempts_max'] ?></b></span>
                                        <?php if (!empty($e['start_time'])): ?><span class="exam-meta-item"><i class="bi bi-calendar-event"></i> Opens <b><?= date('j M, g:i A', strtotime($e['start_time'])) ?></b></span><?php endif; ?>
                                        <?php if (!empty($e['end_time'])): ?><span class="exam-meta-item"><i class="bi bi-calendar-x"></i> Closes <b><?= date('j M, g:i A', strtotime($e['end_time'])) ?></b></span><?php endif; ?>
                                        <?php if ($c['state'] === 'progress' && $c['remaining'] !== null): ?><span class="time-remaining <?= $c['remaining'] < 300 ? 'critical' : '' ?>"><i class="bi bi-hourglass-split"></i> <?= sd_fmt_minutes($c['remaining']) ?> left</span><?php endif; ?>
                                    </div>
                                    <?php if (!empty($e['description'])): ?><p class="exam-desc"><?= htmlspecialchars($e['description']) ?></p><?php endif; ?>
                                </div>
                                <div class="exam-card-footer">
                                    <?php if ($c['score']): ?>
                                        <div class="exam-score">Your score <span class="score-value"><?= round($c['score']['value'], 2) ?></span> / <?= round($c['score']['total'], 2) ?></div>
                                    <?php endif; ?>
                                    <div class="exam-actions">
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
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
              </main>
            </div>
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