<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Core\Logger;
use Lms\Services\Exeat2;
use Lms\Models\SystemSetting;
use Lms\Models\FeatureFlag;
use Lms\Models\User;
use Lms\Models\Role;
use Lms\Models\ActivityLog;

class AdminController extends Controller
{
    public function users(Request $req, Response $res): void
    {
        $this->paginatedQuery(function ($page, $perPage) use ($req) {
            $db = User::db();
            $params = [];
            $joins = '';
            $wheres = ['u.deleted_at IS NULL'];

            if ($req->query('search')) {
                $s = '%' . $req->query('search') . '%';
                $wheres[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
                $params = array_merge($params, [$s, $s, $s]);
            }
            if ($req->query('role')) {
                $joins = 'LEFT JOIN lms_user_roles ur ON u.id = ur.user_id LEFT JOIN lms_roles r ON ur.role_id = r.id';
                $wheres[] = 'r.slug = ?';
                $params[] = $req->query('role');
            }
            if ($req->query('is_active') !== null) {
                $wheres[] = 'u.is_active = ?';
                $params[] = (int) $req->query('is_active');
            }

            $whereClause = implode(' AND ', $wheres);

            $countSql = "SELECT COUNT(DISTINCT u.id) FROM lms_users u $joins WHERE $whereClause";
            $stmt = $db->prepare($countSql);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            $dataSql = "SELECT u.* FROM lms_users u $joins WHERE $whereClause ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
            $dataParams = $params;
            $dataParams[] = $perPage;
            $dataParams[] = ($page - 1) * $perPage;
            $stmt = $db->prepare($dataSql);
            $stmt->execute($dataParams);
            $rows = $stmt->fetchAll();

            $items = array_map(fn($row) => User::hydrate($row), $rows);
            return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        });
    }

    public function updateUser(Request $req, Response $res, string $id): void
    {
        $user = User::find((int) $id);
        if (!$user) $res->notFound('User not found');

        $data = $this->validate($req->body(), [
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
            'email' => 'email',
            'status' => 'in:active,inactive,suspended',
            'is_active' => 'boolean',
        ]);

        foreach ($data as $key => $value) {
            $user->$key = $value;
        }
        $user->save();

        Logger::info('User updated by admin', ['admin_id' => $this->userId(), 'user_id' => $id, 'changes' => array_keys($data)]);
        ActivityLog::log('user.updated', $this->userId(), null, 'user', (int) $id, ['fields' => array_keys($data)]);
        $res->success(['user' => $user->toArray()]);
    }

    public function assignRole(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'user_id' => 'required|integer|exists:lms_users,id',
            'role_id' => 'required|integer|exists:lms_roles,id',
        ]);

        $user = User::find((int) $data['user_id']);
        if (!$user) $res->notFound('User not found');
        $role = Role::find((int) $data['role_id']);
        if (!$role) $res->notFound('Role not found');

        $db = User::db();
        $stmt = $db->prepare("INSERT IGNORE INTO lms_user_roles (user_id, role_id) VALUES (?, ?)");
        $stmt->execute([$data['user_id'], $data['role_id']]);

        Logger::info('Role assigned', ['admin_id' => $this->userId(), 'user_id' => $data['user_id'], 'role_id' => $data['role_id']]);
        ActivityLog::log('role.assigned', $this->userId(), null, 'user_role', $data['user_id'], ['role_id' => $data['role_id']]);
        $res->success(['message' => 'Role assigned']);
    }

    public function removeRole(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'user_id' => 'required|integer',
            'role_id' => 'required|integer',
        ]);

        $db = User::db();
        $stmt = $db->prepare("DELETE FROM lms_user_roles WHERE user_id = ? AND role_id = ?");
        $stmt->execute([$data['user_id'], $data['role_id']]);

        Logger::info('Role removed', ['admin_id' => $this->userId(), 'user_id' => $data['user_id'], 'role_id' => $data['role_id']]);
        ActivityLog::log('role.removed', $this->userId(), null, 'user_role', $data['user_id'], ['role_id' => $data['role_id']]);
        $res->success(['message' => 'Role removed']);
    }

    public function roles(Request $req, Response $res): void
    {
        $roles = Role::all();
        $result = [];
        foreach ($roles as $role) {
            $data = $role->toArray();
            $data['permissions'] = $role->permissions();
            $data['user_count'] = count($role->users());
            $result[] = $data;
        }
        $res->success(['roles' => $result]);
    }

    public function settings(Request $req, Response $res): void
    {
        $db = SystemSetting::db();
        $stmt = $db->query("SELECT DISTINCT setting_group FROM lms_system_settings ORDER BY setting_group");
        $groups = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $grouped = [];
        foreach ($groups as $group) {
            $grouped[$group] = SystemSetting::getGroup($group);
        }
        $res->success(['settings' => $grouped]);
    }

    public function updateSettings(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        foreach ($data['settings'] as $setting) {
            SystemSetting::setValue($setting['key'], $setting['value']);
        }

        Logger::info('System settings updated', ['admin_id' => $this->userId(), 'count' => count($data['settings'])]);
        ActivityLog::log('settings.updated', $this->userId());
        $res->success(['message' => 'Settings updated']);
    }

    public function features(Request $req, Response $res): void
    {
        $features = FeatureFlag::all();
        $result = [];
        foreach ($features as $f) {
            $data = $f->toArray();
            $data['rules'] = $f->getRules();
            $result[] = $data;
        }
        $res->success(['features' => $result]);
    }

    public function toggleFeature(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'feature' => 'required|string',
            'enabled' => 'required|boolean',
        ]);

        if ($data['enabled']) {
            FeatureFlag::enable($data['feature']);
        } else {
            FeatureFlag::disable($data['feature']);
        }

        Logger::info('Feature flag toggled', ['admin_id' => $this->userId(), 'feature' => $data['feature'], 'enabled' => $data['enabled']]);
        ActivityLog::log('feature.toggled', $this->userId(), null, 'feature_flag', null, [
            'feature' => $data['feature'],
            'enabled' => $data['enabled'],
        ]);
        $res->success(['message' => 'Feature flag updated']);
    }

    public function activityLog(Request $req, Response $res): void
    {
        $this->paginatedQuery(function ($page, $perPage) use ($req) {
            $query = ActivityLog::where('id', '>', 0);
            if ($req->query('user_id')) {
                $query->where('user_id', (int) $req->query('user_id'));
            }
            if ($req->query('action')) {
                $query->where('action', $req->query('action'));
            }
            if ($req->query('date_from')) {
                $query->whereRaw('created_at >= ?', [$req->query('date_from') . ' 00:00:00']);
            }
            if ($req->query('date_to')) {
                $query->whereRaw('created_at <= ?', [$req->query('date_to') . ' 23:59:59']);
            }
            $total = $query->count();
            $items = $query->orderBy('created_at', 'DESC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    //  EXEAT2 ERP MANAGEMENT ENDPOINTS
    // ═══════════════════════════════════════════════════════════════════

    public function stats(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $res->success(['stats' => Exeat2::getDashboardStats()]);
    }

    // ─── Faculties ───────────────────────────────────────
    public function manageFaculties(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['faculties' => Exeat2::getFaculties()]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), [
                'name' => 'required|string|max:255',
                'abb' => 'required|string|max:20',
            ]);
            $faculty = Exeat2::createFaculty($data['name'], $data['abb']);
            $res->created(['faculty' => $faculty]);
        }
    }

    public function manageFaculty(Request $req, Response $res, string $id): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $id = (int) $id;
        if ($req->method() === 'GET') {
            $faculty = Exeat2::getFaculty($id);
            if (!$faculty) $res->notFound('Faculty not found');
            $res->success(['faculty' => $faculty]);
        } elseif ($req->method() === 'PUT' || $req->method() === 'PATCH') {
            $data = $this->validate($req->body(), [
                'name' => 'required|string|max:255',
                'abb' => 'required|string|max:20',
            ]);
            $res->success(['faculty' => Exeat2::updateFaculty($id, $data['name'], $data['abb'])]);
        } elseif ($req->method() === 'DELETE') {
            Exeat2::deleteFaculty($id);
            $res->success(['message' => 'Faculty deleted']);
        }
    }

    // ─── Departments ─────────────────────────────────────
    public function manageDepartments(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $facultyId = $req->query('faculty_id') ? (int) $req->query('faculty_id') : null;
            $res->success(['departments' => Exeat2::getDepartments($facultyId)]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), [
                'name' => 'required|string|max:255',
                'abb' => 'required|string|max:20',
                'faculty_id' => 'required|integer',
            ]);
            $res->created(['department' => Exeat2::createDepartment($data['name'], $data['abb'], (int) $data['faculty_id'])]);
        }
    }

    public function manageDepartment(Request $req, Response $res, string $id): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $id = (int) $id;
        if ($req->method() === 'GET') {
            $dept = Exeat2::getDepartment($id);
            if (!$dept) $res->notFound('Department not found');
            $res->success(['department' => $dept]);
        } elseif (in_array($req->method(), ['PUT', 'PATCH'])) {
            $data = $this->validate($req->body(), [
                'name' => 'required|string|max:255',
                'abb' => 'required|string|max:20',
                'faculty_id' => 'required|integer',
            ]);
            $res->success(['department' => Exeat2::updateDepartment($id, $data['name'], $data['abb'], (int) $data['faculty_id'])]);
        } elseif ($req->method() === 'DELETE') {
            Exeat2::deleteDepartment($id);
            $res->success(['message' => 'Department deleted']);
        }
    }

    // ─── Course Studies ──────────────────────────────────
    public function manageCourseStudies(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $deptId = $req->query('department_id') ? (int) $req->query('department_id') : null;
            $res->success(['course_studies' => Exeat2::getCourseStudies($deptId)]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), [
                'name' => 'required|string|max:255',
                'abb' => 'required|string|max:20',
                'department_id' => 'required|integer',
                'program_offered_id' => 'integer',
            ]);
            $res->created(['course_study' => Exeat2::createCourseStudy($data['name'], $data['abb'], (int) $data['department_id'], isset($data['program_offered_id']) ? (int) $data['program_offered_id'] : null)]);
        }
    }

    public function manageCourseStudy(Request $req, Response $res, string $id): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $id = (int) $id;
        if ($req->method() === 'GET') {
            $cs = Exeat2::getCourseStudy($id);
            if (!$cs) $res->notFound('Course study not found');
            $res->success(['course_study' => $cs]);
        } elseif (in_array($req->method(), ['PUT', 'PATCH'])) {
            $data = $this->validate($req->body(), ['name' => 'required|string|max:255', 'abb' => 'required|string|max:20', 'department_id' => 'required|integer', 'program_offered_id' => 'integer']);
            $res->success(['course_study' => Exeat2::updateCourseStudy($id, $data['name'], $data['abb'], (int) $data['department_id'], isset($data['program_offered_id']) ? (int) $data['program_offered_id'] : null)]);
        } elseif ($req->method() === 'DELETE') {
            Exeat2::deleteCourseStudy($id);
            $res->success(['message' => 'Course study deleted']);
        }
    }

    // ─── Courses ─────────────────────────────────────────
    public function manageCourses(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['courses' => Exeat2::getCourses(
                $req->query('department_id') ? (int) $req->query('department_id') : null,
                $req->query('course_study_id') ? (int) $req->query('course_study_id') : null,
                $req->query('level') ? (int) $req->query('level') : null,
            )]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), [
                'code' => 'string|max:20',
                'title' => 'required|string|max:255',
                'department_id' => 'required|integer',
                'course_study_id' => 'integer',
                'credit_load' => 'integer|between:1,12',
                'level' => 'integer',
                'course_category_id' => 'integer',
                'semester_offered' => 'integer',
            ]);
            $res->created(['course' => Exeat2::createCourse($data)]);
        }
    }

    public function manageCourse(Request $req, Response $res, string $id): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $id = (int) $id;
        if ($req->method() === 'GET') {
            $course = Exeat2::getCourse($id);
            if (!$course) $res->notFound('Course not found');
            $res->success(['course' => $course]);
        } elseif (in_array($req->method(), ['PUT', 'PATCH'])) {
            $data = $this->validate($req->body(), [
                'code' => 'string|max:20', 'title' => 'string|max:255', 'department_id' => 'integer',
                'course_study_id' => 'integer', 'credit_load' => 'integer|between:1,12',
                'level' => 'integer', 'course_category_id' => 'integer', 'semester_offered' => 'integer',
            ]);
            $res->success(['course' => Exeat2::updateCourse($id, $data)]);
        } elseif ($req->method() === 'DELETE') {
            Exeat2::deleteCourse($id);
            $res->success(['message' => 'Course deleted']);
        }
    }

    // ─── Levels ─────────────────────────────────────────
    public function manageLevels(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['levels' => Exeat2::getLevels()]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), ['level' => 'required|string|max:50']);
            $res->created(['level' => Exeat2::createLevel($data['level'])]);
        }
    }

    public function manageLevel(Request $req, Response $res, string $id): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $id = (int) $id;
        if (in_array($req->method(), ['PUT', 'PATCH'])) {
            $data = $this->validate($req->body(), ['level' => 'required|string|max:50']);
            $res->success(['level' => Exeat2::updateLevel($id, $data['level'])]);
        } elseif ($req->method() === 'DELETE') {
            Exeat2::deleteLevel($id);
            $res->success(['message' => 'Level deleted']);
        }
    }

    // ─── Vu Sessions ────────────────────────────────────
    public function manageVuSessions(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['vu_sessions' => Exeat2::getVuSessions()]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), [
                'session' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'date',
            ]);
            $res->created(['vu_session' => Exeat2::createVuSession($data['session'], $data['start_date'], $data['end_date'] ?? null)]);
        }
    }

    public function manageVuSession(Request $req, Response $res, string $id): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $id = (int) $id;
        if ($req->method() === 'GET') {
            $s = Exeat2::getVuSession($id);
            if (!$s) $res->notFound('Session not found');
            $res->success(['vu_session' => $s]);
        } elseif (in_array($req->method(), ['PUT', 'PATCH'])) {
            $data = $this->validate($req->body(), ['session' => 'required|string|max:255', 'start_date' => 'required|date', 'end_date' => 'date']);
            $res->success(['vu_session' => Exeat2::updateVuSession($id, $data['session'], $data['start_date'], $data['end_date'] ?? null)]);
        } elseif ($req->method() === 'DELETE') {
            Exeat2::deleteVuSession($id);
            $res->success(['message' => 'Session deleted']);
        }
    }

    // ─── Academic Sessions ──────────────────────────────
    public function manageAcademicSessions(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['academic_sessions' => Exeat2::getAcademicSessions()]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), [
                'vu_session_id' => 'required|integer',
                'programme_id' => 'integer',
                'batch' => 'string|max:50',
            ]);
            $res->created(['academic_session' => Exeat2::createAcademicSession((int) $data['vu_session_id'], isset($data['programme_id']) ? (int) $data['programme_id'] : null, $data['batch'] ?? null)]);
        }
    }

    public function manageAcademicSession(Request $req, Response $res, string $id): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $id = (int) $id;
        if (in_array($req->method(), ['PUT', 'PATCH'])) {
            $data = $this->validate($req->body(), ['vu_session_id' => 'required|integer', 'programme_id' => 'integer', 'batch' => 'string|max:50']);
            $res->success(['academic_session' => Exeat2::updateAcademicSession($id, (int) $data['vu_session_id'], isset($data['programme_id']) ? (int) $data['programme_id'] : null, $data['batch'] ?? null)]);
        } elseif ($req->method() === 'DELETE') {
            Exeat2::deleteAcademicSession($id);
            $res->success(['message' => 'Academic session deleted']);
        }
    }

    // ─── Semesters ──────────────────────────────────────
    public function manageSemesters(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $stmt = Exeat2::conn()->query("SELECT id, semester FROM semesters ORDER BY id");
            $res->success(['semesters' => $stmt->fetchAll()]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), ['semester' => 'required|string|max:100']);
            $res->created(['semester' => Exeat2::createSemester($data['semester'])]);
        }
    }

    public function manageSemester(Request $req, Response $res, string $id): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $id = (int) $id;
        if (in_array($req->method(), ['PUT', 'PATCH'])) {
            $data = $this->validate($req->body(), ['semester' => 'required|string|max:100']);
            $res->success(['semester' => Exeat2::updateSemester($id, $data['semester'])]);
        } elseif ($req->method() === 'DELETE') {
            Exeat2::deleteSemester($id);
            $res->success(['message' => 'Semester deleted']);
        }
    }

    // ─── Vu Semesters ───────────────────────────────────
    public function manageVuSemesters(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['vu_semesters' => Exeat2::getVuSemesters()]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), [
                'academic_session_id' => 'required|integer',
                'semester_id' => 'required|integer',
            ]);
            $res->created(['vu_semester' => Exeat2::createVuSemester((int) $data['academic_session_id'], (int) $data['semester_id'])]);
        }
    }

    public function manageVuSemester(Request $req, Response $res, string $id): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $id = (int) $id;
        if (in_array($req->method(), ['PUT', 'PATCH'])) {
            $data = $this->validate($req->body(), ['academic_session_id' => 'required|integer', 'semester_id' => 'required|integer']);
            $res->success(['vu_semester' => Exeat2::updateVuSemester($id, (int) $data['academic_session_id'], (int) $data['semester_id'])]);
        } elseif ($req->method() === 'DELETE') {
            Exeat2::deleteVuSemester($id);
            $res->success(['message' => 'Vu semester deleted']);
        }
    }

    // ─── Programmes ──────────────────────────────────────
    public function manageProgrammes(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['programmes' => Exeat2::getProgrammes()]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), ['name' => 'required|string|max:255']);
            $res->created(['programme' => Exeat2::createProgramme($data['name'])]);
        }
    }

    // ─── Programs Offered ────────────────────────────────
    public function manageProgramsOffered(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['programs_offered' => Exeat2::getProgramsOffered()]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), ['name' => 'required|string|max:255', 'abb' => 'required|string|max:20', 'programme_certification_id' => 'integer']);
            $res->created(['program_offered' => Exeat2::createProgramOffered($data['name'], $data['abb'], isset($data['programme_certification_id']) ? (int) $data['programme_certification_id'] : null)]);
        }
    }

    // ─── Course Categories ───────────────────────────────
    public function manageCourseCategories(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['course_categories' => Exeat2::getCourseCategories()]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), ['name' => 'required|string|max:255']);
            $res->created(['course_category' => Exeat2::createCourseCategory($data['name'])]);
        }
    }

    // ─── Grading Categories ─────────────────────────────
    public function manageGradingCategories(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['grading_categories' => Exeat2::getGradingCategories()]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), ['name' => 'required|string|max:255', 'course_study_id' => 'integer']);
            $res->created(['grading_category' => Exeat2::createGradingCategory($data['name'], isset($data['course_study_id']) ? (int) $data['course_study_id'] : null)]);
        }
    }

    // ─── Course Grading Systems ─────────────────────────
    public function manageCourseGradingSystems(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $courseId = $req->query('course_id') ? (int) $req->query('course_id') : null;
            $res->success(['course_grading_systems' => Exeat2::getCourseGradingSystems($courseId)]);
        }
    }

    // ─── Students ───────────────────────────────────────
    public function manageStudents(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            if ($req->query('search')) {
                $res->success(['students' => Exeat2::searchStudents($req->query('search'))]);
            } else {
                $page = max(1, (int) $req->query('page', 1));
                $perPage = min((int) $req->query('per_page', 20), 100);
                $deptId = $req->query('department_id') ? (int) $req->query('department_id') : null;
                $level = $req->query('level') ? (int) $req->query('level') : null;
                $res->success(['students' => Exeat2::getAllStudents($page, $perPage, $deptId, $level)]);
            }
        }
    }

    // ─── Staff ──────────────────────────────────────────
    public function manageStaff(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            if ($req->query('search')) {
                $res->success(['staff' => Exeat2::searchStaff($req->query('search'))]);
            } else {
                $page = max(1, (int) $req->query('page', 1));
                $perPage = min((int) $req->query('per_page', 20), 100);
                $res->success(['staff' => Exeat2::getAllStaff($page, $perPage)]);
            }
        }
    }

    // ─── Course Registrations ───────────────────────────
    public function manageCourseRegs(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $page = max(1, (int) $req->query('page', 1));
            $perPage = min((int) $req->query('per_page', 20), 100);
            $res->success(['course_regs' => Exeat2::getAllCourseRegs(
                $page, $perPage,
                $req->query('academic_session_id') ? (int) $req->query('academic_session_id') : null,
                $req->query('department_id') ? (int) $req->query('department_id') : null,
                $req->query('course_study_id') ? (int) $req->query('course_study_id') : null,
            )]);
        } elseif ($req->method() === 'POST') {
            $data = $this->validate($req->body(), [
                'reg_id' => 'required|integer',
                'ca_one' => 'numeric',
                'ca_two' => 'numeric',
                'ca_three' => 'numeric',
                'examination' => 'numeric',
                'total' => 'numeric',
                'grade' => 'string|max:10',
            ]);
            $res->success(['course_reg' => Exeat2::updateCourseRegScores(
                (int) $data['reg_id'],
                isset($data['ca_one']) ? (float) $data['ca_one'] : null,
                isset($data['ca_two']) ? (float) $data['ca_two'] : null,
                isset($data['ca_three']) ? (float) $data['ca_three'] : null,
                isset($data['examination']) ? (float) $data['examination'] : null,
                isset($data['total']) ? (float) $data['total'] : null,
                $data['grade'] ?? null,
            )]);
        }
    }

    // ─── Programme Certifications ───────────────────────
    public function manageProgrammeCertifications(Request $req, Response $res): void
    {
        Exeat2::available() ?: $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        if ($req->method() === 'GET') {
            $res->success(['programme_certifications' => Exeat2::getProgrammeCertifications()]);
        }
    }
}
