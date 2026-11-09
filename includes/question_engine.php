<?php
/**
 * Objective question engine.
 *
 * One place for everything the objective exam modules need to agree on:
 * question types, answer formats, marking, attempt timing, TXT import and
 * the per-student result calculation used by reports.
 *
 * Stored formats (objective_questions.correct_option / objective_answers.selected_option)
 *   mcq_single  "B"
 *   mcq_multi   "A,C"      letters sorted, comma separated
 *   true_false  "A" | "B"  option_a = True, option_b = False
 *   mtf         "TFFT"     one character per statement, in authoring order
 *                          (answers may contain "-" for a statement left blank)
 */

const QE_SCHEMA_VERSION = 7;
const QE_TICK_GAP = 45;          // seconds; a longer silence is treated as an interruption
const QE_MTF_MIN = 2;
const QE_MTF_MAX = 10;
const QE_OPTION_KEYS = ['A', 'B', 'C', 'D'];

function qe_types(): array
{
    return [
        'mcq_single' => 'Single answer',
        'mcq_multi'  => 'Multiple answer',
        'true_false' => 'True / False',
        'mtf'        => 'Multiple True/False',
    ];
}

function qe_type_label(string $type): string
{
    return qe_types()[$type] ?? 'Single answer';
}

/* ------------------------------------------------------------------ *
 *  Schema
 * ------------------------------------------------------------------ */

/**
 * Brings the objective tables up to the shape this engine expects.
 * Runs once per server (a flag file is written in logs/), so the cost on a
 * normal request is a single is_file() call.
 */
function qe_ensure_schema(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $flag = dirname(__DIR__) . '/logs/.qe_schema_v' . QE_SCHEMA_VERSION;
    if (is_file($flag)) {
        return;
    }

    try {
        $db->query("SELECT GET_LOCK('qe_schema_upgrade', 20)");
        qe_run_migrations($db);
        $db->query("SELECT RELEASE_LOCK('qe_schema_upgrade')");
        if (!is_dir(dirname($flag))) {
            @mkdir(dirname($flag), 0755, true);
        }
        @file_put_contents($flag, date('c'));
    } catch (Throwable $e) {
        error_log('question_engine schema upgrade failed: ' . $e->getMessage());
    }
}

function qe_columns(PDO $db, string $table): array
{
    $cols = [];
    foreach ($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cols[$c['Field']] = $c;
    }
    return $cols;
}

function qe_indexes(PDO $db, string $table): array
{
    $idx = [];
    foreach ($db->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $i) {
        $idx[$i['Key_name']] = true;
    }
    return $idx;
}

function qe_run_migrations(PDO $db): void
{
    // objective_questions
    $c = qe_columns($db, 'objective_questions');
    if (!isset($c['question_type'])) {
        $db->exec("ALTER TABLE objective_questions ADD COLUMN question_type VARCHAR(20) NOT NULL DEFAULT 'mcq_single' AFTER exam_id");
    }
    if (!isset($c['question_data'])) {
        $db->exec("ALTER TABLE objective_questions ADD COLUMN question_data TEXT NULL AFTER question_image");
    }
    if (stripos($c['correct_option']['Type'] ?? '', 'varchar(64)') === false) {
        $db->exec("ALTER TABLE objective_questions MODIFY correct_option VARCHAR(64) NOT NULL DEFAULT ''");
    }
    foreach (['a', 'b', 'c', 'd'] as $o) {
        $col = $c["option_$o"] ?? null;
        if ($col && (stripos($col['Type'], 'varchar(1000)') === false || $col['Default'] === null)) {
            $db->exec("ALTER TABLE objective_questions MODIFY option_$o VARCHAR(1000) NOT NULL DEFAULT ''");
        }
    }
    $i = qe_indexes($db, 'objective_questions');
    if (!isset($i['idx_oq_exam_live'])) {
        $db->exec("ALTER TABLE objective_questions ADD INDEX idx_oq_exam_live (exam_id, deleted_at)");
    }

    // objective_answers
    $c = qe_columns($db, 'objective_answers');
    if (stripos($c['selected_option']['Type'] ?? '', 'varchar(255)') === false) {
        $db->exec("ALTER TABLE objective_answers MODIFY selected_option VARCHAR(255) NOT NULL DEFAULT ''");
    }

    // objective_attempts
    $c = qe_columns($db, 'objective_attempts');
    $fresh_timing = false;
    if (!isset($c['time_used'])) {
        $db->exec("ALTER TABLE objective_attempts ADD COLUMN time_used INT NOT NULL DEFAULT 0 AFTER time_taken");
        $fresh_timing = true;
    }
    if (!isset($c['last_tick_at'])) {
        $db->exec("ALTER TABLE objective_attempts ADD COLUMN last_tick_at DATETIME NULL AFTER time_used");
    }
    if (!isset($c['remote_command'])) {
        $db->exec("ALTER TABLE objective_attempts ADD COLUMN remote_command VARCHAR(50) NULL");
    }
    if ($fresh_timing) {
        // Carry existing attempts over: finished ones keep their recorded time,
        // open ones continue from wall-clock time so nobody gains minutes.
        $db->exec("UPDATE objective_attempts SET time_used = COALESCE(time_taken, 0) WHERE finished_at IS NOT NULL");
        $db->exec("UPDATE objective_attempts SET time_used = LEAST(GREATEST(TIMESTAMPDIFF(SECOND, started_at, NOW()), 0), 86400), last_tick_at = NOW() WHERE finished_at IS NULL AND started_at IS NOT NULL");
    }
    $i = qe_indexes($db, 'objective_attempts');
    if (!isset($i['idx_oa_exam_open'])) {
        $db->exec("ALTER TABLE objective_attempts ADD INDEX idx_oa_exam_open (exam_id, finished_at)");
    }
    if (!isset($i['idx_oa_student_exam'])) {
        $db->exec("ALTER TABLE objective_attempts ADD INDEX idx_oa_student_exam (student_id, exam_id)");
    }

    // exams
    $c = qe_columns($db, 'exams');
    if (!isset($c['partial_scoring'])) {
        $db->exec("ALTER TABLE exams ADD COLUMN partial_scoring TINYINT(1) NOT NULL DEFAULT 1 AFTER shuffle_options");
    }

    if (!isset($c['math_notation'])) {
        $db->exec("ALTER TABLE exams ADD COLUMN math_notation TINYINT(1) NOT NULL DEFAULT 0 AFTER partial_scoring");
    }
    if (!isset($c['late_start_minutes'])) {
        $db->exec("ALTER TABLE exams ADD COLUMN late_start_minutes INT NULL DEFAULT NULL AFTER end_time");
    }
    if (isset($c['type']) && stripos($c['type']['Type'], "'research'") === false) {
        $db->exec("ALTER TABLE exams MODIFY type ENUM('practical','objective','viva','research') DEFAULT 'practical'");
    }

    // device approvals are cleared between exams; keep a record of each reset
    $db->exec("CREATE TABLE IF NOT EXISTS device_approval_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reset_at DATETIME NOT NULL,
        reset_by VARCHAR(100) NULL,
        ip_address VARCHAR(45) NOT NULL,
        device_info TEXT NULL,
        status VARCHAR(20) NULL,
        first_seen DATETIME NULL,
        last_seen DATETIME NULL,
        KEY idx_dal_reset (reset_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // exam_violations is queried by attempt on every live-proctor refresh
    $i = qe_indexes($db, 'exam_violations');
    if (!isset($i['idx_ev_attempt'])) {
        $db->exec("ALTER TABLE exam_violations ADD INDEX idx_ev_attempt (attempt_id)");
    }

    qe_migrate_groups($db);
}

/**
 * Grouped examinations and exam centres (schema 6).
 *   main server  : exam_groups, exam_centers, center_tokens, center_students
 *   centre server: exam_groups (copy), center_links, sync_map
 *   both         : sync_log, exams.group_id, exams.remote_id
 */
function qe_migrate_groups(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS exam_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        code VARCHAR(40) NOT NULL,
        description TEXT NULL,
        activation_opens DATETIME NULL,
        activation_closes DATETIME NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        remote_id INT NULL,
        package_version INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        deleted_at DATETIME NULL,
        KEY idx_eg_remote (remote_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $c = qe_columns($db, 'exams');
    if (!isset($c['group_id'])) {
        $db->exec("ALTER TABLE exams ADD COLUMN group_id INT NULL DEFAULT NULL, ADD INDEX idx_exams_group (group_id)");
    }
    if (!isset($c['remote_id'])) {
        $db->exec("ALTER TABLE exams ADD COLUMN remote_id INT NULL DEFAULT NULL");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS exam_centers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        code VARCHAR(40) NOT NULL,
        location VARCHAR(190) NULL,
        manager_name VARCHAR(190) NULL,
        manager_contact VARCHAR(190) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        UNIQUE KEY uq_center_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS center_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        center_id INT NOT NULL,
        prefix VARCHAR(12) NOT NULL,
        pkg_key CHAR(64) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'issued',
        issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        issued_by VARCHAR(100) NULL,
        activated_at DATETIME NULL,
        activated_ip VARCHAR(64) NULL,
        last_download_at DATETIME NULL,
        last_sync_at DATETIME NULL,
        sync_count INT NOT NULL DEFAULT 0,
        revoked_at DATETIME NULL,
        UNIQUE KEY uq_token_prefix (prefix),
        KEY idx_ct_group (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS center_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token_id INT NOT NULL,
        student_id INT NOT NULL,
        UNIQUE KEY uq_cs (token_id, student_id),
        KEY idx_cs_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS center_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        main_url VARCHAR(255) NULL,
        prefix VARCHAR(12) NOT NULL,
        pkg_key CHAR(64) NOT NULL,
        center_name VARCHAR(190) NULL,
        center_code VARCHAR(40) NULL,
        package_version INT NULL,
        activated_at DATETIME NULL,
        last_sync_at DATETIME NULL,
        last_sync_status TEXT NULL,
        UNIQUE KEY uq_link_prefix (prefix)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS sync_map (
        id INT AUTO_INCREMENT PRIMARY KEY,
        link_id INT NOT NULL,
        entity VARCHAR(20) NOT NULL,
        remote_id INT NOT NULL,
        local_id INT NOT NULL,
        UNIQUE KEY uq_sync_map (link_id, entity, remote_id),
        KEY idx_sync_local (entity, local_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // group examiners: bound to one exam group, one sign-in at a time
    $x = qe_columns($db, 'examiners');
    if (!isset($x['firstname'])) {
        $db->exec("ALTER TABLE examiners ADD COLUMN firstname VARCHAR(100) NULL, ADD COLUMN lastname VARCHAR(100) NULL");
    }
    if (!isset($x['group_id'])) {
        $db->exec("ALTER TABLE examiners ADD COLUMN group_id INT NULL DEFAULT NULL, ADD INDEX idx_examiners_group (group_id)");
    }
    if (!isset($x['session_token'])) {
        $db->exec("ALTER TABLE examiners ADD COLUMN session_token VARCHAR(128) NULL, ADD COLUMN last_seen_at DATETIME NULL");
    }
    $g = qe_columns($db, 'exam_groups');
    if (!isset($g['exam_ends'])) {
        $db->exec("ALTER TABLE exam_groups ADD COLUMN exam_ends DATETIME NULL AFTER activation_closes, ADD COLUMN ended_at DATETIME NULL AFTER exam_ends");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS sync_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token_id INT NULL,
        link_id INT NULL,
        action VARCHAR(30) NOT NULL,
        ok TINYINT(1) NOT NULL DEFAULT 1,
        summary TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sl_token (token_id),
        KEY idx_sl_link (link_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ------------------------------------------------------------------ *
 *  Question records
 * ------------------------------------------------------------------ */

/** Decodes question_data and fills defaults so callers can rely on the shape. */
function qe_hydrate(array $q): array
{
    $type = $q['question_type'] ?? 'mcq_single';
    if (!isset(qe_types()[$type])) {
        $type = 'mcq_single';
    }
    $q['question_type'] = $type;
    $data = [];
    if (!empty($q['question_data'])) {
        $decoded = json_decode($q['question_data'], true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    $q['statements'] = [];
    foreach (($data['statements'] ?? []) as $s) {
        $q['statements'][] = is_array($s) ? (string) ($s['text'] ?? '') : (string) $s;
    }
    $q['scoring'] = in_array($data['scoring'] ?? null, ['partial', 'all'], true) ? $data['scoring'] : null;
    $q['mark'] = isset($q['mark']) && $q['mark'] !== '' ? (float) $q['mark'] : 1.0;
    $q['correct_option'] = strtoupper(trim((string) ($q['correct_option'] ?? '')));
    return $q;
}

/** Loads live (not deleted) questions for an exam, keyed by id. */
function qe_load_questions(PDO $db, int $exam_id): array
{
    $st = $db->prepare("SELECT * FROM objective_questions WHERE exam_id = ? AND deleted_at IS NULL ORDER BY id");
    $st->execute([$exam_id]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['id']] = qe_hydrate($row);
    }
    return $out;
}

/**
 * Validates and normalises a question coming from the admin form, CSV or TXT.
 *
 * Input keys: type, text, image, mark, options[A..D] => [text, image],
 * correct (string|array), statements => [[text, answer T|F]], scoring.
 * Returns ['ok' => bool, 'error' => string, 'row' => column values].
 */
function qe_build_question(array $in): array
{
    $type = qe_normalise_type($in['type'] ?? 'mcq_single');
    if ($type === null) {
        return ['ok' => false, 'error' => 'Unknown question type "' . ($in['type'] ?? '') . '".'];
    }
    $text = trim((string) ($in['text'] ?? ''));
    if ($text === '') {
        return ['ok' => false, 'error' => 'Question text is empty.'];
    }
    $mark = isset($in['mark']) && $in['mark'] !== '' ? (float) $in['mark'] : null;

    $row = [
        'question_type'  => $type,
        'question_text'  => $text,
        'question_image' => trim((string) ($in['image'] ?? '')) ?: null,
        'question_data'  => null,
        'option_a' => '', 'option_a_image' => null,
        'option_b' => '', 'option_b_image' => null,
        'option_c' => '', 'option_c_image' => null,
        'option_d' => '', 'option_d_image' => null,
        'correct_option' => '',
        'mark' => 1.0,
    ];
    $scoring = in_array($in['scoring'] ?? null, ['partial', 'all'], true) ? $in['scoring'] : null;

    if ($type === 'true_false') {
        $ans = qe_parse_bool($in['correct'] ?? '');
        if ($ans === null) {
            return ['ok' => false, 'error' => 'True/False question needs an answer of TRUE or FALSE.'];
        }
        $row['option_a'] = 'True';
        $row['option_b'] = 'False';
        $row['correct_option'] = $ans ? 'A' : 'B';
        $row['mark'] = $mark ?? 1.0;
        return ['ok' => true, 'row' => $row];
    }

    if ($type === 'mtf') {
        $statements = [];
        $key = '';
        foreach (($in['statements'] ?? []) as $s) {
            $t = trim((string) ($s['text'] ?? ''));
            if ($t === '') {
                continue;
            }
            $a = qe_parse_bool($s['answer'] ?? '');
            if ($a === null) {
                return ['ok' => false, 'error' => 'Statement "' . qe_short($t) . '" has no TRUE/FALSE answer.'];
            }
            $statements[] = ['text' => $t];
            $key .= $a ? 'T' : 'F';
        }
        $n = count($statements);
        if ($n < QE_MTF_MIN || $n > QE_MTF_MAX) {
            return ['ok' => false, 'error' => 'Multiple True/False needs between ' . QE_MTF_MIN . ' and ' . QE_MTF_MAX . " statements ($n given)."];
        }
        $data = ['statements' => $statements];
        if ($scoring) {
            $data['scoring'] = $scoring;
        }
        $row['question_data'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        $row['correct_option'] = $key;
        $row['mark'] = $mark ?? (float) $n;
        return ['ok' => true, 'row' => $row];
    }

    // mcq_single / mcq_multi
    $present = [];
    foreach (QE_OPTION_KEYS as $k) {
        $opt = $in['options'][$k] ?? null;
        $t = trim((string) (is_array($opt) ? ($opt['text'] ?? '') : ($opt ?? '')));
        $img = is_array($opt) ? trim((string) ($opt['image'] ?? '')) : '';
        $lk = strtolower($k);
        $row["option_$lk"] = $t;
        $row["option_{$lk}_image"] = $img !== '' ? $img : null;
        if ($t !== '' || $img !== '') {
            $present[] = $k;
        }
    }
    if (count($present) < 2) {
        return ['ok' => false, 'error' => 'At least two options are required.'];
    }
    $letters = qe_parse_letters($in['correct'] ?? '');
    if (!$letters) {
        return ['ok' => false, 'error' => 'No correct option given.'];
    }
    foreach ($letters as $l) {
        if (!in_array($l, $present, true)) {
            return ['ok' => false, 'error' => "Correct option $l has no text."];
        }
    }
    if ($type === 'mcq_single' && count($letters) > 1) {
        return ['ok' => false, 'error' => 'Single-answer question has more than one correct option (' . implode(',', $letters) . '). Use TYPE: MULTI.'];
    }
    if ($type === 'mcq_multi' && $scoring) {
        $row['question_data'] = json_encode(['scoring' => $scoring]);
    }
    $row['correct_option'] = implode(',', $letters);
    $row['mark'] = $mark ?? 1.0;
    return ['ok' => true, 'row' => $row];
}

function qe_insert_question(PDO $db, int $exam_id, array $row): int
{
    $cols = array_keys($row);
    $sql = "INSERT INTO objective_questions (exam_id, " . implode(', ', $cols) . ") VALUES (?" . str_repeat(', ?', count($cols)) . ")";
    $db->prepare($sql)->execute(array_merge([$exam_id], array_values($row)));
    return (int) $db->lastInsertId();
}

function qe_update_question(PDO $db, int $exam_id, int $id, array $row): void
{
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($row)));
    $db->prepare("UPDATE objective_questions SET $sets WHERE id = ? AND exam_id = ?")
        ->execute(array_merge(array_values($row), [$id, $exam_id]));
}

function qe_normalise_type($t): ?string
{
    $t = strtolower(trim((string) $t));
    $t = str_replace([' ', '-', '/'], '_', $t);
    $map = [
        '' => 'mcq_single', 'mcq' => 'mcq_single', 'single' => 'mcq_single', 'mcq_single' => 'mcq_single', 'sba' => 'mcq_single',
        'multi' => 'mcq_multi', 'multiple' => 'mcq_multi', 'mcq_multi' => 'mcq_multi', 'mmcq' => 'mcq_multi', 'multiple_answer' => 'mcq_multi', 'ma' => 'mcq_multi',
        'tf' => 'true_false', 'true_false' => 'true_false', 'truefalse' => 'true_false', 'boolean' => 'true_false',
        'mtf' => 'mtf', 'multiple_true_false' => 'mtf', 'multi_tf' => 'mtf', 'multiple_tf' => 'mtf', 'mtfq' => 'mtf',
    ];
    return $map[$t] ?? null;
}

function qe_parse_bool($v): ?bool
{
    if (is_bool($v)) {
        return $v;
    }
    $v = strtoupper(trim((string) $v));
    if (in_array($v, ['T', 'TRUE', 'A', 'YES', '1'], true)) {
        return true;
    }
    if (in_array($v, ['F', 'FALSE', 'B', 'NO', '0'], true)) {
        return false;
    }
    return null;
}

/** "A, c" / "AC" / ['C','A'] -> ['A','C'] (A-D only, sorted, unique). */
function qe_parse_letters($v): array
{
    if (is_array($v)) {
        $v = implode(',', $v);
    }
    $v = strtoupper((string) $v);
    $v = preg_replace('/\bAND\b|[\.\(\)&\/;]/', ',', $v);
    if (preg_match('/[^A-D,\s]/', $v)) {
        return [];
    }
    preg_match_all('/[A-D]/', $v, $m);
    $letters = array_values(array_unique($m[0]));
    sort($letters);
    return $letters;
}

function qe_short(string $t, int $len = 40): string
{
    return mb_strlen($t) > $len ? mb_substr($t, 0, $len - 1) . '…' : $t;
}

/* ------------------------------------------------------------------ *
 *  Answers and marking
 * ------------------------------------------------------------------ */

/**
 * Turns whatever the browser sent into the stored answer format.
 * Returns '' for "no answer" and null for something invalid.
 */
function qe_normalise_answer(array $q, $raw): ?string
{
    $type = $q['question_type'];
    if (is_array($raw)) {
        $raw = implode(',', $raw);
    }
    $raw = strtoupper(trim((string) $raw));
    if ($raw === '') {
        return '';
    }
    switch ($type) {
        case 'mtf':
            $n = count($q['statements']);
            if (strlen($raw) !== $n || preg_match('/[^TF\-]/', $raw)) {
                return null;
            }
            return trim($raw, '-') === '' ? '' : $raw;
        case 'mcq_multi':
            $letters = qe_parse_letters($raw);
            return $letters ? implode(',', $letters) : null;
        case 'true_false':
            return in_array($raw, ['A', 'B'], true) ? $raw : null;
        default:
            return in_array($raw, QE_OPTION_KEYS, true) ? $raw : null;
    }
}

function qe_uses_partial(array $q, array $exam): bool
{
    if ($q['scoring'] === 'partial') {
        return true;
    }
    if ($q['scoring'] === 'all') {
        return false;
    }
    return !isset($exam['partial_scoring']) || (int) $exam['partial_scoring'] === 1;
}

/**
 * Marks one question.
 * status: correct | partial | wrong | unanswered
 * For MTF, 'statements' lists per-statement results (true/false/null for blank).
 */
function qe_score(array $q, ?string $answer, array $exam = []): array
{
    $max = (float) $q['mark'];
    $answer = strtoupper(trim((string) $answer));
    $key = $q['correct_option'];
    $res = ['awarded' => 0.0, 'max' => $max, 'status' => 'unanswered', 'fraction' => 0.0];

    if ($q['question_type'] === 'mtf') {
        $n = max(1, strlen($key));
        $right = 0;
        $answered = 0;
        $per = [];
        for ($i = 0; $i < strlen($key); $i++) {
            $a = $answer[$i] ?? '-';
            if ($a === 'T' || $a === 'F') {
                $answered++;
                $ok = ($a === $key[$i]);
                $right += $ok ? 1 : 0;
                $per[] = $ok;
            } else {
                $per[] = null;
            }
        }
        $res['statements'] = $per;
        $res['right'] = $right;
        $res['of'] = strlen($key);
        if ($answered === 0) {
            return $res;
        }
        $fraction = qe_uses_partial($q, $exam) ? $right / $n : ($right === $n ? 1.0 : 0.0);
        return qe_finish_score($res, $fraction, $max);
    }

    if ($answer === '') {
        return $res;
    }

    if ($q['question_type'] === 'mcq_multi') {
        $want = array_filter(explode(',', $key));
        $got = qe_parse_letters($answer);
        $hits = count(array_intersect($got, $want));
        $wrong = count(array_diff($got, $want));
        if (qe_uses_partial($q, $exam)) {
            // Each wrong pick cancels a right one, so ticking every box earns nothing.
            $fraction = count($want) ? max(0, $hits - $wrong) / count($want) : 0;
        } else {
            $fraction = ($hits === count($want) && $wrong === 0) ? 1.0 : 0.0;
        }
        return qe_finish_score($res, $fraction, $max);
    }

    return qe_finish_score($res, $answer === $key ? 1.0 : 0.0, $max);
}

function qe_finish_score(array $res, float $fraction, float $max): array
{
    $res['fraction'] = $fraction;
    $res['awarded'] = round($max * $fraction, 4);
    $res['status'] = $fraction >= 0.9999 ? 'correct' : ($fraction > 0 ? 'partial' : 'wrong');
    return $res;
}

/** Question ids that belong to an attempt (its stored order, or the whole bank). */
function qe_attempt_question_ids(array $attempt, array $questions): array
{
    if (!empty($attempt['question_order'])) {
        $ids = array_map('intval', explode(',', $attempt['question_order']));
        return array_values(array_filter($ids, fn($id) => isset($questions[$id])));
    }
    return array_keys($questions);
}

/**
 * Marks a full attempt.
 * Returns raw, possible, scaled, scaled_total and per-question results.
 */
function qe_score_attempt(array $exam, array $questions, array $attempt, array $answers): array
{
    $raw = 0.0;
    $possible = 0.0;
    $items = [];
    foreach (qe_attempt_question_ids($attempt, $questions) as $qid) {
        $q = $questions[$qid];
        $r = qe_score($q, $answers[$qid] ?? '', $exam);
        $items[$qid] = $r;
        $raw += $r['awarded'];
        $possible += $r['max'];
    }
    $total_mark = (float) ($exam['total_mark'] ?? 0);
    $scaled = $raw;
    $scaled_total = $possible;
    if ($total_mark > 0) {
        $scaled = $possible > 0 ? $raw / $possible * $total_mark : 0;
        $scaled_total = $total_mark;
    }
    return [
        'raw' => round($raw, 4),
        'possible' => round($possible, 4),
        'scaled' => round($scaled, 4),
        'scaled_total' => round($scaled_total, 4),
        'items' => $items,
    ];
}

/** All answers for an exam (optionally one student) in one query: [student][attempt][question] => answer. */
function qe_load_answers(PDO $db, int $exam_id, ?int $student_id = null): array
{
    $sql = "SELECT student_id, attempt_no, question_id, selected_option FROM objective_answers WHERE exam_id = ?";
    $args = [$exam_id];
    if ($student_id) {
        $sql .= " AND student_id = ?";
        $args[] = $student_id;
    }
    $st = $db->prepare($sql);
    $st->execute($args);
    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $out[(int) $r['student_id']][(int) ($r['attempt_no'] ?: 1)][(int) $r['question_id']] = $r['selected_option'];
    }
    return $out;
}

/**
 * Final result for every student who finished the exam, honouring the exam's
 * grading method (highest, average, first, last).
 *
 * Each entry: student_id, attempts (count), attempt_no (null for average),
 * score, total, items [qid => awarded or null when not assigned].
 */
function qe_exam_results(PDO $db, array $exam, ?array $questions = null, ?int $only_student = null): array
{
    $exam_id = (int) $exam['id'];
    $questions = $questions ?? qe_load_questions($db, $exam_id);
    $sql = "SELECT * FROM objective_attempts WHERE exam_id = ? AND finished_at IS NOT NULL";
    $args = [$exam_id];
    if ($only_student) {
        $sql .= " AND student_id = ?";
        $args[] = $only_student;
    }
    $st = $db->prepare($sql . " ORDER BY student_id, attempt_no");
    $st->execute($args);
    $by_student = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $by_student[(int) $a['student_id']][] = $a;
    }
    $answers = qe_load_answers($db, $exam_id, $only_student);
    $method = $exam['grading_method'] ?? ($exam['score_policy'] ?? 'highest');

    $results = [];
    foreach ($by_student as $sid => $attempts) {
        $scored = [];
        foreach ($attempts as $a) {
            $s = qe_score_attempt($exam, $questions, $a, $answers[$sid][(int) $a['attempt_no']] ?? []);
            $s['attempt_no'] = (int) $a['attempt_no'];
            $s['attempt'] = $a;
            $scored[] = $s;
        }
        $results[$sid] = qe_pick_final($method, $scored, $questions);
        $results[$sid]['student_id'] = $sid;
    }
    return $results;
}

function qe_pick_final(string $method, array $scored, array $questions): array
{
    $count = count($scored);
    if ($method === 'average' && $count > 1) {
        $items = [];
        foreach (array_keys($questions) as $qid) {
            $sum = 0;
            $seen = 0;
            foreach ($scored as $s) {
                if (isset($s['items'][$qid])) {
                    $sum += $s['items'][$qid]['awarded'];
                    $seen++;
                }
            }
            $items[$qid] = $seen ? ['awarded' => round($sum / $seen, 4), 'status' => 'average'] : null;
        }
        return [
            'attempts' => $count,
            'attempt_no' => null,
            'score' => round(array_sum(array_column($scored, 'scaled')) / $count, 4),
            'raw' => round(array_sum(array_column($scored, 'raw')) / $count, 4),
            'total' => $scored[0]['scaled_total'],
            'items' => $items,
            'all' => $scored,
        ];
    }
    if ($method === 'first') {
        $pick = $scored[0];
    } elseif ($method === 'last') {
        $pick = $scored[$count - 1];
    } else {
        $pick = $scored[0];
        foreach ($scored as $s) {
            if ($s['scaled'] > $pick['scaled']) {
                $pick = $s;
            }
        }
    }
    $items = [];
    foreach (array_keys($questions) as $qid) {
        $items[$qid] = $pick['items'][$qid] ?? null;
    }
    return [
        'attempts' => $count,
        'attempt_no' => $pick['attempt_no'],
        'score' => $pick['scaled'],
        'raw' => $pick['raw'],
        'total' => $pick['scaled_total'],
        'items' => $items,
        'all' => $scored,
    ];
}

/** Short text for a single cell in result grids. */
function qe_answer_label(array $q, ?string $answer): string
{
    $answer = (string) $answer;
    if ($answer === '') {
        return '-';
    }
    if ($q['question_type'] === 'true_false') {
        return $answer === 'A' ? 'T' : ($answer === 'B' ? 'F' : $answer);
    }
    return $answer;
}

/* ------------------------------------------------------------------ *
 *  Attempt timing
 * ------------------------------------------------------------------ */

/**
 * Adds the time since the last heartbeat to the attempt's used time.
 * A silence longer than QE_TICK_GAP means the student was cut off (power,
 * network, closed browser), and only the time the browser itself counted
 * ($client_elapsed) is charged for that stretch.
 */
function qe_tick_attempt(PDO $db, int $attempt_id, int $client_elapsed = 0): void
{
    $client_elapsed = max(0, min($client_elapsed, 3600));
    $db->prepare("UPDATE objective_attempts SET
            time_used = time_used + CASE
                WHEN last_tick_at IS NULL THEN 0
                WHEN TIMESTAMPDIFF(SECOND, last_tick_at, NOW()) <= ? THEN GREATEST(TIMESTAMPDIFF(SECOND, last_tick_at, NOW()), 0)
                ELSE LEAST(?, TIMESTAMPDIFF(SECOND, last_tick_at, NOW()))
            END,
            last_tick_at = NOW(),
            last_active = NOW()
        WHERE id = ? AND finished_at IS NULL")
        ->execute([QE_TICK_GAP, $client_elapsed, $attempt_id]);
}

/** Seconds left for an attempt, or null when the exam has neither a duration nor a closing time. */
function qe_remaining_seconds(array $exam, array $attempt): ?int
{
    $rem = null;
    $duration = (int) ($exam['duration_minutes'] ?? 0) * 60;
    if ($duration > 0) {
        $rem = max(0, $duration - (int) ($attempt['time_used'] ?? 0));
    }
    if (!empty($exam['end_time'])) {
        $close = max(0, strtotime($exam['end_time']) - time());
        $rem = $rem === null ? $close : min($rem, $close);
    }
    return $rem;
}

/**
 * When the admin limits late entry, the moment after which nobody may start
 * a new attempt (resuming an attempt already started is still allowed).
 * Returns a timestamp, or null when there is no cutoff.
 */
function qe_late_entry_deadline(array $exam): ?int
{
    $mins = (int) ($exam['late_start_minutes'] ?? 0);
    if ($mins <= 0 || empty($exam['start_time'])) {
        return null;
    }
    return strtotime($exam['start_time']) + $mins * 60;
}

/**
 * Closes an attempt and stores its raw score. Safe to call twice; only the
 * first call has any effect. Returns true when this call closed it.
 */
function qe_finalize_attempt(PDO $db, int $attempt_id): bool
{
    $st = $db->prepare("SELECT * FROM objective_attempts WHERE id = ?");
    $st->execute([$attempt_id]);
    $att = $st->fetch(PDO::FETCH_ASSOC);
    if (!$att || $att['finished_at']) {
        return false;
    }
    $st = $db->prepare("SELECT * FROM exams WHERE id = ?");
    $st->execute([$att['exam_id']]);
    $exam = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    qe_tick_attempt($db, $attempt_id);
    $st = $db->prepare("SELECT time_used FROM objective_attempts WHERE id = ?");
    $st->execute([$attempt_id]);
    $used = (int) $st->fetchColumn();
    $duration = (int) ($exam['duration_minutes'] ?? 0) * 60;
    if ($duration > 0) {
        $used = min($used, $duration);
    }

    $questions = qe_load_questions($db, (int) $att['exam_id']);
    $ans = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
    $ans->execute([$att['exam_id'], $att['student_id'], $att['attempt_no']]);
    $answers = [];
    foreach ($ans->fetchAll(PDO::FETCH_KEY_PAIR) as $qid => $sel) {
        $answers[(int) $qid] = $sel;
    }
    $score = qe_score_attempt($exam, $questions, $att, $answers);

    $up = $db->prepare("UPDATE objective_attempts SET finished_at = NOW(), time_taken = ?, time_used = ?, score = ?, proctor_status = ? WHERE id = ? AND finished_at IS NULL");
    $up->execute([$used, $used, $score['raw'], 'submitted', $attempt_id]);
    return $up->rowCount() > 0;
}

/**
 * Stores one answer. Returns false when the answer is not valid for the question.
 * An empty answer removes the row so "unanswered" stays unambiguous.
 */
function qe_save_answer(PDO $db, array $q, int $exam_id, int $student_id, int $attempt_no, $raw): bool
{
    $norm = qe_normalise_answer($q, $raw);
    if ($norm === null) {
        return false;
    }
    if ($norm === '') {
        $db->prepare("DELETE FROM objective_answers WHERE exam_id = ? AND student_id = ? AND attempt_no = ? AND question_id = ?")
            ->execute([$exam_id, $student_id, $attempt_no, $q['id']]);
        return true;
    }
    $db->prepare("INSERT INTO objective_answers (exam_id, student_id, attempt_no, question_id, selected_option, submitted_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), submitted_at = VALUES(submitted_at)")
        ->execute([$exam_id, $student_id, $attempt_no, $q['id'], $norm]);
    return true;
}

/* ------------------------------------------------------------------ *
 *  What the student's browser gets
 * ------------------------------------------------------------------ */

/** Allows the handful of tags question writers use (sub/sup for formulas, emphasis, line breaks). */
/**
 * Science notation is switched on per exam (exams.math_notation). Pages that
 * show questions call this once with the exam row.
 */
function qe_notation(?array $exam = null): bool
{
    static $on = false;
    if ($exam !== null) {
        $on = !empty($exam['math_notation']);
    }
    return $on;
}

/** MathJax (served locally) for pages that show questions. */
function qe_mathjax_head(?array $exam = null, string $ready_event = ''): string
{
    $inline = [['\\(', '\\)']];
    if ($exam === null ? qe_notation() : !empty($exam['math_notation'])) {
        $inline[] = ['$', '$'];
    }
    $cfg = [
        'tex' => ['inlineMath' => $inline, 'displayMath' => [['$$', '$$'], ['\\[', '\\]']]],
        'options' => ['enableMenu' => false],
    ];
    $ready = $ready_event !== ''
        ? ', startup: { typeset: false, ready: function () { MathJax.startup.defaultReady(); document.dispatchEvent(new Event(' . json_encode($ready_event) . ')); } }'
        : '';
    $json = json_encode($cfg, JSON_UNESCAPED_SLASHES);
    return '<script>window.MathJax = Object.assign(' . $json . ', {' . ltrim($ready, ', ') . '});</script>' . "\n"
        . '  <script async src="assets/vendor/mathjax/es5/tex-mml-chtml.js"></script>';
}

function qe_safe_html(string $text): string
{
    $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = preg_replace('#&lt;(/?)(b|i|u|em|strong|sub|sup)&gt;#i', '<$1$2>', $html);
    $html = preg_replace('#&lt;br\s*/?&gt;#i', '<br>', $html);
    if (qe_notation()) {
        $html = qe_shorthand($html);
    }
    return nl2br($html, false);
}

/**
 * x^2, 10^-3, x^{n+1}, H_2O, v_{max}  ->  sup / sub, everywhere except inside
 * formulas ($..$, $$..$$, \(..\), \[..\]) which MathJax draws itself.
 */
function qe_shorthand(string $html): string
{
    $parts = preg_split('/(\$\$.+?\$\$|\$[^$\n]+?\$|\\\\\(.+?\\\\\)|\\\\\[.+?\\\\\])/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => &$part) {
        if ($i % 2 === 1) {
            continue;
        }
        $part = preg_replace('/\^\{([^{}]{1,40})\}/u', '<sup>$1</sup>', $part);
        $part = preg_replace('/_\{([^{}]{1,40})\}/u', '<sub>$1</sub>', $part);
        $part = preg_replace('/(?<=[\p{L}\p{N})\]])\^([+\-−]?[\p{L}\p{N}.]{1,8}[+\-]?)/u', '<sup>$1</sup>', $part);
        $part = preg_replace('/(?<=[\p{L})\]])_([\p{N}]{1,4}|[\p{L}](?![\p{L}]))/u', '<sub>$1</sub>', $part);
    }
    unset($part);
    return implode('', $parts);
}

function qe_safe_image(?string $file): ?string
{
    $file = trim((string) $file);
    if ($file === '' || preg_match('#(^/|\.\.|\\\\|:)#', $file)) {
        return null;
    }
    return 'uploads/' . str_replace('%2F', '/', rawurlencode($file));
}

/** Stable shuffle: same attempt always sees the same order, no state to store. */
function qe_stable_order(array $keys, string $seed): array
{
    usort($keys, fn($a, $b) => strcmp(sha1($seed . '|' . $a), sha1($seed . '|' . $b)));
    return $keys;
}

/** Question as sent to the exam page: no answer key, options/statements in display order. */
function qe_public_question(array $q, int $attempt_id, bool $shuffle): array
{
    $out = [
        'id' => (int) $q['id'],
        'type' => $q['question_type'],
        'text' => qe_safe_html($q['question_text']),
        'image' => qe_safe_image($q['question_image'] ?? ''),
        'mark' => (float) $q['mark'],
        'options' => [],
        'statements' => [],
    ];
    if ($q['question_type'] === 'mtf') {
        $keys = array_keys($q['statements']);
        if ($shuffle) {
            $keys = qe_stable_order($keys, $attempt_id . ':' . $q['id'] . ':s');
        }
        foreach ($keys as $i) {
            $out['statements'][] = ['key' => $i, 'text' => qe_safe_html($q['statements'][$i])];
        }
        $out['count'] = count($q['statements']);
        return $out;
    }
    $keys = [];
    foreach (QE_OPTION_KEYS as $k) {
        $lk = strtolower($k);
        if (trim((string) $q["option_$lk"]) !== '' || !empty($q["option_{$lk}_image"])) {
            $keys[] = $k;
        }
    }
    if ($shuffle && $q['question_type'] !== 'true_false') {
        $keys = qe_stable_order($keys, $attempt_id . ':' . $q['id']);
    }
    foreach ($keys as $k) {
        $lk = strtolower($k);
        $out['options'][] = [
            'key' => $k,
            'text' => qe_safe_html((string) $q["option_$lk"]),
            'image' => qe_safe_image($q["option_{$lk}_image"] ?? ''),
        ];
    }
    return $out;
}

/* ------------------------------------------------------------------ *
 *  TXT import / export
 * ------------------------------------------------------------------ */

/**
 * Parses the question TXT format. Plain Aiken files work unchanged; the
 * optional directives TYPE:, MARK:, IMAGE: and SCORING: unlock the other types.
 *
 * Returns ['questions' => [input arrays for qe_build_question + 'line'],
 *          'errors' => [['line' => n, 'message' => ...]]]
 */
function qe_parse_txt(string $content): array
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $lines = explode("\n", $content);

    $questions = [];
    $errors = [];
    $pending = [];
    $cur = null;

    $finish = function (?string $answer) use (&$cur, &$questions, &$errors) {
        if ($cur === null) {
            return;
        }
        $built = qe_txt_block_to_input($cur, $answer);
        if (isset($built['error'])) {
            $errors[] = ['line' => $cur['line'], 'message' => $built['error']];
        } else {
            $check = qe_build_question($built);
            if ($check['ok']) {
                $built['line'] = $cur['line'];
                $questions[] = $built;
            } else {
                $errors[] = ['line' => $cur['line'], 'message' => $check['error']];
            }
        }
        $cur = null;
    };

    foreach ($lines as $idx => $rawLine) {
        $no = $idx + 1;
        $line = trim(preg_replace('/[\x{00A0}\t]+/u', ' ', $rawLine));

        if (strncmp($line, '//', 2) === 0) {
            continue; // note for the person typing the file
        }
        if ($line === '') {
            if ($cur !== null && qe_txt_block_complete($cur)) {
                $finish(null);
            }
            continue;
        }

        if (preg_match('/^(TYPE|MARKS?|IMAGE|SCORING)\s*[:=]\s*(.*)$/i', $line, $m)) {
            $name = strtoupper($m[1]) === 'MARKS' ? 'MARK' : strtoupper($m[1]);
            $value = trim($m[2]);
            if ($name === 'TYPE') {
                if ($cur !== null) {
                    $finish(null);
                }
                $pending = ['TYPE' => $value, 'line' => $no];
            } elseif ($cur !== null) {
                $cur['directives'][$name] = $value;
            } else {
                $pending[$name] = $value;
                $pending['line'] = $pending['line'] ?? $no;
            }
            continue;
        }

        if (preg_match('/^(ANSWERS?|ANS|KEY|CORRECT)\s*[:=]\s*(.*)$/i', $line, $m)) {
            if ($cur === null) {
                $errors[] = ['line' => $no, 'message' => 'ANSWER line without a question above it.'];
                continue;
            }
            $finish(trim($m[2]));
            continue;
        }

        if ($cur === null) {
            $cur = [
                'line' => $pending['line'] ?? $no,
                'directives' => $pending,
                'stem' => qe_strip_number($line),
                'items' => [],
            ];
            unset($cur['directives']['line']);
            $pending = [];
            continue;
        }

        if (preg_match('/^\(?([A-Ja-j]|[0-9]{1,2}|i{1,3}|iv|vi{0,3}|ix|x)\s*[\.\)]\s*(.+)$/', $line, $m)
            || preg_match('/^\(([A-Ja-j]|[0-9]{1,2}|i{1,3}|iv|vi{0,3}|ix|x)\)\s*(.+)$/', $line, $m)) {
            $cur['items'][] = qe_txt_item($m[1], $m[2]);
            continue;
        }

        // Continuation: wrapped option/statement text, or a longer stem.
        if ($cur['items']) {
            $last = count($cur['items']) - 1;
            $prev = $cur['items'][$last];
            $merged = qe_txt_item($prev['label'], $prev['raw'] . ' ' . $line);
            if ($merged['answer'] === null && $prev['answer'] !== null) {
                $merged['answer'] = $prev['answer'];
            }
            $cur['items'][$last] = $merged;
        } else {
            $cur['stem'] .= "\n" . $line;
        }
    }
    if ($cur !== null) {
        if (qe_txt_block_complete($cur)) {
            $finish(null);
        } else {
            $errors[] = ['line' => $cur['line'], 'message' => 'Question "' . qe_short($cur['stem']) . '" has no ANSWER line.'];
        }
    }
    return ['questions' => $questions, 'errors' => $errors];
}

function qe_strip_number(string $line): string
{
    return preg_replace('/^(?:Q(?:uestion)?\s*)?\d{1,4}\s*[\.\):]\s+/i', '', $line);
}

/** Splits a trailing [T] / (F) / - TRUE / = FALSE marker off a statement. */
function qe_txt_item(string $label, string $text): array
{
    $text = trim($text);
    $raw = $text;
    $answer = null;
    $patterns = [
        '/\s*[\[\(]\s*(T|F|TRUE|FALSE)\s*[\]\)]\s*$/i',
        '/\s+[-–=|:]\s*(T|F|TRUE|FALSE)\s*$/i',
        '/\s+(TRUE|FALSE)\s*$/',
        '/\s{2,}(T|F)$/',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $text, $m)) {
            $answer = strtoupper($m[1])[0];
            $text = trim(preg_replace($p, '', $text));
            break;
        }
    }
    return ['label' => $label, 'raw' => $raw, 'text' => $text, 'answer' => $answer];
}

/** A block without an ANSWER line is complete only when every statement carries its own T/F marker. */
function qe_txt_block_complete(array $b): bool
{
    if (count($b['items']) < QE_MTF_MIN) {
        return false;
    }
    $type = isset($b['directives']['TYPE']) ? qe_normalise_type($b['directives']['TYPE']) : null;
    if ($type !== null && $type !== 'mtf') {
        return false;
    }
    foreach ($b['items'] as $it) {
        if ($it['answer'] === null) {
            return false;
        }
    }
    return true;
}

function qe_txt_block_to_input(array $b, ?string $answer): array
{
    $d = $b['directives'];
    $type = null;
    if (isset($d['TYPE'])) {
        $type = qe_normalise_type($d['TYPE']);
        if ($type === null) {
            return ['error' => 'Unknown TYPE "' . $d['TYPE'] . '". Use MCQ, MULTI, TF or MTF.'];
        }
    }
    $answerU = strtoupper(trim((string) $answer));
    $items = $b['items'];
    $allMarked = $items && count(array_filter($items, fn($i) => $i['answer'] !== null)) === count($items);

    if ($type === null) {
        if (!$items && qe_parse_bool($answerU) !== null && !in_array($answerU, ['A', 'B'], true)) {
            $type = 'true_false';
        } elseif ($items && ($allMarked || qe_looks_like_tf_key($answerU))) {
            $type = 'mtf';
        } elseif (count(qe_parse_letters($answerU)) > 1) {
            $type = 'mcq_multi';
        } else {
            $type = 'mcq_single';
        }
    }

    $in = [
        'type' => $type,
        'text' => trim($b['stem']),
        'image' => $d['IMAGE'] ?? '',
        'mark' => isset($d['MARK']) && is_numeric($d['MARK']) ? (float) $d['MARK'] : '',
        'scoring' => null,
    ];
    if (isset($d['SCORING'])) {
        $s = strtolower(trim($d['SCORING']));
        $in['scoring'] = in_array($s, ['all', 'all_or_nothing', 'exact', 'strict', 'none'], true) ? 'all' : (in_array($s, ['partial', 'proportional'], true) ? 'partial' : null);
    }

    if ($type === 'true_false') {
        $in['correct'] = $answerU !== '' ? $answerU : '';
        return $in;
    }

    if ($type === 'mtf') {
        $keys = $answerU !== '' ? qe_parse_tf_key($answerU) : array_map(fn($i) => $i['answer'], $items);
        if (count($keys) !== count($items)) {
            return ['error' => 'MTF question "' . qe_short($in['text']) . '" has ' . count($items) . ' statements but ' . count($keys) . ' answers.'];
        }
        $in['statements'] = [];
        foreach ($items as $i => $it) {
            $in['statements'][] = ['text' => $it['text'], 'answer' => $keys[$i]];
        }
        return $in;
    }

    if (count($items) > 4) {
        return ['error' => 'Question "' . qe_short($in['text']) . '" has more than four options (A-D).'];
    }
    $in['options'] = [];
    foreach ($items as $i => $it) {
        $letter = strtoupper($it['label']);
        if (!in_array($letter, QE_OPTION_KEYS, true) || isset($in['options'][$letter])) {
            $letter = QE_OPTION_KEYS[$i];
        }
        $in['options'][$letter] = ['text' => $it['raw'], 'image' => ''];
    }
    $in['correct'] = $answerU;
    if (!qe_parse_letters($answerU) && preg_match('/^([A-D])(?:[\.\)\s:\-]|$)/', $answerU, $m)) {
        // "ANSWER: B. Paris" — keep the letter, ignore the echo of the option text
        $in['correct'] = $m[1];
    }
    return $in;
}

/** "TFFT", "T F F T", "a-T, b-F", "1. TRUE 2. FALSE" -> ['T','F',...] */
function qe_parse_tf_key(string $s): array
{
    preg_match_all('/[TF]/', qe_tf_key_clean($s), $m);
    return $m[0];
}

function qe_tf_key_clean(string $s): string
{
    $s = strtoupper($s);
    $s = preg_replace('/\bTRUE\b/', 'T', $s);
    $s = preg_replace('/\bFALSE\b/', 'F', $s);
    // drop statement labels such as "A-", "1.", "(B)", "c)"
    $s = preg_replace('/\(([A-EG-J]|\d{1,2})\)/', ' ', $s);
    $s = preg_replace('/\b(?:[A-EG-J]|\d{1,2})\s*[\.\)\-:=]\s*/', ' ', $s);
    return $s;
}

/** True when an ANSWER value is a list of T/F marks rather than option letters. */
function qe_looks_like_tf_key(string $s): bool
{
    if ($s === '') {
        return false;
    }
    $clean = qe_tf_key_clean($s);
    return (bool) preg_match('/^[TF\s,;\-\/\.]+$/', $clean) && preg_match_all('/[TF]/', $clean) >= QE_MTF_MIN;
}

/**
 * Reads a TXT or CSV question file and adds every valid question to an exam
 * inside one transaction. Used by the question bank page and the Upload Data
 * Center so both accept exactly the same formats.
 *
 * Returns [added, errors] or null when the database rejected the batch.
 */
/**
 * Text files reach us in whatever encoding the lecturer's editor used: Notepad
 * "ANSI" (Windows-1252), "Unicode" (UTF-16) or UTF-8. Anything that is not
 * valid UTF-8 used to make symbols such as ° ² µ ± vanish, and with them the
 * whole line. Everything is turned into UTF-8 here before parsing.
 */
function qe_to_utf8(string $s): string
{
    if (strncmp($s, "\xFF\xFE", 2) === 0 || strncmp($s, "\xFE\xFF", 2) === 0) {
        $from = $s[0] === "\xFF" ? 'UTF-16LE' : 'UTF-16BE';
        $s = substr($s, 2);
        return function_exists('mb_convert_encoding') ? mb_convert_encoding($s, 'UTF-8', $from) : (string) iconv($from, 'UTF-8//IGNORE', $s);
    }
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
    if (preg_match('//u', $s)) {
        return $s;
    }
    // UTF-16 without a BOM: every other byte is zero in ordinary text
    if (strlen($s) > 3 && substr_count($s, "\0") > strlen($s) / 3) {
        $from = $s[0] === "\0" ? 'UTF-16BE' : 'UTF-16LE';
        return function_exists('mb_convert_encoding') ? mb_convert_encoding($s, 'UTF-8', $from) : (string) iconv($from, 'UTF-8//IGNORE', $s);
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }
    $out = @iconv('Windows-1252', 'UTF-8//IGNORE', $s);
    return $out === false ? $s : $out;
}

function qe_import_file(PDO $db, int $exam_id, string $path, string $ext): ?array
{
    $items = [];
    $errors = [];
    if ($ext === 'docx') {
        require_once __DIR__ . '/docx_import.php';
        $conv = dx_docx_to_txt($path, dirname(__DIR__) . '/uploads');
        if (isset($conv['error'])) {
            return [0, [['line' => 0, 'message' => $conv['error']]]];
        }
        $parsed = qe_parse_txt($conv['text']);
        $items = $parsed['questions'];
        $errors = $parsed['errors'];
        foreach ($conv['warnings'] as $w) {
            $errors[] = ['line' => 0, 'message' => $w];
        }
    } elseif ($ext === 'txt') {
        $parsed = qe_parse_txt(qe_to_utf8((string) file_get_contents($path)));
        $items = $parsed['questions'];
        $errors = $parsed['errors'];
    } else {
        $csv = qe_to_utf8((string) file_get_contents($path));
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, $csv);
        rewind($fh);
        $line = 1;
        fgetcsv($fh); // header row
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            $row = array_map(fn($c) => trim((string) $c), $row);
            if (count(array_filter($row, fn($c) => $c !== '')) === 0) {
                continue;
            }
            if (count($row) < 6) {
                $errors[] = ['line' => $line, 'message' => 'Row has fewer than 6 columns.'];
                continue;
            }
            if (count($row) >= 11) {
                $in = [
                    'text' => $row[0], 'image' => $row[1],
                    'options' => [
                        'A' => ['text' => $row[2], 'image' => $row[3]], 'B' => ['text' => $row[4], 'image' => $row[5]],
                        'C' => ['text' => $row[6], 'image' => $row[7]], 'D' => ['text' => $row[8], 'image' => $row[9]],
                    ],
                    'correct' => $row[10], 'mark' => $row[11] ?? '', 'type' => $row[12] ?? '',
                ];
            } else {
                $in = [
                    'text' => $row[0],
                    'options' => ['A' => $row[1], 'B' => $row[2], 'C' => $row[3], 'D' => $row[4]],
                    'correct' => $row[5], 'mark' => $row[6] ?? '', 'type' => '',
                ];
            }
            $type = qe_normalise_type($in['type']) ?? 'mcq_single';
            if ($in['type'] === '' && count(qe_parse_letters($in['correct'])) > 1) {
                $type = 'mcq_multi';
            }
            $in['type'] = $type;
            if ($type === 'mtf') {
                $in['statements'] = [];
                foreach (preg_split('/\s*\|\|\s*/', $row[13] ?? '') as $part) {
                    if ($part !== '') {
                        $it = qe_txt_item('', $part);
                        $in['statements'][] = ['text' => $it['text'], 'answer' => $it['answer']];
                    }
                }
            }
            $in['line'] = $line;
            $items[] = $in;
        }
        fclose($fh);
    }

    $added = 0;
    $db->beginTransaction();
    try {
        foreach ($items as $in) {
            $built = qe_build_question($in);
            if ($built['ok']) {
                qe_insert_question($db, $exam_id, $built['row']);
                $added++;
            } else {
                $errors[] = ['line' => $in['line'] ?? 0, 'message' => $built['error']];
            }
        }
        $db->commit();
    } catch (Throwable $ex) {
        $db->rollBack();
        error_log('Question import failed: ' . $ex->getMessage());
        return null;
    }
    usort($errors, fn($a, $b) => $a['line'] <=> $b['line']);
    return [$added, $errors];
}

/** Writes questions back out in the TXT format so a bank can be moved between exams. */
function qe_export_txt(array $questions): string
{
    $out = [];
    $n = 0;
    foreach ($questions as $q) {
        $n++;
        $block = [];
        $type = $q['question_type'];
        $block[] = 'TYPE: ' . ['mcq_single' => 'MCQ', 'mcq_multi' => 'MULTI', 'true_false' => 'TF', 'mtf' => 'MTF'][$type];
        $block[] = 'MARK: ' . rtrim(rtrim(number_format((float) $q['mark'], 2, '.', ''), '0'), '.');
        if (!empty($q['question_image'])) {
            $block[] = 'IMAGE: ' . $q['question_image'];
        }
        if ($q['scoring']) {
            $block[] = 'SCORING: ' . strtoupper($q['scoring']);
        }
        $block[] = str_replace("\n", ' ', $q['question_text']);
        if ($type === 'true_false') {
            $block[] = 'ANSWER: ' . ($q['correct_option'] === 'A' ? 'TRUE' : 'FALSE');
        } elseif ($type === 'mtf') {
            foreach ($q['statements'] as $i => $s) {
                $block[] = chr(97 + $i) . ') ' . $s . ' [' . ($q['correct_option'][$i] ?? '?') . ']';
            }
        } else {
            foreach (QE_OPTION_KEYS as $k) {
                $t = $q['option_' . strtolower($k)];
                if (trim((string) $t) !== '') {
                    $block[] = "$k. $t";
                }
            }
            $block[] = 'ANSWER: ' . $q['correct_option'];
        }
        $out[] = implode("\r\n", $block);
    }
    return implode("\r\n\r\n", $out) . "\r\n";
}

require_once __DIR__ . '/center_sync.php';
