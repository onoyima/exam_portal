<?php
/**
 * patch-session-boot.php — insert the config load before session_start().
 *
 * Usage: php patch-session-boot.php [--dry-run] /var/www/html/file1.php [...]
 *
 * config.php must run before session_start() so its session.save_path (the
 * mounted /var/lib/php/sessions) takes effect. This edits the live file in
 * place, touching nothing else, and is safe to run twice.
 */

$args = array_slice($argv, 1);
$dry = false;
if (($args[0] ?? '') === '--dry-run') {
    $dry = true;
    array_shift($args);
}

$changed = 0;
$skipped = 0;
$failed = 0;

foreach ($args as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "missing: {$path}\n");
        $failed++;
        continue;
    }
    $src = file_get_contents($path);
    if ($src === false) {
        fwrite(STDERR, "unreadable: {$path}\n");
        $failed++;
        continue;
    }

    if (!preg_match('/\bsession_start\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
        echo "skip (no session_start): {$path}\n";
        $skipped++;
        continue;
    }
    $startPos = $m[0][1];

    $cfgPos = strpos($src, 'config.php');
    if ($cfgPos !== false && $cfgPos < $startPos) {
        echo "already patched: {$path}\n";
        $skipped++;
        continue;
    }

    $new = preg_replace(
        '/\A<\?php[ \t]*\r?\n/',
        "<?php\n// Load config before the session so the mounted session path is used.\n"
        . "require_once __DIR__ . '/config.php';\n",
        $src,
        1,
        $count
    );
    if ($new === null || $count !== 1) {
        fwrite(STDERR, "cannot patch (unexpected opening tag): {$path}\n");
        $failed++;
        continue;
    }

    $tmp = $path . '.session-boot.tmp';
    if ($dry) {
        echo "patched: {$path} (dry-run)\n";
        $changed++;
        continue;
    }
    if (file_put_contents($tmp, $new) === false) {
        fwrite(STDERR, "cannot write temp file for: {$path}\n");
        $failed++;
        continue;
    }
    @chmod($tmp, fileperms($path) & 0777);
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        fwrite(STDERR, "cannot replace: {$path}\n");
        $failed++;
        continue;
    }
    echo "patched: {$path}\n";
    $changed++;
}

echo "changed={$changed} skipped={$skipped} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
