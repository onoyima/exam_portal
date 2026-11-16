<?php
/**
 * CLI verification for the admin backup/export engine and the answer-override
 * scoring path. It never leaves changes behind: the one write test runs inside
 * a transaction that is rolled back.
 *
 *   sudo docker cp scripts/verify-admin-tools.php veritas-cbt-web:/tmp/verify-admin-tools.php
 *   sudo docker exec -e VERITAS_APP_ROOT=/var/www/html veritas-cbt-web php /tmp/verify-admin-tools.php
 *   sudo docker exec veritas-cbt-web rm -f /tmp/verify-admin-tools.php
 */
$root = getenv('VERITAS_APP_ROOT') ?: dirname(__DIR__);
require $root . '/includes/cli_only.php';
require $root . '/db.php';
require_once $root . '/includes/db_export.php';

$failed = 0;
$pass = function (string $label) {
    echo "  PASS    {$label}\n";
};
$fail = function (string $label, string $why = '') use (&$failed) {
    $failed++;
    echo "  FAIL    {$label}" . ($why !== '' ? " — {$why}" : '') . "\n";
};
$check = function (bool $ok, string $label, string $why = '') use ($pass, $fail) {
    $ok ? $pass($label) : $fail($label, $why);
};
$tmp = sys_get_temp_dir();

$dbname = (string) $db->query('SELECT DATABASE()')->fetchColumn();
echo "Database : {$dbname}\n";
$tables = dbx_table_names($db);
echo "Tables   : " . count($tables) . "\n\n";

/* ── 1. Schema + trigger generation ─────────────────────────────────────── */
echo "Schema helpers\n";
$schema_ok = true;
foreach ($tables as $table) {
    $sql = dbx_create_sql($db, $table);
    if (!preg_match('/^CREATE TABLE/i', ltrim($sql))) {
        $schema_ok = false;
        $fail("SHOW CREATE TABLE {$table}", 'unexpected output');
        break;
    }
}
$check($schema_ok, 'CREATE TABLE generated for every table (' . count($tables) . ')');

$trigger_file = $tmp . '/veritas-triggers-' . getmypid() . '.sql';
$fh = fopen($trigger_file, 'wb');
$trigger_count = dbx_emit_triggers($db, static function (string $s) use ($fh) {
    fwrite($fh, $s);
});
fclose($fh);
$trigger_sql = (string) file_get_contents($trigger_file);
@unlink($trigger_file);
$check($trigger_count >= 0, "trigger exporter ran ({$trigger_count} trigger(s))");
if ($trigger_count > 0) {
    $check(substr_count($trigger_sql, 'CREATE TRIGGER') === $trigger_count, 'every trigger emitted');
    $check(substr_count($trigger_sql, 'DELIMITER ;;') === $trigger_count, 'DELIMITER wrappers balanced');
    $check(strpos($trigger_sql, 'DEFINER') === false, 'DEFINER clauses stripped');
}

/* ── 2. Exam-scoped export (bounded, real data) ──────────────────────────── */
echo "\nExam export\n";
$exam = $db->query("SELECT * FROM exams WHERE deleted_at IS NULL AND type = 'objective' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$exam) {
    $fail('find an objective exam');
} else {
    $exam_id = (int) $exam['id'];
    echo "  exam    : #{$exam_id} " . $exam['name'] . "\n";

    $candidates = ['objective_questions', 'questions', 'exam_questions', 'exam_students',
        'exam_examiners', 'objective_attempts', 'objective_answers', 'exam_violations', 'answers'];
    $exam_tables = [];
    foreach ($candidates as $table) {
        if (dbx_table_exists($db, $table) && in_array('exam_id', dbx_columns($db, $table), true)) {
            $exam_tables[] = $table;
        }
    }
    $check($exam_tables !== [], 'exam tables discovered (' . implode(', ', $exam_tables) . ')');

    $file = $tmp . '/veritas-exam-' . getmypid() . '.sql';
    $fh = fopen($file, 'wb');
    $emit = static function (string $s) use ($fh) {
        fwrite($fh, $s);
    };
    $emit(dbx_sql_header('Verification export', $dbname));
    $delete_order = dbx_exam_delete_order($exam_tables);
    foreach ($delete_order as $table) {
        $emit("DELETE FROM `{$table}` WHERE `exam_id` = {$exam_id};\n");
    }
    $emit("DELETE FROM `exams` WHERE `id` = {$exam_id};\n\n");
    $rows = 0;
    $rows += dbx_emit_table_data($db, 'exams', 'id = ?', [$exam_id], $emit);
    foreach ($exam_tables as $table) {
        $rows += dbx_emit_table_data($db, $table, 'exam_id = ?', [$exam_id], $emit);
    }
    $emit(dbx_sql_footer());
    fclose($fh);
    $sql = (string) file_get_contents($file);
    $size = (int) filesize($file);
    @unlink($file);

    $check($size > 0, 'exam file generated (' . number_format($rows) . ' row(s), ' . number_format($size) . ' bytes)');
    $check(strpos($sql, 'SET FOREIGN_KEY_CHECKS = 0') !== false, 'foreign-key checks disabled for restore');
    $check(strpos($sql, 'INSERT INTO `exams`') !== false, 'exam row emitted');
    $check($rows > 0 ? preg_match('/\) VALUES \(/', $sql) === 1 : true, 'INSERT ... VALUES format valid');

    $pos = -1;
    $ordered = true;
    foreach ($delete_order as $table) {
        $at = strpos($sql, "DELETE FROM `{$table}`");
        if ($at === false || $at < $pos) {
            $ordered = false;
            break;
        }
        $pos = $at;
    }
    $check($ordered, 'delete order respects foreign keys (' . implode(' → ', $delete_order) . ')');
    $check(strpos($sql, 'DELETE FROM `exams`') > $pos, 'exam row deleted after its children');
}

/* ── 3. Binary columns are emitted as hex literals ──────────────────────── */
echo "\nBinary safety\n";
$binary_tested = false;
foreach ($tables as $table) {
    $types = dbx_column_types($db, $table);
    $blob_cols = [];
    foreach ($types as $col => $type) {
        if (str_contains($type, 'blob') || str_contains($type, 'binary')) {
            $blob_cols[] = $col;
        }
    }
    if (!$blob_cols) {
        continue;
    }
    $col = $blob_cols[0];
    $has = (int) $db->query("SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` IS NOT NULL")->fetchColumn();
    if ($has === 0) {
        continue;
    }
    $sql = '';
    dbx_emit_table_data($db, $table, "`{$col}` IS NOT NULL", [], static function (string $s) use (&$sql) {
        $sql .= $s;
    }, 1);
    $binary_tested = true;
    $check(strpos($sql, '0x') !== false, "binary column {$table}.{$col} emitted as hex");
    break;
}
if (!$binary_tested) {
    echo "  SKIP    no non-empty binary column found\n";
}

/* ── 4. Answer override: save + rescore + rollback ──────────────────────── */
echo "\nAnswer override\n";
$attempt = $exam
    ? $db->query("SELECT * FROM objective_attempts WHERE exam_id = " . (int) $exam['id'] . " ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC)
    : false;
if (!$attempt) {
    echo "  SKIP    no attempt to exercise\n";
} else {
    $questions = qe_load_questions($db, (int) $attempt['exam_id']);
    $order = qe_attempt_question_ids($attempt, $questions);
    $check($questions !== [] && $order !== [], 'attempt questions loaded (' . count($order) . ')');

    $load = function () use ($db, $attempt) {
        $st = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
        $st->execute([$attempt['exam_id'], $attempt['student_id'], $attempt['attempt_no']]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $qid => $sel) {
            $out[(int) $qid] = (string) $sel;
        }
        return $out;
    };
    $before = $load();
    $before_score = qe_score_attempt($exam, $questions, $attempt, $before);

    $qid = (int) $order[0];
    $q = $questions[$qid];
    $current = (string) ($before[$qid] ?? '');
    if ($q['question_type'] === 'mtf') {
        $target = str_repeat('T', max(1, count($q['statements'])));
    } elseif ($q['question_type'] === 'mcq_multi') {
        $target = 'A';
    } else {
        $target = $current === 'A' ? 'B' : 'A';
    }
    $target = (string) qe_normalise_answer($q, $target);
    if ($target === '') {
        echo "  SKIP    could not build a valid alternative answer\n";
    } else {
        $db->beginTransaction();
        try {
            $saved = qe_save_answer($db, $q, (int) $attempt['exam_id'], (int) $attempt['student_id'], (int) $attempt['attempt_no'], $target);
            $after = $load();
            $after_score = qe_score_attempt($exam, $questions, $attempt, $after);
            $db->rollBack();
            $check($saved, 'qe_save_answer accepted the edit');
            $check(($after[$qid] ?? null) === $target, 'answer persisted inside the transaction');
            $check(is_array($after_score) && isset($after_score['raw']), 'score recalculated after the edit');
            $check($load() == $before, 'rollback restored the original answers');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $fail('transactional answer edit', $e->getMessage());
        }
    }
}

echo "\n";
if ($failed === 0) {
    echo "ALL ADMIN-TOOL CHECKS PASSED\n";
    exit(0);
}
echo "{$failed} CHECK(S) FAILED\n";
exit(1);
