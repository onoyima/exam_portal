<?php
namespace Lms\Models;

use Lms\Core\Model;

class CourseCategory extends Model
{
    protected static string $table = 'course_categories';

    public function children(): array
    {
        return static::where('parent_id', $this->getKey())->get();
    }

    public function parent(): ?CourseCategory
    {
        if (!$this->attributes['parent_id']) {
            return null;
        }
        return static::find($this->attributes['parent_id']);
    }

    public function courses(): array
    {
        return Course::where('category_id', $this->getKey())->get();
    }
}
