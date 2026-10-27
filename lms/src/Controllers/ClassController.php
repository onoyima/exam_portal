<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Core\Database;
use Lms\Services\Exeat2;
use Lms\Services\ClassroomService;

class ClassController extends Controller
{
    private ClassroomService $classroom;

    public function __construct()
    {
        parent::__construct();
        $this->classroom = new ClassroomService();
    }

    // ─── List classes ─────────────────────────────────────
    public function index(Request $req, Response $res): void
    {
        $res->success($this->classroom->listClasses(
            (int) $req->query('page', 1),
            (int) $req->query('per_page', 20),
            $req->query('search'),
            $req->query('status'),
            $req->query('department_id') ? (int) $req->query('department_id') : null,
            $req->query('vu_session_id') ? (int) $req->query('vu_session_id') : null,
        ));
    }

    // ─── Get single class with details ─────────────────────
    public function show(Request $req, Response $res, string $id): void
    {
        $class = $this->classroom->getClassWithDetails((int) $id);
        if (!$class) $res->notFound('Class not found');
        $res->success(['class' => $class]);
    }

    // ─── Create a class manually ──────────────────────────
    public function store(Request $req, Response $res): void
    {
        $data = $req->body();
        $courseId = (int) ($data['course_id'] ?? 0);
        $vuSessionId = (int) ($data['vu_session_id'] ?? 0);
        $vuSemesterId = (int) ($data['vu_semester_id'] ?? 0);

        if (!$courseId || !$vuSessionId) {
            $res->validationError(['course_id' => 'Course ID and Session ID are required']);
        }

        if (!Exeat2::available()) {
            $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        }

        // Fetch course info from exeat2
        $course = Exeat2::getCourse($courseId);
        if (!$course) $res->notFound('Course not found in exeat2');

        $offering = [
            'course_id' => $courseId,
            'vu_session_id' => $vuSessionId,
            'vu_semester_id' => $vuSemesterId,
            'academic_session_id' => $data['academic_session_id'] ?? null,
            'department_id' => $course['department_id'] ?? null,
            'course_study_id' => $course['course_study_id'] ?? null,
            'level' => $course['level'] ?? null,
            'course_code' => $course['code'] ?? '',
            'course_title' => $course['title'] ?? '',
        ];

        // Get session/semester names
        $sessions = Exeat2::getVuSessions();
        foreach ($sessions as $s) {
            if ((int) $s['id'] === $vuSessionId) {
                $offering['vu_session_name'] = $s['session'];
                break;
            }
        }
        $semesters = Exeat2::getVuSemesters();
        foreach ($semesters as $sem) {
            if ((int) $sem['id'] === $vuSemesterId) {
                $offering['semester_name'] = $sem['semester_name'] ?? $sem['semester'] ?? '';
                break;
            }
        }

        $classId = $this->classroom->findOrCreateClass($offering);
        if (!$classId) $res->serverError('Failed to create class');

        $class = $this->classroom->getClassWithDetails($classId);
        $res->created(['class' => $class]);
    }

    // ─── Update class ─────────────────────────────────────
    public function update(Request $req, Response $res, string $id): void
    {
        $db = Database::connection('lms');
        $fields = [];
        $params = [];

        foreach (['name', 'code', 'status'] as $field) {
            $val = $req->body($field);
            if ($val !== null) {
                $fields[] = "{$field} = ?";
                $params[] = $val;
            }
        }

        if (empty($fields)) {
            $res->validationError(['fields' => 'No fields to update']);
        }

        $params[] = (int) $id;
        $db->prepare("UPDATE lms_classes SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?")->execute($params);

        $class = $this->classroom->getClassWithDetails((int) $id);
        if (!$class) $res->notFound('Class not found');
        $res->success(['class' => $class]);
    }

    // ─── Delete (soft) class ──────────────────────────────
    public function destroy(Request $req, Response $res, string $id): void
    {
        $db = Database::connection('lms');
        $stmt = $db->prepare("UPDATE lms_classes SET deleted_at = NOW(), status = 'archived' WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([(int) $id]);
        if ($stmt->rowCount() === 0) $res->notFound('Class not found');
        $res->noContent();
    }

    // ─── Sync classes from exeat2 ─────────────────────────
    public function sync(Request $req, Response $res): void
    {
        if (!Exeat2::available()) {
            $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        }

        $vuSessionId = $req->body('vu_session_id') ? (int) $req->body('vu_session_id') : null;
        $vuSemesterId = $req->body('vu_semester_id') ? (int) $req->body('vu_semester_id') : null;

        $result = $this->classroom->syncAllClasses($vuSessionId, $vuSemesterId);
        $res->success(['sync_result' => $result]);
    }

    // ─── Enroll a student ─────────────────────────────────
    public function enrollStudent(Request $req, Response $res, string $id): void
    {
        $studentId = (int) ($req->body('student_id') ?? 0);
        if (!$studentId) $res->validationError(['student_id' => 'Student ID is required']);

        $enrollment = $this->classroom->enrollStudent((int) $id, $studentId);
        if (!$enrollment) $res->notFound('Student not found in exeat2');

        $res->success(['enrollment' => $enrollment]);
    }

    // ─── Bulk enroll students from course_regs ────────────
    public function bulkEnroll(Request $req, Response $res, string $id): void
    {
        $count = $this->classroom->bulkEnrollFromCourseRegs((int) $id);
        $res->success(['enrolled' => $count]);
    }

    // ─── Remove a student ─────────────────────────────────
    public function removeStudent(Request $req, Response $res, string $classId, string $studentId): void
    {
        $removed = $this->classroom->removeStudent((int) $classId, (int) $studentId);
        if (!$removed) $res->notFound('Enrollment not found');
        $res->noContent();
    }

    // ─── Assign staff to class ────────────────────────────
    public function assignStaff(Request $req, Response $res, string $id): void
    {
        $staffId = (int) ($req->body('staff_id') ?? 0);
        $role = $req->body('role', 'assistant');

        if (!in_array($role, ['primary', 'assistant', 'guest'])) {
            $res->validationError(['role' => 'Role must be primary, assistant, or guest']);
        }
        if (!$staffId) $res->validationError(['staff_id' => 'Staff ID is required']);

        $assignment = $this->classroom->assignStaff((int) $id, $staffId, $role);
        if (!$assignment) $res->notFound('Staff not found');

        $res->success(['assignment' => $assignment]);
    }

    // ─── Remove staff from class ──────────────────────────
    public function removeStaff(Request $req, Response $res, string $classId, string $staffId): void
    {
        $removed = $this->classroom->removeStaff((int) $classId, (int) $staffId);
        if (!$removed) $res->notFound('Staff assignment not found');
        $res->noContent();
    }

    // ─── My classes (for current user) ────────────────────
    public function myClasses(Request $req, Response $res): void
    {
        $user = $this->user();
        if (!$user) $res->unauthorized();

        $lmsUserId = $user['id'];
        $role = $user['role'] ?? '';

        if (in_array($role, ['super_admin', 'admin'])) {
            $classes = $this->classroom->listClasses(1, 100);
            $res->success($classes);
        } elseif (in_array($role, ['instructor', 'ta'])) {
            $res->success(['classes' => $this->classroom->getStaffClasses($lmsUserId)]);
        } else {
            $res->success(['classes' => $this->classroom->getStudentClasses($lmsUserId)]);
        }
    }

    // ─── Class sessions (lecture schedule) ────────────────
    public function sessions(Request $req, Response $res, string $id): void
    {
        $res->success(['sessions' => $this->classroom->getClassSessions((int) $id)]);
    }

    public function createSession(Request $req, Response $res, string $id): void
    {
        $db = Database::connection('lms');
        $data = $req->body();
        $db->prepare("
            INSERT INTO lms_class_sessions (class_id, title, date, start_time, end_time, venue, topic, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())
        ")->execute([
            (int) $id,
            $data['title'] ?? null,
            $data['date'] ?? null,
            $data['start_time'] ?? null,
            $data['end_time'] ?? null,
            $data['venue'] ?? null,
            $data['topic'] ?? null,
        ]);
        $sessionId = (int) $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM lms_class_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $res->created(['session' => $stmt->fetch()]);
    }

    public function updateSession(Request $req, Response $res, string $classId, string $sessionId): void
    {
        $db = Database::connection('lms');
        $fields = [];
        $params = [];

        foreach (['title', 'date', 'start_time', 'end_time', 'venue', 'topic', 'status'] as $field) {
            $val = $req->body($field);
            if ($val !== null) {
                $fields[] = "{$field} = ?";
                $params[] = $val;
            }
        }

        if (empty($fields)) {
            $res->validationError(['fields' => 'No fields to update']);
        }

        $params[] = (int) $sessionId;
        $params[] = (int) $classId;
        $stmt = $db->prepare("UPDATE lms_class_sessions SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ? AND class_id = ?");
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) $res->notFound('Session not found');

        $s = $db->prepare("SELECT * FROM lms_class_sessions WHERE id = ?");
        $s->execute([(int) $sessionId]);
        $res->success(['session' => $s->fetch()]);
    }

    public function deleteSession(Request $req, Response $res, string $classId, string $sessionId): void
    {
        $db = Database::connection('lms');
        $stmt = $db->prepare("DELETE FROM lms_class_sessions WHERE id = ? AND class_id = ?");
        $stmt->execute([(int) $sessionId, (int) $classId]);
        if ($stmt->rowCount() === 0) $res->notFound('Session not found');
        $res->noContent();
    }

    // ─── Link vu_session to class ─────────────────────────
    public function linkSession(Request $req, Response $res, string $id): void
    {
        $data = $req->body();
        $vuSessionId = (int) ($data['vu_session_id'] ?? 0);
        if (!$vuSessionId) $res->validationError(['vu_session_id' => 'Required']);

        $vuSession = Exeat2::getVuSession($vuSessionId);
        if (!$vuSession) $res->notFound('Academic session not found');

        $db = Database::connection('lms');

        $classCheck = $db->prepare("SELECT id FROM lms_classes WHERE id = ?");
        $classCheck->execute([(int) $id]);
        if (!$classCheck->fetch()) $res->notFound('Class not found');

        $db->prepare("UPDATE lms_classes SET vu_session_id = ?, vu_session_name = ? WHERE id = ?")
            ->execute([$vuSessionId, $vuSession['session'], (int) $id]);

        $db->prepare("DELETE FROM lms_class_sessions WHERE class_id = ? AND vu_session_id = ?")
            ->execute([(int) $id, $vuSessionId]);

        $stmt = $db->prepare("INSERT INTO lms_class_sessions (class_id, vu_session_id, title, date, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'scheduled', NOW(), NOW())");
        $start = new \DateTime($vuSession['start_date']);
        $end = (new \DateTime($vuSession['end_date']))->modify('+1 day');
        $interval = new \DateInterval('P1W');
        $period = new \DatePeriod($start, $interval, $end);
        $count = 0;
        foreach ($period as $date) {
            $weekNum = (int) ceil(($date->getTimestamp() - $start->getTimestamp() + 1) / 604800);
            $stmt->execute([(int) $id, $vuSessionId, 'Week ' . $weekNum, $date->format('Y-m-d')]);
            $count++;
        }

        ActivityLog::log('class.session_linked', $this->userId(), (int) $id, 'class', (int) $id);
        $res->success(['message' => "Linked vu_session '{$vuSession['session']}' with {$count} weekly sessions"]);
    }

    // ─── Class hierarchy grouped by session→semester→level ─
    public function hierarchy(Request $req, Response $res): void
    {
        $vuSessionId = $req->query('vu_session_id') ? (int) $req->query('vu_session_id') : null;
        $res->success(['hierarchy' => $this->classroom->getClassHierarchy($vuSessionId)]);
    }

    // ─── Class analytics ──────────────────────────────────
    public function analytics(Request $req, Response $res, string $id): void
    {
        $class = $this->classroom->getClassWithDetails((int) $id);
        if (!$class) $res->notFound('Class not found');

        $enrollments = $class['enrollments'] ?? [];
        $total = count($enrollments);
        $active = count(array_filter($enrollments, fn($e) => $e['status'] === 'active'));
        $dropped = count(array_filter($enrollments, fn($e) => $e['status'] === 'dropped'));

        $passed = count(array_filter($enrollments, fn($e) => $e['grade'] && $e['grade'] !== 'F' && $e['grade'] !== 'E'));

        $res->success([
            'analytics' => [
                'total_enrolled' => $total,
                'active' => $active,
                'dropped' => $dropped,
                'passed' => $passed,
                'staff_count' => count($class['staff'] ?? []),
                'session_count' => count($class['sessions'] ?? []),
            ]
        ]);
    }
}
