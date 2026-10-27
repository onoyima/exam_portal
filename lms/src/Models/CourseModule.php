<?php
namespace Lms\Models;

use Lms\Core\Model;

class CourseModule extends Model
{
    protected static string $table = 'course_modules';
    protected static bool $useSoftDeletes = true;

    public function lessons(): array
    {
        return Lesson::where('module_id', $this->getKey())
            ->orderBy('order_index')
            ->get();
    }

    public function course(): ?Course
    {
        return Course::find($this->attributes['course_id']);
    }

    public function lessonCount(): int
    {
        return Lesson::where('module_id', $this->getKey())->count();
    }
}
