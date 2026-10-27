<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Message;

class MessageRepository extends Repository
{
    protected function getTable(): string { return 'messages'; }
    protected function getModelClass(): string { return Message::class; }

    public function getByConversation(int $conversationId): array
    {
        return Message::where('conversation_id', '=', $conversationId)
            ->orderBy('created_at', 'ASC')
            ->get();
    }

    public function getBySender(int $senderId): array
    {
        return Message::where('sender_id', '=', $senderId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getUnreadByConversation(int $conversationId, int $userId): array
    {
        return Message::where('conversation_id', '=', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', '=', 0)
            ->orderBy('created_at', 'ASC')
            ->get();
    }
}
