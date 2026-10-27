<?php
namespace Lms\Models;

use Lms\Core\Model;
use Lms\Core\QueryBuilder;

class DiscussionThread extends Model
{
    protected static string $table = 'discussion_threads';
    protected static bool $useSoftDeletes = true;

    public function course(): ?Course
    {
        return Course::find($this->attributes['course_id']);
    }

    public function author(): ?User
    {
        return User::find($this->attributes['created_by']);
    }

    public function replies(): array
    {
        return DiscussionReply::where('thread_id', $this->getKey())
            ->whereNull('parent_id')
            ->orderBy('created_at', 'ASC')
            ->get();
    }

    public function allReplies(): array
    {
        return DiscussionReply::where('thread_id', $this->getKey())
            ->orderBy('created_at', 'ASC')
            ->get();
    }

    public function replyCount(): int
    {
        return DiscussionReply::where('thread_id', $this->getKey())->count();
    }

    public function close(): void
    {
        $this->is_closed = 1;
        $this->save();
    }

    public function pin(): void
    {
        $this->is_pinned = 1;
        $this->save();
    }
}
