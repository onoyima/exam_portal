<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\DiscussionThread;

class DiscussionThreadRepository extends Repository
{
    protected function getTable(): string { return 'discussion_threads'; }
    protected function getModelClass(): string { return DiscussionThread::class; }

    public function getByCourse(int $courseId): array
    {
        return DiscussionThread::where('course_id', '=', $courseId)
            ->orderBy('is_pinned', 'DESC')
            ->orderBy('updated_at', 'DESC')
            ->get();
    }

    public function getByAuthor(int $userId): array
    {
        return DiscussionThread::where('created_by', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getPinned(int $courseId): array
    {
        return DiscussionThread::where('course_id', '=', $courseId)
            ->where('is_pinned', '=', 1)
            ->orderBy('updated_at', 'DESC')
            ->get();
    }
}
