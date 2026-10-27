<?php
namespace Lms\Models;

use Lms\Core\Model;

class Submission extends Model
{
    protected static string $table = 'submissions';

    public function assignment(): ?Assignment
    {
        return Assignment::find($this->attributes['assignment_id']);
    }

    public function user(): ?User
    {
        return User::find($this->attributes['user_id']);
    }

    public function grader(): ?User
    {
        if (!$this->attributes['graded_by']) return null;
        return User::find($this->attributes['graded_by']);
    }

    public function isLate(): bool
    {
        return (bool) ($this->attributes['is_late'] ?? false);
    }

    public function isGraded(): bool
    {
        return $this->attributes['status'] === 'graded';
    }

    public function grade(float $points, int $graderId, ?string $feedback = null): void
    {
        $this->grade = $points;
        $this->graded_by = $graderId;
        $this->graded_at = date('Y-m-d H:i:s');
        $this->feedback = $feedback;
        $this->status = 'graded';
        $this->save();
    }
}
