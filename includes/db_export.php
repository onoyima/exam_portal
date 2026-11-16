<?php
/**
 * Streaming SQL export helpers used by the admin backup page.
 *
 * Everything here works through PDO, so an export does not depend on the
 * mysqldump binary being installed in the web container. Rows are read in
 * chunks and echoed straight to the browser, which keeps memory flat even
 * when the answers / snapshot tables are large.
 *
 * Table names always come from SHOW TABLES, never from the request, and are
 * validated again before being placed inside backticks.
 */

function dbx_assert_identifier(string $table): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        throw new InvalidArgumentException('Unsafe table name: ' . $table);
    }
}

/** Base tables in the current database, sorted by name. */
function dbx_table_names(PDO $db): array
{
    $rows = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    $out = [];
    foreach ($rows as $row) {
        $name = (string) ($row[0] ?? '');
        if ($name !== '' && preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $out[] = $name;
        }
    }
    sort($out);
    return $out;
}

function dbx_table_exists(PDO $db, string $table): bool
{
    dbx_assert_identifier($table);
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ? AND table_type = 'BASE TABLE'");
    $st->execute([$table]);
    return (bool) $st->fetchColumn();
}

function dbx_columns(PDO $db, string $table): array
{
    dbx_assert_identifier($table);
    return $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN, 0);
}

/** Column name => data type, used to emit binary columns as hex literals. */
function dbx_column_types(PDO $db, string $table): array
{
    dbx_assert_identifier($table);
    $rows = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $types = [];
    foreach ($rows as $row) {
        $types[(string) $row['Field']] = strtolower((string) $row['Type']);
    }
    return $types;
}

/** Primary-key columns, so chunked paging has a stable order. */
function dbx_primary_key(PDO $db, string $table): array
{
    dbx_assert_identifier($table);
    $rows = $db->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
    $cols = [];
    foreach ($rows as $row) {
        $cols[(int) $row['Seq_in_index']] = (string) $row['Column_name'];
    }
    ksort($cols);
    return array_values($cols);
}

function dbx_create_sql(PDO $db, string $table): string
{
    dbx_assert_identifier($table);
    $row = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
    $sql = (string) ($row[1] ?? '');
    // A dump should never carry a DEFINER clause that breaks on another server.
    $sql = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`\s*/i', '', $sql);
    $sql = preg_replace('/DEFINER\s*=\s*[^\s]+\s*/i', '', $sql);
    return $sql;
}

function dbx_count(PDO $db, string $table, ?string $where = null, array $args = []): int
{
    dbx_assert_identifier($table);
    $sql = "SELECT COUNT(*) FROM `$table`" . ($where ? " WHERE $where" : '');
    $st = $db->prepare($sql);
    $st->execute($args);
    return (int) $st->fetchColumn();
}

/** Common preamble for every generated .sql file. */
function dbx_sql_header(string $title, string $dbname, string $extra = ''): string
{
    $out = "-- ============================================================\n";
    $out .= "-- Veritas CBT — " . $title . "\n";
    $out .= "-- Database : " . $dbname . "\n";
    $out .= "-- Generated: " . date('Y-m-d H:i:s') . " (" . date_default_timezone_get() . ")\n";
    $out .= "-- Server   : " . php_uname('n') . "\n";
    if ($extra !== '') {
        $out .= "-- " . str_replace("\n", "\n-- ", trim($extra)) . "\n";
    }
    $out .= "-- ============================================================\n\n";
    $out .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
    $out .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
    $out .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
    $out .= "/*!50503 SET NAMES utf8mb4 */;\n";
    $out .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n";
    $out .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n";
    $out .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n\n";
    $out .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $out .= "SET UNIQUE_CHECKS = 0;\n";
    $out .= "SET AUTOCOMMIT = 1;\n\n";
    return $out;
}

function dbx_sql_footer(): string
{
    return "\n/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n"
        . "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n"
        . "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n"
        . "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n"
        . "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n"
        . "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n"
        . "-- Export complete\n";
}

/**
 * Writes INSERT statements for one table, one row per statement.
 *
 * $emit receives a string chunk. Chunking by rows (rather than building the
 * whole table in memory) keeps a full export safe on a small PHP memory limit.
 * $verb can be "INSERT IGNORE INTO" for reference rows that must not clobber
 * an existing copy (students, exam groups, ...).
 */
function dbx_emit_table_data(
    PDO $db,
    string $table,
    ?string $where = null,
    array $args = [],
    ?callable $emit = null,
    int $chunk = 250,
    string $verb = 'INSERT INTO'
): int {
    dbx_assert_identifier($table);
    $emit = $emit ?? static function (string $s): void {
        echo $s;
    };
    $cols = dbx_columns($db, $table);
    if (!$cols) {
        return 0;
    }
    $types = dbx_column_types($db, $table);
    $binary = [];
    foreach ($types as $col => $type) {
        if (str_contains($type, 'blob') || str_contains($type, 'binary')) {
            $binary[$col] = true;
        }
    }
    $colSql = '`' . implode('`, `', $cols) . '`';
    $base = "SELECT * FROM `$table`" . ($where ? " WHERE $where" : '');
    $order = dbx_primary_key($db, $table);
    if (!$order) {
        $order = [$cols[0]];
    }
    $orderSql = ' ORDER BY `' . implode('`, `', $order) . '`';
    $total = 0;
    $offset = 0;

    while (true) {
        // LIMIT/OFFSET values are internal integers, so interpolating them is
        // safe and avoids native-prepare quirks with bound LIMIT parameters.
        $st = $db->prepare($base . $orderSql . " LIMIT $chunk OFFSET $offset");
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $st->closeCursor();
        if (!$rows) {
            break;
        }

        $lines = '';
        foreach ($rows as $row) {
            $values = [];
            foreach ($cols as $col) {
                $value = $row[$col] ?? null;
                if ($value === null) {
                    $values[] = 'NULL';
                } elseif (isset($binary[$col])) {
                    // Binary data cannot survive a quoted string safely.
                    $values[] = '0x' . bin2hex((string) $value);
                } else {
                    $values[] = $db->quote((string) $value);
                }
            }
            $lines .= $verb . " `$table` ($colSql) VALUES (" . implode(', ', $values) . ");\n";
        }
        $emit($lines);

        $count = count($rows);
        $total += $count;
        if ($count < $chunk) {
            break;
        }
        $offset += $chunk;
    }

    return $total;
}

/**
 * Re-creates the database triggers.
 *
 * A full export drops the tables, which drops their triggers too. The audit
 * trail depends on those triggers, so they are emitted after the data with
 * the usual DELIMITER wrapper.
 */
function dbx_emit_triggers(PDO $db, ?callable $emit = null): int
{
    $emit = $emit ?? static function (string $s): void {
        echo $s;
    };
    try {
        $triggers = $db->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Exporting data must not fail just because triggers cannot be read.
        return 0;
    }
    $written = 0;
    foreach ($triggers as $trigger) {
        $name = (string) ($trigger['Trigger'] ?? '');
        if ($name === '' || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            continue;
        }
        try {
            $row = $db->query("SHOW CREATE TRIGGER `$name`")->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            continue;
        }
        $sql = (string) ($row['SQL Original Statement'] ?? '');
        if ($sql === '') {
            continue;
        }
        $sql = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`\s*/i', '', $sql);
        $sql = preg_replace('/DEFINER\s*=\s*[^\s]+\s*/i', '', $sql);
        $emit("DROP TRIGGER IF EXISTS `$name`;\n");
        $emit("DELIMITER ;;\n" . rtrim(rtrim($sql), ';') . ";;\nDELIMITER ;\n");
        $written++;
    }
    return $written;
}

/**
 * Child-before-parent delete order for an exam-scoped restore.
 * Tables without a preferred position are kept in their original order.
 */
function dbx_exam_delete_order(array $exam_tables): array
{
    $preferred = ['answers', 'exam_questions', 'objective_answers', 'objective_attempts',
        'objective_questions', 'questions', 'exam_students', 'exam_violations'];
    $out = [];
    foreach ($preferred as $table) {
        if (in_array($table, $exam_tables, true)) {
            $out[] = $table;
        }
    }
    foreach ($exam_tables as $table) {
        if (!in_array($table, $preferred, true)) {
            $out[] = $table;
        }
    }
    return $out;
}

/** Filename-safe slug for an exam name. */
function dbx_slug(string $text, int $max = 40): string
{
    $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $text);
    $slug = trim((string) $slug, '-');
    if ($slug === '') {
        $slug = 'exam';
    }
    return substr($slug, 0, $max);
}
