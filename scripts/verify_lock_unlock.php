<?php
/**
 * verify_lock_unlock.php — disposable end-to-end check of the lock-only policy.
 *
 * Run inside the web container as www-data (see scripts/verify-lock-unlock.sh).
 *
 * It creates a throwaway exam/student/attempt, then drives the REAL endpoints:
 *   1. 5th violation via /proctor_ping        -> must LOCK, must not submit
 *   2. heartbeat while locked                 -> stays LOCKED, time frozen
 *   3. admin unlock via /admin_live_proctor   -> clears lock + strikes
 *   4. heartbeat after unlock                 -> OK, resumes, does not charge
 *                                                the time spent locked
 *
 * Everything it creates is deleted at the end, including audit rows and the
 * two temporary session files. The live exam is never touched.
 */

// Capture PHP's built-in session path BEFORE config.php changes it. The web
// entry points (auth.php) call session_start() before config.php runs, so the
// live request uses this default path, not the mounted one.
$defaultSessionPath = ini_get('session.save_path');
if ($defaultSessionPath === '' || $defaultSessionPath === false) {
    $defaultSessionPath = sys_get_temp_dir();
}
if (strpos($defaultSessionPath, ';') !== false) {
    $parts = explode(';', $defaultSessionPath);
    $defaultSessionPath = end($parts);
}
$SESSION_PATHS = array_values(array_unique(array_filter(
    [$defaultSessionPath, '/var/lib/php/sessions'],
    static fn($p) => is_string($p) && $p !== '' && is_dir($p) && is_writable($p)
)));
if (!$SESSION_PATHS) {
    fwrite(STDERR, "ERROR: no writable session directory found\n");
    exit(1);
}

require_once '/var/www/html/config.php';
require_once '/var/www/html/includes/question_engine.php';

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}" . ($extra !== '' ? "  ({$extra})" : '') . "\n";
    }
}

function http_post(string $url, string $sid, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIE         => 'PHPSESSID=' . $sid,
        CURLOPT_HTTPHEADER     => ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    if ($redirect !== '') {
        $err = trim($err . ' redirect=' . $redirect);
    }
    curl_close($ch);
    return [$code, (string) $body, $err];
}

/**
 * Write a PHP session file directly (default "php" serialize handler).
 * Using the session engine from CLI is unreliable once anything has been
 * printed, so build the same `key|serialize(value)` blob PHP would write.
 */
function mint_session(string $sid, array $data): void
{
    $blob = '';
    foreach ($data as $k => $v) {
        $blob .= $k . '|' . serialize($v);
    }
    $written = 0;
    foreach ($GLOBALS['SESSION_PATHS'] as $dir) {
        $path = $dir . '/sess_' . $sid;
        if (@file_put_contents($path, $blob) !== false) {
            @chmod($path, 0600);
            $written++;
        }
    }
    if ($written === 0) {
        throw new RuntimeException('cannot write session file for ' . $sid);
    }
}

$db = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$db->exec("SET time_zone = '" . date('P') . "'");

$tag       = 'ZZTEST' . strtoupper(bin2hex(random_bytes(4)));
$examId    = 0;
$studentId = 0;
$attemptId = 0;
$attempt2Id = 0;
$studentSid = bin2hex(random_bytes(16));
$adminSid   = bin2hex(random_bytes(16));
$csrf1      = bin2hex(random_bytes(16));
$csrf2      = bin2hex(random_bytes(16));

echo "Disposable test tag: {$tag}\n";
echo 'Session paths: ' . implode(', ', $SESSION_PATHS) . "\n";

try {
    /* ---------- throwaway rows ---------- */
    $db->prepare("INSERT INTO exams
        (name, type, is_active, duration_minutes, max_attempts, total_mark, is_strict,
         start_time, end_time, proctor_fullscreen, proctor_tab_lock, proctor_kb_lock,
         proctor_auto_submit, proctor_browser_check, proctor_webcam, proctor_live_video)
        VALUES (?, 'objective', 0, 60, 1, 10, 1,
                NOW() - INTERVAL 5 MINUTE, NOW() + INTERVAL 2 HOUR, 1, 1, 1, 0, 0, 0, 0)")
        ->execute([$tag . ' lock test']);
    $examId = (int) $db->lastInsertId();

    $db->prepare("INSERT INTO objective_questions
        (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option, mark)
        VALUES (?, 'Disposable lock-test question?', 'A', 'B', 'C', 'D', 'A', 1)")
        ->execute([$examId]);
    $questionId = (int) $db->lastInsertId();

    $db->prepare("INSERT INTO students (name, matric_no, username, password, current_session_id, source)
        VALUES (?, ?, ?, 'x', ?, 'manual')")
        ->execute([$tag . ' Student', $tag, $tag, $studentSid]);
    $studentId = (int) $db->lastInsertId();

    $db->prepare("INSERT INTO objective_attempts
        (exam_id, student_id, attempt_no, started_at, time_used, last_tick_at,
         question_order, violation_count, is_locked, last_active, proctor_status)
        VALUES (?, ?, 1, NOW() - INTERVAL 120 SECOND, 120, NOW(), ?, 4, 0, NOW(), 'active')")
        ->execute([$examId, $studentId, (string) $questionId]);
    $attemptId = (int) $db->lastInsertId();

    $db->prepare("INSERT INTO objective_answers
        (exam_id, student_id, question_id, selected_option, submitted_at, attempt_no)
        VALUES (?, ?, ?, 'A', NOW(), 1)")
        ->execute([$examId, $studentId, $questionId]);

    mint_session($studentSid, [
        'role' => 'student', 'student_id' => $studentId, 'user' => $tag,
        'student_name' => $tag . ' Student', 'csrf_token' => $csrf1,
    ]);
    mint_session($adminSid, [
        'role' => 'admin', 'user' => 'zztest-admin', 'csrf_token' => $csrf2,
    ]);

    $base = 'http://127.0.0.1';
    $ping = $base . '/proctor_ping';

    $attemptRow = function () use ($db, $attemptId): array {
        $st = $db->prepare("SELECT time_used, violation_count, is_locked, finished_at, last_tick_at,
                excused_seconds, locked_at, last_question_index
            FROM objective_attempts WHERE id = ?");
        $st->execute([$attemptId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    };
    $answers = function () use ($db, $examId): int {
        $st = $db->prepare("SELECT COUNT(*) FROM objective_answers WHERE exam_id = ?");
        $st->execute([$examId]);
        return (int) $st->fetchColumn();
    };

    /* ---------- 1. fifth violation locks, never submits ---------- */
    echo "\n[1] Fifth violation\n";
    [$code, $body, $err] = http_post($ping, $studentSid, [
        'exam_id' => $examId, 'attempt_no' => 1, 'status' => 'blur',
        'violation_type' => 'TAB_SWITCH', 'details' => 'disposable test',
        'client_elapsed' => 1, 'csrf_token' => $csrf1,
    ]);
    $json = json_decode($body, true);
    $a = $attemptRow();
    check('endpoint returned JSON', is_array($json), "http {$code} {$err} body=" . substr($body, 0, 160));
    check('status is LOCKED', ($json['status'] ?? '') === 'LOCKED', 'got ' . ($json['status'] ?? 'none'));
    check('attempt is locked', (int) ($a['is_locked'] ?? 0) === 1);
    check('lock start recorded', !empty($a['locked_at']), 'locked_at=' . ($a['locked_at'] ?? 'null'));
    check('attempt was NOT submitted', empty($a['finished_at']), 'finished_at=' . ($a['finished_at'] ?? 'null'));
    check('violation_count reached 5', (int) ($a['violation_count'] ?? 0) === 5, 'got ' . ($a['violation_count'] ?? '?'));
    check('saved answer still present', $answers() === 1);
    $timeAfterLock = (int) ($a['time_used'] ?? 0);
    check('time_used did not jump', $timeAfterLock >= 120 && $timeAfterLock <= 126, "time_used={$timeAfterLock}");

    /* ---------- 2. locked heartbeat stays locked, time frozen ---------- */
    echo "\n[2] Heartbeat while locked\n";
    sleep(2);
    [$code, $body, $err] = http_post($ping, $studentSid, [
        'exam_id' => $examId, 'attempt_no' => 1, 'status' => 'blur',
        'csrf_token' => $csrf1,
    ]);
    $json = json_decode($body, true);
    $b = $attemptRow();
    check('still LOCKED', ($json['status'] ?? '') === 'LOCKED', 'got ' . ($json['status'] ?? 'none'));
    check('still not submitted', empty($b['finished_at']));
    check('time_used frozen while locked', (int) $b['time_used'] === $timeAfterLock,
        "before={$timeAfterLock} after={$b['time_used']}");
    check('answer still present', $answers() === 1);

    /* ---------- 3. admin unlock clears lock + strikes ---------- */
    echo "\n[3] Admin unlock (simulate 5 minutes locked)\n";
    // Pretend the attempt has been waiting 5 minutes for the invigilator.
    $db->prepare("UPDATE objective_attempts SET locked_at = NOW() - INTERVAL 300 SECOND WHERE id = ?")
        ->execute([$attemptId]);

    [$code, $body, $err] = http_post($base . '/admin_live_proctor.php?action=unlock', $adminSid, [
        'attempt_id' => $attemptId, 'csrf_token' => $csrf2,
    ]);
    $json = json_decode($body, true);
    $c = $attemptRow();
    $tickAge = $c['last_tick_at'] ? time() - strtotime($c['last_tick_at']) : 9999;
    check('unlock returned success', !empty($json['success']), "http {$code} {$err} body=" . substr($body, 0, 160));
    check('lock cleared', (int) ($c['is_locked'] ?? 1) === 0);
    check('strikes reset', (int) ($c['violation_count'] ?? -1) === 0);
    check('lock start cleared', empty($c['locked_at']), 'locked_at=' . ($c['locked_at'] ?? 'null'));
    check('locked time excused', (int) ($c['excused_seconds'] ?? 0) >= 300,
        'excused=' . ($c['excused_seconds'] ?? '?') . 's');
    check('still not submitted', empty($c['finished_at']));
    check('tick clock restarted at unlock', abs($tickAge) <= 5, "last_tick_at age={$tickAge}s");
    check('time_used unchanged by unlock', (int) $c['time_used'] === $timeAfterLock,
        "before={$timeAfterLock} after={$c['time_used']}");
    check('answer still present after unlock', $answers() === 1);

    /* ---------- 4. student resumes without being charged for the lock ---------- */
    echo "\n[4] Resume after unlock\n";
    [$code, $body, $err] = http_post($ping, $studentSid, [
        'exam_id' => $examId, 'attempt_no' => 1, 'status' => 'active',
        'client_elapsed' => 1, 'csrf_token' => $csrf1,
    ]);
    $json = json_decode($body, true);
    $d = $attemptRow();
    $charged = (int) $d['time_used'] - $timeAfterLock;
    check('status is OK (not locked/submitted)', ($json['status'] ?? '') === 'OK', 'got ' . ($json['status'] ?? 'none'));
    check('not submitted', empty($d['finished_at']));
    check('lock time was not charged (<=5s, not 300s)', $charged >= 0 && $charged <= 5, "charged={$charged}s");
    check('remaining time returned', isset($json['remaining_seconds']) && $json['remaining_seconds'] > 3300,
        'remaining=' . ($json['remaining_seconds'] ?? 'none'));
    check('answer still present after resume', $answers() === 1);

    /* ---------- 5. a long silence is charged, never forgiven ---------- */
    echo "\n[5] Wall-clock timing after a 30-minute silence\n";
    $attemptRowById = function (int $id) use ($db): array {
        $st = $db->prepare("SELECT time_used, violation_count, is_locked, finished_at, last_tick_at,
                time_taken, excused_seconds, locked_at, last_question_index
            FROM objective_attempts WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    };
    $db->prepare("INSERT INTO objective_attempts
        (exam_id, student_id, attempt_no, started_at, time_used, last_tick_at,
         question_order, violation_count, is_locked, last_active, proctor_status)
        VALUES (?, ?, 2, NOW() - INTERVAL 1800 SECOND, 60, NOW() - INTERVAL 1800 SECOND,
                ?, 0, 0, NOW(), 'active')")
        ->execute([$examId, $studentId, (string) $questionId]);
    $attempt2Id = (int) $db->lastInsertId();

    [$code, $body, $err] = http_post($ping, $studentSid, [
        'exam_id' => $examId, 'attempt_no' => 2, 'status' => 'active',
        'question_index' => 7, 'csrf_token' => $csrf1,
    ]);
    $json = json_decode($body, true);
    $e = $attemptRowById($attempt2Id);
    check('heartbeat accepted', ($json['status'] ?? '') === 'OK', 'got ' . ($json['status'] ?? 'none') . " http {$code} {$err}");
    check('30-minute gap was charged', (int) ($e['time_used'] ?? 0) >= 1795, 'time_used=' . ($e['time_used'] ?? '?'));
    check('remaining did not roll back', isset($json['remaining_seconds']) && (int) $json['remaining_seconds'] <= 1810,
        'remaining=' . ($json['remaining_seconds'] ?? 'none'));
    check('resume index stored server-side', (int) ($e['last_question_index'] ?? -1) === 7,
        'last_question_index=' . ($e['last_question_index'] ?? 'null'));

    $beforeSecondTick = (int) ($e['time_used'] ?? 0);
    sleep(2);
    [$code, $body, $err] = http_post($ping, $studentSid, [
        'exam_id' => $examId, 'attempt_no' => 2, 'status' => 'active', 'csrf_token' => $csrf1,
    ]);
    $e2 = $attemptRowById($attempt2Id);
    check('time_used never decreases', (int) ($e2['time_used'] ?? 0) >= $beforeSecondTick,
        'before=' . $beforeSecondTick . ' after=' . ($e2['time_used'] ?? '?'));

    /* ---------- 6. retryable submit API ---------- */
    echo "\n[6] Retryable submission API\n";
    $submitUrl = $base . '/objective_exam_api?action=submit_exam';
    [$code, $body, $err] = http_post($submitUrl, $studentSid, [
        'exam_id' => $examId, 'attempt_no' => 2, 'csrf_token' => $csrf1,
        'answers_json' => json_encode([$questionId => 'B']),
    ]);
    $json = json_decode($body, true);
    $f = $attemptRowById($attempt2Id);
    check('submit endpoint returned success', !empty($json['success']), "http {$code} {$err} body=" . substr($body, 0, 160));
    check('attempt was submitted', !empty($f['finished_at']));
    check('final time is wall-clock based', (int) ($f['time_taken'] ?? 0) >= 1795,
        'time_taken=' . ($f['time_taken'] ?? '?'));

    [$code, $body, $err] = http_post($submitUrl, $studentSid, [
        'exam_id' => $examId, 'attempt_no' => 2, 'csrf_token' => $csrf1,
        'answers_json' => json_encode([$questionId => 'B']),
    ]);
    $json = json_decode($body, true);
    check('repeat submit is idempotent', !empty($json['success']) && !empty($json['already']),
        "http {$code} {$err} body=" . substr($body, 0, 160));

    /* ---------- 7. session outlives the exam ---------- */
    echo "\n[7] Session lifetime\n";
    $effectiveLifetime = function_exists('veritas_session_lifetime') ? veritas_session_lifetime() : 0;
    check('effective session lifetime >= 4 hours', $effectiveLifetime >= 14400,
        'effective=' . $effectiveLifetime . ' SESSION_LIFETIME=' . (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 'undefined'));
    check('session.gc_maxlifetime >= 8 hours', (int) ini_get('session.gc_maxlifetime') >= 28800,
        'gc_maxlifetime=' . ini_get('session.gc_maxlifetime'));

    /* ---------- 8. timing arithmetic ---------- */
    echo "\n[8] Timing arithmetic\n";
    $now = time();
    $el = qe_attempt_elapsed_seconds(['started_at' => date('Y-m-d H:i:s', $now - 100), 'time_used' => 0]);
    check('wall clock elapsed', $el >= 100 && $el <= 101, "elapsed={$el}");
    $el = qe_attempt_elapsed_seconds(['started_at' => date('Y-m-d H:i:s', $now - 100), 'excused_seconds' => 30]);
    check('excused time subtracted', $el >= 70 && $el <= 71, "elapsed={$el}");
    $el = qe_attempt_elapsed_seconds([
        'started_at' => date('Y-m-d H:i:s', $now - 100),
        'excused_seconds' => 30,
        'locked_at' => date('Y-m-d H:i:s', $now - 40),
    ]);
    check('current lock excused', $el >= 30 && $el <= 31, "elapsed={$el}");
    check('missing started_at falls back to time_used', qe_attempt_elapsed_seconds(['time_used' => 55]) === 55);
    check('remaining never negative', qe_remaining_seconds(['duration_minutes' => 1], ['started_at' => date('Y-m-d H:i:s', $now - 3600)]) === 0);
} catch (Throwable $e) {
    $fail++;
    echo "  FAIL  test aborted: " . $e->getMessage() . "\n";
} finally {
    /* ---------- cleanup ---------- */
    echo "\n[cleanup]\n";
    try {
        if ($attemptId > 0) {
            $db->prepare("DELETE FROM exam_violations WHERE attempt_id = ? OR exam_id = ?")->execute([$attemptId, $examId]);
            $db->prepare("DELETE FROM objective_attempts WHERE id = ?")->execute([$attemptId]);
        }
        if ($attempt2Id > 0) {
            $db->prepare("DELETE FROM objective_attempts WHERE id = ?")->execute([$attempt2Id]);
        }
        if ($examId > 0) {
            $db->prepare("DELETE FROM objective_answers WHERE exam_id = ?")->execute([$examId]);
            $db->prepare("DELETE FROM exams WHERE id = ?")->execute([$examId]);
        }
        if ($studentId > 0) {
            $db->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);
        }
        if ($examId > 0 || $studentId > 0) {
            $db->prepare("DELETE FROM audit_trail WHERE (table_name = 'exams' AND record_id = ?) OR (table_name = 'students' AND record_id = ?)")
                ->execute([$examId, $studentId]);
        }
        foreach ($GLOBALS['SESSION_PATHS'] as $dir) {
            @unlink($dir . '/sess_' . $studentSid);
            @unlink($dir . '/sess_' . $adminSid);
        }
        echo "  removed test exam #{$examId}, student #{$studentId}, attempts #{$attemptId}/#{$attempt2Id} and both sessions\n";
    } catch (Throwable $e) {
        echo "  WARNING cleanup issue: " . $e->getMessage() . "\n";
        echo "  remove manually: exam #{$examId}, student #{$studentId}, attempt #{$attemptId}\n";
    }
}

echo "\nResult: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
