<?php
/**
 * Repair incomplete student names using the school portal (exeat2) database.
 *
 * Usage:
 *   php tools/repair_student_names.php          # dry run (report only)
 *   php tools/repair_student_names.php --apply  # write changes
 */

require __DIR__ . '/../db.php';
require __DIR__ . '/../db_exeat2.php';
require_once __DIR__ . '/../includes/student_import_helper.php';

$apply = in_array('--apply', $argv ?? [], true);

function exeat2_lookup_by_email($exeat2Db, array $emails): array
{
    $result = [];
    if (!$exeat2Db) {
        return $result;
    }
    $emails = array_values(array_unique(array_filter(array_map('sih_clean', $emails))));
    foreach (array_chunk($emails, 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $sql = "SELECT s.email, sa.matric_no, s.fname AS firstname, s.mname AS middlename,
                       s.lname AS lastname
                FROM students s
                JOIN student_academics sa ON sa.student_id = s.id
                WHERE s.email IN ($placeholders)
                ORDER BY sa.academic_session_id DESC";
        try {
            $stmt = $exeat2Db->prepare($sql);
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!isset($result[$row['email']])) {
                    $result[$row['email']] = $row;
                }
            }
        } catch (Exception $e) {
            error_log('repair email lookup failed: ' . $e->getMessage());
        }
    }
    return $result;
}

$sql = "SELECT * FROM students
        WHERE deleted_at IS NULL AND (
            firstname IS NULL OR firstname = ''
            OR lastname IS NULL OR lastname = ''
            OR middlename = 'NULL'
            OR UPPER(name) LIKE '% NULL %'
            OR name = matric_no
            OR name NOT LIKE '% %'
            OR (middlename = firstname AND middlename <> '')
        )
        ORDER BY id";
$students = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "Database: " . DB_NAME . "\n";
echo "Candidates with incomplete names: " . count($students) . "\n";
echo "Mode: " . ($apply ? "APPLY" : "DRY RUN") . "\n\n";

$byMatric = sih_exeat2_lookup($exeat2_db, array_column($students, 'matric_no'));
$byEmail = exeat2_lookup_by_email($exeat2_db, array_column($students, 'email'));

// Legacy stubs from the old importer have a name fragment in matric_no, so the
// school lookup cannot find them. Recover their names from a unique duplicate
// record (same first/middle/last parts) instead.
$legacyIds = [];
foreach ($students as $student) {
    if (!isset($byMatric[sih_clean($student['matric_no'])]) && !isset($byEmail[sih_clean($student['email'])])) {
        $legacyIds[] = (int) $student['id'];
    }
}

$duplicateMatches = [];
foreach (array_chunk($legacyIds, 500) as $chunk) {
    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
    $dupSql = "SELECT bad.id AS bad_id, good.firstname, good.middlename,
                      good.lastname, good.name, good.email
               FROM students bad
               JOIN students good ON good.deleted_at IS NULL AND good.id <> bad.id
                   AND good.firstname = bad.name
                   AND good.middlename = bad.matric_no
                   AND good.lastname = bad.username
               WHERE bad.deleted_at IS NULL AND bad.id IN ($placeholders)";
    try {
        $dupStmt = $db->prepare($dupSql);
        $dupStmt->execute($chunk);
        $counts = [];
        $chunkMatches = [];
        $rows = $dupStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $bid = (int) $row['bad_id'];
            $counts[$bid] = ($counts[$bid] ?? 0) + 1;
            if (!isset($chunkMatches[$bid])) {
                $chunkMatches[$bid] = $row;
            }
        }
        foreach ($chunkMatches as $bid => $row) {
            if ($counts[$bid] === 1) {
                $duplicateMatches[$bid] = $row;
            }
        }
    } catch (Exception $e) {
        error_log('repair duplicate lookup failed: ' . $e->getMessage());
    }
}

$repaired = 0;
$unmatched = 0;
$unchanged = 0;
$errors = 0;

foreach ($students as $student) {
    $matric = sih_clean($student['matric_no']);
    $email = sih_clean($student['email']);
    $school = $byMatric[$matric] ?? ($email !== '' ? ($byEmail[$email] ?? null) : null);
    $source = $school ? 'school' : null;
    if (!$school && isset($duplicateMatches[(int) $student['id']])) {
        $school = $duplicateMatches[(int) $student['id']];
        $source = 'duplicate';
    }

    $name = preg_replace('/\bNULL\b/i', ' ', sih_clean($student['name'] ?? ''));
    $name = preg_replace('/\s+/', ' ', trim($name));
    [$nameFirst, $nameMiddle, $nameLast] = sih_split_full_name($name);

    $first = sih_clean($student['firstname'] ?? '');
    $middle = sih_clean($student['middlename'] ?? '');
    $last = sih_clean($student['lastname'] ?? '');
    if ($first === '') {
        $first = $nameFirst;
    }
    if ($middle === '') {
        $middle = $nameMiddle;
    }
    if ($last === '') {
        $last = $nameLast;
    }

    $fields = [
        'firstname' => $first,
        'middlename' => $middle,
        'lastname' => $last,
        'name' => $name,
        'email' => $email,
        'matric_no' => $matric,
        'username' => '',
        'password' => '',
        'password_provided' => false,
    ];

    // When the school DB has the student, it is authoritative for names.
    // Never feed it the broken local name parts, or they get duplicated.
    if ($school) {
        $fields['firstname'] = '';
        $fields['middlename'] = '';
        $fields['lastname'] = '';
        $fields['name'] = '';
    }

    $before = sih_compose_name($student['firstname'] ?? '', $student['middlename'] ?? '', $student['lastname'] ?? '');
    if ($before === '') {
        $before = $student['name'] ?? '';
    }

    if (!$school && $name === sih_compose_name($first, $middle, $last)) {
        $unmatched++;
        if ($unmatched <= 10) {
            echo "  [no school match] id={$student['id']} matric={$matric} name='{$before}'\n";
        }
        continue;
    }

    if (!$apply) {
        $after = $school
            ? sih_compose_name($school['firstname'] ?? '', $school['middlename'] ?? '', $school['lastname'] ?? '')
            : sih_compose_name($first, $middle, $last);
        if ($after === $before) {
            $unchanged++;
        } else {
            $repaired++;
            if ($repaired <= 20) {
                echo "  id={$student['id']} matric={$matric}: '{$before}' -> '{$after}'" . ($source ? " [{$source}]" : "") . "\n";
            }
        }
        continue;
    }

    try {
        if ($school && sih_clean($school['matric_no'] ?? '') !== '' && sih_clean($school['matric_no']) !== $matric) {
            $fields['matric_no'] = sih_clean($school['matric_no']);
        }
        $saved = sih_upsert_student($db, $fields, $school, true, false, $school !== null);
        $repaired++;
    } catch (Exception $e) {
        $errors++;
        echo "  [error] id={$student['id']}: " . $e->getMessage() . "\n";
    }
}

echo "\n" . ($apply ? "Repaired" : "Would repair") . ": {$repaired}\n";
echo "No school match: {$unmatched}\n";
if (!$apply) {
    echo "Already complete: {$unchanged}\n";
}
if ($errors) {
    echo "Errors: {$errors}\n";
}
if (!$apply) {
    echo "\nRun with --apply to write these changes.\n";
}
