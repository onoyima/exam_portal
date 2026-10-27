<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\Conversation;
use Lms\Models\Message;
use Lms\Models\Notification;

class MessageController extends Controller
{
    public function conversations(Request $req, Response $res): void
    {
        $db = Conversation::db();
        $stmt = $db->prepare("
            SELECT c.*, cp.last_read_at,
                   (SELECT m.message FROM lms_messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                   (SELECT m.created_at FROM lms_messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_at
            FROM lms_conversations c
            JOIN lms_conversation_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ?
            ORDER BY last_message_at DESC
        ");
        $stmt->execute([$this->userId()]);
        $res->success(['conversations' => $stmt->fetchAll()]);
    }

    public function messages(Request $req, Response $res, string $convId): void
    {
        $conv = Conversation::find((int) $convId);
        if (!$conv) $res->notFound('Conversation not found');

        $page    = max(1, (int) $req->query('page', 1));
        $perPage = min((int) $req->query('per_page', lms_config('pagination.per_page', 20)), lms_config('pagination.max_per_page', 100));
        $query = Message::where('conversation_id', $conv->getKey());
        $total = $query->count();
        $messages = $query->orderBy('created_at', 'DESC')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
        foreach ($messages as $m) {
            $m->setRelation('sender', $m->sender());
        }
        $res->success([
            'items' => array_reverse($messages),
        ], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / max($perPage, 1)),
        ]);
    }

    public function send(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'conversation_id' => 'required|integer|exists:lms_conversations,id',
            'message' => 'required|string',
            'message_type' => 'in:text,image,file,notification',
        ]);

        $conv = Conversation::find((int) $data['conversation_id']);
        if (!$conv) $res->notFound('Conversation not found');

        $message = $conv->sendMessage($this->userId(), $data['message'], $data['message_type'] ?? 'text');
        $res->created(['message' => $message->toArray()]);
    }

    public function createConversation(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'participants' => 'required|array|min:1',
            'participants.*' => 'integer|exists:lms_users,id',
            'subject' => 'string|max:255',
        ]);

        $participants = array_unique(array_merge([$this->userId()], $data['participants']));
        $conv = Conversation::createDirect($participants, $data['subject'] ?? null);
        $res->created(['conversation' => $conv->toArray()]);
    }

    public function notifications(Request $req, Response $res): void
    {
        $this->paginatedQuery(function ($page, $perPage) {
            $query = Notification::where('user_id', $this->userId());
            $total = $query->count();
            $items = $query->orderBy('created_at', 'DESC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        });
    }

    public function unreadCount(Request $req, Response $res): void
    {
        $res->success(['unread' => Notification::unreadCount($this->userId())]);
    }

    public function markRead(Request $req, Response $res, string $id): void
    {
        $notif = Notification::find((int) $id);
        if (!$notif) $res->notFound('Notification not found');
        $notif->markAsRead();
        $res->success(['notification' => $notif->toArray()]);
    }

    public function markAllRead(Request $req, Response $res): void
    {
        Notification::markAllAsRead($this->userId());
        $res->success(['message' => 'All notifications marked as read']);
    }
}
