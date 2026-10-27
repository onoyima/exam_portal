<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Permission;

class PermissionRepository extends Repository
{
    protected function getTable(): string { return 'permissions'; }
    protected function getModelClass(): string { return Permission::class; }

    public function findBySlug(string $slug): ?Permission
    {
        return Permission::findBySlug($slug);
    }

    public function findByGroup(string $group): array
    {
        return Permission::findByGroup($group);
    }

    public function getByRole(int $roleId): array
    {
        $db = $this->db;
        $stmt = $db->prepare("
            SELECT p.* FROM lms_permissions p
            JOIN lms_role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.perm_group, p.name
        ");
        $stmt->execute([$roleId]);
        return Permission::hydrate($stmt->fetchAll());
    }
}
