<?php
/**
 * Demo Course Seeder — Populates one complete course with materials
 * in both exeat2 (school DB) and LMS DB.
 *
 * Usage: php lms/database/migrate.php --seed
 */

declare(strict_types=1);

if (!defined('LMS_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../../bootstrap.php';
}

use Lms\Core\Database;
use Lms\Services\Exeat2;

$lms  = Database::connection('lms');
Exeat2::init();
$exeat = Exeat2::conn();

echo "\n=== LMS Demo Course Seeder ===\n\n";

if (!function_exists('demo_uuid')) {
function demo_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
}

// ══════════════════════════════════════════════
// STEP 1: Academic Structure — use existing records
// ══════════════════════════════════════════════
echo "[1/8] Using existing academic structure...\n";

$facultyId    = 2;   // Natural and Applied Sciences
$deptId       = 17;  // Computer and Information Technology
$progId       = 0;   // Undergraduate
$progCertId   = 2;   // Bacholor Degree
$poId         = 5;   // Bachelor of Science
$csId         = 22;  // Computer Science
$courseCatId  = 1;   // Core
$level200Id   = 2;   // 200
$level100Id   = 1;   // 100
$sem1Id       = 1;   // First
$sem2Id       = 2;   // Second
$sessionId    = 19;  // 2025/2026

echo "   Faculty: Natural and Applied Sciences (ID: {$facultyId})\n";
echo "   Department: Computer and Information Technology (ID: {$deptId})\n";
echo "   Course Study: Computer Science (ID: {$csId})\n";

// ══════════════════════════════════════════════
// STEP 2: Sessions — use existing
// ══════════════════════════════════════════════
echo "\n[2/8] Using existing academic sessions...\n";

$activeSessionId = 19;
$pastSessionId   = 17;

$stmt = $exeat->prepare("SELECT id FROM academic_sessions WHERE vu_session_id = ? LIMIT 1");
$stmt->execute([$activeSessionId]);
$activeAcadSessionId = (int) $stmt->fetchColumn();

$stmt = $exeat->prepare("SELECT id FROM academic_sessions WHERE vu_session_id = ? LIMIT 1");
$stmt->execute([$pastSessionId]);
$pastAcadSessionId = (int) $stmt->fetchColumn();

$stmt = $exeat->prepare("SELECT id FROM vu_semesters WHERE academic_session_id = ? AND semester_id = ? LIMIT 1");
$stmt->execute([$activeAcadSessionId, $sem1Id]);
$activeVuSemId = (int) $stmt->fetchColumn();

$stmt = $exeat->prepare("SELECT id FROM vu_semesters WHERE academic_session_id = ? AND semester_id = ? LIMIT 1");
$stmt->execute([$pastAcadSessionId, $sem2Id]);
$pastVuSemId = (int) $stmt->fetchColumn();

echo "   Active: 2025/2026 (vu_semester: {$activeVuSemId})\n";

// ══════════════════════════════════════════════
// STEP 3: Courses — use existing CSC 201
// ══════════════════════════════════════════════
echo "\n[3/8] Using existing courses...\n";

$currentCourseId = 1220;
echo "   Current: CSC 201 (ID: {$currentCourseId})\n";

// ══════════════════════════════════════════════
// STEP 4: Demo Users (exeat2)
// ══════════════════════════════════════════════
echo "\n[4/8] Finding / creating demo users...\n";

$passwordHash = password_hash('password123', PASSWORD_DEFAULT);

$stmt = $exeat->prepare("SELECT id FROM students WHERE email = 'john@demo.edu' LIMIT 1");
$stmt->execute();
$studentId = (int) $stmt->fetchColumn();
if (!$studentId) {
    $stmt = $exeat->query("SELECT COALESCE(MAX(id), 0) + 1 FROM students");
    $studentId = (int) $stmt->fetchColumn();
    $exeat->prepare("INSERT INTO students (id, fname, mname, lname, email, phone, status, username, password, user_id, student_role_id) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)")
        ->execute([$studentId, 'John', 'Michael', 'Doe', 'john@demo.edu', '08012345678', 'john', $passwordHash, 'john', 1]);
    echo "   Created student: John Doe (ID: {$studentId})\n";
} else {
    echo "   Student exists: John Doe (ID: {$studentId})\n";
}

$stmt = $exeat->prepare("SELECT id FROM staff WHERE email = 'jane@demo.edu' LIMIT 1");
$stmt->execute();
$staffId = (int) $stmt->fetchColumn();
if (!$staffId) {
    $stmt = $exeat->query("SELECT COALESCE(MAX(id), 0) + 1 FROM staff");
    $staffId = (int) $stmt->fetchColumn();
    $exeat->prepare("INSERT INTO staff (id, fname, mname, lname, email, phone, status, password) VALUES (?, ?, ?, ?, ?, ?, 1, ?)")
        ->execute([$staffId, 'Jane', '', 'Smith', 'jane@demo.edu', '08098765432', $passwordHash]);
    echo "   Created staff: Jane Smith (ID: {$staffId})\n";
} else {
    echo "   Staff exists: Jane Smith (ID: {$staffId})\n";
}

if ($studentId && $activeVuSemId && $activeAcadSessionId) {
    $stmt = $exeat->prepare("SELECT id FROM student_academics WHERE student_id = ? AND vu_semester_id = ? LIMIT 1");
    $stmt->execute([$studentId, $activeVuSemId]);
    if (!$stmt->fetch()) {
        $stmt = $exeat->query("SELECT COALESCE(MAX(id), 0) + 1 FROM student_academics");
        $nextId = (int) $stmt->fetchColumn();
        $exeat->prepare("INSERT INTO student_academics (id, student_id, matric_no, course_study_id, level, vu_semester_id, academic_session_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')")
            ->execute([$nextId, $studentId, 'VU/CS/2024/001', $csId, 200, $activeVuSemId, $activeAcadSessionId]);
    }
}

echo "   Student: John Doe (VU/CS/2024/001)  — john@demo.edu / password123\n";
echo "   Staff:   Dr. Jane Smith (STAFF/001) — jane@demo.edu / password123\n";

// ══════════════════════════════════════════════
// STEP 5: Registrations
// ══════════════════════════════════════════════
echo "\n[5/8] Finding / creating registrations...\n";

if ($studentId && $currentCourseId && $activeVuSemId && $activeAcadSessionId && $activeSessionId) {
    $stmt = $exeat->prepare("SELECT id FROM course_regs WHERE course_id = ? AND student_id = ? AND vu_session_id = ? LIMIT 1");
    $stmt->execute([$currentCourseId, $studentId, $activeSessionId]);
    if (!$stmt->fetch()) {
        $exeat->prepare("INSERT INTO course_regs (course_id, student_id, academic_session_id, vu_session_id, vu_semester_id, level, is_course_reg) VALUES (?, ?, ?, ?, ?, 200, 2)")
            ->execute([$currentCourseId, $studentId, $activeAcadSessionId, $activeSessionId, $activeVuSemId]);
    }
}

if ($staffId && $currentCourseId && $activeAcadSessionId && $activeSessionId && $activeVuSemId) {
    $stmt = $exeat->prepare("SELECT id FROM course_assigneds WHERE staff_id = ? AND course_id = ? AND vu_session_id = ? LIMIT 1");
    $stmt->execute([$staffId, $currentCourseId, $activeSessionId]);
    if (!$stmt->fetch()) {
        $exeat->prepare("INSERT INTO course_assigneds (staff_id, course_id, department_id, academic_session_id, vu_session_id, vu_semester_id, assign_type, status) VALUES (?, ?, ?, ?, ?, ?, 'primary', 1)")
            ->execute([$staffId, $currentCourseId, $deptId, $activeAcadSessionId, $activeSessionId, $activeVuSemId]);
    }
}

echo "   Registrations: OK\n";

// ══════════════════════════════════════════════
// STEP 6: Portal DB auth records
// ══════════════════════════════════════════════
echo "\n[6/8] Creating portal DB auth records...\n";

$stmt = $lms->prepare("SELECT id FROM students WHERE matric_no = ? OR email = ? LIMIT 1");
$stmt->execute(['VU/CS/2024/001', 'john@demo.edu']);
if (!$stmt->fetch()) {
    $lms->prepare("INSERT IGNORE INTO students (name, firstname, lastname, email, matric_no, username, password) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute(['John Michael Doe', 'John', 'Doe', 'john@demo.edu', 'VU/CS/2024/001', 'john', $passwordHash]);
    echo "   Portal student record created\n";
} else {
    echo "   Portal student record already exists\n";
}

$stmt = $lms->prepare("SELECT id FROM examiners WHERE email = ? OR username = ? LIMIT 1");
$stmt->execute(['jane@demo.edu', 'jane']);
if (!$stmt->fetch()) {
    $lms->prepare("INSERT IGNORE INTO examiners (name, username, email, password, role) VALUES (?, ?, ?, ?, ?)")
        ->execute(['Dr. Jane Smith', 'jane', 'jane@demo.edu', $passwordHash, 'examiner']);
    echo "   Portal examiner record created\n";
} else {
    echo "   Portal examiner record already exists\n";
}

// ══════════════════════════════════════════════
// STEP 7: LMS Users
// ══════════════════════════════════════════════
echo "\n[7/8] Creating LMS users...\n";

$roles = [];
foreach (['super_admin', 'admin', 'instructor', 'ta', 'student'] as $slug) {
    $stmt = $lms->prepare("SELECT id FROM lms_roles WHERE slug = ?");
    $stmt->execute([$slug]);
    $r = $stmt->fetch();
    $roles[$slug] = $r ? (int) $r['id'] : null;
}

$pwdBcrypt = password_hash('password123', PASSWORD_BCRYPT);

$lmsStudentId = null;
$stmt = $lms->prepare("SELECT id FROM lms_users WHERE email = ?");
$stmt->execute(['john@demo.edu']);
$existing = $stmt->fetch();
if ($existing) {
    $lmsStudentId = (int) $existing['id'];
    echo "   LMS student exists (ID: {$lmsStudentId})\n";
} else {
    $lms->prepare("INSERT INTO lms_users (uuid, email, password_hash, first_name, last_name, phone, is_active, email_verified_at, portal_user_id, portal_user_type, matric_no, department, current_level) VALUES (?, ?, ?, 'John', 'Doe', '08012345678', 1, NOW(), ?, 'student', 'VU/CS/2024/001', 'Computer Science', '200')")
        ->execute([demo_uuid(), 'john@demo.edu', $pwdBcrypt, $studentId]);
    $lmsStudentId = (int) $lms->lastInsertId();
    echo "   LMS student created (ID: {$lmsStudentId})\n";
}

if ($roles['student'] && $lmsStudentId) {
    $lms->prepare("INSERT IGNORE INTO lms_user_roles (user_id, role_id) VALUES (?, ?)")->execute([$lmsStudentId, $roles['student']]);
}

$lmsStaffId = null;
$stmt = $lms->prepare("SELECT id FROM lms_users WHERE email = ?");
$stmt->execute(['jane@demo.edu']);
$existing = $stmt->fetch();
if ($existing) {
    $lmsStaffId = (int) $existing['id'];
    echo "   LMS staff exists (ID: {$lmsStaffId})\n";
} else {
    $lms->prepare("INSERT INTO lms_users (uuid, email, password_hash, first_name, last_name, phone, is_active, email_verified_at, portal_user_id, portal_user_type, department) VALUES (?, ?, ?, 'Jane', 'Smith', '08098765432', 1, NOW(), ?, 'examiner', 'Computer Science')")
        ->execute([demo_uuid(), 'jane@demo.edu', $pwdBcrypt, $staffId]);
    $lmsStaffId = (int) $lms->lastInsertId();
    echo "   LMS staff created (ID: {$lmsStaffId})\n";
}

if ($roles['instructor'] && $lmsStaffId) {
    $lms->prepare("INSERT IGNORE INTO lms_user_roles (user_id, role_id) VALUES (?, ?)")->execute([$lmsStaffId, $roles['instructor']]);
}

$lmsAdminId = null;
$stmt = $lms->prepare("SELECT id FROM lms_users WHERE email = ?");
$stmt->execute(['admin@demo.edu']);
$existing = $stmt->fetch();
if ($existing) {
    $lmsAdminId = (int) $existing['id'];
} else {
    $lms->prepare("INSERT INTO lms_users (uuid, email, password_hash, first_name, last_name, phone, is_active, email_verified_at) VALUES (?, ?, ?, 'Admin', 'User', '08000000000', 1, NOW())")
        ->execute([demo_uuid(), 'admin@demo.edu', password_hash('admin123', PASSWORD_BCRYPT)]);
    $lmsAdminId = (int) $lms->lastInsertId();
    echo "   LMS admin created (ID: {$lmsAdminId})\n";
}
if ($roles['super_admin'] && $lmsAdminId) {
    $lms->prepare("INSERT IGNORE INTO lms_user_roles (user_id, role_id) VALUES (?, ?)")->execute([$lmsAdminId, $roles['super_admin']]);
}

$stmt = $lms->prepare("SELECT id FROM examiners WHERE email = ? OR username = ? LIMIT 1");
$stmt->execute(['admin@demo.edu', 'admin']);
if (!$stmt->fetch()) {
    $lms->prepare("INSERT IGNORE INTO examiners (name, username, email, password, role) VALUES (?, ?, ?, ?, ?)")
        ->execute(['Admin User', 'admin', 'admin@demo.edu', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
}

// ══════════════════════════════════════════════
// STEP 8: Demo Course with Full Materials (LMS)
// ══════════════════════════════════════════════
echo "\n[8/8] Creating course materials...\n";

$stmt = $lms->prepare("SELECT id FROM lms_course_categories WHERE name = 'Computer Science' LIMIT 1");
$stmt->execute();
$catId = (int) $stmt->fetchColumn();
if (!$catId) {
    $lms->prepare("INSERT INTO lms_course_categories (name, slug, description, sort_order) VALUES ('Computer Science', 'computer-science', 'Computer science and programming courses', 1)")->execute();
    $catId = (int) $lms->lastInsertId();
}

$stmt = $lms->prepare("SELECT id FROM lms_courses WHERE code = 'CSC 201' LIMIT 1");
$stmt->execute();
$courseId = (int) $stmt->fetchColumn();
if (!$courseId) {
    $courseUuid = demo_uuid();
    $lms->prepare("INSERT INTO lms_courses (uuid, code, title, slug, description, short_description, category_id, level, credits, status, is_required, max_students, created_by, portal_course_id, vu_session_id, department_id, course_study_id) VALUES (?, 'CSC 201', 'Data Structures and Algorithms', ?, '<p>Comprehensive course covering fundamental data structures and algorithms.</p>', 'An introduction to data structures and algorithms including arrays, stacks, queues, trees, graphs, and sorting.', ?, '200', 3, 'published', 1, 100, ?, ?, ?, ?, ?)")
        ->execute([$courseUuid, 'csc-201-data-structures', $catId, $lmsStaffId, $currentCourseId, $activeSessionId, $deptId, $csId]);
    $courseId = (int) $lms->lastInsertId();
}

$lms->prepare("REPLACE INTO lms_course_instructors (course_id, user_id, role) VALUES (?, ?, 'primary')")
    ->execute([$courseId, $lmsStaffId]);
echo "   Course: CSC 201 (ID: {$courseId})\n";

function findOrCreateLMS($lms, $table, $where, $data) {
    $whereCols = implode(' AND ', array_map(fn($c) => "$c = ?", array_keys($where)));
    $stmt = $lms->prepare("SELECT id FROM {$table} WHERE {$whereCols} LIMIT 1");
    $stmt->execute(array_values($where));
    $id = (int) $stmt->fetchColumn();
    if (!$id) {
        $cols = implode(', ', array_keys($data));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $lms->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$vals})")->execute(array_values($data));
        $id = (int) $lms->lastInsertId();
    }
    return $id;
}

// Module 1
$mod1Id = findOrCreateLMS($lms, 'lms_course_modules', ['course_id' => $courseId, 'title' => 'Introduction to Data Structures'], [
    'course_id' => $courseId, 'title' => 'Introduction to Data Structures',
    'description' => 'Learn about fundamental data structures: arrays, linked lists, and their importance in computing.',
    'order_index' => 0, 'is_visible' => 1,
]);

$mod1L1Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod1Id, 'title' => 'What are Data Structures?'], [
    'module_id' => $mod1Id, 'title' => 'What are Data Structures?', 'slug' => 'what-are-data-structures',
    'content_type' => 'text', 'duration_minutes' => 15, 'order_index' => 0, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$mod1L2Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod1Id, 'title' => 'Arrays and Lists'], [
    'module_id' => $mod1Id, 'title' => 'Arrays and Lists', 'slug' => 'arrays-and-lists',
    'content_type' => 'video', 'duration_minutes' => 20, 'order_index' => 1, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$mod1L3Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod1Id, 'title' => 'Module 1 Quiz: Arrays and Lists'], [
    'module_id' => $mod1Id, 'title' => 'Module 1 Quiz: Arrays and Lists', 'slug' => 'module-1-quiz',
    'content_type' => 'quiz', 'duration_minutes' => 10, 'order_index' => 2, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$quiz1Id = findOrCreateLMS($lms, 'lms_quizzes', ['course_id' => $courseId, 'title' => 'Module 1 Quiz: Arrays and Lists'], [
    'course_id' => $courseId, 'module_id' => $mod1Id, 'title' => 'Module 1 Quiz: Arrays and Lists',
    'description' => 'Test your understanding of arrays and linked lists.', 'quiz_type' => 'graded',
    'max_attempts' => 2, 'time_limit_minutes' => 10, 'passing_score' => 40.00,
    'shuffle_questions' => 1, 'shuffle_options' => 1, 'is_visible' => 1, 'created_by' => $lmsStaffId,
]);

echo "   Module 1: Introduction to Data Structures (3 lessons + quiz)\n";

// Module 2
$mod2Id = findOrCreateLMS($lms, 'lms_course_modules', ['course_id' => $courseId, 'title' => 'Stacks and Queues'], [
    'course_id' => $courseId, 'title' => 'Stacks and Queues',
    'description' => 'Explore stack and queue data structures, implementations, and real-world applications.',
    'order_index' => 1, 'is_visible' => 1,
]);

$mod2L1Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod2Id, 'title' => 'Stack Data Structure'], [
    'module_id' => $mod2Id, 'title' => 'Stack Data Structure', 'slug' => 'stack-data-structure',
    'content_type' => 'text', 'duration_minutes' => 15, 'order_index' => 0, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$mod2L2Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod2Id, 'title' => 'Queue Data Structure'], [
    'module_id' => $mod2Id, 'title' => 'Queue Data Structure', 'slug' => 'queue-data-structure',
    'content_type' => 'video', 'duration_minutes' => 20, 'order_index' => 1, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$mod2L3Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod2Id, 'title' => 'Stacks and Queues Programming Assignment'], [
    'module_id' => $mod2Id, 'title' => 'Stacks and Queues Programming Assignment', 'slug' => 'stacks-queues-assignment',
    'content_type' => 'assignment', 'duration_minutes' => 30, 'order_index' => 2, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$assign1Id = findOrCreateLMS($lms, 'lms_assignments', ['course_id' => $courseId, 'title' => 'Stacks and Queues Programming Assignment'], [
    'course_id' => $courseId, 'module_id' => $mod2Id, 'title' => 'Stacks and Queues Programming Assignment',
    'description' => 'Implement stack and queue data structures.', 'max_points' => 100.00,
    'passing_points' => 50.00, 'submission_type' => 'file', 'is_visible' => 1, 'created_by' => $lmsStaffId,
]);

echo "   Module 2: Stacks and Queues (3 lessons + assignment)\n";

// Module 3
$mod3Id = findOrCreateLMS($lms, 'lms_course_modules', ['course_id' => $courseId, 'title' => 'Trees and Graphs'], [
    'course_id' => $courseId, 'title' => 'Trees and Graphs',
    'description' => 'Non-linear data structures: binary trees, tree traversals, graph representations, BFS, and DFS.',
    'order_index' => 2, 'is_visible' => 1,
]);

$mod3L1Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod3Id, 'title' => 'Binary Trees'], [
    'module_id' => $mod3Id, 'title' => 'Binary Trees', 'slug' => 'binary-trees',
    'content_type' => 'text', 'duration_minutes' => 20, 'order_index' => 0, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$mod3L2Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod3Id, 'title' => 'Graph Traversals'], [
    'module_id' => $mod3Id, 'title' => 'Graph Traversals', 'slug' => 'graph-traversals',
    'content_type' => 'video', 'duration_minutes' => 25, 'order_index' => 1, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$mod3L3Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod3Id, 'title' => 'Module 3 Discussion: Real-World Trees'], [
    'module_id' => $mod3Id, 'title' => 'Module 3 Discussion: Real-World Trees', 'slug' => 'module-3-discussion',
    'content_type' => 'discussion', 'duration_minutes' => 15, 'order_index' => 2, 'is_visible' => 1, 'is_required' => 0, 'created_by' => $lmsStaffId,
]);

echo "   Module 3: Trees and Graphs (3 lessons + discussion)\n";

// Module 4
$mod4Id = findOrCreateLMS($lms, 'lms_course_modules', ['course_id' => $courseId, 'title' => 'Sorting and Searching Algorithms'], [
    'course_id' => $courseId, 'title' => 'Sorting and Searching Algorithms',
    'description' => 'Master sorting and searching algorithms: bubble sort, merge sort, quick sort, binary search.',
    'order_index' => 3, 'is_visible' => 1,
]);

$mod4L1Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod4Id, 'title' => 'Sorting Algorithms'], [
    'module_id' => $mod4Id, 'title' => 'Sorting Algorithms', 'slug' => 'sorting-algorithms',
    'content_type' => 'text', 'duration_minutes' => 20, 'order_index' => 0, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$mod4L2Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod4Id, 'title' => 'Searching Algorithms'], [
    'module_id' => $mod4Id, 'title' => 'Searching Algorithms', 'slug' => 'searching-algorithms',
    'content_type' => 'video', 'duration_minutes' => 25, 'order_index' => 1, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$mod4L3Id = findOrCreateLMS($lms, 'lms_lessons', ['module_id' => $mod4Id, 'title' => 'Final Course Quiz'], [
    'module_id' => $mod4Id, 'title' => 'Final Course Quiz', 'slug' => 'final-course-quiz',
    'content_type' => 'quiz', 'duration_minutes' => 30, 'order_index' => 2, 'is_visible' => 1, 'is_required' => 1, 'created_by' => $lmsStaffId,
]);

$quiz2Id = findOrCreateLMS($lms, 'lms_quizzes', ['course_id' => $courseId, 'title' => 'Final Course Quiz'], [
    'course_id' => $courseId, 'module_id' => $mod4Id, 'title' => 'Final Course Quiz',
    'description' => 'Comprehensive final quiz covering all course topics.', 'quiz_type' => 'graded',
    'max_attempts' => 1, 'time_limit_minutes' => 30, 'passing_score' => 50.00,
    'shuffle_questions' => 1, 'shuffle_options' => 1, 'is_visible' => 1, 'created_by' => $lmsStaffId,
]);

echo "   Module 4: Sorting and Searching (3 lessons + quiz)\n";

echo "   Materials: OK\n";
echo "\n=== Demo Course Seeder Complete ===\n";
echo "   Course: CSC 201 - Data Structures and Algorithms\n";
echo "   LMS Course ID: {$courseId}\n";
echo "   Modules: 4 | Lessons: 12 | Quizzes: 2 | Assignments: 1\n";
echo "   Student: john@demo.edu / password123\n";
echo "   Staff:   jane@demo.edu / password123\n";
echo "   Admin:   admin@demo.edu / admin123\n\n";
