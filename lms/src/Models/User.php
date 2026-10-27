<?php
namespace Lms\Models;

use Lms\Core\Model;
use Lms\Core\QueryBuilder;

class User extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $useUuid = true;
    protected static bool $useTimestamps = true;
    protected static bool $useSoftDeletes = true;

    protected array $hidden = ['password_hash'];

    public function isPortalUser(): bool
    {
        return !empty($this->attributes['portal_user_id']);
    }

    public function isStudent(): bool
    {
        return ($this->attributes['portal_user_type'] ?? '') === 'student';
    }

    public function isStaff(): bool
    {
        $type = $this->attributes['portal_user_type'] ?? '';
        return $type === 'examiner' || $type === 'staff';
    }

    public function portalId(): ?int
    {
        $id = $this->attributes['portal_user_id'] ?? null;
        return $id ? (int) $id : null;
    }

    public function portalType(): ?string
    {
        return $this->attributes['portal_user_type'] ?? null;
    }

    public function roles(): array
    {
        $type = $this->portalType();
        if ($type === 'student') {
            return [Role::findBySlug('student')];
        }
        $portalRole = $this->resolvePortalRole();
        return [Role::findBySlug($portalRole)];
    }

    public function hasRole(string ...$roles): bool
    {
        $slug = $this->resolvePortalRole();
        return in_array($slug, $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        $slug = $this->resolvePortalRole();
        $sql = "SELECT COUNT(*) FROM lms_role_permissions rp
                JOIN lms_permissions p ON rp.permission_id = p.id
                JOIN lms_roles r ON rp.role_id = r.id
                WHERE r.slug = ? AND p.slug = ?";
        $stmt = static::db()->prepare($sql);
        $stmt->execute([$slug, $permission]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        foreach ($this->hidden as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    public function enrollments(): QueryBuilder
    {
        return Enrollment::where('user_id', $this->getKey());
    }

    public function courses(): array
    {
        if ($this->isStudent() && $this->portalId()) {
            return $this->coursesFromPortal();
        }
        $qb = new QueryBuilder('lms_enrollments', Course::class);
        return $qb->join('lms_courses', 'lms_courses.id', '=', 'lms_enrollments.course_id')
            ->where('lms_enrollments.user_id', $this->getKey())
            ->where('lms_enrollments.status', 'active')
            ->get();
    }

    public function getDisplayName(): string
    {
        return trim($this->attributes['first_name'] . ' ' . $this->attributes['last_name']) ?: 'User';
    }

    private function resolvePortalRole(): string
    {
        $type = $this->portalType();
        if ($type === 'student') return 'student';
        $pid = $this->portalId();
        if ($pid) {
            $db = static::db();
            $stmt = $db->prepare("SELECT role FROM examiners WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$pid]);
            $role = $stmt->fetchColumn();
            return match ($role) {
                'admin' => 'super_admin',
                'examiner' => 'instructor',
                default => 'student',
            };
        }
        return 'student';
    }

    private function coursesFromPortal(): array
    {
        $portalId = $this->portalId();
        $db = static::db();
        $stmt = $db->prepare("
            SELECT DISTINCT c.* FROM lms_courses c
            JOIN lms_exam_links el ON el.course_id = c.id
            JOIN exam_students es ON es.exam_id = el.portal_exam_id
            WHERE es.student_id = ? AND c.deleted_at IS NULL AND c.status = 'published'
        ");
        $stmt->execute([$portalId]);
        $rows = $stmt->fetchAll();
        return array_map(fn($row) => Course::hydrate($row), $rows);
    }
}
