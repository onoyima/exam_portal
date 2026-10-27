<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\DiscussionReply;

class DiscussionReplyRepository extends Repository
{
    protected function getTable(): string { return 'discussion_replies'; }
    protected function getModelClass(): string { return DiscussionReply::class; }

    public function getByThread(int $threadId): array
    {
        return DiscussionReply::where('thread_id', '=', $threadId)
            ->orderBy('created_at', 'ASC')
            ->get();
    }

    public function getByAuthor(int $userId): array
    {
        return DiscussionReply::where('created_by', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getSolutionsByThread(int $threadId): array
    {
        return DiscussionReply::where('thread_id', '=', $threadId)
            ->where('is_solution', '=', 1)
            ->get();
    }
}
