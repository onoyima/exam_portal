<?php
namespace Lms\Models;

use Lms\Core\Model;

class Role extends Model
{
    protected static string $table = 'roles';

    public function permissions(): array
    {
        return (new \Lms\Core\QueryBuilder('lms_role_permissions', Permission::class))
            ->join('lms_permissions', 'lms_permissions.id', '=', 'lms_role_permissions.permission_id')
            ->where('lms_role_permissions.role_id', $this->getKey())
            ->get();
    }

    public function users(): array
    {
        return (new \Lms\Core\QueryBuilder('lms_user_roles', User::class))
            ->join('lms_users', 'lms_users.id', '=', 'lms_user_roles.user_id')
            ->where('lms_user_roles.role_id', $this->getKey())
            ->get();
    }

    public static function findBySlug(string $slug): ?static
    {
        return static::findBy('slug', $slug);
    }
}
