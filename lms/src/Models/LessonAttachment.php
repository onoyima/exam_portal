<?php
namespace Lms\Models;

use Lms\Core\Model;

class LessonAttachment extends Model
{
    protected static string $table = 'lesson_attachments';
    protected static bool $useTimestamps = false;

    public function lesson(): ?Lesson
    {
        return Lesson::find($this->attributes['lesson_id']);
    }
}
