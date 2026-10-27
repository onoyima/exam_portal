<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Core\Logger;
use Lms\Models\Lesson;
use Lms\Models\CourseModule;
use Lms\Models\LessonProgress;
use Lms\Models\ActivityLog;
use Lms\Models\GradeItem;
use Lms\Models\GradebookEntry;
use Lms\Services\PortalSyncService;

class LessonController extends Controller
{
    public function index(Request $req, Response $res, string $moduleId): void
    {
        $module = CourseModule::find((int) $moduleId);
        if (!$module) $res->notFound('Module not found');

        $lessons = Lesson::where('module_id', $module->getKey())
            ->orderBy('order_index')
            ->get();
        $res->success(['lessons' => $lessons]);
    }

    public function show(Request $req, Response $res, string $id): void
    {
        $lesson = Lesson::find((int) $id);
        if (!$lesson) $res->notFound('Lesson not found');
        $lesson->setRelation('attachments', $lesson->attachments());
        $res->success(['lesson' => $lesson->toArray()]);
    }

    public function store(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'module_id' => 'required|integer|exists:lms_course_modules,id',
            'title' => 'required|string|max:255',
            'content_type' => 'required|in:text,video,h5p,scorm,pdf,quiz,assignment,discussion',
            'content' => 'string',
            'video_url' => 'string|max:500',
            'video_duration' => 'integer',
            'duration_minutes' => 'integer',
            'is_visible' => 'boolean',
            'is_required' => 'boolean',
            'allow_download' => 'boolean',
        ]);

        $maxOrder = Lesson::where('module_id', $data['module_id'])->max('order_index');
        $data['order_index'] = ($maxOrder ?? -1) + 1;
        $data['created_by'] = $this->userId();
        $data['slug'] = $this->slugify($data['title']);

        $lesson = new Lesson();
        foreach ($data as $key => $value) {
            $lesson->$key = $value;
        }
        $lesson->save();

        ActivityLog::log('lesson.created', $this->userId(), null, 'lesson', $lesson->getKey());
        $res->created(['lesson' => $lesson->toArray()]);
    }

    public function update(Request $req, Response $res, string $id): void
    {
        $lesson = Lesson::find((int) $id);
        if (!$lesson) $res->notFound('Lesson not found');

        $data = $this->validate($req->body(), [
            'title' => 'string|max:255',
            'content' => 'string',
            'video_url' => 'string|max:500',
            'video_duration' => 'integer',
            'duration_minutes' => 'integer',
            'is_visible' => 'boolean',
            'is_required' => 'boolean',
            'allow_download' => 'boolean',
            'order_index' => 'integer',
        ]);

        foreach ($data as $key => $value) {
            $lesson->$key = $value;
        }
        $lesson->save();
        $res->success(['lesson' => $lesson->toArray()]);
    }

    public function destroy(Request $req, Response $res, string $id): void
    {
        $lesson = Lesson::find((int) $id);
        if (!$lesson) $res->notFound('Lesson not found');
        $lesson->delete();
        $res->success(['message' => 'Lesson deleted']);
    }

    public function progress(Request $req, Response $res, string $id): void
    {
        $lesson = Lesson::find((int) $id);
        if (!$lesson) $res->notFound('Lesson not found');

        $data = $this->validate($req->body(), [
            'progress_percent' => 'integer|between:0,100',
            'time_spent_seconds' => 'integer',
        ]);

        $progress = LessonProgress::getOrCreate($this->userId(), (int) $id);
        $progress->updateProgress($data['progress_percent'] ?? 0, $data['time_spent_seconds'] ?? 0);

        ActivityLog::log('lesson.progress', $this->userId(), null, 'lesson', (int) $id, [
            'progress' => $data['progress_percent'] ?? 0,
            'time_spent' => $data['time_spent_seconds'] ?? 0,
        ]);

        $res->success(['progress' => $progress->toArray()]);
    }

    public function complete(Request $req, Response $res, string $id): void
    {
        $lesson = Lesson::find((int) $id);
        if (!$lesson) $res->notFound('Lesson not found');

        $progress = LessonProgress::getOrCreate($this->userId(), (int) $id);
        $progress->markCompleted();

        $module = $lesson->module();
        $course = $module ? $module->course() : null;
        if ($course) {
            $item = GradeItem::where('course_id', $course->getKey())
                ->where('item_type', 'lesson_completion')
                ->where('item_id', $lesson->getKey())
                ->first();
            if (!$item) {
                $item = new GradeItem();
                $item->course_id = $course->getKey();
                $item->item_type = 'lesson_completion';
                $item->item_id = $lesson->getKey();
                $item->title = $lesson->title . ' (Completion)';
                $item->max_points = 100;
                $item->weight = 0;
                $item->category = 'participation';
                $item->save();
            }
            $entry = GradebookEntry::where('user_id', $this->userId())
                ->where('grade_item_id', $item->getKey())
                ->first();
            if (!$entry) {
                $entry = new GradebookEntry();
                $entry->user_id = $this->userId();
                $entry->course_id = $course->getKey();
                $entry->grade_item_id = $item->getKey();
                $entry->points_possible = 100;
            }
            $entry->points_earned = 100;
            $entry->percentage = 100;
            $entry->graded_by = $this->userId();
            $entry->graded_at = date('Y-m-d H:i:s');
            $entry->save();

            $sync = new PortalSyncService();
            $user = \Lms\Models\User::find($this->userId());
            if ($user && $user->isStudent() && $user->portalId()) {
                $sync->syncStudentAcademicContext($this->userId(), $user->portalId());
            }
        }

        Logger::info('Lesson completed', ['user_id' => $this->userId(), 'lesson_id' => (int) $id]);
        ActivityLog::log('lesson.completed', $this->userId(), $course ? $course->getKey() : null, 'lesson', (int) $id);
        $res->success(['progress' => $progress->toArray()]);
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', strtolower(trim($text)));
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-') ?: 'lesson';
    }
}
