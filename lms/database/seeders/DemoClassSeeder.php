<?php
/**
 * Demo Class Seeder — Creates a complete class with enrollments,
 * staff, and ensures all course materials are present.
 *
 * Usage: php lms/database/migrate.php --seed
 */

declare(strict_types=1);

if (!defined('LMS_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../../bootstrap.php';
}

use Lms\Core\Database;
use Lms\Services\Exeat2;
use Lms\Services\ClassroomService;

$lms   = Database::connection('lms');
Exeat2::init();
$exeat = Exeat2::conn();

echo "\n=== Demo Class Seeder ===\n\n";

function uuid4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ───────────────────────────────────────────────────
// STEP 1: Use existing CSC 201 course
// ───────────────────────────────────────────────────
echo "[1/5] Locating course CSC 201...\n";

$stmt = $exeat->prepare("SELECT id, code, title, credit_load, level, department_id, course_study_id FROM courses WHERE id = 1220 LIMIT 1");
$stmt->execute();
$course = $stmt->fetch();
if (!$course) {
    echo "   ERROR: CSC 201 (exeat2 ID 1220) not found.\n";
    exit(1);
}
$courseId = (int) $course['id'];
echo "   Found: {$course['code']} - {$course['title']} (ID: {$courseId})\n";

// ───────────────────────────────────────────────────
// STEP 2: Use session 19 / vu_semester 73
// ───────────────────────────────────────────────────
echo "\n[2/5] Using academic session...\n";

$vuSessionId = 19;
$vuSemesterId = 73;

// Get semester name
$stmt = $exeat->prepare("SELECT s.semester FROM vu_semesters vsem JOIN semesters s ON vsem.semester_id = s.id WHERE vsem.id = ?");
$stmt->execute([$vuSemesterId]);
$semRow = $stmt->fetch();
$semesterName = $semRow ? $semRow['semester'] : 'First';
echo "   vu_session: 19, vu_semester: {$vuSemesterId} ({$semesterName})\n";

// ───────────────────────────────────────────────────
// STEP 3: Create or reuse lms_users for demo students
// ───────────────────────────────────────────────────
echo "\n[3/5] Setting up demo users in LMS...\n";

$passwordHash = password_hash('password123', PASSWORD_DEFAULT);
$pwdBcrypt = password_hash('password123', PASSWORD_BCRYPT);

$demoStudents = [
    ['fname' => 'Alice', 'lname' => 'Johnson', 'matric' => 'VU/CS/2025/001', 'email' => 'alice@demo.edu'],
    ['fname' => 'Bob',   'lname' => 'Williams', 'matric' => 'VU/CS/2025/002', 'email' => 'bob@demo.edu'],
    ['fname' => 'Charlie','lname' => 'Brown',   'matric' => 'VU/CS/2025/003', 'email' => 'charlie@demo.edu'],
    ['fname' => 'Diana', 'lname' => 'Prince',  'matric' => 'VU/CS/2025/004', 'email' => 'diana@demo.edu'],
    ['fname' => 'Eve',   'lname' => 'Davis',   'matric' => 'VU/CS/2025/005', 'email' => 'eve@demo.edu'],
];

$lmsUserIds = [];

foreach ($demoStudents as $s) {
    $stmt = $lms->prepare("SELECT id FROM lms_users WHERE email = ? LIMIT 1");
    $stmt->execute([$s['email']]);
    $existing = $stmt->fetch();
    if ($existing) {
        $uid = (int) $existing['id'];
        echo "   LMS user exists: {$s['fname']} {$s['lname']} (ID: {$uid})\n";
    } else {
        $stmt = $lms->prepare("INSERT INTO lms_users (uuid, email, password_hash, first_name, last_name, portal_user_type, is_active, email_verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'student', 1, NOW(), NOW(), NOW())");
        $stmt->execute([uuid4(), $s['email'], $pwdBcrypt, $s['fname'], $s['lname']]);
        $uid = (int) $lms->lastInsertId();
        echo "   Created lms_user: {$s['fname']} {$s['lname']} (ID: {$uid})\n";
    }

    // Also ensure record in `students` table (portal)
    $stmt = $lms->prepare("SELECT id FROM students WHERE email = ? LIMIT 1");
    $stmt->execute([$s['email']]);
    if (!$stmt->fetch()) {
        $stmt = $lms->prepare("INSERT INTO students (name, firstname, lastname, email, matric_no, username, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(["{$s['fname']} {$s['lname']}", $s['fname'], $s['lname'], $s['email'], $s['matric'], $s['matric'], $passwordHash]);
    }

    $lmsUserIds[] = ['lms_user_id' => $uid, 'matric' => $s['matric'], 'name' => "{$s['fname']} {$s['lname']}", 'email' => $s['email']];
}

// ───────────────────────────────────────────────────
// STEP 4: Create class and enroll students
// ───────────────────────────────────────────────────
echo "\n[4/5] Setting up class with enrollments...\n";

$classroom = new ClassroomService();

// Find or create class
$stmt = $lms->prepare("SELECT id FROM lms_classes WHERE course_id = ? AND vu_session_id = ? AND vu_semester_id = ? LIMIT 1");
$stmt->execute([$courseId, $vuSessionId, $vuSemesterId]);
$existingClass = $stmt->fetch();

if ($existingClass) {
    $classId = (int) $existingClass['id'];
    echo "   Found existing class ID: {$classId}\n";
} else {
    $uuid = uuid4();
    $stmt = $lms->prepare("INSERT INTO lms_classes (uuid, name, code, course_id, vu_session_id, vu_semester_id, total_students, total_staff, status, sync_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 'manual', NOW(), NOW())");
    $stmt->execute([$uuid, "{$course['code']} - {$course['title']} ({$semesterName})", 'csc-201', $courseId, $vuSessionId, $vuSemesterId, 0, 0]);
    $classId = (int) $lms->lastInsertId();
    echo "   Created class ID: {$classId}\n";
}

// Clear and insert enrollments directly
$stmt = $lms->prepare("DELETE FROM lms_class_enrollments WHERE class_id = ?");
$stmt->execute([$classId]);
echo "   Cleared previous enrollments\n";

$enrolled = 0;
$fakeStudentId = 90000;
foreach ($lmsUserIds as $u) {
    $fakeStudentId++;
    $stmt = $lms->prepare("INSERT INTO lms_class_enrollments (class_id, student_id, lms_user_id, matric_no, student_name, enrollment_source, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'manual', NOW(), NOW()) ON DUPLICATE KEY UPDATE lms_user_id = VALUES(lms_user_id), student_name = VALUES(student_name), status = 'active', updated_at = NOW()");
    $stmt->execute([$classId, $fakeStudentId, $u['lms_user_id'], $u['matric'], $u['name']]);
    $enrolled++;
}

$lms->prepare("UPDATE lms_classes SET total_students = ?, total_staff = 0, sync_status = 'manual' WHERE id = ?")
    ->execute([$enrolled, $classId]);
echo "   Enrolled {$enrolled} students\n";

// ───────────────────────────────────────────────────
// STEP 5: Summary
// ───────────────────────────────────────────────────
echo "\n[5/5] Summary\n";
echo "   ─────────────────────────────────────\n";

$class = $classroom->getClassWithDetails($classId);
if ($class) {
    echo "   Class:       {$class['name']}\n";
    echo "   Course:      {$class['course_code']} - {$class['course_title']}\n";
    echo "   Session:     {$class['vu_session_name']}\n";
    echo "   Semester:    {$class['semester_name']}\n";
    echo "   Enrolled:    " . count($class['enrollments']) . " students\n";
    echo "   Staff:       " . count($class['staff']) . " members\n";
    echo "   Status:      {$class['status']}\n";
}

echo "\n=== Demo Class Seeder Complete ===\n";
echo "View class: /lms/public/#/classes/{$classId}\n";
echo "Demo student login: alice@demo.edu .. eve@demo.edu / password123\n\n";
