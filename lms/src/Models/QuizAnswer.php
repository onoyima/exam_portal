<?php
namespace Lms\Models;

use Lms\Core\Model;

class QuizAnswer extends Model
{
    protected static string $table = 'quiz_answers';
    protected static bool $useTimestamps = false;

    public function attempt(): ?QuizAttempt
    {
        return QuizAttempt::find($this->attributes['attempt_id']);
    }

    public function question(): ?QuizQuestion
    {
        return QuizQuestion::find($this->attributes['question_id']);
    }

    public function score(float $points): void
    {
        $this->points_earned = $points;
        $this->is_correct = $points > 0 ? 1 : 0;
        $this->save();
    }
}
