<?php
/**
 * Grouped examinations delivered through exam centres.
 *
 * One installation is the MAIN server (usually online). It owns exam groups
 * such as "Nursing Council Mock Exam", issues one activation token per centre
 * and receives the results. Every centre runs its own copy of this system on
 * the local network (CENTRE server). The centre manager enters the token,
 * the centre downloads a package with the exams, questions and students of
 * that group, runs the exams offline, and sends the results back.
 *
 * Token:   CBT-ABC234-K7QX2-M9TD4-WP6RZ-H3NC8
 *          "ABC234" names the token on the main server; the whole string is
 *          the secret. Neither server stores it: both keep only a key derived
 *          from it (pkg_key), which encrypts packages and signs requests.
 * Package: gzip + AES-256-GCM, so a copied file is useless without the token
 *          and cannot be altered without detection. Answer keys never travel
 *          in clear text.
 */

const CS_FORMAT = 'cbt-group-package';
const CS_RESULTS_FORMAT = 'cbt-group-results';
const CS_TOKEN_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O, 1/I
const CS_CLOCK_SKEW = 900; // seconds a signed request may be early or late

/* ================================================================== *
 *  Server role
 * ================================================================== */

function cs_setting(PDO $db, string $key, ?string $default = null): ?string
{
    $st = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function cs_set_setting(PDO $db, string $key, ?string $value): void
{
    $db->prepare("DELETE FROM system_settings WHERE setting_key = ?")->execute([$key]);
    if ($value !== null) {
        $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $value]);
    }
}

/** 'main' (default) or 'center'. */
function cs_role(PDO $db): string
{
    static $role = null;
    if ($role === null) {
        $role = cs_setting($db, 'server_role', 'main') === 'center' ? 'center' : 'main';
    }
    return $role;
}

function cs_is_center(PDO $db): bool
{
    return cs_role($db) === 'center';
}

/**
 * On a centre, exams that arrived in a package belong to the main server:
 * their questions, marks and students are not edited locally.
 */
function cs_exam_locked(PDO $db, int $exam_id): bool
{
    if ($exam_id <= 0 || !cs_is_center($db)) {
        return false;
    }
    $st = $db->prepare("SELECT group_id FROM exams WHERE id = ?");
    $st->execute([$exam_id]);
    return (bool) $st->fetchColumn();
}

/**
 * Stops a change to a locked exam with a readable message. On the main
 * server it instead notes that the group changed, so centres are told to
 * refresh their package.
 */
function cs_guard_exam(PDO $db, int $exam_id, string $back): void
{
    if (!cs_exam_locked($db, $exam_id)) {
        if ($exam_id > 0 && !cs_is_center($db)) {
            $db->prepare("UPDATE exam_groups g JOIN exams e ON e.group_id = g.id SET g.updated_at = NOW() WHERE e.id = ?")->execute([$exam_id]);
        }
        return;
    }
    if (function_exists('is_ajax_request') && is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'This exam comes from the main server and cannot be changed at the centre.']);
        exit;
    }
    $_SESSION['cs_flash'] = 'This exam was received from the main server. Its questions, marks and students can only be changed there; the centre gets the change when it refreshes the package.';
    header('Location: ' . $back);
    exit;
}

/* ================================================================== *
 *  Tokens, keys and sealed envelopes
 * ================================================================== */

function cs_random_chars(int $n): string
{
    $a = CS_TOKEN_ALPHABET;
    $out = '';
    for ($i = 0; $i < $n; $i++) {
        $out .= $a[random_int(0, strlen($a) - 1)];
    }
    return $out;
}

/** @return array{token: string, prefix: string, pkg_key: string} */
function cs_new_token(): array
{
    $prefix = cs_random_chars(6);
    $groups = [];
    for ($i = 0; $i < 4; $i++) {
        $groups[] = cs_random_chars(5);
    }
    $token = 'CBT-' . $prefix . '-' . implode('-', $groups);
    return ['token' => $token, 'prefix' => $prefix, 'pkg_key' => cs_key_for($token)];
}

/** Accepts the token typed with spaces, lower case or missing dashes. */
function cs_normalise_token(string $raw): ?string
{
    $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
    if (strpos($s, 'CBT') === 0) {
        $s = substr($s, 3);
    }
    if (strlen($s) !== 26 || strspn($s, CS_TOKEN_ALPHABET) !== 26) {
        return null;
    }
    return 'CBT-' . substr($s, 0, 6) . '-' . implode('-', str_split(substr($s, 6), 5));
}

function cs_token_prefix(string $token): string
{
    return substr($token, 4, 6);
}

function cs_key_for(string $token): string
{
    return hash_hmac('sha256', 'cbt-centre-package-v1', $token);
}

function cs_seal(array $data, string $key_hex): string
{
    $plain = gzencode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 6);
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', hex2bin($key_hex), OPENSSL_RAW_DATA, $iv, $tag);
    return json_encode([
        'format' => 'cbt-sealed',
        'v' => 1,
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'data' => base64_encode($ct),
    ]);
}

function cs_open(string $envelope, string $key_hex): ?array
{
    $e = json_decode($envelope, true);
    if (!is_array($e) || ($e['format'] ?? '') !== 'cbt-sealed') {
        return null;
    }
    $plain = openssl_decrypt(base64_decode((string) $e['data']), 'aes-256-gcm', hex2bin($key_hex), OPENSSL_RAW_DATA,
        base64_decode((string) $e['iv']), base64_decode((string) $e['tag']));
    if ($plain === false) {
        return null;
    }
    $json = @gzdecode($plain);
    $data = json_decode($json === false ? $plain : $json, true);
    return is_array($data) ? $data : null;
}

function cs_sign(string $key_hex, string $prefix, string $action, int $ts, string $body): string
{
    return hash_hmac('sha256', $prefix . '|' . $action . '|' . $ts . '|' . hash('sha256', $body), hex2bin($key_hex));
}

/* ================================================================== *
 *  Main server: groups, centres, tokens
 * ================================================================== */

function cs_group(PDO $db, int $id): ?array
{
    $st = $db->prepare("SELECT * FROM exam_groups WHERE id = ? AND deleted_at IS NULL");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function cs_group_exams(PDO $db, int $group_id): array
{
    $st = $db->prepare("SELECT * FROM exams WHERE group_id = ? AND deleted_at IS NULL
        ORDER BY FIELD(type, 'objective', 'practical', 'viva', 'research'), name");
    $st->execute([$group_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Is the activation window open right now? Returns null when it is, or the reason. */
function cs_window_problem(array $group, ?int $now = null): ?string
{
    $now = $now ?? time();
    if (($group['status'] ?? '') !== 'published') {
        return 'This exam group has not been published for centres yet.';
    }
    if (!empty($group['activation_opens']) && $now < strtotime($group['activation_opens'])) {
        return 'Activation opens on ' . date('j M Y, g:i A', strtotime($group['activation_opens'])) . '.';
    }
    if (!empty($group['activation_closes']) && $now > strtotime($group['activation_closes'])) {
        return 'Activation closed on ' . date('j M Y, g:i A', strtotime($group['activation_closes'])) . '.';
    }
    return null;
}

function cs_token_by_prefix(PDO $db, string $prefix): ?array
{
    $st = $db->prepare("SELECT t.*, c.name AS center_name, c.code AS center_code, c.location AS center_location
        FROM center_tokens t JOIN exam_centers c ON c.id = t.center_id WHERE t.prefix = ?");
    $st->execute([$prefix]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function cs_log(PDO $db, ?int $token_id, ?int $link_id, string $action, bool $ok, string $summary): void
{
    $db->prepare("INSERT INTO sync_log (token_id, link_id, action, ok, summary, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
        ->execute([$token_id, $link_id, $action, $ok ? 1 : 0, mb_substr($summary, 0, 2000)]);
}

/**
 * Students a centre receives for one exam: those allocated to the centre
 * (center_students), or every enrolled student when nobody is allocated.
 */
function cs_center_student_ids(PDO $db, array $token): ?array
{
    $st = $db->prepare("SELECT student_id FROM center_students WHERE token_id = ?");
    $st->execute([(int) $token['id']]);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    return $ids ?: null;
}

/** Columns of a table that both servers understand (same code base). */
function cs_row_fields(array $row, array $drop): array
{
    foreach ($drop as $d) {
        unset($row[$d]);
    }
    return $row;
}

/** Builds the package a centre receives for its token. */
function cs_build_package(PDO $db, array $token): array
{
    $group = cs_group($db, (int) $token['group_id']);
    if (!$group) {
        throw new RuntimeException('The exam group no longer exists.');
    }
    $allowed = cs_center_student_ids($db, $token);
    $exams = cs_group_exams($db, (int) $group['id']);

    $pkg_exams = [];
    $student_ids = [];
    $files = [];
    $want_file = function (?string $name) use (&$files) {
        $name = trim((string) $name);
        if ($name !== '' && !isset($files[$name]) && preg_match('/^[A-Za-z0-9 _\-\.\(\)]+\.(png|jpe?g|gif|webp|bmp)$/i', $name)) {
            $path = dirname(__DIR__) . '/uploads/' . $name;
            if (is_file($path) && filesize($path) <= 5 * 1024 * 1024) {
                $files[$name] = base64_encode((string) file_get_contents($path));
            }
        }
    };

    foreach ($exams as $e) {
        $eid = (int) $e['id'];
        $item = [
            'remote_id' => $eid,
            'fields' => cs_row_fields($e, ['id', 'session_id', 'group_id', 'remote_id', 'deleted_at', 'created_at']),
            'objective_questions' => [],
            'steps' => [],
            'students' => [],
        ];
        if ($e['type'] === 'objective') {
            $q = $db->prepare("SELECT * FROM objective_questions WHERE exam_id = ? AND deleted_at IS NULL ORDER BY id");
            $q->execute([$eid]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $item['objective_questions'][] = ['remote_id' => (int) $row['id'], 'fields' => cs_row_fields($row, ['id', 'exam_id', 'deleted_at'])];
                foreach (['question_image', 'option_a_image', 'option_b_image', 'option_c_image', 'option_d_image'] as $col) {
                    $want_file($row[$col] ?? '');
                }
            }
        } else {
            $q = $db->prepare("SELECT q.id, q.text, q.score_values FROM exam_questions eq JOIN questions q ON q.id = eq.question_id
                WHERE eq.exam_id = ? AND q.deleted_at IS NULL ORDER BY eq.id");
            $q->execute([$eid]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $item['steps'][] = ['remote_id' => (int) $row['id'], 'text' => $row['text'], 'score_values' => $row['score_values']];
            }
        }
        $s = $db->prepare("SELECT es.student_id FROM exam_students es JOIN students s ON s.id = es.student_id AND s.deleted_at IS NULL WHERE es.exam_id = ?");
        $s->execute([$eid]);
        foreach (array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN)) as $sid) {
            if ($allowed === null || in_array($sid, $allowed, true)) {
                $item['students'][] = $sid;
                $student_ids[$sid] = true;
            }
        }
        $pkg_exams[] = $item;
    }

    $students = [];
    if ($student_ids) {
        foreach (array_chunk(array_keys($student_ids), 500) as $chunk) {
            $in = implode(',', array_map('intval', $chunk));
            foreach ($db->query("SELECT id, name, firstname, middlename, lastname, email, matric_no, username, password FROM students WHERE id IN ($in)")->fetchAll(PDO::FETCH_ASSOC) as $st) {
                $st['remote_id'] = (int) $st['id'];
                unset($st['id']);
                $students[] = $st;
            }
        }
    }

    return [
        'format' => CS_FORMAT,
        'version' => 1,
        'built_at' => date('c'),
        'package_version' => strtotime($group['updated_at'] ?: $group['created_at']),
        'token_prefix' => $token['prefix'],
        'group' => [
            'remote_id' => (int) $group['id'],
            'name' => $group['name'],
            'code' => $group['code'],
            'description' => $group['description'],
            'activation_opens' => $group['activation_opens'],
            'activation_closes' => $group['activation_closes'],
            'exam_ends' => $group['exam_ends'] ?? null,
        ],
        'center' => ['name' => $token['center_name'], 'code' => $token['center_code'], 'location' => $token['center_location']],
        'exams' => $pkg_exams,
        'students' => $students,
        'files' => $files,
    ];
}

/** Marks a group as changed so centres know their package is out of date. */
function cs_touch_group(PDO $db, ?int $group_id): void
{
    if ($group_id) {
        $db->prepare("UPDATE exam_groups SET updated_at = NOW() WHERE id = ?")->execute([$group_id]);
    }
}

/* ================================================================== *
 *  Centre server: import a package
 * ================================================================== */

function cs_map_get(PDO $db, int $link_id, string $entity, int $remote_id): ?int
{
    $st = $db->prepare("SELECT local_id FROM sync_map WHERE link_id = ? AND entity = ? AND remote_id = ?");
    $st->execute([$link_id, $entity, $remote_id]);
    $v = $st->fetchColumn();
    return $v === false ? null : (int) $v;
}

function cs_map_set(PDO $db, int $link_id, string $entity, int $remote_id, int $local_id): void
{
    $db->prepare("INSERT INTO sync_map (link_id, entity, remote_id, local_id) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE local_id = VALUES(local_id)")->execute([$link_id, $entity, $remote_id, $local_id]);
}

/** Remote id for a local row (used when sending results back). */
function cs_map_remote(PDO $db, int $link_id, string $entity): array
{
    $st = $db->prepare("SELECT local_id, remote_id FROM sync_map WHERE link_id = ? AND entity = ?");
    $st->execute([$link_id, $entity]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['local_id']] = (int) $r['remote_id'];
    }
    return $out;
}

function cs_insert(PDO $db, string $table, array $fields): int
{
    $cols = array_keys($fields);
    $sql = "INSERT INTO $table (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
    $db->prepare($sql)->execute(array_values($fields));
    return (int) $db->lastInsertId();
}

function cs_update(PDO $db, string $table, int $id, array $fields): void
{
    if (!$fields) {
        return;
    }
    $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($fields)));
    $db->prepare("UPDATE $table SET $set WHERE id = ?")->execute([...array_values($fields), $id]);
}

/** Keeps only the columns this database actually has. */
function cs_known_fields(PDO $db, string $table, array $fields): array
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $cache[$table] = array_flip(array_map(fn($r) => $r['Field'], $db->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_ASSOC)));
    }
    return array_intersect_key($fields, $cache[$table]);
}

/**
 * Installs (or refreshes) a package on the centre. Running it again with a
 * newer package updates questions and students in place; answers already
 * given at the centre are never removed.
 *
 * @return array summary counts
 */
function cs_import_package(PDO $db, array $pkg, string $pkg_key, string $main_url): array
{
    if (($pkg['format'] ?? '') !== CS_FORMAT) {
        throw new RuntimeException('This is not an exam group package.');
    }
    $g = $pkg['group'];
    $sum = ['exams' => 0, 'questions' => 0, 'students' => 0, 'enrolments' => 0, 'files' => 0, 'removed_questions' => 0];

    $db->beginTransaction();
    try {
        // link + local copy of the group
        $st = $db->prepare("SELECT * FROM center_links WHERE prefix = ?");
        $st->execute([$pkg['token_prefix']]);
        $link = $st->fetch(PDO::FETCH_ASSOC);

        $gfields = [
            'name' => $g['name'], 'code' => $g['code'], 'description' => $g['description'],
            'activation_opens' => $g['activation_opens'], 'activation_closes' => $g['activation_closes'],
            'exam_ends' => $g['exam_ends'] ?? null, 'status' => 'published', 'remote_id' => (int) $g['remote_id'],
            'package_version' => (int) $pkg['package_version'], 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($link) {
            $group_id = (int) $link['group_id'];
            cs_update($db, 'exam_groups', $group_id, $gfields + ['deleted_at' => null]);
            $db->prepare("UPDATE center_links SET main_url = COALESCE(?, main_url), pkg_key = ?, center_name = ?, center_code = ?, package_version = ?, activated_at = NOW() WHERE id = ?")
                ->execute([$main_url !== '' ? $main_url : null, $pkg_key, $pkg['center']['name'], $pkg['center']['code'], (int) $pkg['package_version'], $link['id']]);
            $link_id = (int) $link['id'];
        } else {
            $group_id = cs_insert($db, 'exam_groups', $gfields + ['created_at' => date('Y-m-d H:i:s')]);
            $link_id = cs_insert($db, 'center_links', [
                'group_id' => $group_id, 'main_url' => $main_url !== '' ? $main_url : null, 'prefix' => $pkg['token_prefix'],
                'pkg_key' => $pkg_key, 'center_name' => $pkg['center']['name'], 'center_code' => $pkg['center']['code'],
                'package_version' => (int) $pkg['package_version'], 'activated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // academic session the exams hang under
        $st = $db->prepare("SELECT id FROM academic_sessions WHERE session_name = ? AND deleted_at IS NULL LIMIT 1");
        $st->execute([$g['name']]);
        $session_id = (int) $st->fetchColumn();
        if (!$session_id) {
            $session_id = cs_insert($db, 'academic_sessions', ['session_name' => $g['name'], 'is_active' => 1]);
        }

        // students, matched by matric number
        $student_local = [];
        $find = $db->prepare("SELECT id FROM students WHERE matric_no = ? LIMIT 1");
        foreach ($pkg['students'] as $s) {
            $rid = (int) $s['remote_id'];
            unset($s['remote_id']);
            $fields = cs_known_fields($db, 'students', $s);
            $find->execute([$s['matric_no']]);
            $lid = (int) $find->fetchColumn();
            if (!$lid && !empty($s['username'])) {
                // a different local student may already hold this username
                $u = $db->prepare("SELECT id FROM students WHERE username = ? LIMIT 1");
                $u->execute([$s['username']]);
                if ($u->fetchColumn()) {
                    $fields['username'] = $s['matric_no'];
                }
            }
            if ($lid) {
                unset($fields['username']);
                cs_update($db, 'students', $lid, $fields + ['deleted_at' => null]);
            } else {
                $lid = cs_insert($db, 'students', $fields + ['source' => 'center']);
            }
            cs_map_set($db, $link_id, 'student', $rid, $lid);
            $student_local[$rid] = $lid;
            $sum['students']++;
        }

        foreach ($pkg['exams'] as $e) {
            $rid = (int) $e['remote_id'];
            $fields = cs_known_fields($db, 'exams', $e['fields']);
            $fields['group_id'] = $group_id;
            $fields['remote_id'] = $rid;
            $fields['session_id'] = $session_id;
            $lid = cs_map_get($db, $link_id, 'exam', $rid);
            if ($lid) {
                // switching an exam on or off is the centre's decision once installed
                unset($fields['is_active']);
                cs_update($db, 'exams', $lid, $fields + ['deleted_at' => null]);
            } else {
                $lid = cs_insert($db, 'exams', $fields + ['created_at' => date('Y-m-d H:i:s')]);
                cs_map_set($db, $link_id, 'exam', $rid, $lid);
            }
            $sum['exams']++;

            if ($fields['type'] === 'objective') {
                $keep = [];
                foreach ($e['objective_questions'] as $q) {
                    $qf = cs_known_fields($db, 'objective_questions', $q['fields']);
                    $qf['exam_id'] = $lid;
                    $qlid = cs_map_get($db, $link_id, 'oq', (int) $q['remote_id']);
                    if ($qlid) {
                        cs_update($db, 'objective_questions', $qlid, $qf + ['deleted_at' => null]);
                    } else {
                        $qlid = cs_insert($db, 'objective_questions', $qf);
                        cs_map_set($db, $link_id, 'oq', (int) $q['remote_id'], $qlid);
                    }
                    $keep[] = $qlid;
                    $sum['questions']++;
                }
                $sql = "UPDATE objective_questions SET deleted_at = NOW() WHERE exam_id = ? AND deleted_at IS NULL";
                if ($keep) {
                    $sql .= ' AND id NOT IN (' . implode(',', $keep) . ')';
                }
                $st = $db->prepare($sql);
                $st->execute([$lid]);
                $sum['removed_questions'] += $st->rowCount();
            } else {
                $keep = [];
                foreach ($e['steps'] as $q) {
                    $qlid = cs_map_get($db, $link_id, 'step', (int) $q['remote_id']);
                    if ($qlid) {
                        $db->prepare("UPDATE questions SET text = ?, score_values = ?, exam_id = ?, deleted_at = NULL WHERE id = ?")
                            ->execute([$q['text'], $q['score_values'], $lid, $qlid]);
                    } else {
                        $qlid = cs_insert($db, 'questions', ['exam_id' => $lid, 'text' => $q['text'], 'score_values' => $q['score_values']]);
                        cs_map_set($db, $link_id, 'step', (int) $q['remote_id'], $qlid);
                    }
                    $has = $db->prepare("SELECT 1 FROM exam_questions WHERE exam_id = ? AND question_id = ?");
                    $has->execute([$lid, $qlid]);
                    if (!$has->fetchColumn()) {
                        $db->prepare("INSERT INTO exam_questions (exam_id, question_id) VALUES (?, ?)")->execute([$lid, $qlid]);
                    }
                    $keep[] = $qlid;
                    $sum['questions']++;
                }
                // a step dropped on the main server goes, unless it has been scored here
                $sql = "DELETE eq FROM exam_questions eq WHERE eq.exam_id = ?
                    AND NOT EXISTS (SELECT 1 FROM answers a WHERE a.exam_id = eq.exam_id AND a.question_id = eq.question_id)";
                if ($keep) {
                    $sql .= ' AND eq.question_id NOT IN (' . implode(',', $keep) . ')';
                }
                $st = $db->prepare($sql);
                $st->execute([$lid]);
                $sum['removed_questions'] += $st->rowCount();
            }

            $enrol = $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) VALUES (?, ?)");
            $wanted = [];
            foreach ($e['students'] as $srid) {
                if (isset($student_local[(int) $srid])) {
                    $enrol->execute([$lid, $student_local[(int) $srid]]);
                    $wanted[] = $student_local[(int) $srid];
                    $sum['enrolments']++;
                }
            }
            // students moved to another centre: drop them unless they already started
            $sql = "DELETE es FROM exam_students es WHERE es.exam_id = ?
                AND NOT EXISTS (SELECT 1 FROM objective_attempts oa WHERE oa.exam_id = es.exam_id AND oa.student_id = es.student_id)
                AND NOT EXISTS (SELECT 1 FROM answers a WHERE a.exam_id = es.exam_id AND a.student_id = es.student_id)";
            if ($wanted) {
                $sql .= ' AND es.student_id NOT IN (' . implode(',', $wanted) . ')';
            }
            $db->prepare($sql)->execute([$lid]);
        }

        // exams removed from the group on the main server are retired here
        $remote_exams = array_map(fn($e) => (int) $e['remote_id'], $pkg['exams']);
        foreach (cs_map_remote($db, $link_id, 'exam') as $local => $remote) {
            if (!in_array($remote, $remote_exams, true)) {
                $db->prepare("UPDATE exams SET is_active = 0, deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$local]);
            }
        }
        $db->commit();
    } catch (Throwable $ex) {
        $db->rollBack();
        throw $ex;
    }

    // pictures go to uploads/ (outside the transaction; harmless if repeated)
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    foreach (($pkg['files'] ?? []) as $name => $b64) {
        $name = basename((string) $name);
        if (!preg_match('/^[A-Za-z0-9 _\-\.\(\)]+\.(png|jpe?g|gif|webp|bmp)$/i', $name)) {
            continue;
        }
        $data = base64_decode((string) $b64, true);
        if ($data !== false && @getimagesizefromstring($data)) {
            file_put_contents("$dir/$name", $data);
            $sum['files']++;
        }
    }

    $sum['link_id'] = $link_id;
    $sum['group_id'] = $group_id;
    cs_log($db, null, $link_id, 'activate', true, cs_summary_text($sum));
    return $sum;
}

function cs_summary_text(array $sum): string
{
    $parts = [];
    $labels = ['exams' => 'exam', 'questions' => 'question', 'students' => 'student', 'files' => 'picture',
        'attempts' => 'attempt', 'answers' => 'answer', 'scores' => 'score', 'skipped' => 'skipped record', 'removed_questions' => 'removed question'];
    foreach ($labels as $k => $label) {
        if (!empty($sum[$k])) {
            $parts[] = $sum[$k] . ' ' . $label . ($sum[$k] == 1 ? '' : 's');
        }
    }
    return $parts ? implode(', ', $parts) : 'nothing to change';
}

/* ================================================================== *
 *  Centre server: collect results
 * ================================================================== */

/** Everything recorded at this centre for one grouped exam. */
function cs_collect_results(PDO $db, array $link, int $exam_local_id): array
{
    $link_id = (int) $link['id'];
    $map_exam = cs_map_remote($db, $link_id, 'exam');
    if (!isset($map_exam[$exam_local_id])) {
        throw new RuntimeException('Exam is not part of this package.');
    }
    $st = $db->prepare("SELECT type FROM exams WHERE id = ?");
    $st->execute([$exam_local_id]);
    $type = (string) $st->fetchColumn();

    $out = [
        'format' => CS_RESULTS_FORMAT,
        'version' => 1,
        'collected_at' => date('c'),
        'token_prefix' => $link['prefix'],
        'exam_remote_id' => $map_exam[$exam_local_id],
        'type' => $type,
        'attempts' => [],
        'answers' => [],
        'scores' => [],
    ];

    if ($type === 'objective') {
        $qmap = cs_map_remote($db, $link_id, 'oq');
        $st = $db->prepare("SELECT oa.*, s.matric_no FROM objective_attempts oa JOIN students s ON s.id = oa.student_id WHERE oa.exam_id = ?");
        $st->execute([$exam_local_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $order = [];
            foreach (array_filter(explode(',', (string) $a['question_order'])) as $qid) {
                if (isset($qmap[(int) $qid])) {
                    $order[] = $qmap[(int) $qid];
                }
            }
            $out['attempts'][] = [
                'matric_no' => $a['matric_no'],
                'attempt_no' => (int) $a['attempt_no'],
                'started_at' => $a['started_at'],
                'finished_at' => $a['finished_at'],
                'time_taken' => $a['time_taken'],
                'time_used' => $a['time_used'],
                'question_order' => implode(',', $order),
                'score' => $a['score'],
                'violation_count' => $a['violation_count'],
                'is_locked' => $a['is_locked'],
                'last_active' => $a['last_active'],
            ];
        }
        $st = $db->prepare("SELECT ans.question_id, ans.selected_option, ans.submitted_at, ans.attempt_no, s.matric_no
            FROM objective_answers ans JOIN students s ON s.id = ans.student_id WHERE ans.exam_id = ?");
        $st->execute([$exam_local_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!isset($qmap[(int) $r['question_id']])) {
                continue;
            }
            $out['answers'][] = [
                'matric_no' => $r['matric_no'], 'question' => $qmap[(int) $r['question_id']], 'attempt_no' => (int) $r['attempt_no'],
                'selected_option' => $r['selected_option'], 'submitted_at' => $r['submitted_at'],
            ];
        }
    } else {
        $qmap = cs_map_remote($db, $link_id, 'step');
        $st = $db->prepare("SELECT a.question_id, a.score, a.created_at, a.updated_at, s.matric_no, x.username AS examiner_username, x.name AS examiner_name
            FROM answers a JOIN students s ON s.id = a.student_id LEFT JOIN examiners x ON x.id = a.examiner_id WHERE a.exam_id = ?");
        $st->execute([$exam_local_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!isset($qmap[(int) $r['question_id']])) {
                continue;
            }
            $out['scores'][] = [
                'matric_no' => $r['matric_no'], 'question' => $qmap[(int) $r['question_id']], 'score' => $r['score'],
                'examiner' => $r['examiner_username'] ?: 'centre', 'examiner_name' => $r['examiner_name'] ?: 'Centre examiner',
                'created_at' => $r['created_at'], 'updated_at' => $r['updated_at'],
            ];
        }
    }
    return $out;
}

/* ================================================================== *
 *  Main server: apply results from a centre
 * ================================================================== */

/** Examiner row on the main server standing for a centre examiner. */
function cs_center_examiner(PDO $db, array $token, string $username, string $name): int
{
    static $cache = [];
    $u = strtolower(preg_replace('/[^A-Za-z0-9_.\-]/', '', $token['center_code'] . '.' . $username));
    if (isset($cache[$u])) {
        return $cache[$u];
    }
    $st = $db->prepare("SELECT id FROM examiners WHERE username = ? LIMIT 1");
    $st->execute([$u]);
    $id = (int) $st->fetchColumn();
    if (!$id) {
        // cannot sign in on the main server: the password is a random hash nobody knows
        $db->prepare("INSERT INTO examiners (name, username, password, role, source) VALUES (?, ?, ?, 'examiner', 'center')")
            ->execute([$name . ' (' . $token['center_name'] . ')', $u, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $id = (int) $db->lastInsertId();
    }
    return $cache[$u] = $id;
}

function cs_apply_results(PDO $db, array $token, array $res): array
{
    if (($res['format'] ?? '') !== CS_RESULTS_FORMAT || ($res['token_prefix'] ?? '') !== $token['prefix']) {
        throw new RuntimeException('These results do not belong to this centre token.');
    }
    $exam_id = (int) $res['exam_remote_id'];
    $st = $db->prepare("SELECT * FROM exams WHERE id = ? AND group_id = ? AND deleted_at IS NULL");
    $st->execute([$exam_id, (int) $token['group_id']]);
    $exam = $st->fetch(PDO::FETCH_ASSOC);
    if (!$exam) {
        throw new RuntimeException('The exam in these results is no longer part of the group.');
    }

    $allowed = cs_center_student_ids($db, $token);
    $students = [];
    $find = $db->prepare("SELECT s.id FROM students s JOIN exam_students es ON es.student_id = s.id AND es.exam_id = ?
        WHERE s.matric_no = ? AND s.deleted_at IS NULL LIMIT 1");
    $student = function (string $matric) use (&$students, $find, $exam_id, $allowed) {
        if (!array_key_exists($matric, $students)) {
            $find->execute([$exam_id, $matric]);
            $id = (int) $find->fetchColumn();
            $students[$matric] = ($id && ($allowed === null || in_array($id, $allowed, true))) ? $id : null;
        }
        return $students[$matric];
    };

    $sum = ['attempts' => 0, 'answers' => 0, 'scores' => 0, 'skipped' => 0];
    $db->beginTransaction();
    try {
        if ($exam['type'] === 'objective') {
            $valid_q = array_flip(array_map('intval', $db->query("SELECT id FROM objective_questions WHERE exam_id = " . $exam_id)->fetchAll(PDO::FETCH_COLUMN)));
            $up = $db->prepare("INSERT INTO objective_attempts (exam_id, student_id, attempt_no, started_at, finished_at, time_taken, time_used,
                    question_order, score, violation_count, is_locked, last_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE started_at = VALUES(started_at), finished_at = VALUES(finished_at), time_taken = VALUES(time_taken),
                    time_used = VALUES(time_used), question_order = VALUES(question_order), score = VALUES(score),
                    violation_count = VALUES(violation_count), is_locked = VALUES(is_locked), last_active = VALUES(last_active)");
            $touched = [];
            foreach ($res['attempts'] as $a) {
                $sid = $student((string) $a['matric_no']);
                if (!$sid) {
                    $sum['skipped']++;
                    continue;
                }
                $order = implode(',', array_filter(array_map('intval', explode(',', (string) $a['question_order'])), fn($q) => isset($valid_q[$q])));
                $up->execute([$exam_id, $sid, (int) $a['attempt_no'], $a['started_at'], $a['finished_at'], $a['time_taken'], $a['time_used'],
                    $order, $a['score'], (int) $a['violation_count'], (int) $a['is_locked'], $a['last_active']]);
                $touched[$sid . ':' . (int) $a['attempt_no']] = [$sid, (int) $a['attempt_no']];
                $sum['attempts']++;
            }
            // the centre's copy of each attempt replaces what was here
            $del = $db->prepare("DELETE FROM objective_answers WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
            foreach ($touched as [$sid, $no]) {
                $del->execute([$exam_id, $sid, $no]);
            }
            $ins = $db->prepare("INSERT INTO objective_answers (exam_id, student_id, question_id, selected_option, submitted_at, attempt_no)
                VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), submitted_at = VALUES(submitted_at)");
            foreach ($res['answers'] as $r) {
                $sid = $student((string) $r['matric_no']);
                if (!$sid || !isset($valid_q[(int) $r['question']]) || !isset($touched[$sid . ':' . (int) $r['attempt_no']])) {
                    $sum['skipped']++;
                    continue;
                }
                $ins->execute([$exam_id, $sid, (int) $r['question'], $r['selected_option'], $r['submitted_at'], (int) $r['attempt_no']]);
                $sum['answers']++;
            }
        } else {
            $valid_q = array_flip(array_map('intval', $db->query("SELECT question_id FROM exam_questions WHERE exam_id = " . $exam_id)->fetchAll(PDO::FETCH_COLUMN)));
            $sel = $db->prepare("SELECT id FROM answers WHERE exam_id = ? AND question_id = ? AND student_id = ? AND examiner_id = ? LIMIT 1");
            foreach ($res['scores'] as $r) {
                $sid = $student((string) $r['matric_no']);
                if (!$sid || !isset($valid_q[(int) $r['question']])) {
                    $sum['skipped']++;
                    continue;
                }
                $xid = cs_center_examiner($db, $token, (string) $r['examiner'], (string) $r['examiner_name']);
                $sel->execute([$exam_id, (int) $r['question'], $sid, $xid]);
                $aid = (int) $sel->fetchColumn();
                if ($aid) {
                    $db->prepare("UPDATE answers SET score = ?, updated_at = ? WHERE id = ?")->execute([$r['score'], $r['updated_at'] ?: date('Y-m-d H:i:s'), $aid]);
                } else {
                    $db->prepare("INSERT INTO answers (exam_id, question_id, student_id, examiner_id, score, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$exam_id, (int) $r['question'], $sid, $xid, $r['score'], $r['created_at'] ?: date('Y-m-d H:i:s'), $r['updated_at']]);
                }
                $db->prepare("INSERT IGNORE INTO exam_examiners (exam_id, examiner_id) SELECT ?, ? FROM DUAL
                    WHERE NOT EXISTS (SELECT 1 FROM exam_examiners WHERE exam_id = ? AND examiner_id = ?)")->execute([$exam_id, $xid, $exam_id, $xid]);
                $sum['scores']++;
            }
        }
        $db->prepare("UPDATE center_tokens SET last_sync_at = NOW(), sync_count = sync_count + 1,
            status = CASE WHEN status = 'issued' THEN 'activated' ELSE status END WHERE id = ?")->execute([(int) $token['id']]);
        $db->commit();
    } catch (Throwable $ex) {
        $db->rollBack();
        throw $ex;
    }
    $sum['exam'] = $exam['name'];
    cs_log($db, (int) $token['id'], null, 'sync', true, $exam['name'] . ': ' . cs_summary_text($sum));
    return $sum;
}

/* ================================================================== *
 *  Centre server: talking to the main server
 * ================================================================== */

function cs_clean_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    return rtrim(preg_replace('#/(center_api(\.php)?)?$#', '', $url), '/');
}

/**
 * Signed POST to the main server's center_api. Returns the decoded JSON
 * reply or throws with a message the centre manager can act on.
 */
function cs_call_main(string $main_url, string $prefix, string $pkg_key, string $action, string $body = ''): array
{
    $ts = time();
    $post = http_build_query([
        'action' => $action,
        'prefix' => $prefix,
        'ts' => $ts,
        'sig' => cs_sign($pkg_key, $prefix, $action, $ts, $body),
        'body' => $body,
    ]);
    // extensionless first (the project's .htaccess), plain .php if rewriting is off
    foreach (['/center_api', '/center_api.php'] as $path) {
        [$raw, $code, $err] = cs_http_post($main_url . $path, $post);
        if ($code !== 404) {
            break;
        }
    }
    if (($raw === false || $raw === null) && stripos($err, 'SSL certificate') !== false) {
        throw new RuntimeException('The centre could not check the main server\'s security certificate. In XAMPP, download cacert.pem from curl.se, '
            . 'save it as C:\\xampp\\php\\extras\\ssl\\cacert.pem, set curl.cainfo to that path in php.ini and restart Apache. Or use the package file instead.');
    }
    if ($raw === false || $raw === null || $raw === '') {
        throw new RuntimeException('Could not reach the main server at ' . $main_url . ($err ? " ($err)" : '') . '. Check the internet connection and the address, or use the package file instead.');
    }
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        throw new RuntimeException('The main server answered with something unexpected (HTTP ' . $code . '). Check that the address points to the exam system.');
    }
    if (empty($j['ok'])) {
        throw new RuntimeException($j['error'] ?? 'The main server refused the request.');
    }
    return $j;
}

/** @return array{0: string|false|null, 1: int, 2: string} body, HTTP status, transport error */
function cs_http_post(string $url, string $post): array
{
    $raw = null;
    $code = 0;
    $err = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Expect:'],
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => $post, 'timeout' => 180, 'ignore_errors' => true, 'follow_location' => 0,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        $err = $raw === false ? 'no response' : '';
    }
    return [$raw, $code, $err];
}

/** Sends every exam of a linked group; one request per exam keeps each small. */
function cs_sync_link(PDO $db, array $link): array
{
    if (empty($link['main_url'])) {
        throw new RuntimeException('No main server address is saved for this group. Enter it, or download the results file and send it to the main server.');
    }
    $done = [];
    foreach (cs_map_remote($db, (int) $link['id'], 'exam') as $local => $remote) {
        $st = $db->prepare("SELECT name FROM exams WHERE id = ? AND deleted_at IS NULL");
        $st->execute([$local]);
        $name = $st->fetchColumn();
        if ($name === false) {
            continue;
        }
        $payload = cs_seal(cs_collect_results($db, $link, $local), $link['pkg_key']);
        $reply = cs_call_main($link['main_url'], $link['prefix'], $link['pkg_key'], 'sync', $payload);
        $done[] = $name . ': ' . cs_summary_text($reply['summary'] ?? []);
    }
    $status = date('j M, g:i A') . ' — ' . ($done ? implode('; ', $done) : 'no exams');
    $db->prepare("UPDATE center_links SET last_sync_at = NOW(), last_sync_status = ? WHERE id = ?")->execute([$status, (int) $link['id']]);
    cs_log($db, null, (int) $link['id'], 'sync', true, $status);
    return $done;
}

/** Offline route: one sealed file with the results of every exam in the group. */
function cs_results_file(PDO $db, array $link): string
{
    $all = [];
    foreach (cs_map_remote($db, (int) $link['id'], 'exam') as $local => $remote) {
        $st = $db->prepare("SELECT 1 FROM exams WHERE id = ? AND deleted_at IS NULL");
        $st->execute([$local]);
        if ($st->fetchColumn()) {
            $all[] = cs_collect_results($db, $link, $local);
        }
    }
    return cs_wrap_file('results', $link['prefix'], (string) $link['center_name'],
        cs_seal(['format' => 'cbt-group-results-bundle', 'token_prefix' => $link['prefix'], 'exams' => $all], $link['pkg_key']));
}

/**
 * Files carried on a flash drive. The outside names the token and the
 * centre so the right row can be found; the inside stays sealed.
 */
function cs_wrap_file(string $kind, string $prefix, string $label, string $sealed): string
{
    return json_encode(['format' => 'cbt-transfer-file', 'kind' => $kind, 'prefix' => $prefix, 'label' => $label,
        'created_at' => date('c'), 'sealed' => $sealed], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** @return array{kind: string, prefix: string, label: string, sealed: string}|null */
function cs_unwrap_file(string $raw): ?array
{
    $j = json_decode($raw, true);
    if (!is_array($j) || ($j['format'] ?? '') !== 'cbt-transfer-file' || empty($j['sealed']) || empty($j['prefix'])) {
        return null;
    }
    return ['kind' => (string) $j['kind'], 'prefix' => (string) $j['prefix'], 'label' => (string) ($j['label'] ?? ''), 'sealed' => (string) $j['sealed']];
}
