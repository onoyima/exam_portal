<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Role;

class RoleRepository extends Repository
{
    protected function getTable(): string { return 'roles'; }
    protected function getModelClass(): string { return Role::class; }

    public function findBySlug(string $slug): ?Role
    {
        return Role::findBySlug($slug);
    }

    public function getByUser(int $userId): array
    {
        $db = $this->db;
        $stmt = $db->prepare("
            SELECT r.* FROM lms_roles r
            JOIN lms_user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        return Role::hydrate($stmt->fetchAll());
    }
}
