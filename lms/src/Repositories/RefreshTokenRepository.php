<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\RefreshToken;

class RefreshTokenRepository extends Repository
{
    protected function getTable(): string { return 'refresh_tokens'; }
    protected function getModelClass(): string { return RefreshToken::class; }

    public function createForUser(int $userId): RefreshToken
    {
        return RefreshToken::createForUser($userId);
    }

    public function findValid(string $tokenHash): ?RefreshToken
    {
        return RefreshToken::findValid($tokenHash);
    }

    public function revokeAllForUser(int $userId): void
    {
        RefreshToken::revokeAllForUser($userId);
    }
}
