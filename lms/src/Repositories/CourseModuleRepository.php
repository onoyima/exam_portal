<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\CourseModule;

class CourseModuleRepository extends Repository
{
    protected function getTable(): string { return 'course_modules'; }
    protected function getModelClass(): string { return CourseModule::class; }

    public function getByCourse(int $courseId): array
    {
        return CourseModule::where('course_id', '=', $courseId)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }
}
