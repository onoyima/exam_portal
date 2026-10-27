<?php
namespace Lms\Services;

use Lms\Core\Database;
use Lms\Core\Logger;
use Lms\Models\Course;
use Lms\Models\Enrollment;
use Lms\Models\ActivityLog;
use Lms\Models\AnalyticsEvent;
use Lms\Models\LessonProgress;
use Lms\Models\QuizAttempt;
use Lms\Models\GradebookEntry;
use Lms\Models\Submission;

class AnalyticsService
{
    public static function trackEvent(string $event, ?int $userId = null, ?int $courseId = null, ?string $entityType = null, ?int $entityId = null, ?array $payload = null): void
    {
        AnalyticsEvent::record($event, $userId, $courseId, $entityType, $entityId, $payload);
        Logger::debug("Analytics event tracked: {$event}", [
            'user_id' => $userId,
            'course_id' => $courseId,
        ]);
    }

    public static function getCourseAnalytics(int $courseId): array
    {
        $course = Course::find($courseId);
        if (!$course) return [];

        $enrollments = Enrollment::where('course_id', $courseId)->get();
        $totalEnrollments = count($enrollments);
        $activeEnrollments = 0;
        $completedEnrollments = 0;
        $droppedEnrollments = 0;

        foreach ($enrollments as $e) {
            match ($e->status) {
                'active' => $activeEnrollments++,
                'completed' => $completedEnrollments++,
                'dropped' => $droppedEnrollments++,
                default => null,
            };
        }

        $modules = $course->modules();
        $totalLessons = 0;
        $totalCompletedLessons = 0;
        foreach ($modules as $module) {
            $lessons = $module->lessons();
            $totalLessons += count($lessons);
            foreach ($lessons as $lesson) {
                $totalCompletedLessons += LessonProgress::where('lesson_id', $lesson->getKey())
                    ->where('status', 'completed')
                    ->count();
            }
        }

        $averageProgress = $totalLessons > 0
            ? round(($totalCompletedLessons / ($totalLessons * max($totalEnrollments, 1))) * 100, 2)
            : 0;

        $quizAttempts = QuizAttempt::where('quiz_id', '>', 0)->count();
        $submissions = Submission::count();

        $recentActivity = ActivityLog::where('course_id', $courseId)
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get();

        return [
            'enrollments' => [
                'total' => $totalEnrollments,
                'active' => $activeEnrollments,
                'completed' => $completedEnrollments,
                'dropped' => $droppedEnrollments,
            ],
            'lessons' => [
                'total' => $totalLessons,
                'completions' => $totalCompletedLessons,
            ],
            'average_progress' => $averageProgress,
            'quiz_attempts' => $quizAttempts,
            'submissions' => $submissions,
            'recent_activity' => $recentActivity,
        ];
    }

    public static function getStudentAnalytics(int $userId): array
    {
        $enrollments = Enrollment::where('user_id', $userId)->get();
        $courses = [];
        $totalPercentage = 0;

        foreach ($enrollments as $enrollment) {
            $course = $enrollment->course();
            if (!$course) continue;

            $grades = GradebookEntry::where('user_id', $userId)
                ->where('course_id', $course->getKey())
                ->get();

            $totalPoints = 0;
            $earnedPoints = 0;
            foreach ($grades as $g) {
                $totalPoints += (float) ($g->points_possible ?? 0);
                $earnedPoints += (float) ($g->points_earned ?? 0);
            }

            $percentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
            $totalPercentage += $percentage;

            $courses[] = [
                'course_id' => $course->getKey(),
                'title' => $course->title,
                'code' => $course->code,
                'status' => $enrollment->status,
                'grade' => $enrollment->grade,
                'letter_grade' => $enrollment->letter_grade,
                'progress' => $enrollment->progress_percent,
                'percentage' => $percentage,
            ];
        }

        $activities = ActivityLog::where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(30)
            ->get();

        return [
            'total_courses' => count($courses),
            'average_grade' => count($courses) > 0 ? round($totalPercentage / count($courses), 2) : 0,
            'courses' => $courses,
            'recent_activity' => $activities,
        ];
    }

    public static function getPlatformStats(string $period = '30d'): array
    {
        $since = match ($period) {
            '7d' => date('Y-m-d H:i:s', strtotime('-7 days')),
            '90d' => date('Y-m-d H:i:s', strtotime('-90 days')),
            '1y' => date('Y-m-d H:i:s', strtotime('-1 year')),
            default => date('Y-m-d H:i:s', strtotime('-30 days')),
        };

        return [
            'courses' => [
                'total' => Course::count(),
                'published' => Course::where('status', 'published')->count(),
                'draft' => Course::where('status', 'draft')->count(),
            ],
            'enrollments' => [
                'total' => Enrollment::count(),
                'recent' => Enrollment::where('created_at', '>=', $since)->count(),
            ],
            'activity' => [
                'total' => ActivityLog::where('created_at', '>=', $since)->count(),
                'by_action' => self::getActivityBreakdown($since),
            ],
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public static function getInstructorStats(int $userId): array
    {
        $db = Database::connection('lms');
        $stmt = $db->prepare("
            SELECT c.id, c.title, c.code, c.status,
                   (SELECT COUNT(*) FROM lms_enrollments e WHERE e.course_id = c.id AND e.enrollment_type = 'student') as student_count,
                   (SELECT COUNT(*) FROM lms_enrollments e WHERE e.course_id = c.id AND e.enrollment_type = 'student' AND e.status = 'completed') as completed_count,
                   (SELECT COUNT(*) FROM lms_assignments a WHERE a.course_id = c.id AND a.deleted_at IS NULL) as assignment_count,
                   (SELECT COUNT(*) FROM lms_quizzes q WHERE q.course_id = c.id AND q.deleted_at IS NULL) as quiz_count
            FROM lms_courses c
            JOIN lms_course_instructors ci ON c.id = ci.course_id
            WHERE ci.user_id = ? AND c.deleted_at IS NULL
        ");
        $stmt->execute([$userId]);
        $courses = $stmt->fetchAll();

        return [
            'total_courses' => count($courses),
            'courses' => $courses,
        ];
    }

    public static function getEngagementMetrics(int $courseId): array
    {
        $db = Database::connection('lms');

        $dailyEngagement = $db->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count, action
            FROM lms_activity_logs
            WHERE course_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at), action
            ORDER BY date ASC
        ");
        $dailyEngagement->execute([$courseId]);

        return [
            'daily' => $dailyEngagement->fetchAll(),
        ];
    }

    private static function getActivityBreakdown(string $since): array
    {
        $db = Database::connection('lms');
        $stmt = $db->prepare("
            SELECT action, COUNT(*) as count
            FROM lms_activity_logs
            WHERE created_at >= ?
            GROUP BY action
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute([$since]);
        return $stmt->fetchAll();
    }
}
