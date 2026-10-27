<?php
namespace Lms\Models;

use Lms\Core\Model;

class Notification extends Model
{
    protected static string $table = 'notifications';
    protected static bool $useTimestamps = false;

    public function user(): ?User
    {
        return User::find($this->attributes['user_id']);
    }

    public function markAsRead(): void
    {
        $this->is_read = 1;
        $this->read_at = date('Y-m-d H:i:s');
        $this->save();
    }

    public function getData(): ?array
    {
        $data = $this->attributes['data'] ?? null;
        if (is_string($data)) {
            return json_decode($data, true);
        }
        return $data;
    }

    public static function unreadCount(int $userId): int
    {
        return static::where('user_id', $userId)
            ->where('is_read', 0)
            ->count();
    }

    public static function markAllAsRead(int $userId): void
    {
        $db = static::db();
        $stmt = $db->prepare("UPDATE lms_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
    }

    public static function send(int $userId, string $type, string $title, string $message, ?array $data = null, ?string $link = null): static
    {
        $n = new static();
        $n->user_id = $userId;
        $n->type = $type;
        $n->title = $title;
        $n->message = $message;
        $n->data = $data ? json_encode($data) : null;
        $n->link = $link;
        $n->is_read = 0;
        $n->save();
        return $n;
    }
}
