<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\ExamSyncLog;

class ExamSyncLogRepository extends Repository
{
    protected function getTable(): string { return 'exam_sync_log'; }
    protected function getModelClass(): string { return ExamSyncLog::class; }

    public function getBySyncType(string $syncType, int $limit = 50): array
    {
        return ExamSyncLog::where('sync_type', '=', $syncType)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function getRecent(int $limit = 50): array
    {
        return ExamSyncLog::where('1', '=', '1')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function getFailed(int $limit = 50): array
    {
        return ExamSyncLog::where('status', '=', 'failed')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function countByDateRange(string $from, string $to): array
    {
        $db = $this->db;
        $stmt = $db->prepare("
            SELECT sync_type, status, COUNT(*) as count
            FROM lms_exam_sync_log
            WHERE created_at >= ? AND created_at <= ?
            GROUP BY sync_type, status
        ");
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }
}
