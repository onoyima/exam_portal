<?php
namespace Lms\Models;

use Lms\Core\Model;

class DiscussionReply extends Model
{
    protected static string $table = 'discussion_replies';
    protected static bool $useSoftDeletes = true;

    public function thread(): ?DiscussionThread
    {
        return DiscussionThread::find($this->attributes['thread_id']);
    }

    public function author(): ?User
    {
        return User::find($this->attributes['created_by']);
    }

    public function parent(): ?DiscussionReply
    {
        if (!$this->attributes['parent_id']) return null;
        return static::find($this->attributes['parent_id']);
    }

    public function children(): array
    {
        return static::where('parent_id', $this->getKey())
            ->orderBy('created_at', 'ASC')
            ->get();
    }

    public function markAsSolution(): void
    {
        $this->is_solution = 1;
        $this->save();
    }
}
