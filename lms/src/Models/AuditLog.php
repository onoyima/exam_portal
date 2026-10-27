<?php
namespace Lms\Models;

use Lms\Core\Model;

class AuditLog extends Model
{
    protected static string $table = 'activity_logs';
    protected static bool $useTimestamps = false;

    public function user(): ?User
    {
        if (!$this->attributes['user_id']) return null;
        return User::find($this->attributes['user_id']);
    }

    public function course(): ?Course
    {
        if (!$this->attributes['course_id']) return null;
        return Course::find($this->attributes['course_id']);
    }

    public function getMetadata(): ?array
    {
        $meta = $this->attributes['metadata'] ?? null;
        if (is_string($meta)) {
            return json_decode($meta, true);
        }
        return $meta;
    }

    public static function getByUser(int $userId, int $limit = 50): array
    {
        return static::where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public static function getByEntity(string $entityType, int $entityId): array
    {
        return static::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public static function getByAction(string $action, string $since = null): array
    {
        $query = static::where('action', $action);
        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        return $query->orderBy('created_at', 'DESC')->get();
    }

    public static function getByCourse(int $courseId, int $limit = 50): array
    {
        return static::where('course_id', $courseId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public static function getByDateRange(string $from, string $to, int $limit = 100): array
    {
        return static::where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public static function record(
        string $action,
        ?int $userId = null,
        ?int $courseId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sessionId = null,
        ?int $durationMs = null
    ): static {
        return ActivityLog::log($action, $userId, $courseId, $entityType, $entityId, $metadata, $ipAddress, $userAgent, $sessionId, $durationMs);
    }
}
