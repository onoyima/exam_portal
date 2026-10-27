<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Assignment;

class AssignmentRepository extends Repository
{
    protected function getTable(): string { return 'assignments'; }
    protected function getModelClass(): string { return Assignment::class; }

    public function getByCourse(int $courseId): array
    {
        return Assignment::where('course_id', '=', $courseId)
            ->orderBy('due_at', 'ASC')
            ->get();
    }

    public function getByModule(int $moduleId): array
    {
        return Assignment::where('module_id', '=', $moduleId)
            ->orderBy('due_at', 'ASC')
            ->get();
    }

    public function getUpcoming(int $limit = 10): array
    {
        return Assignment::where('due_at', '>=', date('Y-m-d H:i:s'))
            ->orderBy('due_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    public function getOverdue(): array
    {
        return Assignment::where('due_at', '<', date('Y-m-d H:i:s'))
            ->where('status', '=', 'published')
            ->orderBy('due_at', 'DESC')
            ->get();
    }
}
