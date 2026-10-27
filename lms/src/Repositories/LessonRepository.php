<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Lesson;

class LessonRepository extends Repository
{
    protected function getTable(): string { return 'lessons'; }
    protected function getModelClass(): string { return Lesson::class; }

    public function getByModule(int $moduleId): array
    {
        return Lesson::where('module_id', '=', $moduleId)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    public function getByCourse(int $courseId): array
    {
        return Lesson::where('course_id', '=', $courseId)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }
}
