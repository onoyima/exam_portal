<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Core\QueryBuilder;
use Lms\Models\Course;
use Lms\Models\CourseCategory;
use Lms\Models\Enrollment;
use Lms\Models\ActivityLog;

class CourseController extends Controller
{
    public function index(Request $req, Response $res): void
    {
        $page    = max(1, (int) $req->query('page', 1));
        $perPage = min((int) $req->query('per_page', lms_config('pagination.per_page', 20)), lms_config('pagination.max_per_page', 100));
        $query = Course::where('status', '!=', 'archived');
        if ($req->query('search')) {
            $search = '%' . $req->query('search') . '%';
            $query->whereRaw('(title LIKE ? OR code LIKE ?)', [$search, $search]);
        }
        if ($req->query('category_id')) {
            $query->where('category_id', $req->query('category_id'));
        }
        if ($req->query('level')) {
            $query->where('level', $req->query('level'));
        }
        $total = $query->count();
        $items = $query->orderBy('created_at', 'DESC')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
        $res->success([
            'courses' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / max($perPage, 1)),
            ],
        ]);
    }

    public function show(Request $req, Response $res, string $id): void
    {
        $course = Course::findBy('uuid', $id) ?? Course::find((int) $id);
        if (!$course) $res->notFound('Course not found');

        $modules = $course->modules();
        foreach ($modules as $module) {
            $module->setRelation('lessons', $module->lessons());
        }
        $course->setRelation('modules', $modules);
        $course->setRelation('instructors', $course->instructors());
        $course->setRelation('category', $course->category());
        $course->setRelation('student_count', $course->studentCount());

        $res->success(['course' => $course->toArray()]);
    }

    public function store(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'code' => 'string|max:20',
            'title' => 'required|string|max:255',
            'description' => 'string',
            'short_description' => 'string|max:500',
            'category_id' => 'integer|exists:lms_course_categories,id',
            'level' => 'string|max:50',
            'credits' => 'integer|between:1,12',
            'duration_weeks' => 'integer',
            'status' => 'in:draft,published,archived',
        ]);

        $data['created_by'] = $this->userId();
        $data['slug'] = $this->slugify($data['title']);
        $course = new Course();
        foreach ($data as $key => $value) {
            $course->$key = $value;
        }
        $course->save();

        ActivityLog::log('course.created', $this->userId(), $course->getKey(), 'course', $course->getKey());
        $res->created(['course' => $course->toArray()]);
    }

    public function update(Request $req, Response $res, string $id): void
    {
        $course = Course::findBy('uuid', $id) ?? Course::find((int) $id);
        if (!$course) $res->notFound('Course not found');

        $data = $this->validate($req->body(), [
            'title' => 'string|max:255',
            'description' => 'string',
            'short_description' => 'string|max:500',
            'category_id' => 'integer|exists:lms_course_categories,id',
            'level' => 'string|max:50',
            'credits' => 'integer|between:1,12',
            'duration_weeks' => 'integer',
            'status' => 'in:draft,published,archived',
            'max_students' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ]);

        foreach ($data as $key => $value) {
            $course->$key = $value;
        }
        $course->save();

        ActivityLog::log('course.updated', $this->userId(), $course->getKey(), 'course', $course->getKey());
        $res->success(['course' => $course->toArray()]);
    }

    public function destroy(Request $req, Response $res, string $id): void
    {
        $course = Course::findBy('uuid', $id) ?? Course::find((int) $id);
        if (!$course) $res->notFound('Course not found');
        $course->delete();
        ActivityLog::log('course.deleted', $this->userId(), $course->getKey(), 'course', $course->getKey());
        $res->success(['message' => 'Course deleted']);
    }

    public function categories(Request $req, Response $res): void
    {
        $categories = CourseCategory::whereNull('parent_id')->orderBy('sort_order')->get();
        $res->success(['categories' => $categories]);
    }

    public function myCourses(Request $req, Response $res): void
    {
        $user = $this->user();
        if (!$user) $res->unauthorized();

        $role = $user['role'] ?? $user['portal_user_type'] ?? '';

        if (in_array($role, ['super_admin', 'admin'])) {
            $courses = Course::where('status', '!=', 'archived')
                ->orderBy('created_at', 'DESC')
                ->get();
            $res->success(['courses' => $courses]);
        } elseif (in_array($role, ['instructor', 'ta', 'examiner'])) {
            $qb = new QueryBuilder('lms_course_instructors', Course::class);
            $courses = $qb->join('lms_courses', 'lms_courses.id', '=', 'lms_course_instructors.course_id')
                ->where('lms_course_instructors.user_id', $user['id'])
                ->where('lms_courses.status', '!=', 'archived')
                ->orderBy('lms_courses.created_at', 'DESC')
                ->get();
            $res->success(['courses' => $courses]);
        } else {
            $enrollments = Enrollment::where('user_id', $user['id'])
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
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', strtolower(trim($text)));
        $text = preg_replace('/[\s-]+/', '-', $text);
        $text = trim($text, '-');
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        return $text ? "$text-$suffix" : "course-$suffix";
    }
}
