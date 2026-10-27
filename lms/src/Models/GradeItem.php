<?php
namespace Lms\Models;

use Lms\Core\Model;

class GradeItem extends Model
{
    protected static string $table = 'grade_items';

    public function course(): ?Course
    {
        return Course::find($this->attributes['course_id']);
    }

    public function grades(): array
    {
        return GradebookEntry::where('grade_item_id', $this->getKey())->get();
    }

    public function averageScore(): float
    {
        $grades = $this->grades();
        if (empty($grades)) return 0;
        $sum = 0;
        $count = 0;
        foreach ($grades as $g) {
            if ($g->points_earned !== null) {
                $sum += (float) $g->points_earned;
                $count++;
            }
        }
        return $count > 0 ? round($sum / $count, 2) : 0;
    }
}
