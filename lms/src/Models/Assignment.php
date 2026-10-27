<?php
namespace Lms\Models;

use Lms\Core\Model;

class Assignment extends Model
{
    protected static string $table = 'assignments';
    protected static bool $useSoftDeletes = true;

    public function course(): ?Course
    {
        return Course::find($this->attributes['course_id']);
    }

    public function module(): ?CourseModule
    {
        if (!$this->attributes['module_id']) return null;
        return CourseModule::find($this->attributes['module_id']);
    }

    public function submissions(): array
    {
        return Submission::where('assignment_id', $this->getKey())->get();
    }

    public function submissionsByUser(int $userId): array
    {
        return Submission::where('assignment_id', $this->getKey())
            ->where('user_id', $userId)
            ->orderBy('attempt_number', 'DESC')
            ->get();
    }

    public function submissionCount(): int
    {
        return Submission::where('assignment_id', $this->getKey())->count();
    }

    public function isOpen(): bool
    {
        $now = time();
        if ($this->attributes['available_from'] && strtotime($this->attributes['available_from']) > $now) {
            return false;
        }
        if ($this->attributes['available_until'] && strtotime($this->attributes['available_until']) < $now) {
            return false;
        }
        return true;
    }

    public function isPastDue(): bool
    {
        if (!$this->attributes['due_at']) return false;
        return strtotime($this->attributes['due_at']) < time();
    }
}
