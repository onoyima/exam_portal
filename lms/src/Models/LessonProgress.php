<?php
namespace Lms\Models;

use Lms\Core\Model;

class LessonProgress extends Model
{
    protected static string $table = 'lesson_progress';

    public function user(): ?User
    {
        return User::find($this->attributes['user_id']);
    }

    public function lesson(): ?Lesson
    {
        return Lesson::find($this->attributes['lesson_id']);
    }

    public function markCompleted(): void
    {
        $this->status = 'completed';
        $this->progress_percent = 100;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save();
    }

    public function updateProgress(int $percent, int $timeSpentSeconds): void
    {
        $this->progress_percent = min(100, $percent);
        $this->time_spent_seconds = $timeSpentSeconds;
        if ($percent >= 100) {
            $this->status = 'completed';
            $this->completed_at = $this->completed_at ?? date('Y-m-d H:i:s');
        } elseif ($percent > 0) {
            $this->status = 'in_progress';
        }
        $this->last_accessed_at = date('Y-m-d H:i:s');
        $this->save();
    }

    public static function getOrCreate(int $userId, int $lessonId): static
    {
        $existing = static::where('user_id', $userId)
            ->where('lesson_id', $lessonId)
            ->first();
        if ($existing) {
            return $existing;
        }
        $p = new static();
        $p->user_id = $userId;
        $p->lesson_id = $lessonId;
        $p->status = 'not_started';
        $p->save();
        return $p;
    }
}
