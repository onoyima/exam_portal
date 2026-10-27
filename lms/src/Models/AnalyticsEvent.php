<?php
namespace Lms\Models;

use Lms\Core\Model;

class AnalyticsEvent extends Model
{
    protected static string $table = 'analytics_events';
    protected static bool $useTimestamps = false;
    protected static bool $useSoftDeletes = false;

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

    public function getPayload(): ?array
    {
        $data = $this->attributes['payload'] ?? null;
        if (is_string($data)) {
            return json_decode($data, true);
        }
        return $data;
    }

    public static function record(
        string $event,
        ?int $userId = null,
        ?int $courseId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $payload = null,
        ?string $sessionId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): static {
        $record = new static();
        $record->event = $event;
        $record->user_id = $userId;
        $record->course_id = $courseId;
        $record->entity_type = $entityType;
        $record->entity_id = $entityId;
        $record->payload = $payload ? json_encode($payload) : null;
        $record->session_id = $sessionId;
        $record->ip_address = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $record->user_agent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        $record->save();
        return $record;
    }

    public static function getEvents(string $event, ?int $userId = null, string $since = null): array
    {
        $query = static::where('event', $event);
        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        return $query->orderBy('created_at', 'DESC')->get();
    }

    public static function getCourseAnalytics(int $courseId, string $since = null): array
    {
        $query = static::where('course_id', $courseId);
        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        return $query->orderBy('created_at', 'DESC')->get();
    }

    public static function countByEvent(string $event, string $since = null): int
    {
        $query = static::where('event', $event);
        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        return $query->count();
    }
}
