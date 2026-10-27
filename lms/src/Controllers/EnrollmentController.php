<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\Enrollment;
use Lms\Models\Course;
use Lms\Models\ActivityLog;

class EnrollmentController extends Controller
{
    public function enroll(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'course_id' => 'required|integer|exists:lms_courses,id',
            'user_id' => 'integer|exists:lms_users,id',
        ]);

        $userId = $data['user_id'] ?? $this->userId();
        $courseId = $data['course_id'];

        $course = Course::find($courseId);
        if (!$course) $res->notFound('Course not found');
        if ($course->status !== 'published') $res->error('NOT_OPEN', 'Course is not open for enrollment');

        if ($course->max_students && $course->studentCount() >= $course->max_students) {
            $res->error('COURSE_FULL', 'Course is full');
        }

        if (Enrollment::isEnrolled($userId, $courseId)) {
            $res->conflict('Already enrolled');
        }

        $enrollment = Enrollment::enrollStudent($userId, $courseId);
        ActivityLog::log('enrollment.created', $userId, $courseId, 'enrollment', $enrollment->getKey());
        $res->created(['enrollment' => $enrollment->toArray()]);
    }

    public function unenroll(Request $req, Response $res, string $id): void
    {
        $enrollment = Enrollment::find((int) $id);
        if (!$enrollment) $res->notFound('Enrollment not found');

        $enrollment->status = 'dropped';
        $enrollment->dropped_at = date('Y-m-d H:i:s');
        $enrollment->save();

        ActivityLog::log('enrollment.dropped', $this->userId(), $enrollment->course_id, 'enrollment', $enrollment->getKey());
        $res->success(['message' => 'Unenrolled successfully']);
    }

    public function myCourses(Request $req, Response $res): void
    {
        $enrollments = Enrollment::where('user_id', $this->userId())
            ->where('status', '!=', 'dropped')
            ->orderBy('created_at', 'DESC')
            ->get();

        $results = [];
        foreach ($enrollments as $e) {
            $course = $e->course();
            if ($course) {
                $courseData = $course->toArray();
                $courseData['enrollment_status'] = $e->status;
                $courseData['progress_percent'] = $e->progress_percent;
                $courseData['enrolled_at'] = $e->enrolled_at;
                $results[] = $courseData;
            }
        }
        $res->success(['courses' => $results]);
    }

    public function courseStudents(Request $req, Response $res, string $courseId): void
    {
        $course = Course::findBy('uuid', $courseId) ?? Course::find((int) $courseId);
        if (!$course) $res->notFound('Course not found');

        $this->paginatedQuery(function ($page, $perPage) use ($course) {
            $query = Enrollment::where('course_id', $course->getKey())
                ->where('enrollment_type', 'student');
            $total = $query->count();
            $enrollments = $query->orderBy('created_at', 'DESC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            $items = [];
            foreach ($enrollments as $e) {
                $u = $e->user();
                if ($u) {
                    $items[] = array_merge($u->toArray(), [
                        'enrollment_status' => $e->status,
                        'progress_percent' => $e->progress_percent,
                        'grade' => $e->grade,
                        'enrolled_at' => $e->enrolled_at,
                    ]);
                }
            }
            return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        });
    }
}
