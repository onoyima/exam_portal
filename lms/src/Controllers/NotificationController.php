<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\Notification;
use Lms\Services\NotificationService;

class NotificationController extends Controller
{
    public function index(Request $req, Response $res): void
    {
        $page = max(1, (int) $req->query('page', 1));
        $perPage = min((int) $req->query('per_page', 20), 100);

        $result = NotificationService::getUserNotifications($this->userId(), $page, $perPage);
        $res->success($result['items'], [
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
            'total_pages' => $result['total_pages'],
            'unread_count' => $result['unread_count'],
        ]);
    }

    public function unreadCount(Request $req, Response $res): void
    {
        $count = NotificationService::getUnreadCount($this->userId());
        $res->success(['unread' => $count]);
    }

    public function markRead(Request $req, Response $res, string $id): void
    {
        $result = NotificationService::markAsRead((int) $id, $this->userId());
        if (!$result) $res->notFound('Notification not found');
        $res->success(['message' => 'Notification marked as read']);
    }

    public function markAllRead(Request $req, Response $res): void
    {
        NotificationService::markAllAsRead($this->userId());
        $res->success(['message' => 'All notifications marked as read']);
    }

    public function destroy(Request $req, Response $res, string $id): void
    {
        $result = NotificationService::deleteNotification((int) $id, $this->userId());
        if (!$result) $res->notFound('Notification not found');
        $res->success(['message' => 'Notification deleted']);
    }

    public function preferences(Request $req, Response $res): void
    {
        $prefs = \Lms\Models\UserPreference::get($this->userId(), 'notifications', [
            'email' => true,
            'in_app' => true,
            'push' => false,
            'digest' => 'instant',
        ]);
        $res->success(['preferences' => $prefs]);
    }

    public function updatePreferences(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'email' => 'boolean',
            'in_app' => 'boolean',
            'push' => 'boolean',
            'digest' => 'in:instant,daily,weekly',
        ]);

        \Lms\Models\UserPreference::set($this->userId(), 'notifications', $data);
        $res->success(['message' => 'Preferences updated']);
    }
}
