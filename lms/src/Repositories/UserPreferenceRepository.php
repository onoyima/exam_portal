<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\UserPreference;

class UserPreferenceRepository extends Repository
{
    protected function getTable(): string { return 'user_preferences'; }
    protected function getModelClass(): string { return UserPreference::class; }

    public function forUser(int $userId): UserPreference
    {
        return UserPreference::forUser($userId);
    }

    public function getPreference(int $userId, string $key, mixed $default = null): mixed
    {
        $prefs = UserPreference::forUser($userId);
        return $prefs->get($key, $default);
    }

    public function setPreference(int $userId, string $key, mixed $value): void
    {
        $prefs = UserPreference::forUser($userId);
        $prefs->set($key, $value);
    }
}
