<?php
use Lms\Core\Router;
use Lms\Core\Request;
use Lms\Core\Response;

$router = new Router();

// ─── Public Routes (no auth) ────────────────────────────────────────────────────
$router->post('/auth/register', ['Lms\\Controllers\\AuthController', 'register']);
$router->post('/auth/login', ['Lms\\Controllers\\AuthController', 'login']);
$router->post('/auth/refresh', ['Lms\\Controllers\\AuthController', 'refresh']);

// ─── Auth Middleware ────────────────────────────────────────────────────────────
$auth = function (Request $req, Response $res, callable $next) {
    $header = $req->header('Authorization');
    if (!$header || !str_starts_with($header, 'Bearer ')) {
        $res->unauthorized('Missing or invalid token');
    }
    $token = substr($header, 7);
    try {
        $secret = lms_config('auth.jwt_secret', 'lms-secret-key-change-in-production');
        $payload = (array) \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
    } catch (\Throwable $e) {
        $res->unauthorized('Invalid or expired token');
    }
    $req->setUserContext(
        (int) $payload['sub'],
        $payload['uuid'] ?? '',
        $payload['email'] ?? '',
        isset($payload['portal_user_id']) ? (int) $payload['portal_user_id'] : null,
        $payload['portal_user_type'] ?? null,
        $payload['portal_role'] ?? null
    );
    return $next($req, $res);
};

// ─── Role Middleware ─────────────────────────────────────────────────────────────
function role(string ...$roles): callable {
    return function (Request $req, Response $res, callable $next) use ($roles) {
        $hasRole = false;
        foreach ($roles as $role) {
            if ($req->user() && ($req->userRole() === $role)) {
                $hasRole = true;
                break;
            }
        }
        if (!$hasRole) {
            $res->forbidden('Insufficient permissions');
        }
        return $next($req, $res);
    };
}

// ─── Authenticated Routes ───────────────────────────────────────────────────────
$router->group('/auth', [$auth], function (Router $r) {
    $r->post('/logout', ['Lms\\Controllers\\AuthController', 'logout']);
    $r->get('/profile', ['Lms\\Controllers\\AuthController', 'profile']);
    $r->put('/profile', ['Lms\\Controllers\\AuthController', 'updateProfile']);
});

// ─── Dashboard ──────────────────────────────────────────────────────────────────
$router->group('', [$auth], function (Router $r) {
    $r->get('/dashboard/student', ['Lms\\Controllers\\DashboardController', 'student']);
    $r->get('/dashboard/instructor', ['Lms\\Controllers\\DashboardController', 'instructor']);
    $r->get('/dashboard/admin', ['Lms\\Controllers\\DashboardController', 'admin']);
});

// ─── Course Categories ──────────────────────────────────────────────────────────
$router->group('/categories', [$auth], function (Router $r) {
    $r->get('', ['Lms\\Controllers\\CourseController', 'categories']);
});

// ─── Courses ────────────────────────────────────────────────────────────────────
$router->group('/courses', [$auth], function (Router $r) {
    $r->get('', ['Lms\\Controllers\\CourseController', 'index']);
    $r->get('/my', ['Lms\\Controllers\\CourseController', 'myCourses']);
    $r->get('/:id', ['Lms\\Controllers\\CourseController', 'show']);
    $r->post('', ['Lms\\Controllers\\CourseController', 'store'], [role('super_admin', 'admin', 'instructor')]);
    $r->put('/:id', ['Lms\\Controllers\\CourseController', 'update'], [role('super_admin', 'admin', 'instructor')]);
    $r->delete('/:id', ['Lms\\Controllers\\CourseController', 'destroy'], [role('super_admin', 'admin')]);

    // Course enrollments
    $r->get('/:courseId/students', ['Lms\\Controllers\\EnrollmentController', 'courseStudents'], [role('super_admin', 'admin', 'instructor', 'ta')]);
    $r->get('/:courseId/modules', ['Lms\\Controllers\\ModuleController', 'index']);
    $r->get('/:courseId/assignments', ['Lms\\Controllers\\AssignmentController', 'index']);
    $r->get('/:courseId/quizzes', ['Lms\\Controllers\\QuizController', 'index']);

    // Gradebook
    $r->get('/:courseId/gradebook', ['Lms\\Controllers\\GradebookController', 'items']);
    $r->get('/:courseId/gradebook/:userId', ['Lms\\Controllers\\GradebookController', 'studentGrades']);

    // Exam links
    $r->get('/:courseId/exam-links', ['Lms\\Controllers\\ExamBridgeController', 'links']);

    // Analytics
    $r->get('/:courseId/stats', ['Lms\\Controllers\\AnalyticsController', 'courseStats'], [role('super_admin', 'admin', 'instructor')]);

    // Discussions
    $r->get('/:courseId/discussions', ['Lms\\Controllers\\DiscussionController', 'threads']);

    // Content library
    $r->get('/:courseId/content', ['Lms\\Controllers\\ContentController', 'library']);
});

// ─── Modules ────────────────────────────────────────────────────────────────────
$router->group('/modules', [$auth], function (Router $r) {
    $r->get('/:id', ['Lms\\Controllers\\ModuleController', 'show']);
    $r->post('', ['Lms\\Controllers\\ModuleController', 'store'], [role('super_admin', 'admin', 'instructor')]);
    $r->put('/:id', ['Lms\\Controllers\\ModuleController', 'update'], [role('super_admin', 'admin', 'instructor')]);
    $r->delete('/:id', ['Lms\\Controllers\\ModuleController', 'destroy'], [role('super_admin', 'admin', 'instructor')]);
    $r->post('/reorder', ['Lms\\Controllers\\ModuleController', 'reorder'], [role('super_admin', 'admin', 'instructor')]);
    $r->get('/:moduleId/lessons', ['Lms\\Controllers\\LessonController', 'index']);
});

// ─── Lessons ────────────────────────────────────────────────────────────────────
$router->group('/lessons', [$auth], function (Router $r) {
    $r->get('', ['Lms\\Controllers\\LessonController', 'index']);
    $r->get('/:id', ['Lms\\Controllers\\LessonController', 'show']);
    $r->get('/:id/attachments', ['Lms\\Controllers\\LessonController', 'attachments']);
    $r->get('/:id/progress', ['Lms\\Controllers\\LessonController', 'getProgress']);
    $r->post('/:id/progress', ['Lms\\Controllers\\LessonController', 'progress']);
    $r->post('/:id/complete', ['Lms\\Controllers\\LessonController', 'complete']);
    $r->post('', ['Lms\\Controllers\\LessonController', 'store'], [role('super_admin', 'admin', 'instructor')]);
    $r->put('/:id', ['Lms\\Controllers\\LessonController', 'update'], [role('super_admin', 'admin', 'instructor')]);
    $r->delete('/:id', ['Lms\\Controllers\\LessonController', 'destroy'], [role('super_admin', 'admin', 'instructor')]);
});

// ─── Enrollments ────────────────────────────────────────────────────────────────
$router->group('/enrollments', [$auth], function (Router $r) {
    $r->get('/my', ['Lms\\Controllers\\EnrollmentController', 'myCourses']);
    $r->post('', ['Lms\\Controllers\\EnrollmentController', 'enroll']);
    $r->delete('/:id', ['Lms\\Controllers\\EnrollmentController', 'unenroll']);
});

// ─── Assignments ────────────────────────────────────────────────────────────────
$router->group('/assignments', [$auth], function (Router $r) {
    $r->get('/:id', ['Lms\\Controllers\\AssignmentController', 'show']);
    $r->post('', ['Lms\\Controllers\\AssignmentController', 'store'], [role('super_admin', 'admin', 'instructor')]);
    $r->put('/:id', ['Lms\\Controllers\\AssignmentController', 'update'], [role('super_admin', 'admin', 'instructor')]);
    $r->delete('/:id', ['Lms\\Controllers\\AssignmentController', 'destroy'], [role('super_admin', 'admin', 'instructor')]);
});

// ─── Submissions ──────────────────────────────────────────────────────────────
$router->group('/submissions', [$auth], function (Router $r) {
    $r->get('/my', ['Lms\\Controllers\\SubmissionController', 'mySubmissions']);
    $r->get('/:id', ['Lms\\Controllers\\SubmissionController', 'show']);
    $r->post('/:id/grade', ['Lms\\Controllers\\SubmissionController', 'grade'], [role('super_admin', 'admin', 'instructor', 'ta')]);
});

// ─── Quizzes ────────────────────────────────────────────────────────────────────
$router->group('/quizzes', [$auth], function (Router $r) {
    $r->get('/:id', ['Lms\\Controllers\\QuizController', 'show']);
    $r->post('', ['Lms\\Controllers\\QuizController', 'store'], [role('super_admin', 'admin', 'instructor')]);
    $r->put('/:id', ['Lms\\Controllers\\QuizController', 'update'], [role('super_admin', 'admin', 'instructor')]);
    $r->delete('/:id', ['Lms\\Controllers\\QuizController', 'destroy'], [role('super_admin', 'admin', 'instructor')]);
    $r->post('/:id/questions', ['Lms\\Controllers\\QuizController', 'addQuestion'], [role('super_admin', 'admin', 'instructor')]);
    $r->get('/:id/attempts', ['Lms\\Controllers\\QuizAttemptController', 'index']);
    $r->get('/:id/attempts/:attemptId', ['Lms\\Controllers\\QuizAttemptController', 'show']);
    $r->post('/:id/attempts', ['Lms\\Controllers\\QuizAttemptController', 'start']);
    $r->post('/:id/attempts/:attemptId/submit', ['Lms\\Controllers\\QuizAttemptController', 'submit']);
    $r->get('/:id/attempts/:attemptId/results', ['Lms\\Controllers\\QuizAttemptController', 'results']);
    $r->get('/:id/attempts/:attemptId/time', ['Lms\\Controllers\\QuizAttemptController', 'remainingTime']);
});

// ─── Discussions ────────────────────────────────────────────────────────────────
$router->group('/discussions', [$auth], function (Router $r) {
    $r->get('/:id', ['Lms\\Controllers\\DiscussionController', 'showThread']);
    $r->post('', ['Lms\\Controllers\\DiscussionController', 'createThread']);
    $r->post('/:threadId/replies', ['Lms\\Controllers\\DiscussionController', 'reply']);
});

// ─── Conversations / Messages ───────────────────────────────────────────────────
$router->group('/messages', [$auth], function (Router $r) {
    $r->get('/conversations', ['Lms\\Controllers\\MessageController', 'conversations']);
    $r->post('/conversations', ['Lms\\Controllers\\MessageController', 'createConversation']);
    $r->get('/conversations/:convId', ['Lms\\Controllers\\MessageController', 'messages']);
    $r->post('/send', ['Lms\\Controllers\\MessageController', 'send']);
});

// ─── Notifications ──────────────────────────────────────────────────────────────
$router->group('/notifications', [$auth], function (Router $r) {
    $r->get('', ['Lms\\Controllers\\NotificationController', 'index']);
    $r->get('/unread-count', ['Lms\\Controllers\\NotificationController', 'unreadCount']);
    $r->post('/:id/read', ['Lms\\Controllers\\NotificationController', 'markRead']);
    $r->post('/mark-all-read', ['Lms\\Controllers\\NotificationController', 'markAllRead']);
    $r->delete('/:id', ['Lms\\Controllers\\NotificationController', 'destroy']);
    $r->get('/preferences', ['Lms\\Controllers\\NotificationController', 'preferences']);
    $r->put('/preferences', ['Lms\\Controllers\\NotificationController', 'updatePreferences']);
});

// ─── Gradebook ──────────────────────────────────────────────────────────────────
$router->group('/gradebook', [$auth], function (Router $r) {
    $r->get('/my', ['Lms\\Controllers\\GradebookController', 'studentGrades']);
    $r->get('/course/:courseId', ['Lms\\Controllers\\GradebookController', 'items']);
    $r->post('/items', ['Lms\\Controllers\\GradebookController', 'createItem'], [role('super_admin', 'admin', 'instructor')]);
    $r->post('/grades', ['Lms\\Controllers\\GradebookController', 'updateGrade'], [role('super_admin', 'admin', 'instructor', 'ta')]);
});

// ─── Content / Library ──────────────────────────────────────────────────────────
$router->group('/content', [$auth], function (Router $r) {
    $r->get('', ['Lms\\Controllers\\ContentController', 'library']);
    $r->get('/:id', ['Lms\\Controllers\\ContentController', 'showContent']);
    $r->post('', ['Lms\\Controllers\\ContentController', 'upload'], [role('super_admin', 'admin', 'instructor')]);
    $r->delete('/:id', ['Lms\\Controllers\\ContentController', 'destroyContent'], [role('super_admin', 'admin', 'instructor')]);
    $r->get('/:id/download', ['Lms\\Controllers\\ContentController', 'download']);
    $r->post('/attachments', ['Lms\\Controllers\\ContentController', 'attachToLesson'], [role('super_admin', 'admin', 'instructor')]);
});

// ─── Analytics ──────────────────────────────────────────────────────────────────
$router->group('/analytics', [$auth], function (Router $r) {
    $r->get('/platform', ['Lms\\Controllers\\AnalyticsController', 'platformStats'], [role('super_admin', 'admin')]);
    $r->get('/course/:courseId', ['Lms\\Controllers\\AnalyticsController', 'courseStats']);
    $r->get('/course/:courseId/engagement', ['Lms\\Controllers\\AnalyticsController', 'courseEngagement']);
    $r->get('/progress', ['Lms\\Controllers\\AnalyticsController', 'userProgress']);
    $r->get('/progress/:userId', ['Lms\\Controllers\\AnalyticsController', 'userProgress'], [role('super_admin', 'admin', 'instructor')]);
});

// ─── Exam Bridge ────────────────────────────────────────────────────────────────
$router->group('/exam-bridge', [$auth], function (Router $r) {
    $r->get('/sync-log', ['Lms\\Controllers\\ExamBridgeController', 'syncLog'], [role('super_admin', 'admin')]);
    $r->post('/link', ['Lms\\Controllers\\ExamBridgeController', 'link'], [role('super_admin', 'admin', 'instructor')]);
    $r->post('/unlink/:id', ['Lms\\Controllers\\ExamBridgeController', 'unlink'], [role('super_admin', 'admin', 'instructor')]);
    $r->post('/sync', ['Lms\\Controllers\\ExamBridgeController', 'sync'], [role('super_admin', 'admin')]);
    $r->post('/sso', ['Lms\\Controllers\\ExamBridgeController', 'ssoRedirect']);
});

// ─── Academic (exeat2 integration) ──────────────────────────────────────────
$router->group('/academic', [$auth], function (Router $r) {
    $r->get('/faculties', ['Lms\\Controllers\\AcademicController', 'faculties']);
    $r->get('/departments', ['Lms\\Controllers\\AcademicController', 'departments']);
    $r->get('/course-studies', ['Lms\\Controllers\\AcademicController', 'courseStudies']);
    $r->get('/courses', ['Lms\\Controllers\\AcademicController', 'courses']);
    $r->get('/courses/:id', ['Lms\\Controllers\\AcademicController', 'courseDetail']);
    $r->get('/sessions', ['Lms\\Controllers\\AcademicController', 'sessions']);
    $r->get('/sessions/active', ['Lms\\Controllers\\AcademicController', 'activeSession']);
    $r->get('/semesters', ['Lms\\Controllers\\AcademicController', 'semesters']);
    $r->get('/levels', ['Lms\\Controllers\\AcademicController', 'levels']);
    $r->get('/my-context', ['Lms\\Controllers\\AcademicController', 'studentContext']);
    $r->get('/my-registrations', ['Lms\\Controllers\\AcademicController', 'myRegistrations']);
    $r->get('/venues', ['Lms\\Controllers\\AcademicController', 'venues']);
});

// ─── Classes ─────────────────────────────────────────────────────────────────
$router->group('/classes', [$auth], function (Router $r) {
    $r->get('', ['Lms\\Controllers\\ClassController', 'index']);
    $r->get('/hierarchy', ['Lms\\Controllers\\ClassController', 'hierarchy']);
    $r->get('/my', ['Lms\\Controllers\\ClassController', 'myClasses']);
    $r->post('', ['Lms\\Controllers\\ClassController', 'store'], [role('super_admin', 'admin', 'instructor')]);
    $r->get('/:id', ['Lms\\Controllers\\ClassController', 'show']);
    $r->put('/:id', ['Lms\\Controllers\\ClassController', 'update'], [role('super_admin', 'admin')]);
    $r->delete('/:id', ['Lms\\Controllers\\ClassController', 'destroy'], [role('super_admin', 'admin')]);
    $r->post('/sync', ['Lms\\Controllers\\ClassController', 'sync'], [role('super_admin', 'admin')]);
    $r->post('/:id/enroll', ['Lms\\Controllers\\ClassController', 'enrollStudent'], [role('super_admin', 'admin', 'instructor')]);
    $r->post('/:id/enroll/bulk', ['Lms\\Controllers\\ClassController', 'bulkEnroll'], [role('super_admin', 'admin', 'instructor')]);
    $r->delete('/:classId/enroll/:studentId', ['Lms\\Controllers\\ClassController', 'removeStudent'], [role('super_admin', 'admin', 'instructor')]);
    $r->post('/:id/staff', ['Lms\\Controllers\\ClassController', 'assignStaff'], [role('super_admin', 'admin')]);
    $r->delete('/:classId/staff/:staffId', ['Lms\\Controllers\\ClassController', 'removeStaff'], [role('super_admin', 'admin')]);
    $r->get('/:id/sessions', ['Lms\\Controllers\\ClassController', 'sessions']);
    $r->post('/:id/sessions', ['Lms\\Controllers\\ClassController', 'createSession'], [role('super_admin', 'admin', 'instructor')]);
    $r->post('/:id/link-session', ['Lms\\Controllers\\ClassController', 'linkSession'], [role('super_admin', 'admin')]);
    $r->put('/:classId/sessions/:sessionId', ['Lms\\Controllers\\ClassController', 'updateSession'], [role('super_admin', 'admin', 'instructor')]);
    $r->delete('/:classId/sessions/:sessionId', ['Lms\\Controllers\\ClassController', 'deleteSession'], [role('super_admin', 'admin', 'instructor')]);
    $r->get('/:id/analytics', ['Lms\\Controllers\\ClassController', 'analytics'], [role('super_admin', 'admin', 'instructor')]);
});

// ─── Sync ───────────────────────────────────────────────────────────────────
$router->group('/sync', [$auth], function (Router $r) {
    $r->post('/enrollments', function(Request $req, Response $res) {
        $sync = new \Lms\Services\PortalSyncService();
        $result = $sync->syncAllActiveEnrollments();
        $res->success(['message' => 'Sync completed', 'stats' => $result]);
    }, [role('super_admin', 'admin')]);
});

// ─── Admin ──────────────────────────────────────────────────────────────────────
$router->group('/admin', [$auth, role('super_admin', 'admin')], function (Router $r) {
    $r->get('/users', ['Lms\\Controllers\\AdminController', 'users']);
    $r->put('/users/:id', ['Lms\\Controllers\\AdminController', 'updateUser']);
    $r->post('/roles', ['Lms\\Controllers\\AdminController', 'assignRole']);
    $r->delete('/roles', ['Lms\\Controllers\\AdminController', 'removeRole']);
    $r->get('/roles', ['Lms\\Controllers\\AdminController', 'roles']);
    $r->get('/settings', ['Lms\\Controllers\\AdminController', 'settings']);
    $r->put('/settings', ['Lms\\Controllers\\AdminController', 'updateSettings']);
    $r->get('/features', ['Lms\\Controllers\\AdminController', 'features']);
    $r->post('/features', ['Lms\\Controllers\\AdminController', 'toggleFeature']);
    $r->get('/activity', ['Lms\\Controllers\\AdminController', 'activityLog']);

    // ─── ERP Management ─────────────────────────────────────────────────────
    $r->get('/stats', ['Lms\\Controllers\\AdminController', 'stats']);

    // Faculties
    $r->match(['GET','POST'], '/faculties', ['Lms\\Controllers\\AdminController', 'manageFaculties']);
    $r->match(['GET','PUT','PATCH','DELETE'], '/faculties/:id', ['Lms\\Controllers\\AdminController', 'manageFaculty']);

    // Departments
    $r->match(['GET','POST'], '/departments', ['Lms\\Controllers\\AdminController', 'manageDepartments']);
    $r->match(['GET','PUT','PATCH','DELETE'], '/departments/:id', ['Lms\\Controllers\\AdminController', 'manageDepartment']);

    // Course Studies
    $r->match(['GET','POST'], '/course-studies', ['Lms\\Controllers\\AdminController', 'manageCourseStudies']);
    $r->match(['GET','PUT','PATCH','DELETE'], '/course-studies/:id', ['Lms\\Controllers\\AdminController', 'manageCourseStudy']);

    // Courses
    $r->match(['GET','POST'], '/courses', ['Lms\\Controllers\\AdminController', 'manageCourses']);
    $r->match(['GET','PUT','PATCH','DELETE'], '/courses/:id', ['Lms\\Controllers\\AdminController', 'manageCourse']);

    // Levels
    $r->match(['GET','POST'], '/levels', ['Lms\\Controllers\\AdminController', 'manageLevels']);
    $r->match(['PUT','PATCH','DELETE'], '/levels/:id', ['Lms\\Controllers\\AdminController', 'manageLevel']);

    // Vu Sessions
    $r->match(['GET','POST'], '/vu-sessions', ['Lms\\Controllers\\AdminController', 'manageVuSessions']);
    $r->match(['GET','PUT','PATCH','DELETE'], '/vu-sessions/:id', ['Lms\\Controllers\\AdminController', 'manageVuSession']);

    // Academic Sessions
    $r->match(['GET','POST'], '/academic-sessions', ['Lms\\Controllers\\AdminController', 'manageAcademicSessions']);
    $r->match(['PUT','PATCH','DELETE'], '/academic-sessions/:id', ['Lms\\Controllers\\AdminController', 'manageAcademicSession']);

    // Semesters
    $r->match(['GET','POST'], '/semesters', ['Lms\\Controllers\\AdminController', 'manageSemesters']);
    $r->match(['PUT','PATCH','DELETE'], '/semesters/:id', ['Lms\\Controllers\\AdminController', 'manageSemester']);

    // Vu Semesters
    $r->match(['GET','POST'], '/vu-semesters', ['Lms\\Controllers\\AdminController', 'manageVuSemesters']);
    $r->match(['PUT','PATCH','DELETE'], '/vu-semesters/:id', ['Lms\\Controllers\\AdminController', 'manageVuSemester']);

    // Programmes
    $r->match(['GET','POST'], '/programmes', ['Lms\\Controllers\\AdminController', 'manageProgrammes']);

    // Programs Offered
    $r->match(['GET','POST'], '/programs-offered', ['Lms\\Controllers\\AdminController', 'manageProgramsOffered']);

    // Course Categories
    $r->match(['GET','POST'], '/course-categories', ['Lms\\Controllers\\AdminController', 'manageCourseCategories']);

    // Grading Categories
    $r->match(['GET','POST'], '/grading-categories', ['Lms\\Controllers\\AdminController', 'manageGradingCategories']);

    // Course Grading Systems
    $r->get('/course-grading-systems', ['Lms\\Controllers\\AdminController', 'manageCourseGradingSystems']);

    // Students & Staff
    $r->get('/students', ['Lms\\Controllers\\AdminController', 'manageStudents']);
    $r->get('/staff', ['Lms\\Controllers\\AdminController', 'manageStaff']);

    // Course Registrations (with score management)
    $r->match(['GET','POST'], '/course-regs', ['Lms\\Controllers\\AdminController', 'manageCourseRegs']);

    // Programme Certifications
    $r->get('/programme-certifications', ['Lms\\Controllers\\AdminController', 'manageProgrammeCertifications']);
});

return $router;
