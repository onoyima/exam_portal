<?php
namespace Lms\Models;

use Lms\Core\Model;

class QuizAttempt extends Model
{
    protected static string $table = 'quiz_attempts';

    public function quiz(): ?Quiz
    {
        return Quiz::find($this->attributes['quiz_id']);
    }

    public function user(): ?User
    {
        return User::find($this->attributes['user_id']);
    }

    public function answers(): array
    {
        return QuizAnswer::where('attempt_id', $this->getKey())->get();
    }

    public function isInProgress(): bool
    {
        return $this->attributes['status'] === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->attributes['status'] === 'completed';
    }

    public function timeRemainingSeconds(): int
    {
        $quiz = $this->quiz();
        if (!$quiz || !$quiz->hasTimeLimit()) return PHP_INT_MAX;
        $timeLimit = (int) $quiz->time_limit_minutes * 60;
        $elapsed = time() - strtotime($this->attributes['started_at']);
        return max(0, $timeLimit - $elapsed);
    }

    public function complete(): void
    {
        $this->finished_at = date('Y-m-d H:i:s');
        $this->time_taken_seconds = time() - strtotime($this->attributes['started_at']);
        $this->calculateScore();
        $this->status = 'completed';
        $this->save();
    }

    protected function calculateScore(): void
    {
        $answers = $this->answers();
        $earned = 0;
        $possible = 0;
        foreach ($answers as $answer) {
            $earned += (float) ($answer->points_earned ?? 0);
            $possible += (float) ($answer->points_possible ?? 0);
        }
        $this->score = $earned;
        $this->max_score = $possible;
        $this->percentage = $possible > 0 ? round(($earned / $possible) * 100, 2) : 0;
        $quiz = $this->quiz();
        if ($quiz && $quiz->passing_score) {
            $this->passed = $this->percentage >= (float) $quiz->passing_score;
        } else {
            $this->passed = null;
        }
    }
}
