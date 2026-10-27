<?php
namespace Lms\Services;

use Lms\Core\Database;
use Lms\Models\Course;
use Lms\Models\Enrollment;
use Lms\Models\ActivityLog;
use Lms\Models\SystemSetting;

class PortalSyncService
{
    private \PDO $portalDb;
    private bool $exeat2Available = false;

    public function __construct()
    {
        $this->portalDb = Database::connection('lms');
        try {
            Exeat2::init();
            $this->exeat2Available = Exeat2::available();
        } catch (\Throwable $e) {
            $this->exeat2Available = false;
        }
    }

    public function syncStudentAcademicContext(int $lmsUserId, int $portalStudentId): void
    {
        if (!$this->exeat2Available) return;

        try {
            $academic = Exeat2::getStudentAcademic($portalStudentId);
            if ($academic) {
                $this->portalDb->prepare("UPDATE lms_users SET
                    department = ?, faculty = ?, course_study = ?, current_level = ?,
                    matric_no = ?, updated_at = NOW()
                    WHERE id = ?")->execute([
                    $academic['department_name'] ?? '',
                    $academic['faculty_name'] ?? '',
                    $academic['course_study_name'] ?? '',
                    $academic['current_level'] ?? '',
                    $academic['matric_no'] ?? '',
                    $lmsUserId,
                ]);
            }
        } catch (\Throwable $e) {
            error_log("[LMS Sync] Academic context sync failed: " . $e->getMessage());
        }
    }

    public function syncUserCoursesFromPortal(int $lmsUserId, int $portalUserId, string $userType): array
    {
        $synced = [];

        if ($userType === 'student' && $this->exeat2Available) {
            try {
                $courseRegs = Exeat2::getStudentCurrentCourses($portalUserId);
                foreach ($courseRegs as $reg) {
                    $lmsCourseId = $this->findOrCreateLmsCourse($reg);
                    if ($lmsCourseId) {
                        $this->syncEnrollment($lmsUserId, $lmsCourseId, $reg);
                        $synced[] = $lmsCourseId;
                    }
                }
            } catch (\Throwable $e) {
                error_log("[LMS Sync] Course reg sync failed: " . $e->getMessage());
            }
        }

        try {
            $stmt = $this->portalDb->prepare("
                SELECT e.id as exam_id, e.name as exam_name, e.type as exam_type,
                       el.course_id, el.lms_quiz_id
                FROM exam_students es
                JOIN exams e ON es.exam_id = e.id
                LEFT JOIN lms_exam_links el ON el.portal_exam_id = e.id
                WHERE es.student_id = ?
            ");
            $stmt->execute([$portalUserId]);
            $exams = $stmt->fetchAll();

            foreach ($exams as $exam) {
                if ($exam['course_id']) {
                    $this->syncExamEnrollment($lmsUserId, (int) $exam['course_id'], $exam);
                    $synced[] = (int) $exam['course_id'];
                }
            }
        } catch (\Throwable $e) {
            error_log("[LMS Sync] Exam enrollment sync failed: " . $e->getMessage());
        }

        return $synced;
    }

    public function syncStaffCourses(int $lmsUserId, int $portalStaffId): array
    {
        if (!$this->exeat2Available) return [];

        $synced = [];
        try {
            $assignments = Exeat2::getStaffAssignedCourses($portalStaffId);
            foreach ($assignments as $assigned) {
                $lmsCourseId = $this->findOrCreateLmsCourse($assigned);
                if ($lmsCourseId) {
                    $this->ensureInstructorAssignment($lmsCourseId, $lmsUserId);
                    $synced[] = $lmsCourseId;
                }
            }
        } catch (\Throwable $e) {
            error_log("[LMS Sync] Staff course sync failed: " . $e->getMessage());
        }
        return $synced;
    }

    public function syncAllActiveEnrollments(): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => 0];

        if (!$this->exeat2Available) return $results;

        try {
            $stmt = $this->portalDb->query("
                SELECT lu.id as lms_user_id, lu.portal_user_id, lu.portal_user_type
                FROM lms_users lu
                WHERE lu.portal_user_type = 'student' AND lu.is_active = 1 AND lu.deleted_at IS NULL
            ");
            $users = $stmt->fetchAll();

            foreach ($users as $user) {
                try {
                    $this->syncUserCoursesFromPortal((int) $user['lms_user_id'], (int) $user['portal_user_id'], 'student');
                    $results['updated']++;
                } catch (\Throwable $e) {
                    $results['errors']++;
                }
            }
        } catch (\Throwable $e) {
            error_log("[LMS Sync] Bulk enrollment sync failed: " . $e->getMessage());
        }

        return $results;
    }

    public function getStudentFullProfile(int $portalStudentId): ?array
    {
        $profile = [
            'portal' => null,
            'academic' => null,
            'courses' => [],
            'current_registrations' => [],
            'exam_enrollments' => [],
        ];

        $stmt = $this->portalDb->prepare("SELECT * FROM students WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$portalStudentId]);
        $profile['portal'] = $stmt->fetch() ?: null;

        if ($this->exeat2Available) {
            $profile['academic'] = Exeat2::getStudentAcademic($portalStudentId);
            $profile['courses'] = Exeat2::getStudentCourseRegs($portalStudentId);
            $profile['current_registrations'] = Exeat2::getStudentCurrentCourses($portalStudentId);
        }

        $stmt = $this->portalDb->prepare("
            SELECT e.*, es.id as enrollment_id FROM exam_students es
            JOIN exams e ON es.exam_id = e.id
            WHERE es.student_id = ?
        ");
        $stmt->execute([$portalStudentId]);
        $profile['exam_enrollments'] = $stmt->fetchAll();

        return $profile;
    }

    public function getCourseStudyWithCourses(int $courseStudyId): ?array
    {
        if (!$this->exeat2Available) return null;

        $courseStudy = Exeat2::getCourseStudy($courseStudyId);
        if (!$courseStudy) return null;

        $courseStudy['courses'] = Exeat2::getCourses(
            $courseStudy['department_id'],
            $courseStudyId
        );
        $courseStudy['department'] = Exeat2::getDepartment($courseStudy['department_id']);

        return $courseStudy;
    }

    private function findOrCreateLmsCourse(array $sourceCourse): ?int
    {
        $sourceId = $sourceCourse['course_id'] ?? $sourceCourse['id'] ?? null;
        if (!$sourceId) return null;

        $stmt = $this->portalDb->prepare("SELECT id FROM lms_courses WHERE portal_course_id = ?");
        $stmt->execute([$sourceId]);
        $existing = $stmt->fetch();

        if ($existing) return (int) $existing['id'];

        $code = $sourceCourse['course_code'] ?? $sourceCourse['code'] ?? '';
        $title = $sourceCourse['course_title'] ?? $sourceCourse['title'] ?? '';
        $credits = $sourceCourse['credit_load'] ?? 3;
        $level = $sourceCourse['level_name'] ?? $sourceCourse['level'] ?? '';

        $uuid = \lms_uuid();
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $code ?: substr($title, 0, 20))) . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

        $this->portalDb->prepare("
            INSERT INTO lms_courses (uuid, code, title, slug, credits, level, status, portal_course_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'published', ?, NOW(), NOW())
        ")->execute([$uuid, $code, $title, $slug, $credits, (string) $level, $sourceId]);

        return (int) $this->portalDb->lastInsertId();
    }

    private function syncEnrollment(int $lmsUserId, int $courseId, array $reg): void
    {
        $stmt = $this->portalDb->prepare("
            SELECT id FROM lms_enrollments WHERE user_id = ? AND course_id = ? AND enrollment_type = 'student'
        ");
        $stmt->execute([$lmsUserId, $courseId]);
        if ($stmt->fetch()) return;

        $this->portalDb->prepare("
            INSERT INTO lms_enrollments (user_id, course_id, enrollment_type, status, created_at, updated_at)
            VALUES (?, ?, 'student', 'active', NOW(), NOW())
        ")->execute([$lmsUserId, $courseId]);
    }

    private function syncExamEnrollment(int $lmsUserId, int $courseId, array $exam): void
    {
        $stmt = $this->portalDb->prepare("
            SELECT id FROM lms_enrollments WHERE user_id = ? AND course_id = ? AND enrollment_type = 'student'
        ");
        $stmt->execute([$lmsUserId, $courseId]);
        if ($stmt->fetch()) return;

        $this->portalDb->prepare("
            INSERT INTO lms_enrollments (user_id, course_id, enrollment_type, status, created_at, updated_at)
            VALUES (?, ?, 'student', 'active', NOW(), NOW())
        ")->execute([$lmsUserId, $courseId]);
    }

    private function ensureInstructorAssignment(int $courseId, int $lmsUserId): void
    {
        $stmt = $this->portalDb->prepare("
            SELECT 1 FROM lms_course_instructors WHERE course_id = ? AND user_id = ?
        ");
        $stmt->execute([$courseId, $lmsUserId]);
        if ($stmt->fetch()) return;

        $this->portalDb->prepare("
            INSERT INTO lms_course_instructors (course_id, user_id, role, created_at)
            VALUES (?, ?, 'primary', NOW())
        ")->execute([$courseId, $lmsUserId]);
    }
}
