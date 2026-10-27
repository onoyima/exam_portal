<?php
namespace Lms\Models;

use Lms\Core\Model;

class RefreshToken extends Model
{
    protected static string $table = 'refresh_tokens';
    protected static bool $useTimestamps = false;
    protected static bool $useSoftDeletes = false;

    public function user(): ?User
    {
        return User::find($this->attributes['user_id']);
    }

    public function isValid(): bool
    {
        if ($this->attributes['revoked_at']) return false;
        return strtotime($this->attributes['expires_at']) > time();
    }

    public function revoke(): void
    {
        $this->revoked_at = date('Y-m-d H:i:s');
        $this->save();
    }

    public static function createForUser(int $userId): static
    {
        $token = new static();
        $token->user_id = $userId;
        $token->token_hash = hash('sha256', bin2hex(random_bytes(32)));
        $token->expires_at = date('Y-m-d H:i:s', time() + 604800);
        $token->save();
        return $token;
    }

    public static function findValid(string $tokenHash): ?static
    {
        $token = static::findBy('token_hash', $tokenHash);
        if (!$token || !$token->isValid()) {
            return null;
        }
        return $token;
    }

    public static function revokeAllForUser(int $userId): void
    {
        $db = static::db();
        $stmt = $db->prepare("UPDATE lms_refresh_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL");
        $stmt->execute([$userId]);
    }
}
