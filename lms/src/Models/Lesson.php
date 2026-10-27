<?php
namespace Lms\Models;

use Lms\Core\Model;

class Lesson extends Model
{
    protected static string $table = 'lessons';
    protected static bool $useSoftDeletes = true;

    public function module(): ?CourseModule
    {
        return CourseModule::find($this->attributes['module_id']);
    }

    public function course(): ?Course
    {
        $module = $this->module();
        if (!$module) return null;
        return $module->course();
    }

    public function attachments(): array
    {
        return LessonAttachment::where('lesson_id', $this->getKey())
            ->orderBy('sort_order')
            ->get();
    }

    public function progress(int $userId): ?LessonProgress
    {
        return LessonProgress::where('user_id', $userId)
            ->where('lesson_id', $this->getKey())
            ->first();
    }
}
