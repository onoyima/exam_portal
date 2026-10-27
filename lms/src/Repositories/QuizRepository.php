<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Quiz;

class QuizRepository extends Repository
{
    protected function getTable(): string { return 'quizzes'; }
    protected function getModelClass(): string { return Quiz::class; }

    public function getByCourse(int $courseId): array
    {
        return Quiz::where('course_id', '=', $courseId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByModule(int $moduleId): array
    {
        return Quiz::where('module_id', '=', $moduleId)
            ->orderBy('created_at', 'ASC')
            ->get();
    }

    public function getAvailable(): array
    {
        return Quiz::where('status', '=', 'published')
            ->where('available_from', '<=', date('Y-m-d H:i:s'))
            ->where(function ($q) {
                $q->whereNull('available_until')
                    ->orWhere('available_until', '>=', date('Y-m-d H:i:s'));
            })
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getUpcoming(): array
    {
        return Quiz::where('status', '=', 'published')
            ->where('available_from', '>', date('Y-m-d H:i:s'))
            ->orderBy('available_from', 'ASC')
            ->get();
    }

    public function countByCourse(int $courseId): int
    {
        return Quiz::where('course_id', '=', $courseId)->count();
    }
}
