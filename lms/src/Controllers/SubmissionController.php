<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\Submission;
use Lms\Models\Assignment;
use Lms\Models\ActivityLog;

class SubmissionController extends Controller
{
    public function index(Request $req, Response $res, string $assignmentId): void
    {
        $assignment = Assignment::find((int) $assignmentId);
        if (!$assignment) $res->notFound('Assignment not found');

        $this->paginatedQuery(function ($page, $perPage) use ($assignment) {
            $query = Submission::where('assignment_id', $assignment->getKey());
            $total = $query->count();
            $items = $query->orderBy('submitted_at', 'DESC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            foreach ($items as $item) {
                $item->setRelation('user', $item->user());
            }
            return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        });
    }

    public function show(Request $req, Response $res, string $id): void
    {
        $submission = Submission::find((int) $id);
        if (!$submission) $res->notFound('Submission not found');
        $submission->setRelation('user', $submission->user());
        $submission->setRelation('assignment', $submission->assignment());
        $submission->setRelation('grader', $submission->grader());
        $res->success(['submission' => $submission->toArray()]);
    }

    public function submit(Request $req, Response $res, string $assignmentId): void
    {
        $assignment = Assignment::find((int) $assignmentId);
        if (!$assignment) $res->notFound('Assignment not found');
        if (!$assignment->isOpen()) $res->error('NOT_OPEN', 'Assignment is not open for submissions');

        $data = $this->validate($req->body(), [
            'content' => 'string',
            'file_url' => 'string|max:500',
            'file_size' => 'integer',
            'file_type' => 'string|max:100',
        ]);

        $userId = $this->userId();
        $existing = Submission::where('assignment_id', $assignment->getKey())
            ->where('user_id', $userId)
            ->get();
        $attempt = count($existing) + 1;

        if ($assignment->max_attempts > 0 && $attempt > $assignment->max_attempts) {
            $res->error('MAX_ATTEMPTS', 'Maximum attempts reached');
        }

        $submission = new Submission();
        $submission->assignment_id = (int) $assignmentId;
        $submission->user_id = $userId;
        $submission->attempt_number = $attempt;
        $submission->content = $data['content'] ?? null;
        $submission->file_url = $data['file_url'] ?? null;
        $submission->file_size = $data['file_size'] ?? null;
        $submission->file_type = $data['file_type'] ?? null;
        $submission->is_late = $assignment->isPastDue() ? 1 : 0;
        $submission->status = 'submitted';
        $submission->save();

        ActivityLog::log('submission.submitted', $userId, $assignment->course_id, 'submission', $submission->getKey());
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
            $res->error('POINTS_EXCEEDED', "Points cannot exceed {$maxPoints}");
        }

        $submission->grade($data['points'], $this->userId(), $data['feedback'] ?? null);
        ActivityLog::log('submission.graded', $this->userId(), $assignment ? $assignment->course_id : null, 'submission', (int) $id);
        $res->success(['submission' => $submission->toArray()]);
    }

    public function mySubmissions(Request $req, Response $res): void
    {
        $this->paginatedQuery(function ($page, $perPage) use ($req) {
            $query = Submission::where('user_id', $this->userId());
            if ($req->query('assignment_id')) {
                $query->where('assignment_id', (int) $req->query('assignment_id'));
            }
            $total = $query->count();
            $items = $query->orderBy('submitted_at', 'DESC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            foreach ($items as $item) {
                $item->setRelation('assignment', $item->assignment());
            }
            return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        });
    }
}
