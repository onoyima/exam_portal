<?php
namespace Lms\Models;

use Lms\Core\Model;

class Enrollment extends Model
{
    protected static string $table = 'enrollments';

    public function user(): ?User
    {
        return User::find($this->attributes['user_id']);
    }

    public function course(): ?Course
    {
        return Course::find($this->attributes['course_id']);
    }

    public static function isEnrolled(int $userId, int $courseId, string $type = 'student'): bool
    {
        return static::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('enrollment_type', $type)
            ->where('status', 'active')
            ->exists();
    }

    public static function enrollStudent(int $userId, int $courseId): static
    {
        $enrollment = new static();
        $enrollment->user_id = $userId;
        $enrollment->course_id = $courseId;
        $enrollment->enrollment_type = 'student';
        $enrollment->status = 'active';
        $enrollment->save();
        return $enrollment;
    }

    public function markComplete(): void
    {
        $this->status = 'completed';
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save();
    }

    public function isActive(): bool
    {
        return $this->attributes['status'] === 'active';
    }
}
