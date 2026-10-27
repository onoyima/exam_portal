<?php
namespace Lms\Models;

use Lms\Core\Model;

class QuizQuestion extends Model
{
    protected static string $table = 'quiz_questions';
    protected static bool $useTimestamps = true;

    public function quiz(): ?Quiz
    {
        return Quiz::find($this->attributes['quiz_id']);
    }

    public function isCorrect(mixed $answer): bool
    {
        $correct = json_decode($this->attributes['correct_answer'] ?? '[]', true);
        if (is_string($correct)) $correct = [$correct];

        $given = is_string($answer) ? [$answer] : (array) $answer;
        sort($correct);
        sort($given);
        return $correct === $given;
    }

    public function getOptions(): ?array
    {
        $opts = $this->attributes['options'] ?? null;
        if (is_string($opts)) {
            return json_decode($opts, true);
        }
        return $opts;
    }
}
