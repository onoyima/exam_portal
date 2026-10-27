<?php
namespace Lms\Models;

use Lms\Core\Model;
use Lms\Core\QueryBuilder;

class Quiz extends Model
{
    protected static string $table = 'quizzes';
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

    public function questions(): array
    {
        return QuizQuestion::where('quiz_id', $this->getKey())
            ->orderBy('order_index')
            ->get();
    }

    public function attempts(): array
    {
        return QuizAttempt::where('quiz_id', $this->getKey())->get();
    }

    public function attemptsByUser(int $userId): array
    {
        return QuizAttempt::where('quiz_id', $this->getKey())
            ->where('user_id', $userId)
            ->orderBy('attempt_number', 'DESC')
            ->get();
    }

    public function totalPoints(): float
    {
        $total = 0;
        foreach ($this->questions() as $q) {
            $total += (float) $q->points;
        }
        return $total;
    }

    public function isAvailable(): bool
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

    public function remainingAttempts(int $userId): int
    {
        $max = (int) ($this->attributes['max_attempts'] ?? 1);
        $used = QuizAttempt::where('quiz_id', $this->getKey())
            ->where('user_id', $userId)
            ->count();
        return max(0, $max - $used);
    }

    public function hasTimeLimit(): bool
    {
        return !empty($this->attributes['time_limit_minutes']);
    }
}
