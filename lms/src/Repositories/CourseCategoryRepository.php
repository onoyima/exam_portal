<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\CourseCategory;

class CourseCategoryRepository extends Repository
{
    protected function getTable(): string { return 'course_categories'; }
    protected function getModelClass(): string { return CourseCategory::class; }

    public function getRootCategories(): array
    {
        return CourseCategory::whereNull('parent_id')
            ->orderBy('name', 'ASC')
            ->get();
    }

    public function getChildren(int $parentId): array
    {
        return CourseCategory::where('parent_id', '=', $parentId)
            ->orderBy('name', 'ASC')
            ->get();
    }

    public function getWithCourseCount(): array
    {
        return CourseCategory::select('c.*, (SELECT COUNT(*) FROM lms_courses WHERE category_id = c.id) as course_count')
            ->from('course_categories', 'c')
            ->orderBy('name', 'ASC')
            ->get();
    }
}
