<?php
namespace Lms\Models;

use Lms\Core\Model;
use Lms\Core\QueryBuilder;

class Course extends Model
{
    protected static string $table = 'courses';
    protected static string $primaryKey = 'id';
    protected static bool $useUuid = true;
    protected static bool $useSoftDeletes = true;

    public function modules(): array
    {
        return CourseModule::where('course_id', $this->getKey())
            ->orderBy('order_index')
            ->get();
    }

    public function enrollments(): array
    {
        return Enrollment::where('course_id', $this->getKey())->get();
    }

    public function instructors(): array
    {
        $qb = new QueryBuilder('lms_course_instructors', User::class);
        return $qb->join('lms_users', 'lms_users.id', '=', 'lms_course_instructors.user_id')
            ->where('lms_course_instructors.course_id', $this->getKey())
            ->get();
    }

    public function assignments(): array
    {
        return Assignment::where('course_id', $this->getKey())->get();
    }

    public function quizzes(): array
    {
        return Quiz::where('course_id', $this->getKey())->get();
    }

    public function category(): ?CourseCategory
    {
        if (!$this->attributes['category_id']) {
            return null;
        }
        return CourseCategory::find($this->attributes['category_id']);
    }

    public function studentCount(): int
    {
        return Enrollment::where('course_id', $this->getKey())
            ->where('enrollment_type', 'student')
            ->where('status', 'active')
            ->count();
    }

    public function completionRate(): float
    {
        $total = Enrollment::where('course_id', $this->getKey())
            ->where('enrollment_type', 'student')
            ->count();
        if ($total === 0) return 0.0;
        $completed = Enrollment::where('course_id', $this->getKey())
            ->where('enrollment_type', 'student')
            ->where('status', 'completed')
            ->count();
        return round(($completed / $total) * 100, 2);
    }

    public function creator(): ?User
    {
        if (!$this->attributes['created_by']) {
            return null;
        }
        return User::find($this->attributes['created_by']);
    }
}
