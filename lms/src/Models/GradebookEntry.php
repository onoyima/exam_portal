<?php
namespace Lms\Models;

use Lms\Core\Model;

class GradebookEntry extends Model
{
    protected static string $table = 'gradebook';

    public function user(): ?User
    {
        return User::find($this->attributes['user_id']);
    }

    public function course(): ?Course
    {
        return Course::find($this->attributes['course_id']);
    }

    public function gradeItem(): ?GradeItem
    {
        return GradeItem::find($this->attributes['grade_item_id']);
    }

    public function grader(): ?User
    {
        if (!$this->attributes['graded_by']) return null;
        return User::find($this->attributes['graded_by']);
    }
}
