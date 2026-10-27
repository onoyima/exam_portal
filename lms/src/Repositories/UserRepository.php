<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\User;

class UserRepository extends Repository
{
    protected function getTable(): string { return 'users'; }
    protected function getModelClass(): string { return User::class; }

    public function findByPortalId(int $portalId): ?User
    {
        return User::findBy('portal_user_id', $portalId);
    }

    public function findActiveByEmail(string $email): ?User
    {
        return User::where('email', '=', $email)
            ->where('is_active', '=', 1)
            ->first();
    }

    public function getByRole(string $role): array
    {
        return User::where('portal_user_type', '=', $role)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByCourse(int $courseId): array
    {
        return User::where('id', 'IN', function ($q) use ($courseId) {
            $q->select('user_id')->from('enrollments')->where('course_id', '=', $courseId);
        })->get();
    }

    public function search(string $term, int $limit = 20): array
    {
        return User::where('first_name', 'LIKE', "%{$term}%")
            ->orWhere('last_name', 'LIKE', "%{$term}%")
            ->orWhere('email', 'LIKE', "%{$term}%")
            ->limit($limit)
            ->get();
    }
}
