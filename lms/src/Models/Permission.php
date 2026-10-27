<?php
namespace Lms\Models;

use Lms\Core\Model;

class Permission extends Model
{
    protected static string $table = 'permissions';
    protected static bool $useTimestamps = false;

    public static function findBySlug(string $slug): ?static
    {
        return static::findBy('slug', $slug);
    }

    public static function findByGroup(string $group): array
    {
        return static::where('perm_group', $group)->get();
    }
}
