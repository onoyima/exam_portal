<?php
namespace Lms\Services;

use Lms\Core\Database;

class Exeat2
{
    private static bool $available = false;
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;
        $config = $GLOBALS['lms_config']['database']['exeat2'] ?? [];

        // Try primary (remote) exeat2 connection first
        if (!empty($config['host']) && !empty($config['database'])) {
            Database::configure('exeat2', $config);
            try {
                Database::connection('exeat2');
                self::$available = true;
                return;
            } catch (\Throwable $e) {
                // Fall through to local fallback
            }
        }

        // Fallback: local exeat2 copy
        $localConfig = [
            'driver'   => 'mysql',
            'host'     => 'localhost',
            'port'     => '3306',
            'database' => 'exeat2',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
        ];
        Database::configure('exeat2', $localConfig);
        try {
            Database::connection('exeat2');
            self::$available = true;
        } catch (\Throwable $e) {
            self::$available = false;
        }
    }

    public static function available(): bool
    {
        self::init();
        return self::$available;
    }

    public static function conn(): \PDO
    {
        self::init();
        return Database::connection('exeat2');
    }

    // ─── Faculties ────────────────────────────────────────
    public static function getFaculties(): array
    {
        return self::conn()->query("SELECT id, name, abb FROM faculties ORDER BY name")->fetchAll();
    }

    public static function getFaculty(int $id): ?array
    {
        $stmt = self::conn()->prepare("SELECT id, name, abb FROM faculties WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ─── Departments ──────────────────────────────────────
    public static function getDepartments(?int $facultyId = null): array
    {
        if ($facultyId) {
            $stmt = self::conn()->prepare("SELECT id, name, abb, faculty_id FROM departments WHERE faculty_id = ? ORDER BY name");
            $stmt->execute([$facultyId]);
        } else {
            $stmt = self::conn()->query("SELECT id, name, abb, faculty_id FROM departments ORDER BY name");
        }
        return $stmt->fetchAll();
    }

    public static function getDepartment(int $id): ?array
    {
        $stmt = self::conn()->prepare("SELECT id, name, abb, faculty_id FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ─── Course Studies (Programs of Study) ───────────────
    public static function getCourseStudies(?int $departmentId = null): array
    {
        if ($departmentId) {
            $stmt = self::conn()->prepare("SELECT cs.*, po.name as program_name FROM course_studies cs
                LEFT JOIN program_offereds po ON cs.program_offered_id = po.id
                WHERE cs.department_id = ? ORDER BY cs.name");
            $stmt->execute([$departmentId]);
        } else {
            $stmt = self::conn()->query("SELECT cs.*, po.name as program_name FROM course_studies cs
                LEFT JOIN program_offereds po ON cs.program_offered_id = po.id ORDER BY cs.name");
        }
        return $stmt->fetchAll();
    }

    public static function getCourseStudy(int $id): ?array
    {
        $stmt = self::conn()->prepare("SELECT cs.*, d.name as department_name, f.name as faculty_name
            FROM course_studies cs
            JOIN departments d ON cs.department_id = d.id
            JOIN faculties f ON d.faculty_id = f.id
            WHERE cs.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ─── Courses (Academic Course Definitions) ────────────
    public static function getCourses(?int $departmentId = null, ?int $courseStudyId = null, ?int $level = null): array
    {
        $sql = "SELECT c.*, d.name as department_name, cs.name as course_study_name, lvl.level as level_name
                FROM courses c
                JOIN departments d ON c.department_id = d.id
                LEFT JOIN course_studies cs ON c.course_study_id = cs.id
                LEFT JOIN levels lvl ON c.level = lvl.id
                WHERE 1=1";
        $params = [];

        if ($departmentId) { $sql .= " AND c.department_id = ?"; $params[] = $departmentId; }
        if ($courseStudyId) { $sql .= " AND c.course_study_id = ?"; $params[] = $courseStudyId; }
        if ($level) { $sql .= " AND c.level = ?"; $params[] = $level; }

        $sql .= " ORDER BY c.code";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getCourse(int $id): ?array
    {
        $stmt = self::conn()->prepare("SELECT c.*, d.name as department_name, cs.name as course_study_name,
                lvl.level as level_name, cc.name as category_name
            FROM courses c
            JOIN departments d ON c.department_id = d.id
            LEFT JOIN course_studies cs ON c.course_study_id = cs.id
            LEFT JOIN levels lvl ON c.level = lvl.id
            LEFT JOIN course_categories cc ON c.course_category_id = cc.id
            WHERE c.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getCourseByCode(string $code): ?array
    {
        $stmt = self::conn()->prepare("SELECT c.*, d.name as department_name FROM courses c
            JOIN departments d ON c.department_id = d.id
            WHERE c.code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    // ─── Vu Sessions (Academic Year Sessions) ─────────────
    public static function getVuSessions(): array
    {
        return self::conn()->query("SELECT id, session, start_date, end_date FROM vu_sessions ORDER BY start_date DESC")->fetchAll();
    }

    public static function getVuSession(int $id): ?array
    {
        $stmt = self::conn()->prepare("SELECT id, session, start_date, end_date FROM vu_sessions WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getActiveVuSession(): ?array
    {
        $stmt = self::conn()->query("SELECT id, session, start_date, end_date FROM vu_sessions
            WHERE start_date <= NOW() AND (end_date IS NULL OR end_date >= NOW()) ORDER BY start_date DESC LIMIT 1");
        return $stmt->fetch() ?: null;
    }

    // ─── Academic Sessions ────────────────────────────────
    public static function getAcademicSessions(?int $vuSessionId = null): array
    {
        if ($vuSessionId) {
            $stmt = self::conn()->prepare("SELECT asess.*, vs.session as vu_session_name, p.name as programme_name
                FROM academic_sessions asess
                JOIN vu_sessions vs ON asess.vu_session_id = vs.id
                LEFT JOIN programmes p ON asess.programme_id = p.id
                WHERE asess.vu_session_id = ? ORDER BY asess.id");
            $stmt->execute([$vuSessionId]);
        } else {
            $stmt = self::conn()->query("SELECT asess.*, vs.session as vu_session_name, p.name as programme_name
                FROM academic_sessions asess
                JOIN vu_sessions vs ON asess.vu_session_id = vs.id
                LEFT JOIN programmes p ON asess.programme_id = p.id
                ORDER BY vs.start_date DESC, asess.id");
        }
        return $stmt->fetchAll();
    }

    // ─── Vu Semesters ─────────────────────────────────────
    public static function getVuSemesters(?int $academicSessionId = null): array
    {
        if ($academicSessionId) {
            $stmt = self::conn()->prepare("SELECT vsem.*, s.semester as semester_name, vs.session as academic_session_name
                FROM vu_semesters vsem
                JOIN semesters s ON vsem.semester_id = s.id
                JOIN academic_sessions asess ON vsem.academic_session_id = asess.id
                JOIN vu_sessions vs ON asess.vu_session_id = vs.id
                WHERE vsem.academic_session_id = ? AND vsem.status = 1 ORDER BY vsem.semester_id");
            $stmt->execute([$academicSessionId]);
        } else {
            $stmt = self::conn()->query("SELECT vsem.*, s.semester as semester_name, vs.session as academic_session_name
                FROM vu_semesters vsem
                JOIN semesters s ON vsem.semester_id = s.id
                JOIN academic_sessions asess ON vsem.academic_session_id = asess.id
                JOIN vu_sessions vs ON asess.vu_session_id = vs.id
                WHERE vsem.status = 1
                ORDER BY asess.id, vsem.semester_id");
        }
        return $stmt->fetchAll();
    }

    // ─── Levels ───────────────────────────────────────────
    public static function getLevels(): array
    {
        return self::conn()->query("SELECT id, level FROM levels ORDER BY level")->fetchAll();
    }

    // ─── Student Academic Context ─────────────────────────
    public static function getStudentAcademic(int $studentId): ?array
    {
        $stmt = self::conn()->prepare("
            SELECT sa.*, cs.name as course_study_name, d.name as department_name,
                   f.name as faculty_name, lvl.level as current_level,
                   vs.session as vu_session_name, s.semester as semester_name
            FROM student_academics sa
            JOIN course_studies cs ON sa.course_study_id = cs.id
            JOIN departments d ON sa.department_id = d.id
            JOIN faculties f ON sa.faculty_id = f.id
            JOIN levels lvl ON sa.level = lvl.id
            LEFT JOIN vu_semesters vsem ON sa.vu_semester_id = vsem.id
            LEFT JOIN semesters s ON vsem.semester_id = s.id
            LEFT JOIN vu_sessions vs ON sa.academic_session_id = vs.id
            WHERE sa.student_id = ? AND sa.status = 1
            ORDER BY sa.id DESC LIMIT 1
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetch() ?: null;
    }

    public static function getStudentByMatricNo(string $matricNo): ?array
    {
        $stmt = self::conn()->prepare("
            SELECT s.id, s.fname, s.mname, s.lname, s.email, sa.matric_no,
                   sa.course_study_id, sa.level, sa.department_id, sa.faculty_id,
                   cs.name as course_study_name, d.name as department_name, f.name as faculty_name
            FROM students s
            JOIN student_academics sa ON s.id = sa.student_id
            JOIN course_studies cs ON sa.course_study_id = cs.id
            JOIN departments d ON sa.department_id = d.id
            JOIN faculties f ON sa.faculty_id = f.id
            WHERE sa.matric_no = ? AND s.status = 1 AND sa.status = 1
            LIMIT 1
        ");
        $stmt->execute([$matricNo]);
        return $stmt->fetch() ?: null;
    }

    // ─── Course Registrations ─────────────────────────────
    public static function getStudentCourseRegs(int $studentId, ?int $academicSessionId = null): array
    {
        try {
            $sql = "SELECT cr.*, c.code as course_code, c.title as course_title, c.credit_load,
                           cc.name as category_name, lvl.level as level_name
                    FROM course_regs cr
                    JOIN courses c ON cr.course_id = c.id
                    LEFT JOIN course_categories cc ON c.course_category_id = cc.id
                    LEFT JOIN levels lvl ON cr.level = lvl.id
                    WHERE cr.student_id = ? AND cr.is_course_reg = 2";
            $params = [$studentId];

            if ($academicSessionId) {
                $sql .= " AND cr.academic_session_id = ?";
                $params[] = $academicSessionId;
            }

            $sql .= " ORDER BY c.code";
            $stmt = self::conn()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }

    public static function getStudentCurrentCourses(int $studentId): array
    {
        try {
            $stmt = self::conn()->prepare("
                SELECT c.*, cr.ca_one, cr.ca_two, cr.ca_three, cr.examination, cr.total, cr.grade,
                       cr.academic_session_id, cr.vu_semester_id
                FROM course_regs cr
                JOIN courses c ON cr.course_id = c.id
                JOIN vu_semesters vsem ON cr.vu_semester_id = vsem.id
                JOIN academic_sessions asess ON cr.academic_session_id = asess.id
                JOIN vu_sessions vs ON cr.vu_session_id = vs.id
                WHERE cr.student_id = ? AND cr.is_course_reg = 2
                ORDER BY vs.start_date DESC, vsem.semester_id, c.code
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }

    // ─── Course Registrations with Results (Grouped by Session/Semester) ─
    public static function getStudentCourseRegsWithResults(int $studentId): array
    {
        try {
            $stmt = self::conn()->prepare("
                SELECT
                    cr.id as reg_id, cr.ca_one, cr.ca_two, cr.ca_three, cr.examination, cr.total, cr.grade,
                    cr.is_course_reg, cr.created_at as reg_date,
                    c.id as course_id, c.code as course_code, c.title as course_title, c.credit_load,
                    lvl.level as level_name,
                    vs.id as vu_session_id, vs.session as vu_session_name,
                    vs.start_date as session_start_date, vs.end_date as session_end_date,
                    s.id as semester_id, s.semester as semester_name,
                    cr.academic_session_id
                FROM course_regs cr
                JOIN courses c ON cr.course_id = c.id
                LEFT JOIN levels lvl ON cr.level = lvl.id
                LEFT JOIN vu_sessions vs ON cr.vu_session_id = vs.id
                LEFT JOIN vu_semesters vsem ON cr.vu_semester_id = vsem.id
                LEFT JOIN semesters s ON vsem.semester_id = s.id
                WHERE cr.student_id = ? AND cr.is_course_reg = 2
                ORDER BY vs.start_date DESC, s.id, c.code
            ");
            $stmt->execute([$studentId]);
            $rows = $stmt->fetchAll();
        } catch (\PDOException $e) {
            \Lms\Core\Logger::warning('course_regs table not available: ' . $e->getMessage());
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $key = ($row['vu_session_id'] ?? '0') . '-' . ($row['semester_id'] ?? '0');
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'vu_session_id' => $row['vu_session_id'],
                    'vu_session_name' => $row['vu_session_name'],
                    'semester_id' => $row['semester_id'],
                    'semester_name' => $row['semester_name'] ?? 'All Semesters',
                    'academic_session_id' => $row['academic_session_id'],
                    'session_start_date' => $row['session_start_date'],
                    'session_end_date' => $row['session_end_date'],
                    'courses' => [],
                ];
            }
            $course = $row;
            unset($course['vu_session_id'], $course['vu_session_name'], $course['semester_id'],
                  $course['semester_name'], $course['academic_session_id'],
                  $course['session_start_date'], $course['session_end_date']);
            $grouped[$key]['courses'][] = $course;
        }

        return array_values($grouped);
    }

    // ─── Assigned Courses (Staff/Instructor) ──────────────
    public static function getStaffAssignedCourses(int $staffId): array
    {
        $stmt = self::conn()->prepare("
            SELECT ca.id, ca.staff_id, ca.course_id, ca.department_id, ca.academic_session_id,
                   ca.vu_session_id, ca.vu_semester_id, ca.assign_type, ca.status,
                   c.code as course_code, c.title as course_title, c.credit_load,
                   d.name as department_name
            FROM course_assigneds ca
            JOIN courses c ON ca.course_id = c.id
            JOIN departments d ON ca.department_id = d.id
            WHERE ca.staff_id = ? AND ca.status = 1
            ORDER BY c.code
        ");
        $stmt->execute([$staffId]);
        return $stmt->fetchAll();
    }

    // ─── All Courses for Registration Context ─────────────
    public static function getDepartmentCourses(int $departmentId, ?int $level = null, ?int $semesterId = null): array
    {
        $sql = "SELECT c.*, cc.name as category_name FROM courses c
                LEFT JOIN course_categories cc ON c.course_category_id = cc.id
                WHERE c.department_id = ?";
        $params = [$departmentId];

        if ($level) { $sql .= " AND c.level = ?"; $params[] = $level; }
        if ($semesterId) {
            $sql .= " AND (c.semester_offered = (SELECT semester FROM semesters WHERE id = ?) OR c.semester_offered IS NULL)";
            $params[] = $semesterId;
        }

        $sql .= " ORDER BY c.code";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  ADMIN CRUD METHODS
    // ═══════════════════════════════════════════════════════════════════

    // ─── Faculties CRUD ──────────────────────────────────
    public static function createFaculty(string $name, string $abb): array
    {
        $stmt = self::conn()->prepare("INSERT INTO faculties (name, abb) VALUES (?, ?)");
        $stmt->execute([$name, $abb]);
        return self::getFaculty((int) self::conn()->lastInsertId());
    }

    public static function updateFaculty(int $id, string $name, string $abb): array
    {
        $stmt = self::conn()->prepare("UPDATE faculties SET name = ?, abb = ? WHERE id = ?");
        $stmt->execute([$name, $abb, $id]);
        return self::getFaculty($id);
    }

    public static function deleteFaculty(int $id): void
    {
        self::conn()->prepare("DELETE FROM faculties WHERE id = ?")->execute([$id]);
    }

    // ─── Departments CRUD ────────────────────────────────
    public static function createDepartment(string $name, string $abb, int $facultyId): array
    {
        $stmt = self::conn()->prepare("INSERT INTO departments (name, abb, faculty_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $abb, $facultyId]);
        return self::getDepartment((int) self::conn()->lastInsertId());
    }

    public static function updateDepartment(int $id, string $name, string $abb, int $facultyId): array
    {
        $stmt = self::conn()->prepare("UPDATE departments SET name = ?, abb = ?, faculty_id = ? WHERE id = ?");
        $stmt->execute([$name, $abb, $facultyId, $id]);
        return self::getDepartment($id);
    }

    public static function deleteDepartment(int $id): void
    {
        self::conn()->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
    }

    // ─── Course Studies CRUD ─────────────────────────────
    public static function createCourseStudy(string $name, string $abb, int $departmentId, ?int $programOfferedId = null): array
    {
        $stmt = self::conn()->prepare("INSERT INTO course_studies (name, abb, department_id, program_offered_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $abb, $departmentId, $programOfferedId]);
        return self::getCourseStudy((int) self::conn()->lastInsertId());
    }

    public static function updateCourseStudy(int $id, string $name, string $abb, int $departmentId, ?int $programOfferedId = null): array
    {
        $stmt = self::conn()->prepare("UPDATE course_studies SET name = ?, abb = ?, department_id = ?, program_offered_id = ? WHERE id = ?");
        $stmt->execute([$name, $abb, $departmentId, $programOfferedId, $id]);
        return self::getCourseStudy($id);
    }

    public static function deleteCourseStudy(int $id): void
    {
        self::conn()->prepare("DELETE FROM course_studies WHERE id = ?")->execute([$id]);
    }

    // ─── Courses CRUD ────────────────────────────────────
    public static function createCourse(array $data): array
    {
        $fields = ['code', 'title', 'department_id', 'course_study_id', 'credit_load', 'level', 'course_category_id', 'semester_offered', 'programme_id'];
        $cols = [];
        $vals = [];
        $placeholders = [];
        foreach ($fields as $f) {
            if (isset($data[$f]) && $data[$f] !== '' && $data[$f] !== null) {
                $cols[] = $f;
                $vals[] = $data[$f];
                $placeholders[] = '?';
            }
        }
        $sql = "INSERT INTO courses (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($vals);
        return self::getCourse((int) self::conn()->lastInsertId());
    }

    public static function updateCourse(int $id, array $data): array
    {
        $allowed = ['code', 'title', 'department_id', 'course_study_id', 'credit_load', 'level', 'course_category_id', 'semester_offered', 'programme_id'];
        $sets = [];
        $vals = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "$f = ?";
                $vals[] = $data[$f];
            }
        }
        if (empty($sets)) return self::getCourse($id);
        $vals[] = $id;
        $stmt = self::conn()->prepare("UPDATE courses SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($vals);
        return self::getCourse($id);
    }

    public static function deleteCourse(int $id): void
    {
        self::conn()->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
    }

    // ─── Levels CRUD ─────────────────────────────────────
    public static function createLevel(string $level): array
    {
        $stmt = self::conn()->prepare("INSERT INTO levels (level) VALUES (?)");
        $stmt->execute([$level]);
        $id = (int) self::conn()->lastInsertId();
        $stmt = self::conn()->prepare("SELECT id, level FROM levels WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    public static function updateLevel(int $id, string $level): array
    {
        $stmt = self::conn()->prepare("UPDATE levels SET level = ? WHERE id = ?");
        $stmt->execute([$level, $id]);
        $stmt = self::conn()->prepare("SELECT id, level FROM levels WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    public static function deleteLevel(int $id): void
    {
        self::conn()->prepare("DELETE FROM levels WHERE id = ?")->execute([$id]);
    }

    // ─── Vu Sessions CRUD ────────────────────────────────
    public static function createVuSession(string $session, string $startDate, ?string $endDate = null): array
    {
        $stmt = self::conn()->prepare("INSERT INTO vu_sessions (session, start_date, end_date) VALUES (?, ?, ?)");
        $stmt->execute([$session, $startDate, $endDate]);
        return self::getVuSession((int) self::conn()->lastInsertId());
    }

    public static function updateVuSession(int $id, string $session, string $startDate, ?string $endDate = null): array
    {
        $stmt = self::conn()->prepare("UPDATE vu_sessions SET session = ?, start_date = ?, end_date = ? WHERE id = ?");
        $stmt->execute([$session, $startDate, $endDate, $id]);
        return self::getVuSession($id);
    }

    public static function deleteVuSession(int $id): void
    {
        self::conn()->prepare("DELETE FROM vu_sessions WHERE id = ?")->execute([$id]);
    }

    // ─── Academic Sessions CRUD ──────────────────────────
    public static function createAcademicSession(int $vuSessionId, ?int $programmeId = null, ?string $batch = null): array
    {
        $stmt = self::conn()->prepare("INSERT INTO academic_sessions (vu_session_id, programme_id, batch) VALUES (?, ?, ?)");
        $stmt->execute([$vuSessionId, $programmeId, $batch]);
        $id = (int) self::conn()->lastInsertId();
        $rows = self::getAcademicSessions();
        foreach ($rows as $r) {
            if ((int)$r['id'] === $id) return $r;
        }
        return [];
    }

    public static function updateAcademicSession(int $id, int $vuSessionId, ?int $programmeId = null, ?string $batch = null): array
    {
        $stmt = self::conn()->prepare("UPDATE academic_sessions SET vu_session_id = ?, programme_id = ?, batch = ? WHERE id = ?");
        $stmt->execute([$vuSessionId, $programmeId, $batch, $id]);
        $rows = self::getAcademicSessions();
        foreach ($rows as $r) {
            if ((int)$r['id'] === $id) return $r;
        }
        return [];
    }

    public static function deleteAcademicSession(int $id): void
    {
        self::conn()->prepare("DELETE FROM academic_sessions WHERE id = ?")->execute([$id]);
    }

    // ─── Vu Semesters CRUD ───────────────────────────────
    public static function createVuSemester(int $academicSessionId, int $semesterId): array
    {
        $stmt = self::conn()->prepare("INSERT INTO vu_semesters (academic_session_id, semester_id) VALUES (?, ?)");
        $stmt->execute([$academicSessionId, $semesterId]);
        $id = (int) self::conn()->lastInsertId();
        $rows = self::getVuSemesters();
        foreach ($rows as $r) {
            if ((int)$r['id'] === $id) return $r;
        }
        return [];
    }

    public static function updateVuSemester(int $id, int $academicSessionId, int $semesterId): array
    {
        $stmt = self::conn()->prepare("UPDATE vu_semesters SET academic_session_id = ?, semester_id = ? WHERE id = ?");
        $stmt->execute([$academicSessionId, $semesterId, $id]);
        $rows = self::getVuSemesters();
        foreach ($rows as $r) {
            if ((int)$r['id'] === $id) return $r;
        }
        return [];
    }

    public static function deleteVuSemester(int $id): void
    {
        self::conn()->prepare("DELETE FROM vu_semesters WHERE id = ?")->execute([$id]);
    }

    // ─── Semesters CRUD ──────────────────────────────────
    public static function createSemester(string $semester): array
    {
        $stmt = self::conn()->prepare("INSERT INTO semesters (semester) VALUES (?)");
        $stmt->execute([$semester]);
        $id = (int) self::conn()->lastInsertId();
        $stmt = self::conn()->prepare("SELECT id, semester FROM semesters WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    public static function updateSemester(int $id, string $semester): array
    {
        $stmt = self::conn()->prepare("UPDATE semesters SET semester = ? WHERE id = ?");
        $stmt->execute([$semester, $id]);
        $stmt = self::conn()->prepare("SELECT id, semester FROM semesters WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    public static function deleteSemester(int $id): void
    {
        self::conn()->prepare("DELETE FROM semesters WHERE id = ?")->execute([$id]);
    }

    // ─── Programmes CRUD ─────────────────────────────────
    public static function getProgrammes(): array
    {
        return self::conn()->query("SELECT id, name FROM programmes ORDER BY name")->fetchAll();
    }

    public static function getProgramme(int $id): ?array
    {
        $stmt = self::conn()->prepare("SELECT id, name FROM programmes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createProgramme(string $name): array
    {
        $stmt = self::conn()->prepare("INSERT INTO programmes (name) VALUES (?)");
        $stmt->execute([$name]);
        $id = (int) self::conn()->lastInsertId();
        return self::getProgramme($id);
    }

    public static function updateProgramme(int $id, string $name): array
    {
        $stmt = self::conn()->prepare("UPDATE programmes SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        return self::getProgramme($id);
    }

    public static function deleteProgramme(int $id): void
    {
        self::conn()->prepare("DELETE FROM programmes WHERE id = ?")->execute([$id]);
    }

    // ─── Program Offered CRUD ────────────────────────────
    public static function getProgramsOffered(): array
    {
        return self::conn()->query("SELECT po.*, pc.name as certification_name FROM program_offereds po
            LEFT JOIN programme_certifications pc ON po.programme_certification_id = pc.id ORDER BY po.name")->fetchAll();
    }

    public static function getProgramOffered(int $id): ?array
    {
        $stmt = self::conn()->prepare("SELECT po.*, pc.name as certification_name FROM program_offereds po
            LEFT JOIN programme_certifications pc ON po.programme_certification_id = pc.id WHERE po.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createProgramOffered(string $name, string $abb, ?int $certificationId = null): array
    {
        $stmt = self::conn()->prepare("INSERT INTO program_offereds (name, abb, programme_certification_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $abb, $certificationId]);
        return self::getProgramOffered((int) self::conn()->lastInsertId());
    }

    public static function updateProgramOffered(int $id, string $name, string $abb, ?int $certificationId = null): array
    {
        $stmt = self::conn()->prepare("UPDATE program_offereds SET name = ?, abb = ?, programme_certification_id = ? WHERE id = ?");
        $stmt->execute([$name, $abb, $certificationId, $id]);
        return self::getProgramOffered($id);
    }

    public static function deleteProgramOffered(int $id): void
    {
        self::conn()->prepare("DELETE FROM program_offereds WHERE id = ?")->execute([$id]);
    }

    // ─── Students CRUD / Search ──────────────────────────
    public static function searchStudents(string $search, int $limit = 20): array
    {
        $s = "%$search%";
        $stmt = self::conn()->prepare("
            SELECT s.id, s.fname, s.mname, s.lname, s.email, s.phone, sa.matric_no,
                   sa.level, sa.course_study_id, sa.department_id, sa.faculty_id,
                   cs.name as course_study_name, d.name as department_name
            FROM students s
            LEFT JOIN student_academics sa ON s.id = sa.student_id AND sa.status = 1
            LEFT JOIN course_studies cs ON sa.course_study_id = cs.id
            LEFT JOIN departments d ON sa.department_id = d.id
            WHERE s.fname LIKE ? OR s.lname LIKE ? OR s.email LIKE ? OR sa.matric_no LIKE ?
            LIMIT ?
        ");
        $stmt->execute([$s, $s, $s, $s, $limit]);
        return $stmt->fetchAll();
    }

    public static function getAllStudents(int $page = 1, int $perPage = 20, ?int $departmentId = null, ?int $level = null): array
    {
        $sql = "SELECT s.id, s.fname, s.mname, s.lname, s.email, s.phone, s.status,
                       sa.matric_no, sa.level, cs.name as course_study_name, d.name as department_name
                FROM students s
                LEFT JOIN student_academics sa ON s.id = sa.student_id AND sa.status = 1
                LEFT JOIN course_studies cs ON sa.course_study_id = cs.id
                LEFT JOIN departments d ON sa.department_id = d.id
                WHERE 1=1";
        $params = [];
        if ($departmentId) { $sql .= " AND sa.department_id = ?"; $params[] = $departmentId; }
        if ($level) { $sql .= " AND sa.level = ?"; $params[] = $level; }
        $sql .= " ORDER BY s.lname, s.fname LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ─── Staff CRUD / Search ─────────────────────────────
    public static function searchStaff(string $search, int $limit = 20): array
    {
        $s = "%$search%";
        $stmt = self::conn()->prepare("
            SELECT s.id, s.fname, s.mname, s.lname, s.email, s.phone,
                   swp.staff_no, swp.department_id, d.name as department_name
            FROM staff s
            LEFT JOIN staff_work_profiles swp ON s.id = swp.staff_id
            LEFT JOIN departments d ON swp.department_id = d.id
            WHERE s.fname LIKE ? OR s.lname LIKE ? OR s.email LIKE ? OR swp.staff_no LIKE ?
            LIMIT ?
        ");
        $stmt->execute([$s, $s, $s, $s, $limit]);
        return $stmt->fetchAll();
    }

    public static function getAllStaff(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = self::conn()->prepare("
            SELECT s.id, s.fname, s.mname, s.lname, s.email, s.phone, s.status,
                   swp.staff_no, swp.department_id, d.name as department_name,
                   sp.name as position_name
            FROM staff s
            LEFT JOIN staff_work_profiles swp ON s.id = swp.staff_id
            LEFT JOIN departments d ON swp.department_id = d.id
            LEFT JOIN staff_positions sp ON swp.staff_position_id = sp.id
            ORDER BY s.lname, s.fname LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        return $stmt->fetchAll();
    }

    // ─── Course Reg Management ───────────────────────────
    public static function getAllCourseRegs(int $page = 1, int $perPage = 20, ?int $academicSessionId = null, ?int $departmentId = null, ?int $courseStudyId = null): array
    {
        $sql = "SELECT cr.*, c.code as course_code, c.title as course_title, c.credit_load,
                       s.fname, s.lname, sa.matric_no,
                       lvl.level as level_name
                FROM course_regs cr
                JOIN courses c ON cr.course_id = c.id
                JOIN students s ON cr.student_id = s.id
                LEFT JOIN student_academics sa ON s.id = sa.student_id AND sa.status = 1
                LEFT JOIN levels lvl ON cr.level = lvl.id
                WHERE cr.is_course_reg = 2";
        $params = [];
        if ($academicSessionId) { $sql .= " AND cr.academic_session_id = ?"; $params[] = $academicSessionId; }
        if ($departmentId) { $sql .= " AND cr.department_id = ?"; $params[] = $departmentId; }
        if ($courseStudyId) { $sql .= " AND cr.course_study_id = ?"; $params[] = $courseStudyId; }
        $sql .= " ORDER BY c.code LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function updateCourseRegScores(int $regId, ?float $caOne = null, ?float $caTwo = null, ?float $caThree = null, ?float $examination = null, ?float $total = null, ?string $grade = null): array
    {
        $sets = [];
        $vals = [];
        if ($caOne !== null) { $sets[] = 'ca_one = ?'; $vals[] = $caOne; }
        if ($caTwo !== null) { $sets[] = 'ca_two = ?'; $vals[] = $caTwo; }
        if ($caThree !== null) { $sets[] = 'ca_three = ?'; $vals[] = $caThree; }
        if ($examination !== null) { $sets[] = 'examination = ?'; $vals[] = $examination; }
        if ($total !== null) { $sets[] = 'total = ?'; $vals[] = $total; }
        if ($grade !== null) { $sets[] = 'grade = ?'; $vals[] = $grade; }
        if (empty($sets)) return [];
        $vals[] = $regId;
        $stmt = self::conn()->prepare("UPDATE course_regs SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($vals);
        $stmt = self::conn()->prepare("SELECT * FROM course_regs WHERE id = ?");
        $stmt->execute([$regId]);
        return $stmt->fetch() ?: [];
    }

    // ─── Course Categories ───────────────────────────────
    public static function getCourseCategories(): array
    {
        return self::conn()->query("SELECT id, name FROM course_categories ORDER BY name")->fetchAll();
    }

    public static function createCourseCategory(string $name): array
    {
        $stmt = self::conn()->prepare("INSERT INTO course_categories (name) VALUES (?)");
        $stmt->execute([$name]);
        $id = (int) self::conn()->lastInsertId();
        $stmt = self::conn()->prepare("SELECT id, name FROM course_categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    public static function updateCourseCategory(int $id, string $name): array
    {
        $stmt = self::conn()->prepare("UPDATE course_categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        $stmt = self::conn()->prepare("SELECT id, name FROM course_categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    public static function deleteCourseCategory(int $id): void
    {
        self::conn()->prepare("DELETE FROM course_categories WHERE id = ?")->execute([$id]);
    }

    // ─── Grading / Assessment ────────────────────────────
    public static function getGradingCategories(): array
    {
        return self::conn()->query("SELECT gc.*, cs.name as course_study_name
            FROM grading_categories gc
            LEFT JOIN course_studies cs ON gc.course_study_id = cs.id
            ORDER BY cs.name, gc.name")->fetchAll();
    }

    public static function createGradingCategory(string $name, ?int $courseStudyId = null): array
    {
        $stmt = self::conn()->prepare("INSERT INTO grading_categories (name, course_study_id) VALUES (?, ?)");
        $stmt->execute([$name, $courseStudyId]);
        $id = (int) self::conn()->lastInsertId();
        $rows = self::getGradingCategories();
        foreach ($rows as $r) {
            if ((int)$r['id'] === $id) return $r;
        }
        return [];
    }

    public static function updateGradingCategory(int $id, string $name, ?int $courseStudyId = null): array
    {
        $stmt = self::conn()->prepare("UPDATE grading_categories SET name = ?, course_study_id = ? WHERE id = ?");
        $stmt->execute([$name, $courseStudyId, $id]);
        $rows = self::getGradingCategories();
        foreach ($rows as $r) {
            if ((int)$r['id'] === $id) return $r;
        }
        return [];
    }

    public static function deleteGradingCategory(int $id): void
    {
        self::conn()->prepare("DELETE FROM grading_categories WHERE id = ?")->execute([$id]);
    }

    // ─── Course Grading Systems ──────────────────────────
    public static function getCourseGradingSystems(?int $courseId = null): array
    {
        if ($courseId) {
            $stmt = self::conn()->prepare("SELECT cgs.*, gc.name as category_name, c.code as course_code
                FROM course_grading_systems cgs
                JOIN grading_categories gc ON cgs.grading_category_id = gc.id
                JOIN courses c ON cgs.course_id = c.id
                WHERE cgs.course_id = ? ORDER BY gc.name");
            $stmt->execute([$courseId]);
        } else {
            $stmt = self::conn()->query("SELECT cgs.*, gc.name as category_name, c.code as course_code
                FROM course_grading_systems cgs
                JOIN grading_categories gc ON cgs.grading_category_id = gc.id
                JOIN courses c ON cgs.course_id = c.id ORDER BY c.code, gc.name");
        }
        return $stmt->fetchAll();
    }

    // ─── Programme Certifications ────────────────────────
    public static function getProgrammeCertifications(): array
    {
        return self::conn()->query("SELECT id, name FROM programme_certifications ORDER BY name")->fetchAll();
    }

    // ─── Dashboard Stats ─────────────────────────────────
    public static function getDashboardStats(): array
    {
        $totalStudents = (int) self::conn()->query("SELECT COUNT(*) FROM students")->fetchColumn();
        $totalStaff = (int) self::conn()->query("SELECT COUNT(*) FROM staff")->fetchColumn();
        $totalCourses = (int) self::conn()->query("SELECT COUNT(*) FROM courses")->fetchColumn();
        $totalFaculties = (int) self::conn()->query("SELECT COUNT(*) FROM faculties")->fetchColumn();
        $totalDepartments = (int) self::conn()->query("SELECT COUNT(*) FROM departments")->fetchColumn();
        $totalCourseRegs = (int) self::conn()->query("SELECT COUNT(*) FROM course_regs WHERE is_course_reg = 2")->fetchColumn();
        $totalSessions = (int) self::conn()->query("SELECT COUNT(*) FROM vu_sessions")->fetchColumn();
        return compact('totalStudents', 'totalStaff', 'totalCourses', 'totalFaculties', 'totalDepartments', 'totalCourseRegs', 'totalSessions');
    }

    // ─── Class Sync Queries ──────────────────────────────

    /** Get unique course offerings from course_regs for a given session */
    public static function getCourseRegClassOfferings(?int $vuSessionId = null, ?int $vuSemesterId = null): array
    {
        try {
            $sql = "SELECT DISTINCT
                        cr.course_id, cr.vu_session_id, cr.vu_semester_id, cr.academic_session_id,
                        cr.department_id, cr.course_study_id, cr.level,
                        c.code as course_code, c.title as course_title, c.credit_load,
                        vs.session as vu_session_name,
                        s.semester as semester_name
                    FROM course_regs cr
                    JOIN courses c ON cr.course_id = c.id
                    LEFT JOIN vu_sessions vs ON cr.vu_session_id = vs.id
                    LEFT JOIN vu_semesters vsem ON cr.vu_semester_id = vsem.id
                    LEFT JOIN semesters s ON vsem.semester_id = s.id
                    WHERE cr.is_course_reg = 2";
            $params = [];
            if ($vuSessionId) { $sql .= " AND cr.vu_session_id = ?"; $params[] = $vuSessionId; }
            if ($vuSemesterId) { $sql .= " AND cr.vu_semester_id = ?"; $params[] = $vuSemesterId; }
            $sql .= " ORDER BY vs.start_date DESC, s.id, c.code";
            $stmt = self::conn()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            \Lms\Core\Logger::warning('getCourseRegClassOfferings failed: ' . $e->getMessage());
            return [];
        }
    }

    /** Get all students registered for a specific course offering */
    public static function getCourseRegStudentsForClass(int $courseId, int $vuSessionId, int $vuSemesterId): array
    {
        try {
            $stmt = self::conn()->prepare("
                SELECT
                    cr.id as reg_id, cr.student_id, cr.ca_one, cr.ca_two, cr.ca_three,
                    cr.examination, cr.total, cr.grade, cr.is_course_reg,
                    st.fname, st.mname, st.lname, st.email,
                    sa.matric_no, sa.level as academic_level,
                    sa.department_id, sa.course_study_id
                FROM course_regs cr
                JOIN students st ON cr.student_id = st.id
                LEFT JOIN student_academics sa ON st.id = sa.student_id AND sa.status = 'active'
                WHERE cr.course_id = ? AND cr.vu_session_id = ? AND cr.vu_semester_id = ?
                    AND cr.is_course_reg = 2
                ORDER BY st.lname, st.fname
            ");
            $stmt->execute([$courseId, $vuSessionId, $vuSemesterId]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            \Lms\Core\Logger::warning('getCourseRegStudentsForClass failed: ' . $e->getMessage());
            return [];
        }
    }

    /** Get staff assigned to teach a specific course offering */
    public static function getAssignedCourseStaffForClass(int $courseId, int $vuSessionId, int $vuSemesterId): array
    {
        try {
            $stmt = self::conn()->prepare("
                SELECT DISTINCT ca.staff_id, ca.id as assignment_id, ca.department_id,
                    ca.assign_type, ca.status as assign_status,
                    st.fname, st.mname, st.lname, st.email,
                    swp.department_id as swp_dept_id, swp.faculty_id
                FROM course_assigneds ca
                JOIN staff st ON ca.staff_id = st.id
                LEFT JOIN staff_work_profiles swp ON st.id = swp.staff_id
                WHERE ca.course_id = ? AND ca.vu_session_id = ? AND ca.vu_semester_id = ?
                    AND ca.status IN (1, 2)
                ORDER BY st.lname, st.fname
            ");
            $stmt->execute([$courseId, $vuSessionId, $vuSemesterId]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            \Lms\Core\Logger::warning('getAssignedCourseStaffForClass failed: ' . $e->getMessage());
            return [];
        }
    }

    /** Get all active course registrations in a session/semester (for bulk sync) */
    public static function getAllCourseRegsInSession(?int $vuSessionId = null, ?int $vuSemesterId = null): array
    {
        try {
            $sql = "SELECT cr.*, c.code as course_code, c.title as course_title, c.credit_load,
                        st.fname, st.mname, st.lname, st.email, sa.matric_no
                    FROM course_regs cr
                    JOIN courses c ON cr.course_id = c.id
                    JOIN students st ON cr.student_id = st.id
                    LEFT JOIN student_academics sa ON st.id = sa.student_id AND sa.status = 'active'
                    WHERE cr.is_course_reg = 2";
            $params = [];
            if ($vuSessionId) { $sql .= " AND cr.vu_session_id = ?"; $params[] = $vuSessionId; }
            if ($vuSemesterId) { $sql .= " AND cr.vu_semester_id = ?"; $params[] = $vuSemesterId; }
            $sql .= " ORDER BY c.code, st.lname";
            $stmt = self::conn()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            \Lms\Core\Logger::warning('getAllCourseRegsInSession failed: ' . $e->getMessage());
            return [];
        }
    }

    /** Get all active staff-course assignments in a session/semester */
    public static function getAllAssignedCoursesInSession(?int $vuSessionId = null, ?int $vuSemesterId = null): array
    {
        try {
            $sql = "SELECT ca.*, c.code as course_code, c.title as course_title,
                        st.fname, st.mname, st.lname, st.email
                    FROM course_assigneds ca
                    JOIN courses c ON ca.course_id = c.id
                    JOIN staff st ON ca.staff_id = st.id
                    WHERE ca.status IN (1, 2)";
            $params = [];
            if ($vuSessionId) { $sql .= " AND ca.vu_session_id = ?"; $params[] = $vuSessionId; }
            if ($vuSemesterId) { $sql .= " AND ca.vu_semester_id = ?"; $params[] = $vuSemesterId; }
            $sql .= " ORDER BY c.code, st.lname";
            $stmt = self::conn()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            \Lms\Core\Logger::warning('getAllAssignedCoursesInSession failed: ' . $e->getMessage());
            return [];
        }
    }

    /** Get course_assigneds with their assigned_programs for a course offering */
    public static function getCourseAssignedsWithPrograms(int $courseId, int $vuSessionId, int $vuSemesterId): array
    {
        try {
            $stmt = self::conn()->prepare("
                SELECT ca.*, ap.id as program_id, ap.course_study_id, ap.department_id as prog_dept_id,
                       ap.level as prog_level, ap.student_ids, ap.status as prog_status,
                       c.code as course_code, c.title as course_title, c.credit_load,
                       st.fname, st.mname, st.lname, st.email,
                       cs.name as course_study_name
                FROM course_assigneds ca
                LEFT JOIN assigned_programs ap ON ca.id = ap.course_assigned_id
                JOIN courses c ON ca.course_id = c.id
                JOIN staff st ON ca.staff_id = st.id
                LEFT JOIN course_studies cs ON ap.course_study_id = cs.id
                WHERE ca.course_id = ? AND ca.vu_session_id = ? AND ca.vu_semester_id = ?
                    AND ca.status IN (1, 2)
                ORDER BY st.lname, st.fname
            ");
            $stmt->execute([$courseId, $vuSessionId, $vuSemesterId]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            \Lms\Core\Logger::warning('getCourseAssignedsWithPrograms failed: ' . $e->getMessage());
            return [];
        }
    }

    /** Get all lecture venues */
    public static function getLectureVenues(): array
    {
        try {
            return self::conn()->query("SELECT id, name, capacity, coordinate, attitude FROM lecture_venues ORDER BY name")->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }

    /** Get SBC groups for a vu_semester (can be used for class grouping) */
    public static function getSbcGroups(int $vuSemesterId): array
    {
        try {
            $stmt = self::conn()->prepare("
                SELECT sg.*, st.fname, st.mname, st.lname, st.email
                FROM sbc_groups sg
                LEFT JOIN staff st ON sg.staff_id = st.id
                WHERE sg.vu_semester_id = ?
                ORDER BY st.lname
            ");
            $stmt->execute([$vuSemesterId]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }

    /** Get course_regs filtered by offer_method (1=general, 2=departments, 3=groups) */
    public static function getCourseRegsByOfferMethod(int $courseId, int $vuSessionId, int $vuSemesterId, int $offerMethod): array
    {
        try {
            $stmt = self::conn()->prepare("
                SELECT cr.*, st.fname, st.mname, st.lname, st.email, sa.matric_no,
                       sa.course_study_id, sa.department_id, sa.level as academic_level
                FROM course_regs cr
                JOIN students st ON cr.student_id = st.id
                LEFT JOIN student_academics sa ON st.id = sa.student_id AND sa.status = 'active'
                WHERE cr.course_id = ? AND cr.vu_session_id = ? AND cr.vu_semester_id = ?
                    AND cr.is_course_reg = 2 AND cr.offer_method = ?
                ORDER BY st.lname, st.fname
            ");
            $stmt->execute([$courseId, $vuSessionId, $vuSemesterId, $offerMethod]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }
}
