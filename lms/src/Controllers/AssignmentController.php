<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\Assignment;
use Lms\Models\Submission;
use Lms\Models\Course;
use Lms\Models\ActivityLog;

class AssignmentController extends Controller
{
    public function index(Request $req, Response $res, string $courseId): void
    {
        $course = Course::findBy('uuid', $courseId) ?? Course::find((int) $courseId);
        if (!$course) $res->notFound('Course not found');

        $assignments = Assignment::where('course_id', $course->getKey())
            ->where('is_visible', 1)
            ->orderBy('due_at', 'ASC')
            ->get();
        $res->success(['assignments' => $assignments]);
    }

    public function show(Request $req, Response $res, string $id): void
    {
        $assignment = Assignment::find((int) $id);
        if (!$assignment) $res->notFound('Assignment not found');
        $res->success(['assignment' => $assignment->toArray()]);
    }

    public function store(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'course_id' => 'required|integer|exists:lms_courses,id',
            'module_id' => 'integer|exists:lms_course_modules,id',
            'title' => 'required|string|max:255',
            'description' => 'string',
            'instructions' => 'string',
            'max_points' => 'required|numeric|min:0',
            'passing_points' => 'numeric|min:0',
            'allow_late_submission' => 'boolean',
            'late_penalty_percent' => 'numeric|between:0,100',
            'max_attempts' => 'integer|min:1',
            'due_at' => 'date',
            'available_from' => 'date',
            'available_until' => 'date',
            'submission_type' => 'required|in:file,text,url,online_text',
        ]);

        $data['created_by'] = $this->userId();
        $assignment = new Assignment();
        foreach ($data as $key => $value) {
            $assignment->$key = $value;
        }
        $assignment->save();
        $res->created(['assignment' => $assignment->toArray()]);
    }

    public function update(Request $req, Response $res, string $id): void
    {
        $assignment = Assignment::find((int) $id);
        if (!$assignment) $res->notFound('Assignment not found');

        $data = $this->validate($req->body(), [
            'title' => 'string|max:255',
            'description' => 'string',
            'max_points' => 'numeric|min:0',
            'due_at' => 'date',
            'available_until' => 'date',
        ]);

        foreach ($data as $key => $value) {
            $assignment->$key = $value;
        }
        $assignment->save();
        $res->success(['assignment' => $assignment->toArray()]);
    }

    public function destroy(Request $req, Response $res, string $id): void
    {
        $assignment = Assignment::find((int) $id);
        if (!$assignment) $res->notFound('Assignment not found');
        $assignment->delete();
        $res->success(['message' => 'Assignment deleted']);
    }

    public function submit(Request $req, Response $res, string $id): void
    {
        $assignment = Assignment::find((int) $id);
        if (!$assignment) $res->notFound('Assignment not found');
        if (!$assignment->isOpen()) $res->error('NOT_OPEN', 'Assignment is not open for submissions');

        $data = $this->validate($req->body(), [
            'content' => 'string',
            'file_url' => 'string|max:500',
        ]);

        $userId = $this->userId();
        $existing = $assignment->submissionsByUser($userId);
        $attempt = count($existing) + 1;

        if ($assignment->max_attempts > 0 && $attempt > $assignment->max_attempts) {
            $res->error('MAX_ATTEMPTS', 'Maximum attempts reached');
        }

        $submission = new Submission();
        $submission->assignment_id = (int) $id;
        $submission->user_id = $userId;
        $submission->attempt_number = $attempt;
        $submission->content = $data['content'] ?? null;
        $submission->file_url = $data['file_url'] ?? null;
        $submission->is_late = $assignment->isPastDue() ? 1 : 0;
        $submission->status = 'submitted';
        $submission->save();

        ActivityLog::log('assignment.submitted', $userId, $assignment->course_id, 'submission', $submission->getKey());
        $res->created(['submission' => $submission->toArray()]);
    }

    public function grade(Request $req, Response $res, string $id): void
    {
        $submission = Submission::find((int) $id);
        if (!$submission) $res->notFound('Submission not found');

        $data = $this->validate($req->body(), [
            'points' => 'required|numeric|min:0',
            'feedback' => 'string',
        ]);

        $assignment = $submission->assignment();
        $maxPoints = $assignment ? (float) $assignment->max_points : 100;
        if ($data['points'] > $maxPoints) {
            $res->error('POINTS_EXCEEDED', "Points cannot exceed $maxPoints");
        }

        $submission->grade($data['points'], $this->userId(), $data['feedback'] ?? null);
        $res->success(['submission' => $submission->toArray()]);
    }
}
