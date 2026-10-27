<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\Course;
use Lms\Models\Enrollment;
use Lms\Models\ActivityLog;
use Lms\Models\LessonProgress;
use Lms\Models\GradebookEntry;

class AnalyticsController extends Controller
{
    public function courseStats(Request $req, Response $res, string $courseId): void
    {
        $course = Course::findBy('uuid', $courseId) ?? Course::find((int) $courseId);
        if (!$course) $res->notFound('Course not found');
        $cid = $course->getKey();

        $totalStudents = Enrollment::where('course_id', $cid)->where('enrollment_type', 'student')->count();
        $activeStudents = Enrollment::where('course_id', $cid)->where('enrollment_type', 'student')->where('status', 'active')->count();
        $completedStudents = Enrollment::where('course_id', $cid)->where('enrollment_type', 'student')->where('status', 'completed')->count();

        $completionRate = $totalStudents > 0 ? round(($completedStudents / $totalStudents) * 100, 2) : 0;

        $modules = $course->modules();
        $totalLessons = 0;
        $completedLessons = 0;
        foreach ($modules as $m) {
            $lessons = $m->lessons();
            $totalLessons += count($lessons);
            foreach ($lessons as $l) {
                $completedLessons += LessonProgress::where('lesson_id', $l->getKey())
                    ->where('status', 'completed')
                    ->count();
            }
        }

        $res->success([
            'course' => ['id' => $cid, 'title' => $course->title],
            'enrollments' => ['total' => $totalStudents, 'active' => $activeStudents, 'completed' => $completedStudents],
            'completion_rate' => $completionRate,
            'lessons' => ['total' => $totalLessons, 'completed_count' => $completedLessons],
            'recent_activity' => ActivityLog::where('course_id', $cid)
                ->orderBy('created_at', 'DESC')
                ->limit(20)
                ->get(),
        ]);
    }

    public function platformStats(Request $req, Response $res): void
    {
        $period = $req->query('period', '30d');
        $since = match ($period) {
            '7d' => date('Y-m-d H:i:s', strtotime('-7 days')),
            '90d' => date('Y-m-d H:i:s', strtotime('-90 days')),
            '1y' => date('Y-m-d H:i:s', strtotime('-1 year')),
            default => date('Y-m-d H:i:s', strtotime('-30 days')),
        };

        $res->success([
            'courses' => ['total' => Course::count(), 'published' => Course::where('status', 'published')->count()],
            'enrollments' => ['total' => Enrollment::count(), 'recent' => Enrollment::where('created_at', '>=', $since)->count()],
            'activity' => ['count' => ActivityLog::where('created_at', '>=', $since)->count()],
            'period' => $period,
        ]);
    }

    public function userProgress(Request $req, Response $res, string $userId = null): void
    {
        $uid = $userId ? (int) $userId : $this->userId();
        $enrollments = Enrollment::where('user_id', $uid)->where('status', '!=', 'dropped')->get();
        $results = [];
        foreach ($enrollments as $e) {
            $course = $e->course();
            if (!$course) continue;
            $grades = GradebookEntry::where('user_id', $uid)->where('course_id', $course->getKey())->get();
            $totalPoints = 0;
            $earnedPoints = 0;
            foreach ($grades as $g) {
                $totalPoints += (float) ($g->points_possible ?? 0);
                $earnedPoints += (float) ($g->points_earned ?? 0);
            }
            $results[] = [
                'course_id' => $course->getKey(),
                'course_title' => $course->title,
                'course_code' => $course->code,
                'progress_percent' => $e->progress_percent,
                'status' => $e->status,
                'grade' => $e->grade,
                'letter_grade' => $e->letter_grade,
                'total_points' => $totalPoints,
                'earned_points' => $earnedPoints,
            ];
        }
        $res->success(['progress' => $results]);
    }
}
