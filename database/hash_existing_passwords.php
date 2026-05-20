<?php
/**
 * hash_existing_passwords.php — One-Time Migration Script
 *
 * Migrates all plaintext passwords in `examiners` and `students` tables
 * to bcrypt hashes using PHP's password_hash(PASSWORD_BCRYPT).
 *
 * Usage: Run once from the browser or CLI:
 *   php hash_existing_passwords.php
 *
 * SECURITY: Delete or move this file outside webroot after running.
 */
require_once 'db.php';

// Safety: only allow this script to run from CLI or with a secret token
$cliOnly = php_sapi_name() === 'cli';
$token   = $_GET['token'] ?? '';
$secret  = 'migrate_' . substr(md5(DB_NAME . DB_USER), 0, 12); // Simple one-time token

if (!$cliOnly && $token !== $secret) {
    http_response_code(403);
    die("<h2>Access Denied</h2><p>Run from CLI or append <code>?token=$secret</code> to URL.</p>");
}

header('Content-Type: text/plain; charset=utf-8');

$migrated = 0;
$skipped  = 0;
$errors   = 0;

// ── Migrate examiners ─────────────────────────────────────────────────────────
echo "=== Migrating EXAMINERS ===\n";
$rows = $db->query("SELECT id, username, password FROM examiners")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $pwd = $row['password'];

    // Skip if already a bcrypt hash (starts with $2y$ or $2b$)
    if (strlen($pwd) === 60 && (str_starts_with($pwd, '$2y$') || str_starts_with($pwd, '$2b$'))) {
        echo "  SKIP  [{$row['username']}] — already hashed\n";
        $skipped++;
        continue;
    }

    try {
        $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE examiners SET password = ? WHERE id = ?")->execute([$hash, $row['id']]);
        echo "  OK    [{$row['username']}] — hashed successfully\n";
        $migrated++;
    } catch (Exception $e) {
        echo "  ERROR [{$row['username']}] — " . $e->getMessage() . "\n";
        $errors++;
    }
}

// ── Migrate students ──────────────────────────────────────────────────────────
echo "\n=== Migrating STUDENTS ===\n";
$rows = $db->query("SELECT id, username, password FROM students WHERE password IS NOT NULL AND password != ''")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $pwd = $row['password'];

    if (strlen($pwd) === 60 && (str_starts_with($pwd, '$2y$') || str_starts_with($pwd, '$2b$'))) {
        echo "  SKIP  [{$row['username']}] — already hashed\n";
        $skipped++;
        continue;
    }

    try {
        $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE students SET password = ? WHERE id = ?")->execute([$hash, $row['id']]);
        echo "  OK    [{$row['username']}] — hashed successfully\n";
        $migrated++;
    } catch (Exception $e) {
        echo "  ERROR [{$row['username']}] — " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Migrated : $migrated\n";
echo "Skipped  : $skipped (already bcrypt)\n";
echo "Errors   : $errors\n";
echo "\nDone. Delete or move this file outside webroot now.\n";
