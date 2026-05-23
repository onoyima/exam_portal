# Integration Plan: exeat2 School Portal as Student Data Source

## 1. Objective

Allow the exam portal to optionally pull student data from the school's existing **exeat2** database when assigning students to exams — in addition to the current upload-from-CSV and manual entry methods. This makes the platform adaptable to any school: each institution plugs in their own student database connection.

---

## 2. Current Architecture

### 2.1 Exam Portal DB (`backup_db_pre_implementation`)

| Table | Columns |
|---|---|
| `students` | `id`, `name`, `firstname`, `middlename`, `lastname`, `email`, `matric_no` (UNIQUE), `username`, `password`, `current_session_id` |
| `exam_students` | `id`, `exam_id` (FK), `student_id` (FK) — pivot linking students to exams |

**Connection:** Single PDO in `db.php` → constants from `config.php` → `.env`

**Current enrollment methods:**
- **Upload CSV** (`admin_upload.php`) — parses firstname/middlename/lastname/email/matric_no/password, inserts into `students`, auto-assigns to selected exams
- **Manual assign** (`admin_assign_students.php`) — checkboxes + bulk actions against `students` table
- **Enroll All** — `INSERT IGNORE ... SELECT id FROM students`
- **Enroll by Domain** — `INSERT IGNORE ... WHERE email LIKE '%@domain'`

### 2.2 exeat2 DB — School Portal (Laravel)

The `Models/` folder contains 186 Eloquent models. The relevant student schema:

```
exeat2.students (4,397 rows)
  id (bigint PK), user_id, user_type, student_role_id,
  email, password (bcrypt hash), username,
  fname, mname, lname, gender, dob, phone, address,
  title_id, country_id, state_id, lga_name, city,
  religion, marital_status, passport, signature,
  status (1=active), created_at, updated_at

exeat2.student_academics (12,324 rows) — one student may have multiple records
  id (PK), student_id (FK → students.id),
  matric_no (UNIQUE VARCHAR 255), old_matric_no,
  course_study_id, level, department_id, faculty_id,
  academic_session_id, program_type,
  status, created_at, updated_at

exeat2.departments — id, name, abb, faculty_id
exeat2.course_studies — id, name, abb, department_id, program_offered_id
exeat2.programmes — id, name
exeat2.levels — id, level (varchar like "100", "200", ...)
```

**Field mapping:**
| Exam Portal | exeat2 Source |
|---|---|
| `name` | CONCAT(`fname`, ' ', IFNULL(`mname`,''), ' ', `lname`) |
| `firstname` | `students.fname` |
| `middlename` | `students.mname` |
| `lastname` | `students.lname` |
| `email` | `students.email` |
| `matric_no` | **`student_academics.matric_no`** (not in students table) |
| `username` | `students.username` |
| `password` | `students.password` (bcrypt — cannot reverse) |

### 2.3 Current `.env` Problem

The `.env` file has duplicate `DB_HOST` keys — the second `DB_HOST=localhost` (for exeat2) silently overwrites the first because `config.php` uses `defined()` + `define()`. Only the **first** database connection works today.

---

### 2.3 Staff/Examiners Management

| Table | Columns |
|---|---|
| `examiners` | `id`, `name`, `username` (UNIQUE), `password`, `role` (`admin`/`examiner`) |
| `exam_examiners` | `id`, `exam_id` (FK), `examiner_id` (FK) — pivot linking examiners to exams |

Staff are created manually via `admin_staff.php` (add name, username, password). Currently no CSV upload or external import for staff.

### 2.4 exeat2 Staff Schema (Laravel Portal)

```
exeat2.staff
  id (PK), fname, lname, mname, email, phone, gender, title,
  passport (longblob), signature (longblob), status,
  password (bcrypt), created_at, updated_at

exeat2.staff_contacts — additional contact info
exeat2.staff_work_profiles — department/position assignments
```

**Field mapping (exeat2.staff → exam_portal.examiners):**
| Exam Portal | exeat2 Source |
|---|---|
| `name` | CONCAT(`fname`, ' ', IFNULL(`mname`,''), ' ', `lname`) |
| `username` | `email` or `lname + first initial` |
| `password` | `staff.password` (bcrypt) |

---

### Approach A — Direct Database Connection (Recommended)

**Concept:** Open a second PDO connection to exeat2 alongside the existing exam portal connection.

**Pros:**
- Zero network latency (same MySQL instance)
- Full SQL flexibility (JOINs, WHERE filters by department/level/program)
- Real-time data — no sync lag
- No extra HTTP infrastructure to maintain

**Cons:**
- Both databases on same MySQL server required (works here: localhost)
- Direct DB credentials in config (already in .env)
- Higher coupling (schema changes in exeat2 could break queries)

### Approach B — REST API (Decoupled)

**Concept:** Add an API layer to the Laravel school portal (`/api/v1/students?department=X`) and call it from the exam portal via `curl`/`file_get_contents`.

**Pros:**
- Complete decoupling — API contract only
- exeat2 schema can change without breaking
- Access control at API layer (tokens, rate limits)
- Works across different servers

**Cons:**
- Must add API routes + controller to the Laravel codebase
- Network latency per request
- Pagination complexity for 4K+ students
- Authentication setup (API tokens)
- Extra failure points (web server, network, framework)

**Decision: Approach A (Direct DB connection)** for this environment (same host, same MySQL). The plan notes exactly where to swap to Approach B if needed.

---

## 4. Implementation Steps

### 4.1 Fix `.env` — Distinct Keys for Second DB

The `loadEnvConfig()` function in `config.php` uses `defined()` + `define()` — duplicate keys silently overwrite. Fix by using distinct constant names:

```env
# Exam Portal DB
DB_HOST=localhost
DB_PORT=3306
DB_NAME=backup_db_pre_implementation
DB_USER=root
DB_PASS=

# School Portal DB (exeat2)
EXEAT2_DB_HOST=localhost
EXEAT2_DB_PORT=3306
EXEAT2_DB_NAME=exeat2
EXEAT2_DB_USER=root
EXEAT2_DB_PASS=
```

### 4.2 Create `db_exeat2.php` — Second Connection

New file alongside `db.php`:

```php
<?php
// db_exeat2.php — Secondary connection to school portal database
require_once __DIR__ . '/config.php';

$exeat2_db = null;
$required = ['EXEAT2_DB_HOST', 'EXEAT2_DB_NAME', 'EXEAT2_DB_USER'];
$all_defined = true;
foreach ($required as $c) {
    if (!defined($c)) { $all_defined = false; break; }
}

if ($all_defined) {
    try {
        $exeat2_db = new PDO(
            "mysql:host=" . EXEAT2_DB_HOST . ";dbname=" . EXEAT2_DB_NAME . ";charset=" . DB_CHARSET,
            EXEAT2_DB_USER,
            defined('EXEAT2_DB_PASS') ? EXEAT2_DB_PASS : ''
        );
        $exeat2_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        error_log("exeat2 connection failed: " . $e->getMessage());
        // Silently fail — feature degrades gracefully
    }
}
```

Graceful degradation: if exeat2 is unreachable or not configured, the "School Portal" source option is hidden/disabled.

### 4.3 Updated `.env` Fallbacks in `config.php`

```php
if (!defined('EXEAT2_DB_HOST')) define('EXEAT2_DB_HOST', 'localhost');
if (!defined('EXEAT2_DB_NAME')) define('EXEAT2_DB_NAME', 'exeat2');
if (!defined('EXEAT2_DB_USER')) define('EXEAT2_DB_USER', 'root');
if (!defined('EXEAT2_DB_PASS')) define('EXEAT2_DB_PASS', '');
```

### 4.4 Source Selector in `admin_assign_students.php`

Add a source toggle at the top of the page, before the student table:

**UI:**
```
Student Source:
[○] Exam Portal Database    [○] School Portal (exeat2)    [○] Upload CSV
```

- **Exam Portal Database** — current behavior (reads from `backup_db_pre_implementation.students`)
- **School Portal (exeat2)** — reads from `exeat2.students` + `exeat2.student_academics` with live JOIN
- **Upload CSV** — unchanged, links to `admin_upload.php`

When "School Portal" is selected, additional filters appear:

```
Department: [Dropdown of exeat2.departments ▼]
Level:      [Dropdown of exeat2.levels ▼]
Programme:  [Dropdown — filtered by selected department ▼]
```

The student table below refreshes to show matched exeat2 students.

### 4.5 Query to Fetch Students from exeat2

```sql
SELECT
  s.id                 AS exeat2_id,
  CONCAT(s.fname, ' ', COALESCE(s.mname,''), ' ', s.lname) AS name,
  s.fname              AS firstname,
  s.mname              AS middlename,
  s.lname              AS lastname,
  s.email,
  sa.matric_no,
  s.username,
  s.password           -- bcrypt hash (copied as-is)
FROM exeat2.students s
JOIN exeat2.student_academics sa ON sa.student_id = s.id
  AND sa.status = 1
  AND sa.academic_session_id = (
    SELECT MAX(sa2.academic_session_id)
    FROM exeat2.student_academics sa2
    WHERE sa2.student_id = s.id
  )
WHERE s.status = 1
  AND sa.matric_no IS NOT NULL AND sa.matric_no != ''
  [AND sa.department_id = ?]     -- optional filter
  [AND sa.level = ?]             -- optional filter
  [AND sa.course_study_id = ?]   -- optional filter
ORDER BY sa.matric_no ASC
```

The subquery on `academic_session_id` ensures the student's latest academic record is used (handles the 12K-academic-records-for-4K-students situation).

### 4.6 Deduplication Strategy — Source-Aware (Recommended)

#### 4.6.1 Schema Change: Add `source` Column

Add a `source` column to the exam portal's `students` table to track where each record originated. This is the foundation of the safe dedup strategy.

```sql
ALTER TABLE students
  ADD COLUMN `source` VARCHAR(20) NOT NULL DEFAULT 'upload'
    COMMENT 'Origin: upload, exeat2, manual',
  ADD COLUMN `external_id` BIGINT(20) UNSIGNED DEFAULT NULL
    COMMENT 'FK to exeat2.students.id if source=exeat2',
  ADD INDEX `idx_source` (`source`),
  ADD INDEX `idx_external_id` (`external_id`);
```

- `source = 'upload'` — student imported via CSV upload (`admin_upload.php`)
- `source = 'exeat2'` — student imported from school portal
- `source = 'manual'` — manually created (future)
- `external_id` — stores `exeat2.students.id` for faster re-identification

#### 4.6.2 Import Logic — Source-Based Decision Tree

When the admin clicks "Assign Selected" or "Assign All Matching":

```
For each selected exeat2 row:
│
├─ 1. Does matric_no exist in exam_portal.students?
│   ├─ NO  → INSERT new student with source='exeat2', external_id=exeat2_id
│   │        Then INSERT IGNORE into exam_students
│   │        [Status: "Imported"]
│   │
│   └─ YES → Check source of existing record:
│       │
│       ├─ source = 'exeat2' (same source)
│       │   → UPDATE name, email, username (safe sync)
│       │   → Then INSERT IGNORE into exam_students
│       │   [Status: "Updated from school portal"]
│       │
│       ├─ source = 'upload' (different source — admin's data)
│       │   → DO NOT UPDATE name/email/password
│       │   → Just INSERT IGNORE into exam_students
│       │   → Log warning: "Skipped update — student managed via upload"
│       │   [Status: "Already exists (upload source)"]
│       │
│       └─ source = 'manual'
│           → DO NOT UPDATE anything (admin's custom entry)
│           → Just INSERT IGNORE into exam_students
│           → Log warning: "Skipped update — manually managed student"
│           [Status: "Already exists (manual source)"]
│
└─ Show summary after import
```

#### 4.6.3 Pseudocode

```php
function importStudentFromExeat2(PDO $db, array $exeat2_row, int $exam_id): array {
    $matric_no = $exeat2_row['matric_no'];
    $existing = $db->prepare("SELECT id, source FROM students WHERE matric_no = ?");
    $existing->execute([$matric_no]);
    $local = $existing->fetch(PDO::FETCH_ASSOC);
    
    if (!$local) {
        // New student — import
        $insert = $db->prepare("
            INSERT INTO students 
                (name, firstname, middlename, lastname, email, 
                 matric_no, username, password, source, external_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'exeat2', ?)
        ");
        $insert->execute([
            $exeat2_row['name'], $exeat2_row['firstname'], $exeat2_row['middlename'],
            $exeat2_row['lastname'], $exeat2_row['email'], $exeat2_row['matric_no'],
            $exeat2_row['username'], $exeat2_row['password'], $exeat2_row['exeat2_id']
        ]);
        $local_id = $db->lastInsertId();
        $status = 'imported';
    } elseif ($local['source'] === 'exeat2') {
        // Same source — safe to sync
        $db->prepare("
            UPDATE students SET 
                name=?, firstname=?, middlename=?, lastname=?,
                email=?, username=?, password=?, external_id=?
            WHERE id=?
        ")->execute([
            $exeat2_row['name'], $exeat2_row['firstname'], $exeat2_row['middlename'],
            $exeat2_row['lastname'], $exeat2_row['email'], $exeat2_row['username'],
            $exeat2_row['password'], $exeat2_row['exeat2_id'], $local['id']
        ]);
        $local_id = $local['id'];
        $status = 'updated';
    } else {
        // Different source — link only, do NOT overwrite
        $local_id = $local['id'];
        $status = 'skipped_' . $local['source'];
    }
    
    // Link to exam (safe)
    $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) VALUES (?, ?)")
       ->execute([$exam_id, $local_id]);
    
    return ['student_id' => $local_id, 'status' => $status];
}
```

**Post-import summary shown to admin:**

```
┌─────────────────────────────────────────────────────┐
│ Import Complete                                      │
│                                                     │
│  ✅  15  New students imported from School Portal    │
│  🔄   3  Existing exeat2-sourced records updated     │
│  ⚠️   2  Upload-sourced students linked (not mod.)   │
│  ⚠️   0  Manual students linked (not modified)        │
│                                                     │
│  Total: 20 students assigned to this exam            │
└─────────────────────────────────────────────────────┘
```

#### 4.6.4 Password Handling & Toggle Setting

**Current behavior:** `student_login.php` does NOT check passwords — only a matric_no lookup + identity confirmation.

**New setting:** `require_student_password` in `system_settings` (admin toggle).

```
Admin Portal → Settings → Security:
[☑] Require password for student login
```

- **When ON (`'true'`):** `student_login.php` shows a password input field. The login flow validates `password_verify($input_password, $stored_hash)` before allowing access.
- **When OFF (`'false'`):** Current behavior — no password check, matric_no only.

**⚠️ Critical prerequisite — hash existing passwords:**
Before the toggle can be turned ON, all existing plain-text passwords must be hashed. Currently default passwords (`12345`) are stored as plain text. `password_verify('12345', '12345')` returns `false`.

```sql
-- Migration: hash all plain-text student passwords
UPDATE students SET password = '$2y$10$...bcrypt_of_12345...'
WHERE password = '12345' OR password IS NULL OR password = '';
```

The login code must also include a legacy fallback:
```php
if (!password_verify($input, $stored_hash)) {
    // Fallback: was it a plain-text match? (pre-migration legacy)
    if ($input === $stored_hash) {
        // Upgrade to hash on next login
        $new_hash = password_hash($input, PASSWORD_DEFAULT);
        $db->prepare("UPDATE students SET password=? WHERE id=?")
           ->execute([$new_hash, $student['id']]);
    } else {
        // Real mismatch
        echo json_encode(['success' => false, 'error' => 'Invalid credentials.']);
        exit;
    }
}
```

**Import behavior with the toggle:**
- Even when the toggle is OFF, passwords are still imported and stored for data completeness
- When importing from exeat2, password is overwritten **only if the student's source is `'exeat2'`** (same-source sync). Upload-sourced students keep their existing password — they were not created via the school portal, so their password should not be silently changed.
- Rationale: the exeat2 school portal is authoritative only for students who came from it. Upload-sourced students may have a different password scheme.

**Login flow when toggle is ON:**

```
1. Student enters matric_no + password
2. Lookup student by matric_no
3. Verify password_verify(input, stored_hash) with legacy plain-text fallback
4. If fails → "Invalid credentials"
5. If passes → identity confirmation modal (same as today)
6. Confirm → session created
```

**Seed the default setting:**
```php
// In admin_portal_settings.php default_cms_settings
'require_student_password' => 'false',  // off by default
```

### 4.7 UI State Diagram

```
admin_assign_students.php?exam_id=X
│
├─ Source: [○ Exam Portal DB]  [● School Portal]  [○ Upload CSV → admin_upload.php]
│
│  When School Portal selected:
│  ┌──────────────────────────────────────────┐
│  │ Filters:                                 │
│  │   Department: [Biological Sciences  ▼]   │
│  │   Level:      [100 Level            ▼]   │
│  │   Programme:  [All                   ▼]  │
│  │                                          │
│  │  [↻ Refresh from School Portal]          │
│  └──────────────────────────────────────────┘
│
│  Student Table (fetched from exeat2 via JOIN):
│  ┌─────┬───────────┬────────────┬──────────────────┬────────┐
│  │ ☐   │ Matric    │ Name       │ Email            │ Dept   │
│  ├─────┼───────────┼────────────┼──────────────────┼────────┤
│  │ ☐   │ VUG/...   │ John Doe   │ j.doe@...        │ Bio    │
│  └─────┴───────────┴────────────┴──────────────────┴────────┘
│
│  [Assign Selected]  [Assign All Matching Filters]
│    → Imports to exam_portal.students (if new)
│    → Inserts into exam_students
│    → Shows success count
```

### 4.8 Student Login Flow — No Changes Needed

`student_login.php` queries `backup_db_pre_implementation.students` by `matric_no`. Once a student is imported from exeat2 via the assignment page, they can log in normally — no code changes required.

The `password` field is copied from exeat2 (bcrypt hash). If a student doesn't have a password in exeat2 or the portal needs a fallback, the import can set `password = '$2y$10$...'` (a known default hash) or the original default `12345`.

**Recommended:** On first import from exeat2, set `password` to the school portal's bcrypt hash. Students use their existing school portal password to log into the exam portal — no new password to remember.

### 4.9 Staff/Examiner Import from exeat2

The same three-source pattern applies to staff: **Exam Portal DB**, **School Portal (exeat2)**, **CSV Upload**.

#### 4.9.1 Schema Changes to `examiners` Table

```sql
ALTER TABLE examiners
  ADD COLUMN `source` VARCHAR(20) NOT NULL DEFAULT 'manual'
    COMMENT 'Origin: manual, exeat2, upload',
  ADD COLUMN `external_id` BIGINT(20) UNSIGNED DEFAULT NULL
    COMMENT 'FK to exeat2.staff.id if source=exeat2',
  ADD COLUMN `email` VARCHAR(255) DEFAULT NULL;
```

#### 4.9.2 Staff Import UI

A new page `admin_staff_import.php` (or integrated into `admin_staff.php`) with:

```
Source:
[○] Exam Portal Database    [●] School Portal (exeat2)    [○] Upload CSV

When "School Portal" selected:
  Department: [Dropdown of exeat2.departments ▼] (optional filter)

┌─────┬──────────┬──────────────┬──────────────────┬──────────────┐
│ ☐   │ Name     │ Email        │ Department       │ Status       │
├─────┼──────────┼──────────────┼──────────────────┼──────────────┤
│ ☐   │ Dr. Smith│ smith@...    │ Biological Scis  │ 🟢 New       │
│ ☐   │ Prof. Lee│ lee@...      │ Computer Science │ 🔵 Exists    │
└─────┴──────────┴──────────────┴──────────────────┴──────────────┘

[Import Selected as Examiners]  [Import All Matching]
```

#### 4.9.3 Query to Fetch Staff from exeat2

```sql
SELECT
  st.id            AS exeat2_staff_id,
  CONCAT(st.fname, ' ', COALESCE(st.mname,''), ' ', st.lname) AS name,
  st.email         AS email,
  COALESCE(st.email, CONCAT(LOWER(st.lname), '.', LOWER(SUBSTRING(st.fname,1,1)), '@veritas.edu.ng')) AS username,
  st.password      AS password,  -- bcrypt
  st.passport      AS passport   -- longblob image
FROM exeat2.staff st
WHERE st.status = 1
  [AND st.fname LIKE ?]        -- optional name search
ORDER BY st.lname ASC
```

#### 4.9.4 Dedup Strategy (Same Source-Aware Pattern)

```php
// Same decision tree as students:
// 1. Check exam_portal.examiners WHERE username = ? (or email)
// 2. If not found → INSERT with source='exeat2', external_id
// 3. If found AND source='exeat2' → UPDATE details (same source sync)
// 4. If found AND source != 'exeat2' → skip update, just link
```

Staff are linked to exams via `exam_examiners` (pivot), analogous to `exam_students`:

```php
$db->prepare("INSERT IGNORE INTO exam_examiners (exam_id, examiner_id) VALUES (?, ?)")
   ->execute([$exam_id, $local_examiner_id]);
```

#### 4.9.5 CSV Upload for Staff (Future)

Same pattern as `admin_upload.php` for students — parse CSV with columns: `Firstname, Middlename, Lastname, Email, Username` and insert into `examiners` with `source = 'upload'`.

---

### 4.10 Student/Staff Passport Image from exeat2

Both `exeat2.students` and `exeat2.staff` have a `passport` column stored as `longblob` (binary image data, typically JPEG/PNG).

#### 4.10.1 Strategy Options

| Option | Pros | Cons |
|---|---|---|
| **A — Store as file on import** (Recommended) | ✅ `SELECT *` stays lean, no schema change to main tables, simple URL serving | Need to manage file storage, ensure directory exists |
| **B — Proxy script serving from exeat2 blob** | Always fresh, zero disk sync | Live exeat2 connection required per request, slow |
| **C — Store blob in exam portal's `students`/`examiners` table** | Single DB, no filesystem | ❌ `SELECT *` on students/examiners drags binary data — slows every list query. Avoid. |
| **D — Separate `student_passports` table** | Keeps main table clean, queried only when needed | Extra JOIN or second query per page |

**Recommended: Option A — write passport to disk on import.**

```
uploads/passports/students/{id}.jpg
uploads/passports/examiners/{id}.jpg
```

During import from exeat2:
```php
$passport_data = $row['passport'];  // binary from exeat2 blob
if ($passport_data) {
    $dir = __DIR__ . '/uploads/passports/students';
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    file_put_contents("$dir/{$new_student_id}.jpg", $passport_data);
}
```

The `passport_image.php` proxy reads from file instead of DB:

```php
$path = __DIR__ . "/uploads/passports/$type/$id.jpg";
if (file_exists($path)) {
    header('Content-Type: ' . mime_content_type($path));
    header('Cache-Control: public, max-age=86400');
    readfile($path);
} else {
    header('Location: assets/img/default_avatar.png');
}
```

**Why Option A beats C:**
- Pages that do `SELECT * FROM students` (like `admin_assign_students.php:66`, `assess.php:40`, `student_login.php:46`) won't transfer 500 KB of blob per row
- `admin_live_proctor.php` loading 100+ active attempts would become noticeably slower with inline blobs
- File system serving is faster than DB→PHP→HTTP for binary data
- Easy to seed default avatars for students without passports

#### 4.10.2 Schema Changes

```sql
-- No blob columns on main students table.
-- Passports stored as files in uploads/passports/students/ and uploads/passports/examiners/
```

#### 4.10.3 Import Passport During Student/Staff Import

The INSERT query does NOT include passport in the SQL — it's written to disk separately:

```php
// 1. Insert student (no passport column in SQL)
$insert = $db->prepare("
    INSERT INTO students 
        (name, firstname, middlename, lastname, email, 
         matric_no, username, password, source, external_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'exeat2', ?)
");
$insert->execute([
    $row['name'], $row['firstname'], $row['middlename'],
    $row['lastname'], $row['email'], $row['matric_no'],
    $row['username'], $row['password'],
    $row['exeat2_id']
]);
$new_id = $db->lastInsertId();

// 2. Write passport to disk (if present)
if (!empty($row['passport'])) {
    $dir = __DIR__ . '/uploads/passports/students';
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    file_put_contents("$dir/{$new_id}.jpg", $row['passport']);
}
```

The `$row['passport']` is fetched from the exeat2 PDO query — returned as binary string.

#### 4.10.4 Serving the Passport Image

Passport is **never compulsory** — if no photo exists, the proxy generates an initials-based SVG avatar on the fly using the student's name from the database.

```php
<?php
// passport_image.php?type=student&id=123
require 'db.php';
$type = $_GET['type'] ?? '';
$id   = intval($_GET['id']  ?? 0);
$allowed = ['student', 'examiner'];

if (!in_array($type, $allowed) || !$id) {
    serve_initial_avatar('?');
    exit;
}

// 1. Try file-based passport
$path = __DIR__ . "/uploads/passports/$type/$id.jpg";
if (file_exists($path)) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    readfile($path);
    exit;
}

// 2. Fallback: generate initials avatar from name
$name = '';
if ($type === 'student') {
    $stmt = $db->prepare("SELECT name FROM students WHERE id = ?");
} else {
    $stmt = $db->prepare("SELECT name FROM examiners WHERE id = ?");
}
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    serve_initial_avatar($row['name']);
} else {
    serve_initial_avatar('?');
}

function serve_initial_avatar(string $name): void {
    // Extract initials (up to 2 characters)
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        if (!empty($p)) { $initials .= strtoupper(mb_substr($p, 0, 1)); }
    }
    if ($initials === '') { $initials = '?'; }

    // Generate a deterministic background color from the name
    $hash = crc32($name);
    $hue = $hash % 360;
    $bg = "hsl($hue, 55%, 45%)";

    // Return inline SVG
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=86400');
    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
  <rect width="200" height="200" rx="100" fill="$bg"/>
  <text x="100" y="100" text-anchor="middle" dominant-baseline="central"
        font-family="Arial, sans-serif" font-size="80" font-weight="bold"
        fill="white">$initials</text>
</svg>
SVG;
}
```

**Key behavior:**
- Never shows a broken image or generic placeholder — always shows either the actual passport or a colored circle with initials
- Background color is deterministic (derived from name hash) — same person always gets the same color
- No extra avatar image file to maintain
- Works offline, no external dependencies

Usage in views:
```html
<img src="passport_image.php?type=student&id=<?= $student['id'] ?>" 
     class="rounded-circle" width="48" height="48" alt="Passport">
```

#### 4.10.5 Where to Display the Passport

| Page | Student Passport | Staff/Examiner Passport |
|---|---|---|
| `assess.php` (practical grading) | ✅ Show in student info header alongside name + matric | — |
| `student_objective_exam.php` | ✅ Show in exam header or sidebar | — |
| `admin_live_proctor.php` | ✅ Show in attempt card/panel | — |
| `admin_viva_exams.php` (if exists) | ✅ Show in student listing | — |
| `admin_staff.php` | — | ✅ Show in staff list table |
| `admin_students.php` | ✅ Show in student list table | — |
| `student_dashboard.php` | ✅ Show as avatar in header | — |

**Example on `assess.php`** — replace student info card:

```html
<div class="d-flex align-items-center gap-3">
  <img src="passport_image.php?type=student&id=<?= $stu['id'] ?>"
       class="rounded-circle border border-2 border-success"
       width="64" height="64" style="object-fit: cover; object-position: center top;">
  <div>
    <h5 class="mb-0"><?= htmlspecialchars($stu['name']) ?></h5>
    <small class="text-muted"><?= htmlspecialchars($stu['matric_no']) ?></small>
  </div>
</div>
```

No `onerror` needed — the proxy always returns a valid image (passport or generated initials SVG).

---

### 5.1 What Happens When an Already-Existing Student Is Assigned from exeat2?

When an admin selects students from exeat2 and clicks "Assign", the behavior depends on the student's presence and source in the exam portal DB:

| Scenario | Local Record | Detection | What Happens |
|---|---|---|---|
| **A** — Student doesn't exist locally | None | Matric lookup returns 0 rows | INSERT new student, source='exeat2', link to exam |
| **B** — Student exists, source='exeat2' | Previously imported from school portal | Matric + source match | UPDATE name/email, link to exam (same source sync) |
| **C** — Student exists, source='upload' | Originally uploaded via CSV | Matric found, source differs | **Do NOT modify local record.** Just INSERT IGNORE into exam_students |
| **D** — Student exists, already assigned to this exam | Already in exam_students | INSERT IGNORE silently skips | No change — safe no-op |

**Key: the system always detects presence** by querying `students WHERE matric_no = ?`. The `matric_no` field is UNIQUE on both sides — it is the deduplication anchor.

### 5.2 Risks of the Naive Approach (Overwrite-Everything)

If the original plan (blind UPDATE on match) were followed without the `source` column:

| Risk | Impact | Scenario |
|---|---|---|---|
| **Data overwrite** | Admin's cleaned/corrected data is replaced by exeat2's version | Admin renamed "John H. Doe" → "John Henry Doe" in exam portal; exeat2 import overwrites back to "John H. Doe" |
| **Unintended re-assignment** | Student removed from exam for disciplinary reasons gets re-added | Admin deletes row from `exam_students`; "Assign All from exeat2" re-inserts it |
| **Source ambiguity** | Admin can't tell where a student came from | No `source` column means no audit trail of origin |

### 5.3 Why the Source Column Mitigates These Risks

| Source Status | UPDATE Rules | Re-assignment Safety |
|---|---|---|
| `source = 'exeat2'` | ✅ Update name/email | ✅ INSERT IGNORE prevents double-add. Manual removal is NOT overridden by Assign Selected (only by Assign All). |
| `source = 'upload'` | ❌ Never update | ✅ INSERT IGNORE safe. Admin's manual removals stay respected. |
| `source = 'manual'` | ❌ Never update | ✅ INSERT IGNORE safe. |

**Note:** Password is excluded from the table above because student login does not use passwords (matric_no lookup only). The `password` column is copied for data completeness but has no effect on authentication.

**Key safety principle:** A student that was uploaded or manually created is presumed to have admin-curated data. The system respects that by never overwriting students from a different source.

### 5.4 The "Assign All Matching" Danger

The "Assign All Matching Filters" button is the riskiest operation — it imports **every** student matching the department/level filter without checkbox confirmation.

**Safeguards:**
1. **Show preview count** before executing: *"This will import and assign 342 students from Biological Sciences. Proceed?"*
2. **Never auto-delete** from `exam_students` — only INSERT IGNORE
3. **Log every auto-import** with timestamp, admin ID, filter used, count
4. **Dry-run mode** — first click shows what *would* happen (preview only), second click executes

### 5.5 Best-Practice Approaches (Ranked)

**Rank 1 — Source-Aware Import with Preview (Recommended)**
- Add `source` + `external_id` columns to `students`
- Show inline status per student in the table: "New", "Existing (exeat2)", "Existing (Upload)", "Already Assigned"
- Admin sees exactly what will happen before clicking
- Summary dialog with counts per status category
- Never modify non-exeat2-sourced students

**Rank 2 — Live Lookup Without Local Import**
- Do not copy students into `backup_db_pre_implementation.students` at all
- Instead, create a `student_links` table: `id, exam_id, external_source, external_student_id`
- `student_login.php` checks both `students` and `student_links` + exeat2 live
- **Problem:** Authentication flow becomes complex — two password sources
- **Use case:** Only if exeat2 is the authoritative auth source (LDAP/SSO)

**Rank 3 — Manual Sync + Flag**
- Sync students from exeat2 into exam portal in a **separate admin page** (not during assignment)
- Show a diff: "New: 15, Changed: 3, Removed: 2"
- Admin reviews changes and confirms sync
- Assignment page only sees the local `students` table
- **Problem:** Two-step workflow is cumbersome

**Rank 4 — One-Way Sync on Schedule**
- Run a nightly cron job that syncs exeat2 → exam portal
- Same `source`-based rules as Rank 1, but automated
- Admin simply assigns from the local table any time
- **Best for:** Production with stable exeat2 data

### 5.6 Detection UX — What the Admin Sees

The student table when fetching from exeat2 should have a live "Status" column so the admin knows the outcome before clicking Assign:

```
┌─────┬──────────────┬──────────────┬──────────────────┬──────────┬─────────────────────────┐
│ ☐   │ Matric       │ Name         │ Email            │ Dept     │ Status                  │
├─────┼──────────────┼──────────────┼──────────────────┼──────────┼─────────────────────────┤
│ ☐   │ VUG/.../001  │ John Doe     │ j.doe@...        │ Bio Sci  │ 🟢 New — will import    │
│ ☐   │ VUG/.../002  │ Jane Smith   │ j.smith@...      │ Bio Sci  │ 🔵 Exists (exeat2)      │
│ ☐   │ VUG/.../003  │ Bob King     │ b.king@...       │ Bio Sci  │ 🟡 Exists (upload src)  │
│ ☐   │ VUG/.../004  │ Amy Lee      │ a.lee@...        │ Bio Sci  │ ✅ Already assigned     │
└─────┴──────────────┴──────────────┴──────────────────┴──────────┴─────────────────────────┘
```

Each status is determined by a single preparatory query before rendering the table:

```sql
SELECT matric_no, id, source 
FROM backup_db_pre_implementation.students 
WHERE matric_no IN (?, ?, ?, ...)  -- batch of exeat2 matric numbers
```

Results mapped into an associative array for O(1) lookup per row.

---

## 6. Performance & Risk Assessment

### 6.1 Will the Exam Run Slower?

| Feature | Impact on Exam Pages | Mitigation |
|---|---|---|
| **exeat2 DB connection** | ⬜ None — connection only opens during admin import | Student pages never touch exeat2 |
| **Passport image display** | 🟢 Minimal — single separate HTTP request, cached 24h by browser | `passport_image.php` with `Cache-Control: public, max-age=86400` |
| **Passport on proctor page** | 🟡 100 active attempts = 100 image tags — all cached after first load | Only uncached requests hit PHP; subsequent loads from browser cache |
| **Source column** | ⬜ None — tiny VARCHAR, indexed | Indexed for fast lookups |
| **`SELECT *` with blob** | ❌ Would be slow — but plan uses file-based storage, not blob (see §4.10.1) | File path avoids binary data transfer in list queries |
| **Password verification** | 🟢 Negligible — `password_verify()` is microseconds | — |

**Conclusion:** No measurable impact on exam runtime. All integration features activate only during admin import/settings actions.

### 6.2 What Could Break

| Risk | Likelihood | Impact | Root Cause | Fix |
|---|---|---|---|---|
| **Existing students locked out if toggle turned ON** | Medium | High — students can't log in | `password_verify('12345', '12345')` fails on plain-text | Hash all existing passwords during migration; add legacy fallback in login code (§4.6.4) |
| **Staff lockout after exeat2 import** | Medium | High — examiners locked out | Staff login checks passwords; import overwrites with unknown bcrypt | Source-aware dedup prevents overwriting upload-sourced; show clear warning before import |
| **Passport file permission errors** | Low | Low — passport silently missing | `file_put_contents()` fails if `uploads/passports/` not writable | Check directory exists + writable; `onerror` fallback on `<img>` shows default avatar |
| **Large passport images hitting PHP memory limit** | Low | Medium — import fails for that student | 10 MB blob exceeds `memory_limit` or `post_max_size` | Skip oversized blobs (>2 MB); log warning |
| **`passport_image.php` path traversal** | Low | Critical — arbitrary file read | Unsanitized type/id parameters | Whitelist `$allowed = ['student','examiner']` and cast `$id = intval($id)` (already in §4.10.4) |
| **Duplicate constant definition in `.env`** | High (current state) | Medium — second DB config ignored | `defined()` skips dup keys | Fix: rename to `EXEAT2_DB_*` (in Phase 1) |

### 6.3 Design Decisions That Avoid Breakage

- **File-based passport storage** (not blob in students table) — keeps `SELECT *` queries lean across all pages
- **Explicit column SELECTs** recommended for passport queries — `passport_image.php` does targeted lookup, not `SELECT *`
- **`INSERT IGNORE`** on `exam_students` — safe against duplicate assignments
- **Source-aware dedup** — upload-sourced students never get their data silently overwritten
- **Password fallback** — plain-text passwords still work even after password toggle is activated (legacy check)
- **Staff import warning** — admin sees count of existing staff before importing

---

| File | Action | Description |
|---|---|---|
| `database/migration_v2.sql` | **Create** | Schema migration: `students.source`, `students.external_id`, `examiners.source`, `examiners.external_id`, `examiners.email` |
| `.env` | **Modify** | Rename second DB config keys to `EXEAT2_DB_*` |
| `config.php` | **Modify** | Add fallback `define()` for `EXEAT2_DB_*` constants |
| `db_exeat2.php` | **Create** | Second PDO connection (`$exeat2_db`), null if config missing |
| `includes/fetch_exeat2_students.php` | **Create** | AJAX handler — filtered student list from exeat2 + inline status |
| `includes/import_exeat2_student.php` | **Create** | Import student: dedup + source tracking + passport blob copy |
| `includes/fetch_exeat2_staff.php` | **Create** | AJAX handler — filtered staff list from exeat2 + inline status |
| `includes/import_exeat2_staff.php` | **Create** | Import staff: dedup + source tracking + passport blob copy |
| `admin_assign_students.php` | **Modify** | Add source selector, filters, exeat2 fetch, inline status column, import logic |
| `admin_staff.php` | **Modify** | Add exeat2 import tab + CSV upload alongside manual add |
| `admin_portal_settings.php` | **Modify** | Add `require_student_password` toggle to Security section |
| `student_login.php` | **Modify** | Add password input + `password_verify()` check when toggle=ON |
| `passport_image.php` | **Create** | Proxy script serving passport blob as image (`?type=student\|examiner&id=N`) |
| `assess.php` | **Modify** | Show student passport in info card (practical grading) |
| `student_objective_exam.php` | **Modify** | Show passport in exam header |
| `admin_live_proctor.php` | **Modify** | Show passport in attempt cards + proctor dashboard |
| `student_dashboard.php` | **Modify** | Show passport as user avatar |
| `admin_students.php` | **Modify** | Show passport in student list rows |
| `style_enhanced.css` | **Modify** | Styles for source selector tabs, passport image in cards, password toggle UI |
| `debug_exeat2.php` | **Create** | (Optional) Diagnostic page to test exeat2 connection |

---

## 6. Edge Cases & Considerations

### 6.1 Student Has No `matric_no` in `student_academics`
- `sa.matric_no` has `IS NOT NULL` constraint in the query — students without matric numbers are excluded
- Admin sees a count of skipped students with a warning

### 6.2 Student Has Multiple Academic Records
- The subquery `MAX(academic_session_id)` picks the latest session
- Could also filter by `status = 1` (active/accepted) within student_academics
- Fallback: if no rows match the filter, student is skipped with a warning

### 6.3 Student Login Does Not Use Passwords

- `student_login.php` performs a simple matric_no lookup + identity confirmation — **no password check** at any step
- The `password` column in `backup_db_pre_implementation.students` is populated (default `12345`) but never verified at login
- Therefore password handling during exeat2 import is **irrelevant to authentication**:
  - The exeat2 password (bcrypt) can be copied for data completeness
  - Or a placeholder can be set — students won't notice either way
- If password-based auth is added in the future, the imported bcrypt hash is forward-compatible with `password_verify()`

### 6.4 exeat2 Connection Failure
- `db_exeat2.php` catches exceptions silently — `$exeat2_db` stays `null`
- `admin_assign_students.php` checks `if (!$exeat2_db)` and shows a warning: *"School Portal database not reachable. Check EXEAT2_DB_* config or try again later."*
- The "School Portal" source tab shows a disabled state with the error

### 6.5 Very Large Result Sets (4K+ students)
- Initial fetch with no filters returns all 4K+ students — that's fine for the server, but the browser table might lag
- **Solution:** Require at least one filter (department or level) before loading. Show a prompt: *"Please select at least one filter to narrow results."*
- Implement server-side pagination (LIMIT 50 / OFFSET) in the AJAX handler

### 6.6 Department/Programme Mapping Complexity
- `student_academics.department_id` → `departments.id` ✓ (direct)
- `student_academics.course_study_id` → `course_studies.id` → `program_offered_id` → `programmes.id` (two hops)
- The plan should expose a **flat** dropdown of course studies (programmes) from the student's department, not all programmes in the system
- SQL: `SELECT cs.id, cs.name FROM course_studies cs WHERE cs.department_id = ? ORDER BY cs.name`

### 6.7 Switching to API Mode (Approach B)
- Replace `db_exeat2.php` with an HTTP client class
- Replace SQL queries with `curl` calls to `https://school-portal/api/v1/students?...`
- The import/dedup logic stays identical — only the data-fetch layer changes
- Create an interface/abstract class so both modes share the same contract

---

## 7. Recommended Implementation Order

### Phase 1 — Foundation (DB config + connections)
1. **Fix `.env`** and `config.php` — rename second DB keys, add fallback defines
2. **Create `db_exeat2.php`** — test connection with `debug_exeat2.php`

### Phase 2 — Schema Migration
3. **Run `database/migration_v2.sql`** — add columns: `students.source`, `students.external_id`, `examiners.source`, `examiners.external_id`, `examiners.email`
4. **Backfill existing students** — `UPDATE students SET source = 'upload'` for all current records
5. **Backfill existing examiners** — `UPDATE examiners SET source = 'manual'` for all current records

### Phase 3 — Student Import from exeat2
6. **Create `passport_image.php`** — proxy script to serve blob images
7. **Create `includes/fetch_exeat2_students.php`** — AJAX handler: filtered student list + inline status detection
8. **Create `includes/import_exeat2_student.php`** — source-aware import with passport copy
9. **Modify `admin_assign_students.php`** — source tabs, filters, inline status column, import logic, summary dialog

### Phase 4 — Staff Import from exeat2
10. **Create `includes/fetch_exeat2_staff.php`** — AJAX handler: filtered staff list + inline status
11. **Create `includes/import_exeat2_staff.php`** — source-aware import with passport copy
12. **Modify `admin_staff.php`** — add exeat2 import tab + CSV upload section

### Phase 5 — Password Toggle
13. **Modify `admin_portal_settings.php`** — add `require_student_password` toggle in Security section
14. **Modify `student_login.php`** — when toggle=ON, show password input + `password_verify()` check

### Phase 6 — Passport Display
15. **Modify `assess.php`** — show passport in student info card
16. **Modify `student_objective_exam.php`** — show passport in exam header
17. **Modify `admin_live_proctor.php`** — show passport in attempt cards
18. **Modify `student_dashboard.php`** — show passport as user avatar
19. **Modify `admin_students.php`** — show passport in student list

### Phase 7 — Testing & Polish
20. **Test student import scenarios** (§5.1 A/B/C/D) — verify no data loss on upload-sourced students
21. **Test staff import** — import from exeat2, link to exam, verify login
22. **Test password toggle** — ON and OFF both work correctly
23. **Test passport display** — all 5 pages show image with `onerror` fallback
24. **Document** — update `.env.example`, add inline comments

---

## 8. Open Questions

- **Password override rule:** Should exeat2 password ALWAYS override the local one (as specified — §4.6.4), or only when source='exeat2'? Current plan: always override, because the toggle makes the school portal the authoritative password source.
- **Staff username generation:** exeat2.staff has no `username` column — use `email` as username? Or generate from name (`lname.firstinitial`)? The plan uses email if available, falls back to generated.
- **Passport storage:** MEDIUMBLOB vs LONGBLOB vs file path? LONGBLOB is simplest (no filesystem management), but may impact DB backup size. Maximum passport image: ~500 KB per student × 4K students ≈ 2 GB if all have photos. MEDIUMBLOB (16 MB max) is sufficient.
- **Passport MIME detection:** The `passport_image.php` proxy assumes `image/jpeg` — should detect from magic bytes (first 4 bytes) for JPEG vs PNG.
- **Sync frequency:** Import on-demand (when admin assigns) vs nightly batch sync vs live lookup? See §5.5 — Rank 4 (scheduled sync) is best for production but Rank 1 (on-demand with preview) is best for initial implementation.
- **What if exeat2 updates a student's matric_no?** The `external_id` column (`exeat2.students.id`) handles this — the matric_no lookup might fail but the external_id lookup succeeds. The import should fall back to external_id if matric_no is not found.
- **Should `Assign All Matching Filters` also remove students no longer in exeat2?** No — that would delete manually uploaded students who happen to match the filter. Only ever ADD, never DELETE.
- **Deleted/graduated students:** Should `s.status = 1` filter be strict? Yes for filtering; the admin can toggle "Include inactive students" if needed.
- **Multiple academic sessions:** Picking `MAX(academic_session_id)` is simple but might pick a "graduated" session over a "current" one — should filter by a specific academic session instead?
- **Audit trail:** Should each import/sync action be logged in the `audit_trail` table? Strongly recommended for security compliance.
- **What happens to exam_students records if a student is deleted from exeat2?** The local `students` record and `exam_students` links remain intact. exeat2 is a data source, not an authoritative reference for existing exam data.
