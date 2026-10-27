<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Enrollment;

class EnrollmentRepository extends Repository
{
    protected function getTable(): string { return 'enrollments'; }
    protected function getModelClass(): string { return Enrollment::class; }

    public function isEnrolled(int $userId, int $courseId): bool
    {
        return Enrollment::isEnrolled($userId, $courseId);
    }

    public function getByCourse(int $courseId): array
    {
        return Enrollment::where('course_id', '=', $courseId)
            ->where('status', '=', 'active')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByUser(int $userId): array
    {
        return Enrollment::where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getActiveByUser(int $userId): array
    {
        return Enrollment::where('user_id', '=', $userId)
            ->where('status', '=', 'active')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getCompletedByUser(int $userId): array
    {
        return Enrollment::where('user_id', '=', $userId)
            ->where('status', '=', 'completed')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function countByCourse(int $courseId): int
    {
        return Enrollment::where('course_id', '=', $courseId)
            ->where('status', '=', 'active')
            ->count();
    }
}
