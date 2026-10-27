<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\ActivityLog;

class ActivityLogRepository extends Repository
{
    protected function getTable(): string { return 'activity_logs'; }
    protected function getModelClass(): string { return ActivityLog::class; }

    public function getByUser(int $userId, int $limit = 50): array
    {
        return ActivityLog::where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function getByCourse(int $courseId, int $limit = 50): array
    {
        return ActivityLog::where('course_id', '=', $courseId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function getByAction(string $action, int $limit = 50): array
    {
        return ActivityLog::where('action', '=', $action)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function getByDateRange(string $from, string $to, int $limit = 100): array
    {
        return ActivityLog::where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }
}
