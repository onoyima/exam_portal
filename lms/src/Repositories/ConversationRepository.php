<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Conversation;

class ConversationRepository extends Repository
{
    protected function getTable(): string { return 'conversations'; }
    protected function getModelClass(): string { return Conversation::class; }

    public function getByParticipant(int $userId): array
    {
        $db = $this->db;
        $stmt = $db->prepare("
            SELECT c.* FROM lms_conversations c
            JOIN lms_conversation_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ?
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([$userId]);
        return Conversation::hydrate($stmt->fetchAll());
    }
}
