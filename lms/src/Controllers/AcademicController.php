<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Services\Exeat2;

class AcademicController extends Controller
{
    public function faculties(Request $req, Response $res): void
    {
        if (!Exeat2::available()) {
            $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        }
        $res->success(['faculties' => Exeat2::getFaculties()]);
    }

    public function departments(Request $req, Response $res): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $facultyId = $req->query('faculty_id') ? (int) $req->query('faculty_id') : null;
        $res->success(['departments' => Exeat2::getDepartments($facultyId)]);
    }

    public function courseStudies(Request $req, Response $res): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $deptId = $req->query('department_id') ? (int) $req->query('department_id') : null;
        $res->success(['course_studies' => Exeat2::getCourseStudies($deptId)]);
    }

    public function courses(Request $req, Response $res): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $res->success(['courses' => Exeat2::getCourses(
            $req->query('department_id') ? (int) $req->query('department_id') : null,
            $req->query('course_study_id') ? (int) $req->query('course_study_id') : null,
            $req->query('level') ? (int) $req->query('level') : null,
        )]);
    }

    public function courseDetail(Request $req, Response $res, string $id): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $course = Exeat2::getCourse((int) $id);
        if (!$course) $res->notFound('Course not found');
        $res->success(['course' => $course]);
    }

    public function sessions(Request $req, Response $res): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $res->success(['vu_sessions' => Exeat2::getVuSessions()]);
    }

    public function activeSession(Request $req, Response $res): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $res->success(['session' => Exeat2::getActiveVuSession()]);
    }

    public function semesters(Request $req, Response $res): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $sessionId = $req->query('academic_session_id') ? (int) $req->query('academic_session_id') : null;
        $res->success(['semesters' => Exeat2::getVuSemesters($sessionId)]);
    }

    public function levels(Request $req, Response $res): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $res->success(['levels' => Exeat2::getLevels()]);
    }

    public function studentContext(Request $req, Response $res): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $userId = $this->userId();
        $user = \Lms\Models\User::find($userId);
        if (!$user || !$user->isStudent() || !$user->portalId()) {
            $res->error('NO_CONTEXT', 'No student academic context available');
        }
        $academic = Exeat2::getStudentAcademic($user->portalId());
        if (!$academic) $res->notFound('Academic record not found');

        $courseStudy = Exeat2::getCourseStudy((int) $academic['course_study_id']);
        if ($courseStudy) {
            $courseStudy['courses'] = Exeat2::getCourses(
                (int) $academic['department_id'],
                (int) $academic['course_study_id'],
                (int) $academic['level']
            );
        }

        $res->success([
            'academic' => $academic,
            'course_study' => $courseStudy,
            'current_courses' => Exeat2::getStudentCurrentCourses($user->portalId()),
            'registrations' => Exeat2::getStudentCourseRegs($user->portalId()),
            'active_session' => Exeat2::getActiveVuSession(),
        ]);
    }

    public function myRegistrations(Request $req, Response $res): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $userId = $this->userId();
        $user = \Lms\Models\User::find($userId);
        if (!$user || !$user->isStudent() || !$user->portalId()) {
            $res->error('NO_CONTEXT', 'No student academic context available');
        }
        $academic = Exeat2::getStudentAcademic($user->portalId());
        $activeSession = Exeat2::getActiveVuSession();
        $registrations = Exeat2::getStudentCourseRegsWithResults($user->portalId());

        $res->success([
            'academic' => $academic,
            'active_session' => $activeSession,
            'registrations' => $registrations,
        ]);
    }

    public function venues(Request $req, Response $res): void
    {
        if (!Exeat2::available()) $res->error('EXEAT2_UNAVAILABLE', 'Academic records not available', 503);
        $res->success(['venues' => Exeat2::getLectureVenues()]);
    }
}
