<?php
namespace Lms\Models;

use Lms\Core\Model;

class Message extends Model
{
    protected static string $table = 'messages';
    protected static bool $useSoftDeletes = true;

    public function sender(): ?User
    {
        return User::find($this->attributes['sender_id']);
    }

    public function conversation(): ?Conversation
    {
        return Conversation::find($this->attributes['conversation_id']);
    }

    public function markAsRead(): void
    {
        $db = static::db();
        $stmt = $db->prepare("UPDATE lms_conversation_participants SET last_read_at = NOW() WHERE conversation_id = ? AND user_id = ?");
        $stmt->execute([$this->attributes['conversation_id'], $this->attributes['sender_id']]);
    }
}
