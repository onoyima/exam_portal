<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\AuditLog;

class AuditLogRepository extends Repository
{
    protected function getTable(): string { return 'activity_logs'; }
    protected function getModelClass(): string { return AuditLog::class; }

    public function getByUser(int $userId, int $limit = 50): array
    {
        return AuditLog::getByUser($userId, $limit);
    }

    public function getByEntity(string $entityType, int $entityId): array
    {
        return AuditLog::getByEntity($entityType, $entityId);
    }

    public function getByAction(string $action, ?string $since = null): array
    {
        return AuditLog::getByAction($action, $since);
    }

    public function getByCourse(int $courseId, int $limit = 50): array
    {
        return AuditLog::getByCourse($courseId, $limit);
    }

    public function getByDateRange(string $from, string $to, int $limit = 100): array
    {
        return AuditLog::getByDateRange($from, $to, $limit);
    }
}
