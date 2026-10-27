<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\GradeItem;

class GradeItemRepository extends Repository
{
    protected function getTable(): string { return 'grade_items'; }
    protected function getModelClass(): string { return GradeItem::class; }

    public function getByCourse(int $courseId): array
    {
        return GradeItem::where('course_id', '=', $courseId)
            ->orderBy('due_date', 'ASC')
            ->get();
    }

    public function getByCategory(int $courseId, string $category): array
    {
        return GradeItem::where('course_id', '=', $courseId)
            ->where('category', '=', $category)
            ->orderBy('due_date', 'ASC')
            ->get();
    }
}
