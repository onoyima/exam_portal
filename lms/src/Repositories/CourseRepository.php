<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Course;

class CourseRepository extends Repository
{
    protected function getTable(): string { return 'courses'; }
    protected function getModelClass(): string { return Course::class; }

    public function findByCode(string $code): ?Course
    {
        return Course::findBy('code', $code);
    }

    public function getByCategory(int $categoryId): array
    {
        return Course::where('category_id', '=', $categoryId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByInstructor(int $userId): array
    {
        return Course::where('id', 'IN', function ($q) use ($userId) {
            $q->select('course_id')->from('course_instructors')->where('user_id', '=', $userId);
        })->get();
    }

    public function getPublished(): array
    {
        return Course::where('status', '=', 'published')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function search(string $term, int $limit = 20): array
    {
        return Course::where('title', 'LIKE', "%{$term}%")
            ->orWhere('description', 'LIKE', "%{$term}%")
            ->orWhere('code', 'LIKE', "%{$term}%")
            ->limit($limit)
            ->get();
    }

    public function getWithAnalytics(int $courseId): array
    {
        $course = $this->find($courseId);
        if (!$course) {
            return [];
        }
        return [
            'course'           => $course,
            'student_count'    => $course->studentCount(),
            'completion_rate'  => $course->completionRate(),
            'modules_count'    => count($course->modules()),
        ];
    }
}
