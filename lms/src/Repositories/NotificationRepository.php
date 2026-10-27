<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Notification;

class NotificationRepository extends Repository
{
    protected function getTable(): string { return 'notifications'; }
    protected function getModelClass(): string { return Notification::class; }

    public function getByUser(int $userId, int $limit = 50): array
    {
        return Notification::where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function getUnreadByUser(int $userId, int $limit = 50): array
    {
        return Notification::where('user_id', '=', $userId)
            ->where('is_read', '=', 0)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function getByType(int $userId, string $type): array
    {
        return Notification::where('user_id', '=', $userId)
            ->where('type', '=', $type)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function countUnread(int $userId): int
    {
        return Notification::unreadCount($userId);
    }

    public function markAllRead(int $userId): void
    {
        Notification::markAllAsRead($userId);
    }
}
