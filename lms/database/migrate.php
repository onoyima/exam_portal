<?php
/**
 * LMS Migration Runner — Veritas Integrated LMS
 * 
 * Usage: php lms/database/migrate.php [--rollback] [--seed]
 * 
 * Executes all migration files in order, tracking applied migrations
 * in the `lms_migrations` table.
 */

// ─── Bootstrap ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/../bootstrap.php';

use Lms\Core\Database;
use Lms\Core\Logger;

// ─── Configuration ──────────────────────────────────────────────────────────
$migrationsDir = __DIR__ . '/migrations';
$seedsDir      = __DIR__ . '/seeders';
$migrationTable = 'lms_migrations';

// ─── Parse CLI Arguments ────────────────────────────────────────────────────
$rollback = in_array('--rollback', $argv ?? []);
$seed     = in_array('--seed', $argv ?? []);
$fresh    = in_array('--fresh', $argv ?? []);

// ─── Migration Runner ───────────────────────────────────────────────────────
try {
    $db = Database::connection('lms');

    // Ensure migration tracking table exists
    $db->exec("CREATE TABLE IF NOT EXISTS {$migrationTable} (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        batch INT UNSIGNED NOT NULL,
        executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($fresh) {
        echo "[LMS] --fresh: Dropping all LMS tables...\n";
        $tables = $db->query("SHOW TABLES LIKE 'lms_%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $db->exec("DROP TABLE IF EXISTS {$table}");
            echo "  Dropped: {$table}\n";
        }
        $db->exec("TRUNCATE TABLE {$migrationTable}");
        echo "[LMS] All tables dropped. Running fresh migrations.\n";
    }

    // Get current batch
    $currentBatch = (int) $db->query("SELECT COALESCE(MAX(batch), 0) FROM {$migrationTable}")->fetchColumn();

    if ($rollback) {
        echo "[LMS] Rolling back last batch ({$currentBatch})...\n";
        $migrations = $db->prepare("SELECT migration FROM {$migrationTable} WHERE batch = ? ORDER BY id DESC");
        $migrations->execute([$currentBatch]);
        $files = $migrations->fetchAll(PDO::FETCH_COLUMN);

        foreach ($files as $file) {
            $filePath = $migrationsDir . '/' . $file;
            if (file_exists($filePath)) {
                $sql = file_get_contents($filePath);
                // Extract rollback (everything after -- DOWN)
                $parts = preg_split('/^--\s*DOWN\s*$/m', $sql);
                if (isset($parts[1]) && trim($parts[1])) {
                    echo "  Rolling back: {$file}\n";
                    $db->exec($parts[1]);
                } else {
                    echo "  Skipping (no DOWN section): {$file}\n";
                }
            }
            $db->prepare("DELETE FROM {$migrationTable} WHERE migration = ?")->execute([$file]);
        }
        echo "[LMS] Rollback complete.\n";
        exit(0);
    }

    // Get already applied migrations
    $applied = $db->query("SELECT migration FROM {$migrationTable}")->fetchAll(PDO::FETCH_COLUMN);
    $applied = array_flip($applied);

    // Get migration files
    $files = glob($migrationsDir . '/*.sql');
    sort($files);

    $newBatch = $currentBatch + 1;
    $count    = 0;

    echo "[LMS] Running migrations...\n";

    foreach ($files as $filePath) {
        $filename = basename($filePath);

        if (isset($applied[$filename])) {
            continue;
        }

        echo "  Applying: {$filename}... ";

        $sql = file_get_contents($filePath);

        // Extract UP section (before -- DOWN)
        $parts = preg_split('/^--\s*DOWN\s*$/m', $sql);
        $upSQL = trim($parts[0]);

        if (empty($upSQL)) {
            echo "SKIPPED (empty)\n";
            continue;
        }

        try {
            // Split by semicolons for multi-statement execution
            $statements = array_filter(
                array_map('trim', explode(';', $upSQL)),
                fn($s) => !empty($s)
            );

            foreach ($statements as $stmt) {
                $db->exec($stmt . ';');
            }

            // Record migration
            $stmt = $db->prepare("INSERT INTO {$migrationTable} (migration, batch) VALUES (?, ?)");
            $stmt->execute([$filename, $newBatch]);

            echo "OK\n";
            $count++;
        } catch (\PDOException $e) {
            echo "FAILED: {$e->getMessage()}\n";
            exit(1);
        }
    }

    if ($count === 0) {
        echo "[LMS] Nothing to migrate. All migrations are up to date.\n";
    } else {
        echo "[LMS] Applied {$count} migration(s) in batch {$newBatch}.\n";
    }

    // ─── Seed ────────────────────────────────────────────────────────────────
    if ($seed && is_dir($seedsDir)) {
        echo "[LMS] Running seeders...\n";
        $seedFiles = glob($seedsDir . '/*.php');
        sort($seedFiles);

        foreach ($seedFiles as $seedFile) {
            echo "  Seeding: " . basename($seedFile) . "... ";
            try {
                require $seedFile;
                echo "OK\n";
            } catch (\Exception $e) {
                echo "FAILED: {$e->getMessage()}\n";
            }
        }
        echo "[LMS] Seeding complete.\n";
    }

} catch (\Exception $e) {
    Logger::error('Migration failed', ['error' => $e->getMessage()]);
    echo "[LMS] Migration failed: {$e->getMessage()}\n";
    exit(1);
}
