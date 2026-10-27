<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\GradebookEntry;

class GradebookEntryRepository extends Repository
{
    protected function getTable(): string { return 'gradebook'; }
    protected function getModelClass(): string { return GradebookEntry::class; }

    public function getByUser(int $userId): array
    {
        return GradebookEntry::where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByCourse(int $courseId): array
    {
        return GradebookEntry::where('course_id', '=', $courseId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByGradeItem(int $gradeItemId): array
    {
        return GradebookEntry::where('grade_item_id', '=', $gradeItemId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByUserAndCourse(int $userId, int $courseId): array
    {
        return GradebookEntry::where('user_id', '=', $userId)
            ->where('course_id', '=', $courseId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByUserAndGradeItem(int $userId, int $gradeItemId): ?GradebookEntry
    {
        return GradebookEntry::where('user_id', '=', $userId)
            ->where('grade_item_id', '=', $gradeItemId)
            ->first();
    }
}
