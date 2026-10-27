<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\Course;
use Lms\Models\Enrollment;
use Lms\Models\Assignment;
use Lms\Models\Quiz;
use Lms\Models\ActivityLog;

class DashboardController extends Controller
{
    public function student(Request $req, Response $res): void
    {
        $userId = $this->userId();
        $enrollments = Enrollment::where('user_id', $userId)->where('status', 'active')->count();
        $completed = Enrollment::where('user_id', $userId)->where('status', 'completed')->count();

        $res->success([
            'active_enrollments' => $enrollments,
            'completed_courses' => $completed,
            'unread_notifications' => \Lms\Models\Notification::unreadCount($userId),
            'recent_activity' => ActivityLog::where('user_id', $userId)
                ->orderBy('created_at', 'DESC')
                ->limit(10)
                ->get(),
        ]);
    }

    public function instructor(Request $req, Response $res): void
    {
        $userId = $this->userId();
        $db = Course::db();
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT c.id) as total_courses,
                   COUNT(DISTINCT e.id) as total_enrollments,
                   COUNT(DISTINCT CASE WHEN e.status = 'active' THEN e.id END) as active_enrollments
            FROM lms_courses c
            LEFT JOIN lms_course_instructors ci ON c.id = ci.course_id
            LEFT JOIN lms_enrollments e ON c.id = e.course_id
            WHERE ci.user_id = ? AND c.deleted_at IS NULL
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();

        $recentCourses = Course::where('created_by', $userId)
            ->orderBy('updated_at', 'DESC')
            ->limit(5)
            ->get();

        $res->success([
            'stats' => $stats,
            'recent_courses' => $recentCourses,
        ]);
    }

    public function admin(Request $req, Response $res): void
    {
        $res->success([
            'total_users' => \Lms\Models\User::count(),
            'total_courses' => Course::count(),
            'total_enrollments' => Enrollment::count(),
            'active_enrollments' => Enrollment::where('status', 'active')->count(),
            'recent_activity' => ActivityLog::orderBy('created_at', 'DESC')->limit(20)->get(),
        ]);
    }
}
