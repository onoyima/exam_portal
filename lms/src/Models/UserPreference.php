<?php
namespace Lms\Models;

use Lms\Core\Model;

class UserPreference extends Model
{
    protected static string $table = 'user_preferences';

    public function user(): ?User
    {
        return User::find($this->attributes['user_id']);
    }

    public function getPreferences(): array
    {
        $prefs = $this->attributes['preferences'] ?? '{}';
        if (is_string($prefs)) {
            return json_decode($prefs, true) ?? [];
        }
        return $prefs;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $prefs = $this->getPreferences();
        return $prefs[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $prefs = $this->getPreferences();
        $prefs[$key] = $value;
        $this->preferences = json_encode($prefs);
        $this->save();
    }

    public static function forUser(int $userId): static
    {
        $pref = static::findBy('user_id', $userId);
        if (!$pref) {
            $pref = new static();
            $pref->user_id = $userId;
            $pref->preferences = '{}';
            $pref->save();
        }
        return $pref;
    }
}
