<?php
/**
 * student_import_helper.php
 * Shared CSV name parsing / normalization for student imports.
 */

if (!function_exists('sih_clean')) {
    function sih_clean($value): string
    {
        if ($value === null) {
            return '';
        }
        $value = (string) $value;
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = trim($value);
        $upper = strtoupper($value);
        if (in_array($upper, ['NULL', 'N/A', 'NA', 'NIL', 'NONE', '-', '--', 'UNKNOWN'], true)) {
            return '';
        }
        return $value;
    }
}

if (!function_exists('sih_norm_header')) {
    function sih_norm_header($header): string
    {
        $header = strtolower(sih_clean($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        return trim($header, '_');
    }
}

if (!function_exists('sih_header_map')) {
    function sih_header_map(array $headers): array
    {
        $aliases = [
            'firstname' => ['firstname', 'first_name', 'fname', 'given_name', 'givenname', 'forename'],
            'middlename' => ['middlename', 'middle_name', 'mname', 'othername', 'other_name', 'othernames', 'other_names', 'middle'],
            'lastname' => ['lastname', 'last_name', 'lname', 'surname', 'family_name', 'familyname'],
            'name' => ['name', 'fullname', 'full_name', 'full_names', 'student_name', 'studentname', 'full'],
            'email' => ['email', 'email_address', 'e_mail', 'mail'],
            'matric_no' => ['matric_no', 'matric', 'matric_number', 'matriculation_number', 'mat_no', 'matno', 'reg_no', 'reg_number', 'registration_number', 'admission_no', 'admission_number'],
            'password' => ['password', 'pass', 'pwd'],
            'username' => ['username', 'user_name', 'user'],
        ];

        $normalized = array_map('sih_norm_header', $headers);
        $map = [];
        foreach ($normalized as $index => $header) {
            if ($header === '') {
                continue;
            }
            foreach ($aliases as $field => $names) {
                if (!isset($map[$field]) && in_array($header, $names, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }
        return $map;
    }
}

if (!function_exists('sih_row_value')) {
    function sih_row_value(array $row, array $map, string $field, string $default = ''): string
    {
        if (!isset($map[$field])) {
            return $default;
        }
        $value = sih_clean($row[$map[$field]] ?? '');
        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('sih_split_full_name')) {
    function sih_split_full_name($fullName): array
    {
        $fullName = preg_replace('/\s+/', ' ', sih_clean($fullName));
        $fullName = preg_replace('/\b(NULL|N\/A|NIL|NONE|UNKNOWN)\b/i', ' ', $fullName);
        $fullName = preg_replace('/\s+/', ' ', trim($fullName));
        if ($fullName === '') {
            return ['', '', ''];
        }
        $parts = explode(' ', $fullName);
        if (count($parts) === 1) {
            return [$parts[0], '', ''];
        }
        if (count($parts) === 2) {
            return [$parts[0], '', $parts[1]];
        }
        $first = array_shift($parts);
        $last = array_pop($parts);
        return [$first, implode(' ', $parts), $last];
    }
}

if (!function_exists('sih_compose_name')) {
    function sih_compose_name($first, $middle, $last): string
    {
        $name = trim($first . ' ' . $middle . ' ' . $last);
        return preg_replace('/\s+/', ' ', $name);
    }
}

if (!function_exists('sih_resolve_names')) {
    /**
     * Returns [firstname, middlename, lastname, full_name].
     * Accepts either separate name columns or a single full-name column.
     */
    function sih_resolve_names(array $row, array $map): array
    {
        $first = sih_row_value($row, $map, 'firstname');
        $middle = sih_row_value($row, $map, 'middlename');
        $last = sih_row_value($row, $map, 'lastname');
        $full = sih_row_value($row, $map, 'name');

        if ($full !== '') {
            if ($first === '' || $last === '') {
                [$f, $m, $l] = sih_split_full_name($full);
                if ($first === '') {
                    $first = $f;
                }
                if ($middle === '') {
                    $middle = $m;
                }
                if ($last === '') {
                    $last = $l;
                }
            }
        }

        $composed = sih_compose_name($first, $middle, $last);
        $name = $composed;

        if ($full !== '') {
            $full = preg_replace('/\s+/', ' ', $full);
            if (strlen($full) > strlen($composed)) {
                $name = $full;
                if ($first === '' || $last === '') {
                    [$f, $m, $l] = sih_split_full_name($full);
                    if ($first === '') {
                        $first = $f;
                    }
                    if ($middle === '') {
                        $middle = $m;
                    }
                    if ($last === '') {
                        $last = $l;
                    }
                }
            }
        }

        return [$first, $middle, $last, $name];
    }
}

if (!function_exists('sih_merge_names')) {
    /**
     * Merge CSV name parts with an existing/local record and/or school DB row.
     * CSV values win; blank CSV values fall back to school DB, then existing.
     */
    function sih_merge_names(array $csvParts, ?array $exeat2, ?array $existing): array
    {
        [$first, $middle, $last, $name] = $csvParts;

        $schoolFirst = $exeat2 ? sih_clean($exeat2['firstname'] ?? '') : '';
        $schoolMiddle = $exeat2 ? sih_clean($exeat2['middlename'] ?? '') : '';
        $schoolLast = $exeat2 ? sih_clean($exeat2['lastname'] ?? '') : '';

        $oldFirst = $existing ? sih_clean($existing['firstname'] ?? '') : '';
        $oldMiddle = $existing ? sih_clean($existing['middlename'] ?? '') : '';
        $oldLast = $existing ? sih_clean($existing['lastname'] ?? '') : '';

        if ($first === '') {
            $first = $schoolFirst !== '' ? $schoolFirst : $oldFirst;
        }
        if ($middle === '') {
            $middle = $schoolMiddle !== '' ? $schoolMiddle : $oldMiddle;
        }
        if ($last === '') {
            $last = $schoolLast !== '' ? $schoolLast : $oldLast;
        }

        if ($first === '' && $last === '' && $name !== '') {
            [$first, $middle, $last] = sih_split_full_name($name);
        }

        $composed = sih_compose_name($first, $middle, $last);
        if ($composed !== '') {
            $name = $composed;
        }

        return [$first, $middle, $last, $name];
    }
}

if (!function_exists('sih_read_student_csv')) {
    /**
     * Read a CSV/TXT upload into [header_map, header_found, rows].
     * Detects comma/semicolon/tab delimiters and skips blank lines.
     */
    function sih_read_student_csv(string $file): array
    {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return [[], false, []];
        }

        $firstLine = fgets($handle);
        $delimiter = ',';
        if ($firstLine !== false) {
            $counts = [
                ',' => substr_count($firstLine, ','),
                ';' => substr_count($firstLine, ';'),
                "\t" => substr_count($firstLine, "\t"),
            ];
            arsort($counts);
            $candidate = array_key_first($counts);
            if ($counts[$candidate] > 0) {
                $delimiter = $candidate;
            }
        }
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null]) {
                continue;
            }
            if (count($row) === 1 && sih_clean($row[0]) === '') {
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);

        if (empty($rows)) {
            return [[], false, []];
        }

        $map = sih_header_map($rows[0]);
        $headerFound = !empty($map);
        if ($headerFound) {
            array_shift($rows);
        }
        return [$map, $headerFound, $rows];
    }
}

if (!function_exists('sih_parse_fields')) {
    /**
     * Convert one raw row into normalized student fields.
     */
    function sih_parse_fields(array $row, array $map, bool $headerFound): array
    {
        if ($headerFound) {
            [$first, $middle, $last, $name] = sih_resolve_names($row, $map);
            return [
                'firstname' => $first,
                'middlename' => $middle,
                'lastname' => $last,
                'name' => $name,
                'email' => sih_row_value($row, $map, 'email'),
                'matric_no' => sih_row_value($row, $map, 'matric_no'),
                'username' => sih_row_value($row, $map, 'username'),
                'password' => sih_row_value($row, $map, 'password'),
                'password_provided' => isset($map['password']),
            ];
        }

        $row = array_pad($row, 6, '');
        $c0 = sih_clean($row[0]);
        $c1 = sih_clean($row[1]);
        $c2 = sih_clean($row[2]);

        // Legacy/hand-made layout: full name, personal email, matric (or name, matric, username)
        if ($c0 !== '' && strpos($c0, ' ') !== false && $c1 !== '' && strpos($c1, '@') !== false && preg_match('/[0-9\/]/', $c2)) {
            [$first, $middle, $last] = sih_split_full_name($c0);
            return [
                'firstname' => $first,
                'middlename' => $middle,
                'lastname' => $last,
                'name' => $c0,
                'email' => $c1,
                'matric_no' => $c2,
                'username' => '',
                'password' => '',
                'password_provided' => false,
            ];
        }
        if ($c0 !== '' && strpos($c0, ' ') !== false && $c1 !== '' && preg_match('/[0-9\/]/', $c1) && $c2 === '') {
            [$first, $middle, $last] = sih_split_full_name($c0);
            return [
                'firstname' => $first,
                'middlename' => $middle,
                'lastname' => $last,
                'name' => $c0,
                'email' => '',
                'matric_no' => $c1,
                'username' => $c2,
                'password' => '',
                'password_provided' => false,
            ];
        }

        // Template order: firstname, middlename, lastname, email, matric_no, password
        return [
            'firstname' => $c0,
            'middlename' => $c1,
            'lastname' => $c2,
            'name' => sih_compose_name($c0, $c1, $c2),
            'email' => sih_clean($row[3]),
            'matric_no' => sih_clean($row[4]),
            'username' => '',
            'password' => sih_clean($row[5]),
            'password_provided' => sih_clean($row[5]) !== '',
        ];
    }
}

if (!function_exists('sih_upsert_student')) {
    /**
     * Insert or update a student using CSV fields plus optional school-DB row.
     * Returns ['id' => int, 'created' => bool].
     */
    function sih_upsert_student(PDO $db, array $csv, ?array $school = null, bool $preferCsv = true, bool $passwordProvided = false, bool $ignoreExistingParts = false): array
    {
        $matric = sih_clean($csv['matric_no'] ?? '');
        $stmt = $db->prepare("SELECT * FROM students WHERE matric_no = ?");
        $stmt->execute([$matric]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $csvFirst = sih_clean($csv['firstname'] ?? '');
        $csvMiddle = sih_clean($csv['middlename'] ?? '');
        $csvLast = sih_clean($csv['lastname'] ?? '');
        $csvName = sih_clean($csv['name'] ?? '');

        $schoolFirst = $school ? sih_clean($school['firstname'] ?? '') : '';
        $schoolMiddle = $school ? sih_clean($school['middlename'] ?? '') : '';
        $schoolLast = $school ? sih_clean($school['lastname'] ?? '') : '';

        $oldFirst = $existing ? sih_clean($existing['firstname'] ?? '') : '';
        $oldMiddle = $existing ? sih_clean($existing['middlename'] ?? '') : '';
        $oldLast = $existing ? sih_clean($existing['lastname'] ?? '') : '';

        $pick = static function ($csvValue, $schoolValue, $oldValue) use ($preferCsv, $ignoreExistingParts) {
            if ($ignoreExistingParts) {
                $oldValue = '';
            }
            if ($preferCsv) {
                return $csvValue !== '' ? $csvValue : ($schoolValue !== '' ? $schoolValue : $oldValue);
            }
            return $oldValue !== '' ? $oldValue : ($schoolValue !== '' ? $schoolValue : $csvValue);
        };

        $first = $pick($csvFirst, $schoolFirst, $oldFirst);
        $middle = $pick($csvMiddle, $schoolMiddle, $oldMiddle);
        $last = $pick($csvLast, $schoolLast, $oldLast);

        if ($first === '' && $last === '' && $csvName !== '') {
            [$first, $middle, $last] = sih_split_full_name($csvName);
        }

        $fullName = sih_compose_name($first, $middle, $last);
        if ($csvName !== '' && strlen($csvName) > strlen($fullName)) {
            $fullName = $csvName;
        }
        if ($fullName === '') {
            $fullName = $matric;
        }

        $email = sih_clean($csv['email'] ?? '');
        if ($email === '') {
            $email = $school ? sih_clean($school['email'] ?? '') : '';
        }
        if ($email === '' && $existing) {
            $email = sih_clean($existing['email'] ?? '');
        }

        $password = '';
        if ($passwordProvided) {
            $password = sih_clean($csv['password'] ?? '');
        }
        if ($password === '' && !$existing) {
            $password = '12345';
        }

        if ($existing) {
            $sql = "UPDATE students SET name=?, firstname=?, middlename=?, lastname=?, email=?, deleted_at=NULL";
            $params = [$fullName, $first, $middle, $last, $email];
            if ($password !== '') {
                $sql .= ", password=?";
                $params[] = $password;
            }
            $sql .= " WHERE id=?";
            $params[] = $existing['id'];
            $db->prepare($sql)->execute($params);
            return ['id' => (int) $existing['id'], 'created' => false];
        }

        $username = sih_clean($csv['username'] ?? '');
        if ($username === '') {
            $username = $matric;
        }
        $db->prepare("INSERT INTO students (name, firstname, middlename, lastname, email, matric_no, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([$fullName, $first, $middle, $last, $email, $matric, $username, $password]);
        return ['id' => (int) $db->lastInsertId(), 'created' => true];
    }
}

if (!function_exists('sih_exeat2_lookup')) {
    /**
     * Batch-fetch school DB name details keyed by matric number.
     * Intentionally ignores academic status so names can still be completed
     * for students excluded from the portal listing.
     */
    function sih_exeat2_lookup($exeat2Db, array $matricNumbers): array
    {
        $result = [];
        if (!$exeat2Db) {
            return $result;
        }

        $matricNumbers = array_values(array_unique(array_filter(array_map('sih_clean', $matricNumbers))));
        foreach (array_chunk($matricNumbers, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT sa.matric_no, s.fname AS firstname, s.mname AS middlename,
                           s.lname AS lastname, s.email
                    FROM students s
                    JOIN student_academics sa ON sa.student_id = s.id
                    WHERE sa.matric_no IN ($placeholders)
                    ORDER BY sa.academic_session_id DESC";
            try {
                $stmt = $exeat2Db->prepare($sql);
                $stmt->execute($chunk);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (!isset($result[$row['matric_no']])) {
                        $result[$row['matric_no']] = $row;
                    }
                }
            } catch (Exception $e) {
                error_log('sih_exeat2_lookup failed: ' . $e->getMessage());
            }
        }
        return $result;
    }
}

if (!function_exists('sih_display_name_parts')) {
    /**
     * Guarantees complete names for exports. Falls back to the stored full
     * name (or splits it) when first/last/middle parts are missing or "NULL".
     */
    function sih_display_name_parts(array $student): array
    {
        $first = sih_clean($student['firstname'] ?? '');
        $middle = sih_clean($student['middlename'] ?? '');
        $last = sih_clean($student['lastname'] ?? '');
        $full = sih_clean($student['name'] ?? '');

        if (($first === '' || $last === '' || $middle === '') && $full !== '') {
            [$f, $m, $l] = sih_split_full_name($full);
            if ($first === '') {
                $first = $f;
            }
            if ($middle === '') {
                $middle = $m;
            }
            if ($last === '') {
                $last = $l;
            }
        }

        if ($first === '' && $last === '' && $full !== '') {
            $first = $full;
        }

        return [$first, $middle, $last];
    }
}
