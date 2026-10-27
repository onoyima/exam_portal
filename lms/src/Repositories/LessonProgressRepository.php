<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\LessonProgress;

class LessonProgressRepository extends Repository
{
    protected function getTable(): string { return 'lesson_progress'; }
    protected function getModelClass(): string { return LessonProgress::class; }

    public function getByUser(int $userId): array
    {
        return LessonProgress::where('user_id', '=', $userId)
            ->orderBy('updated_at', 'DESC')
            ->get();
    }

    public function getByLesson(int $lessonId): array
    {
        return LessonProgress::where('lesson_id', '=', $lessonId)
            ->orderBy('updated_at', 'DESC')
            ->get();
    }

    public function getByUserAndLesson(int $userId, int $lessonId): ?LessonProgress
    {
        return LessonProgress::where('user_id', '=', $userId)
            ->where('lesson_id', '=', $lessonId)
            ->first();
    }

    public function getCompletedByUser(int $userId): array
    {
        return LessonProgress::where('user_id', '=', $userId)
            ->where('status', '=', 'completed')
            ->orderBy('completed_at', 'DESC')
            ->get();
    }

    public function countCompletedByLesson(int $lessonId): int
    {
        return LessonProgress::where('lesson_id', '=', $lessonId)
            ->where('status', '=', 'completed')
            ->count();
    }
}
