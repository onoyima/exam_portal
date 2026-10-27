<?php
namespace Lms\Services;

use Lms\Core\Database;
use Lms\Services\Exeat2;

class ClassroomService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::connection('lms');
    }

    /**
     * Sync all classes from exeat2 course_regs for the active session.
     * Creates lms_classes, lms_class_enrollments, and lms_class_staff records.
     */
    public function syncAllClasses(?int $vuSessionId = null, ?int $vuSemesterId = null): array
    {
        $result = ['classes_created' => 0, 'classes_updated' => 0, 'enrollments_added' => 0, 'staff_assigned' => 0, 'errors' => 0];

        if (!Exeat2::available()) return $result;

        // Use active session if not specified
        if (!$vuSessionId) {
            $active = Exeat2::getActiveVuSession();
            $vuSessionId = $active['id'] ?? null;
        }

        // Get all unique course offerings from course_regs
        $offerings = Exeat2::getCourseRegClassOfferings($vuSessionId, $vuSemesterId);

        foreach ($offerings as $offering) {
            try {
                $classId = $this->findOrCreateClass($offering);
                if (!$classId) { $result['errors']++; continue; }

                $offering['_class_id'] = $classId;
                $result['classes_created'] += ($offering['_is_new'] ?? false) ? 1 : 0;
                $result['classes_updated'] += ($offering['_is_new'] ?? false) ? 0 : 1;

                // Sync enrollments from course_regs
                $enrolled = $this->syncClassEnrollments($classId, $offering);
                $result['enrollments_added'] += $enrolled;

                // Sync staff from assigned_courses
                $staffAssigned = $this->syncClassStaff($classId, $offering);
                $result['staff_assigned'] += $staffAssigned;
            } catch (\Throwable $e) {
                error_log("[ClassroomService] Sync error for course {$offering['course_id']}: " . $e->getMessage());
                $result['errors']++;
            }
        }

        return $result;
    }

    /**
     * Create or update a class for a course offering.
     */
    public function findOrCreateClass(array $offering): ?int
    {
        $courseId = $offering['course_id'];
        $vuSessionId = $offering['vu_session_id'];
        $vuSemesterId = $offering['vu_semester_id'] ?? 0;

        // Check if class already exists
        $stmt = $this->db->prepare("SELECT id FROM lms_classes WHERE course_id = ? AND vu_session_id = ? AND vu_semester_id = ? AND deleted_at IS NULL");
        $stmt->execute([$courseId, $vuSessionId, $vuSemesterId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $offering['_is_new'] = false;
            return (int) $existing['id'];
        }

        $code = $offering['course_code'] ?? '';
        $title = $offering['course_title'] ?? '';
        $sessionName = $offering['vu_session_name'] ?? '';
        $semesterName = $offering['semester_name'] ?? '';
        $name = $code ? "{$code} - {$title} ({$sessionName} - {$semesterName})" : "{$title} ({$sessionName})";
        $uuid = \lms_uuid();
        $slugCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $code ?: substr($title, 0, 20)));

        // Find matching lms_course
        $lmsCourseId = null;
        $lcStmt = $this->db->prepare("SELECT id FROM lms_courses WHERE portal_course_id = ? LIMIT 1");
        $lcStmt->execute([$courseId]);
        $lc = $lcStmt->fetch();
        if ($lc) $lmsCourseId = (int) $lc['id'];

        $this->db->prepare("
            INSERT INTO lms_classes (uuid, name, code, course_id, lms_course_id, vu_session_id, vu_semester_id, academic_session_id, level_id, department_id, course_study_id, sync_status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'synced', NOW(), NOW())
        ")->execute([
            $uuid, $name, $slugCode, $courseId, $lmsCourseId,
            $vuSessionId, $vuSemesterId, $offering['academic_session_id'] ?? null,
            $offering['level'] ?? null, $offering['department_id'] ?? null,
            $offering['course_study_id'] ?? null
        ]);

        $offering['_is_new'] = true;
        return (int) $this->db->lastInsertId();
    }

    /**
     * Sync enrollments from course_regs into a class.
     */
    public function syncClassEnrollments(int $classId, array $offering): int
    {
        $courseId = $offering['course_id'];
        $vuSessionId = $offering['vu_session_id'];
        $vuSemesterId = $offering['vu_semester_id'] ?? 0;

        $students = Exeat2::getCourseRegStudentsForClass($courseId, $vuSessionId, $vuSemesterId);
        $count = 0;

        foreach ($students as $student) {
            try {
                $studentId = $student['student_id'];
                $studentName = trim(($student['lname'] ?? '') . ' ' . ($student['fname'] ?? '') . ' ' . ($student['mname'] ?? ''));
                $matricNo = $student['matric_no'] ?? '';

                // Find lms_user_id
                $lmsUserId = $this->findLmsUserIdByPortalStudentId($studentId);

                $stmt = $this->db->prepare("
                    INSERT INTO lms_class_enrollments (class_id, student_id, lms_user_id, matric_no, student_name, ca_one, ca_two, ca_three, examination, total, grade, enrollment_source, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'auto_sync', NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        lms_user_id = VALUES(lms_user_id),
                        matric_no = VALUES(matric_no),
                        student_name = VALUES(student_name),
                        ca_one = VALUES(ca_one),
                        ca_two = VALUES(ca_two),
                        ca_three = VALUES(ca_three),
                        examination = VALUES(examination),
                        total = VALUES(total),
                        grade = VALUES(grade),
                        status = 'active',
                        updated_at = NOW()
                ");
                $stmt->execute([
                    $classId, $studentId, $lmsUserId, $matricNo, $studentName,
                    $student['ca_one'] ?? null, $student['ca_two'] ?? null,
                    $student['ca_three'] ?? null, $student['examination'] ?? null,
                    $student['total'] ?? null, $student['grade'] ?? null
                ]);
                if ($stmt->rowCount() > 0) $count++;
            } catch (\Throwable $e) {
                error_log("[ClassroomService] Enrollment sync error: " . $e->getMessage());
            }
        }

        // Update total count
        $this->updateClassCounts($classId);

        return $count;
    }

    /**
     * Sync staff from assigned_courses into a class.
     */
    public function syncClassStaff(int $classId, array $offering): int
    {
        $courseId = $offering['course_id'];
        $vuSessionId = $offering['vu_session_id'];
        $vuSemesterId = $offering['vu_semester_id'] ?? 0;

        $staffList = Exeat2::getAssignedCourseStaffForClass($courseId, $vuSessionId, $vuSemesterId);
        $count = 0;

        foreach ($staffList as $staff) {
            try {
                $staffId = $staff['staff_id'];
                $staffName = trim(($staff['lname'] ?? '') . ' ' . ($staff['fname'] ?? '') . ' ' . ($staff['mname'] ?? ''));
                $lmsUserId = $this->findLmsUserIdByPortalStaffId($staffId);

                $stmt = $this->db->prepare("
                    INSERT INTO lms_class_staff (class_id, staff_id, lms_user_id, staff_name, role, created_at)
                    VALUES (?, ?, ?, ?, 'assistant', NOW())
                    ON DUPLICATE KEY UPDATE
                        lms_user_id = VALUES(lms_user_id),
                        staff_name = VALUES(staff_name),
                        role = CASE WHEN role = 'primary' THEN 'primary' ELSE 'assistant' END
                ");
                $stmt->execute([$classId, $staffId, $lmsUserId, $staffName]);
                if ($stmt->rowCount() > 0) $count++;
            } catch (\Throwable $e) {
                error_log("[ClassroomService] Staff sync error: " . $e->getMessage());
            }
        }

        $this->updateClassCounts($classId);
        return $count;
    }

    /**
     * Get a class with all details (students, staff, course info).
     */
    public function getClassWithDetails(int $classId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, co.code as course_code, co.title as course_title, co.credit_load,
                   vs.session as vu_session_name,
                   s.semester as semester_name,
                   d.name as department_name, f.name as faculty_name,
                   cs.name as course_study_name
            FROM lms_classes c
            LEFT JOIN lms_courses lc ON c.lms_course_id = lc.id
            LEFT JOIN exeat2.courses co ON c.course_id = co.id
            LEFT JOIN exeat2.vu_sessions vs ON c.vu_session_id = vs.id
            LEFT JOIN exeat2.vu_semesters vsem ON c.vu_semester_id = vsem.id
            LEFT JOIN exeat2.semesters s ON vsem.semester_id = s.id
            LEFT JOIN exeat2.departments d ON c.department_id = d.id
            LEFT JOIN exeat2.faculties f ON d.faculty_id = f.id
            LEFT JOIN exeat2.course_studies cs ON c.course_study_id = cs.id
            WHERE c.id = ? AND c.deleted_at IS NULL
        ");
        $stmt->execute([$classId]);
        $class = $stmt->fetch();
        if (!$class) return null;

        // Auto-sync enrollments from course_regs so students registered for this
        // course offering are automatically enrolled without manual intervention.
        try {
            $this->syncClassEnrollments($classId, [
                'course_id' => $class['course_id'],
                'vu_session_id' => $class['vu_session_id'],
                'vu_semester_id' => $class['vu_semester_id'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            error_log("[ClassroomService] Auto-sync failed for class {$classId}: " . $e->getMessage());
        }

        $class['enrollments'] = $this->getClassEnrollments($classId);
        $class['staff'] = $this->getClassStaff($classId);

        return $class;
    }

    public function getClassEnrollments(int $classId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.* FROM lms_class_enrollments e
            WHERE e.class_id = ? ORDER BY e.student_name
        ");
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }

    public function getClassStaff(int $classId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.* FROM lms_class_staff s
            WHERE s.class_id = ? ORDER BY s.role, s.staff_name
        ");
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }

    public function getClassSessions(int $classId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM lms_class_sessions WHERE class_id = ? ORDER BY date DESC, start_time
        ");
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }

    /**
     * List classes with pagination and filters.
     */
    public function listClasses(int $page = 1, int $perPage = 20, ?string $search = null, ?string $status = null, ?int $departmentId = null, ?int $vuSessionId = null): array
    {
        $where = ['c.deleted_at IS NULL'];
        $params = [];

        if ($search) { $where[] = '(c.name LIKE ? OR c.code LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
        if ($status) { $where[] = 'c.status = ?'; $params[] = $status; }
        if ($departmentId) { $where[] = 'c.department_id = ?'; $params[] = $departmentId; }
        if ($vuSessionId) { $where[] = 'c.vu_session_id = ?'; $params[] = $vuSessionId; }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM lms_classes c WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch
        $stmt = $this->db->prepare("
            SELECT c.*, co.code as course_code, co.title as course_title,
                   vs.session as vu_session_name,
                   s.semester as semester_name,
                   d.name as department_name
            FROM lms_classes c
            LEFT JOIN exeat2.courses co ON c.course_id = co.id
            LEFT JOIN exeat2.vu_sessions vs ON c.vu_session_id = vs.id
            LEFT JOIN exeat2.vu_semesters vsem ON c.vu_semester_id = vsem.id
            LEFT JOIN exeat2.semesters s ON vsem.semester_id = s.id
            LEFT JOIN exeat2.departments d ON c.department_id = d.id
            WHERE {$whereClause}
            ORDER BY c.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Enroll a student into a class.
     */
    public function enrollStudent(int $classId, int $studentId, string $source = 'manual'): ?array
    {
        // Get student info from exeat2
        $stmt = Exeat2::conn()->prepare("
            SELECT st.id, st.fname, st.mname, st.lname, st.email, sa.matric_no
            FROM students st
            LEFT JOIN student_academics sa ON st.id = sa.student_id AND sa.status = 'active'
            WHERE st.id = ?
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        if (!$student) return null;

        $studentName = trim(($student['lname'] ?? '') . ' ' . ($student['fname'] ?? '') . ' ' . ($student['mname'] ?? ''));
        $lmsUserId = $this->findLmsUserIdByPortalStudentId($studentId);

        $this->db->prepare("
            INSERT INTO lms_class_enrollments (class_id, student_id, lms_user_id, matric_no, student_name, enrollment_source, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                lms_user_id = VALUES(lms_user_id),
                student_name = VALUES(student_name),
                status = 'active',
                enrollment_source = CASE WHEN enrollment_source = 'auto_sync' THEN 'auto_sync' ELSE VALUES(enrollment_source) END,
                updated_at = NOW()
        ")->execute([$classId, $studentId, $lmsUserId, $student['matric_no'] ?? '', $studentName, $source]);

        $this->updateClassCounts($classId);

        return [
            'student_id' => $studentId,
            'student_name' => $studentName,
            'matric_no' => $student['matric_no'] ?? '',
        ];
    }

    /**
     * Remove a student from a class (soft - sets status to dropped).
     */
    public function removeStudent(int $classId, int $studentId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE lms_class_enrollments SET status = 'dropped', updated_at = NOW()
            WHERE class_id = ? AND student_id = ?
        ");
        $stmt->execute([$classId, $studentId]);
        $this->updateClassCounts($classId);
        return $stmt->rowCount() > 0;
    }

    /**
     * Assign a staff member to a class.
     */
    public function assignStaff(int $classId, int $staffId, string $role = 'assistant'): ?array
    {
        $stmt = Exeat2::conn()->prepare("SELECT id, fname, mname, lname, email FROM staff WHERE id = ?");
        $stmt->execute([$staffId]);
        $staff = $stmt->fetch();

        if (!$staff) return null;

        $staffName = trim(($staff['lname'] ?? '') . ' ' . ($staff['fname'] ?? '') . ' ' . ($staff['mname'] ?? ''));
        $lmsUserId = $this->findLmsUserIdByPortalStaffId($staffId);

        $this->db->prepare("
            INSERT INTO lms_class_staff (class_id, staff_id, lms_user_id, staff_name, role, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                lms_user_id = VALUES(lms_user_id),
                staff_name = VALUES(staff_name),
                role = VALUES(role)
        ")->execute([$classId, $staffId, $lmsUserId, $staffName, $role]);

        $this->updateClassCounts($classId);

        return [
            'staff_id' => $staffId,
            'staff_name' => $staffName,
            'role' => $role,
        ];
    }

    /**
     * Remove a staff member from a class.
     */
    public function removeStaff(int $classId, int $staffId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM lms_class_staff WHERE class_id = ? AND staff_id = ?");
        $stmt->execute([$classId, $staffId]);
        $this->updateClassCounts($classId);
        return $stmt->rowCount() > 0;
    }

    /**
     * Bulk enroll students from course_regs into a class.
     */
    public function bulkEnrollFromCourseRegs(int $classId): int
    {
        $class = $this->db->prepare("SELECT * FROM lms_classes WHERE id = ? AND deleted_at IS NULL");
        $class->execute([$classId]);
        $c = $class->fetch();
        if (!$c) return 0;

        return $this->syncClassEnrollments($classId, [
            'course_id' => $c['course_id'],
            'vu_session_id' => $c['vu_session_id'],
            'vu_semester_id' => $c['vu_semester_id'],
        ]);
    }

    /**
     * Get classes assigned to a staff member (by lms_user_id).
     */
    public function getStaffClasses(int $lmsUserId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, cs.staff_id, cs.role as staff_role,
                   co.code as course_code, co.title as course_title,
                   vs.session as vu_session_name,
                   s.semester as semester_name
            FROM lms_class_staff cs
            JOIN lms_classes c ON cs.class_id = c.id
            LEFT JOIN exeat2.courses co ON c.course_id = co.id
            LEFT JOIN exeat2.vu_sessions vs ON c.vu_session_id = vs.id
            LEFT JOIN exeat2.vu_semesters vsem ON c.vu_semester_id = vsem.id
            LEFT JOIN exeat2.semesters s ON vsem.semester_id = s.id
            WHERE cs.lms_user_id = ? AND c.deleted_at IS NULL AND c.status = 'active'
            ORDER BY c.name
        ");
        $stmt->execute([$lmsUserId]);
        return $stmt->fetchAll();
    }

    /**
     * Get classes a student is enrolled in (by lms_user_id).
     */
    public function getStudentClasses(int $lmsUserId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, e.status as enrollment_status, e.student_id,
                   co.code as course_code, co.title as course_title,
                   vs.session as vu_session_name,
                   s.semester as semester_name
            FROM lms_class_enrollments e
            JOIN lms_classes c ON e.class_id = c.id
            LEFT JOIN exeat2.courses co ON c.course_id = co.id
            LEFT JOIN exeat2.vu_sessions vs ON c.vu_session_id = vs.id
            LEFT JOIN exeat2.vu_semesters vsem ON c.vu_semester_id = vsem.id
            LEFT JOIN exeat2.semesters s ON vsem.semester_id = s.id
            WHERE e.lms_user_id = ? AND e.status = 'active' AND c.deleted_at IS NULL
            ORDER BY c.name
        ");
        $stmt->execute([$lmsUserId]);
        return $stmt->fetchAll();
    }

    // ─── Private helpers ──────────────────────────────────

    private function updateClassCounts(int $classId): void
    {
        $this->db->prepare("
            UPDATE lms_classes SET
                total_students = (SELECT COUNT(*) FROM lms_class_enrollments WHERE class_id = ? AND status = 'active'),
                total_staff = (SELECT COUNT(*) FROM lms_class_staff WHERE class_id = ?)
            WHERE id = ?
        ")->execute([$classId, $classId, $classId]);
    }

    private function findLmsUserIdByPortalStudentId(int $studentId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM lms_users WHERE portal_user_id = ? AND portal_user_type = 'student' AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$studentId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    public function getClassHierarchy(?int $vuSessionId = null): array
    {
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        if ($vuSessionId) {
            $where[] = 'c.vu_session_id = ?';
            $params[] = $vuSessionId;
        }

        $whereClause = implode(' AND ', $where);
        $stmt = $this->db->prepare("
            SELECT c.*, co.code as course_code, co.title as course_title,
                   vs.session as vu_session_name,
                   s.semester as semester_name,
                   lvl.level as level_name
            FROM lms_classes c
            LEFT JOIN exeat2.courses co ON c.course_id = co.id
            LEFT JOIN exeat2.vu_sessions vs ON c.vu_session_id = vs.id
            LEFT JOIN exeat2.vu_semesters vsem ON c.vu_semester_id = vsem.id
            LEFT JOIN exeat2.semesters s ON vsem.semester_id = s.id
            LEFT JOIN exeat2.levels lvl ON c.level_id = lvl.id
            WHERE {$whereClause}
            ORDER BY vs.start_date DESC, vsem.semester_id, lvl.level
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $hierarchy = [];
        foreach ($rows as $row) {
            $sessKey = $row['vu_session_id'] ?? 0;
            $semKey = $row['vu_semester_id'] ?? 0;
            $lvlKey = $row['level_id'] ?? 0;

            if (!isset($hierarchy[$sessKey])) {
                $hierarchy[$sessKey] = [
                    'vu_session_id' => $sessKey,
                    'vu_session_name' => $row['vu_session_name'] ?? 'Unknown',
                    'semesters' => [],
                ];
            }

            $semName = $row['semester_name'] ?? 'Unknown';
            if (!isset($hierarchy[$sessKey]['semesters'][$semKey])) {
                $hierarchy[$sessKey]['semesters'][$semKey] = [
                    'vu_semester_id' => $semKey,
                    'semester_name' => $semName,
                    'levels' => [],
                ];
            }

            $lvlName = $row['level_name'] ?? 'Unknown';
            if (!isset($hierarchy[$sessKey]['semesters'][$semKey]['levels'][$lvlKey])) {
                $hierarchy[$sessKey]['semesters'][$semKey]['levels'][$lvlKey] = [
                    'level_id' => $lvlKey,
                    'level_name' => $lvlName,
                    'classes' => [],
                ];
            }

            $hierarchy[$sessKey]['semesters'][$semKey]['levels'][$lvlKey]['classes'][] = $row;
        }

        $result = [];
        foreach ($hierarchy as $sess) {
            $sess['semesters'] = array_values($sess['semesters']);
            foreach ($sess['semesters'] as &$sem) {
                $sem['levels'] = array_values($sem['levels']);
            }
            $result[] = $sess;
        }

        return $result;
    }

    private function findLmsUserIdByPortalStaffId(int $staffId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM lms_users WHERE portal_user_id = ? AND portal_user_type = 'staff' AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$staffId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }
}
