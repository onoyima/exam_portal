<?php
/**
 * Direct migration runner for production DB - loads creds from .env
 * Run: php run_migration.php
 */
$envPath = __DIR__ . '/.env';

function loadGroups(string $envPath): array {
    $groups = [];
    $cur = null;
    foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $raw) {
        $line = preg_replace('/^(?:\s*#\s*)+/', '', trim($raw));
        if ($line === '' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if (preg_match('/^"(.*)"$/', $v, $m) || preg_match("/^'(.*)'$/", $v, $m)) $v = $m[1];
        $map = ['DB_HOST'=>'host','DB_PORT'=>'port','DB_NAME'=>'name','DB_DATABASE'=>'name','DB_USER'=>'user','DB_USERNAME'=>'user','DB_PASS'=>'pass','DB_PASSWORD'=>'pass'];
        if (!isset($map[$k])) continue;
        $field = $map[$k];
        if ($cur === null || $field === 'host' || ($field === 'name' && !empty($cur['name']) && $cur['name'] !== $v)) {
            if ($cur !== null && !empty($cur['host'])) $groups[] = $cur;
            $cur = [];
        }
        $cur[$field] = $v;
    }
    if ($cur !== null && !empty($cur['host'])) $groups[] = $cur;
    return $groups;
}

function connectSet(array $c): PDO {
    $dsn = 'mysql:host=' . $c['host'] . ';port=' . ($c['port'] ?? 3306) . ';dbname=' . $c['name'] . ';charset=utf8mb4';
    return new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 30, PDO::ATTR_EMULATE_PREPARES => false]);
}

$groups = loadGroups($envPath);
$target = null;
foreach ($groups as $g) {
    if (($g['name'] ?? '') === 'dbnhedghziuzs9') { $target = $g; break; }
}
if (!$target) { fwrite(STDERR, "dbnhedghziuzs9 config not found in .env\n"); exit(1); }

try {
    $pdo = connectSet($target);
    echo "Connected to {$target['name']} on {$target['host']}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Connection failed: {$e->getMessage()}\n");
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/migrations/server_schema_migration.sql');
if ($sql === false) { fwrite(STDERR, "Could not read migration file\n"); exit(1); }

// Split statements
$statements = [];
$buffer = '';
$inSingle = false; $inDouble = false;
$len = strlen($sql);
for ($i = 0; $i < $len; $i++) {
    $ch = $sql[$i];
    $buffer .= $ch;
    if ($ch === "'" && !$inDouble) $inSingle = !$inSingle;
    elseif ($ch === '"' && !$inSingle) $inDouble = !$inDouble;
    elseif ($ch === ';' && !$inSingle && !$inDouble) {
        $stmt = trim($buffer); $buffer = '';
        if ($stmt !== '' && !preg_match('/^\s*--/', $stmt)) $statements[] = $stmt;
    }
}
if (trim($buffer) !== '') { $stmt = trim($buffer); if (!preg_match('/^\s*--/', $stmt)) $statements[] = $stmt; }

echo "Found " . count($statements) . " statements\n";

$ok = 0; $fail = 0;
foreach ($statements as $i => $stmt) {
    echo "[" . ($i+1) . "/" . count($statements) . "] ";
    try {
        $pdo->exec($stmt);
        echo "OK\n"; $ok++;
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column') !== false || strpos($msg, 'Duplicate key') !== false) {
            echo "SKIP (already exists)\n"; $ok++;
        } else {
            echo "FAIL: $msg\n"; echo "  SQL: " . substr($stmt, 0, 120) . "...\n"; $fail++;
        }
    }
}

echo "\nDone: $ok ok, $fail failed\n";

// Verification
try {
    $t = $pdo->query("SHOW TABLES LIKE 'exam_groups'")->fetchColumn(); echo "exam_groups: " . ($t ? "YES" : "NO") . "\n";
    $c = $pdo->query("SHOW COLUMNS FROM examiners LIKE 'session_token'")->fetchColumn(); echo "examiners.session_token: " . ($c ? "YES" : "NO") . "\n";
    $c = $pdo->query("SHOW COLUMNS FROM exams LIKE 'group_id'")->fetchColumn(); echo "exams.group_id: " . ($c ? "YES" : "NO") . "\n";
    $c = $pdo->query("SHOW COLUMNS FROM objective_questions LIKE 'question_type'")->fetchColumn(); echo "objective_questions.question_type: " . ($c ? "YES" : "NO") . "\n";
    $c = $pdo->query("SHOW COLUMNS FROM objective_answers LIKE 'selected_option'")->fetchColumn(); echo "objective_answers.selected_option: " . ($c ? "YES" : "NO") . "\n";
    $c = $pdo->query("SHOW COLUMNS FROM objective_attempts LIKE 'time_used'")->fetchColumn(); echo "objective_attempts.time_used: " . ($c ? "YES" : "NO") . "\n";
} catch (Throwable $e) { echo "Verify error: " . $e->getMessage() . "\n"; }