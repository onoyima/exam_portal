<?php
namespace Lms\Models;

use Lms\Core\Model;

class ActivityLog extends Model
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

    public static function log(
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
        $log = new static();
        $log->user_id = $userId;
        $log->course_id = $courseId;
        $log->action = $action;
        $log->entity_type = $entityType;
        $log->entity_id = $entityId;
        $log->metadata = $metadata ? json_encode($metadata) : null;
        $log->ip_address = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $log->user_agent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        $log->session_id = $sessionId;
        $log->duration_ms = $durationMs;
        $log->save();
        return $log;
    }
}
