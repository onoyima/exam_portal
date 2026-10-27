<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\Submission;
use Lms\Models\Assignment;

class SubmissionRepository extends Repository
{
    protected function getTable(): string { return 'submissions'; }
    protected function getModelClass(): string { return Submission::class; }

    public function getByAssignment(int $assignmentId): array
    {
        return Submission::where('assignment_id', '=', $assignmentId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByUser(int $userId): array
    {
        return Submission::where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByUserAndAssignment(int $userId, int $assignmentId): array
    {
        return Submission::where('user_id', '=', $userId)
            ->where('assignment_id', '=', $assignmentId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getUngraded(int $limit = 20): array
    {
        return Submission::where('status', '=', 'submitted')
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    public function getByGrader(int $graderId): array
    {
        return Submission::where('graded_by', '=', $graderId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getLateSubmissions(int $assignmentId): array
    {
        $assignment = Assignment::find($assignmentId);
        if (!$assignment || !$assignment->due_at) {
            return [];
        }
        return Submission::where('assignment_id', '=', $assignmentId)
            ->where('created_at', '>', $assignment->due_at)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function countByAssignment(int $assignmentId): int
    {
        return Submission::where('assignment_id', '=', $assignmentId)->count();
    }

    public function countGradedByAssignment(int $assignmentId): int
    {
        return Submission::where('assignment_id', '=', $assignmentId)
            ->where('status', '=', 'graded')
            ->count();
    }
}
