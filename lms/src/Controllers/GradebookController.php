<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\GradeItem;
use Lms\Models\GradebookEntry;
use Lms\Models\Course;

class GradebookController extends Controller
{
    public function items(Request $req, Response $res, string $courseId): void
    {
        $course = Course::findBy('uuid', $courseId) ?? Course::find((int) $courseId);
        if (!$course) $res->notFound('Course not found');

        $items = GradeItem::where('course_id', $course->getKey())
            ->where('is_active', 1)
            ->get();

        $results = [];
        foreach ($items as $item) {
            $data = $item->toArray();
            $data['average_score'] = $item->averageScore();
            $results[] = $data;
        }
        $res->success(['grade_items' => $results]);
    }

    public function studentGrades(Request $req, Response $res, string $courseId, string $userId = null): void
    {
        $course = Course::findBy('uuid', $courseId) ?? Course::find((int) $courseId);
        if (!$course) $res->notFound('Course not found');

        $uid = $userId ? (int) $userId : $this->userId();
        $grades = GradebookEntry::where('user_id', $uid)
            ->where('course_id', $course->getKey())
            ->get();

        $results = [];
        foreach ($grades as $g) {
            $item = $g->gradeItem();
            $data = $g->toArray();
            $data['item_title'] = $item ? $item->title : null;
            $data['max_points'] = $item ? $item->max_points : null;
            $results[] = $data;
        }
        $res->success(['grades' => $results]);
    }

    public function createItem(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'course_id' => 'required|integer|exists:lms_courses,id',
            'item_type' => 'required|in:assignment,quiz,participation,manual,final_exam',
            'item_id' => 'integer',
            'title' => 'required|string|max:255',
            'max_points' => 'required|numeric|min:0',
            'weight' => 'numeric|between:0,100',
            'category' => 'string|max:100',
        ]);

        $item = new GradeItem();
        foreach ($data as $key => $value) {
            $item->$key = $value;
        }
        $item->save();
        $res->created(['grade_item' => $item->toArray()]);
    }

    public function updateGrade(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'user_id' => 'required|integer|exists:lms_users,id',
            'grade_item_id' => 'required|integer|exists:lms_grade_items,id',
            'points_earned' => 'required|numeric|min:0',
            'comments' => 'string',
        ]);

        $entry = GradebookEntry::where('user_id', $data['user_id'])
            ->where('grade_item_id', $data['grade_item_id'])
            ->first();

        if (!$entry) {
            $item = GradeItem::find($data['grade_item_id']);
            if (!$item) $res->notFound('Grade item not found');

            $entry = new GradebookEntry();
            $entry->user_id = $data['user_id'];
            $entry->course_id = $item->course_id;
            $entry->grade_item_id = $data['grade_item_id'];
        }

        $entry->points_earned = $data['points_earned'];
        if ($item ?? null) {
            $entry->percentage = $item->max_points > 0
                ? round(($data['points_earned'] / $item->max_points) * 100, 2) : 0;
        }
        $entry->comments = $data['comments'] ?? null;
        $entry->graded_by = $this->userId();
        $entry->graded_at = date('Y-m-d H:i:s');
        $entry->save();

        $res->success(['grade_entry' => $entry->toArray()]);
    }
}
