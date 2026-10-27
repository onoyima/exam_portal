<?php
namespace Lms\Models;

use Lms\Core\Model;

class Conversation extends Model
{
    protected static string $table = 'conversations';

    public function participants(): array
    {
        return (new \Lms\Core\QueryBuilder('lms_conversation_participants', User::class))
            ->join('lms_users', 'lms_users.id', '=', 'lms_conversation_participants.user_id')
            ->where('lms_conversation_participants.conversation_id', $this->getKey())
            ->get();
    }

    public function messages(): array
    {
        return Message::where('conversation_id', $this->getKey())
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function lastMessage(): ?Message
    {
        $messages = Message::where('conversation_id', $this->getKey())
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->get();
        return $messages[0] ?? null;
    }

    public function addParticipant(int $userId): void
    {
        $db = static::db();
        $stmt = $db->prepare("INSERT IGNORE INTO lms_conversation_participants (conversation_id, user_id) VALUES (?, ?)");
        $stmt->execute([$this->getKey(), $userId]);
    }

    public function sendMessage(int $senderId, string $message, string $type = 'text'): Message
    {
        $msg = new Message();
        $msg->conversation_id = $this->getKey();
        $msg->sender_id = $senderId;
        $msg->message = $message;
        $msg->message_type = $type;
        $msg->save();
        return $msg;
    }

    public static function createDirect(array $participantIds, ?string $subject = null): static
    {
        $conv = new static();
        $conv->type = 'direct';
        $conv->subject = $subject;
        $conv->created_by = $participantIds[0];
        $conv->save();
        foreach ($participantIds as $id) {
            $conv->addParticipant($id);
        }
        return $conv;
    }
}
