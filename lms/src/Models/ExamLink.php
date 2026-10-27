<?php
namespace Lms\Models;

use Lms\Core\Model;

class ExamLink extends Model
{
    protected static string $table = 'exam_links';

    public function quiz(): ?Quiz
    {
        if (!$this->attributes['lms_quiz_id']) return null;
        return Quiz::find($this->attributes['lms_quiz_id']);
    }

    public function course(): ?Course
    {
        return Course::find($this->attributes['course_id']);
    }

    public function module(): ?CourseModule
    {
        if (!$this->attributes['module_id']) return null;
        return CourseModule::find($this->attributes['module_id']);
    }
}
