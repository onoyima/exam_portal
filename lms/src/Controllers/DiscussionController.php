<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\DiscussionThread;
use Lms\Models\DiscussionReply;
use Lms\Models\ActivityLog;

class DiscussionController extends Controller
{
    public function threads(Request $req, Response $res, string $courseId): void
    {
        $this->paginatedQuery(function ($page, $perPage) use ($courseId) {
            $query = DiscussionThread::where('course_id', (int) $courseId)
                ->where('is_visible', 1);
            $total = $query->count();
            $threads = $query->orderBy('is_pinned', 'DESC')
                ->orderBy('created_at', 'DESC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            foreach ($threads as $t) {
                $t->setRelation('author', $t->author());
                $t->setRelation('reply_count', $t->replyCount());
            }
            return ['items' => $threads, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        });
    }

    public function showThread(Request $req, Response $res, string $id): void
    {
        $thread = DiscussionThread::find((int) $id);
        if (!$thread) $res->notFound('Thread not found');

        $thread->setRelation('author', $thread->author());
        $thread->setRelation('replies', $thread->replies());
        $res->success(['thread' => $thread->toArray()]);
    }

    public function createThread(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'course_id' => 'required|integer|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_announcement' => 'boolean',
        ]);

        $thread = new DiscussionThread();
        $thread->course_id = (int) $data['course_id'];
        $thread->title = $data['title'];
        $thread->content = $data['content'];
        $thread->is_announcement = $data['is_announcement'] ?? 0;
        $thread->created_by = $this->userId();
        $thread->save();

        ActivityLog::log('discussion.created', $this->userId(), (int) $data['course_id'], 'thread', $thread->getKey());
        $res->created(['thread' => $thread->toArray()]);
    }

    public function reply(Request $req, Response $res, string $threadId): void
    {
        $thread = DiscussionThread::find((int) $threadId);
        if (!$thread) $res->notFound('Thread not found');
        if ($thread->is_closed) $res->error('THREAD_CLOSED', 'Thread is closed');

        $data = $this->validate($req->body(), [
            'content' => 'required|string',
            'parent_id' => 'integer|exists:lms_discussion_replies,id',
        ]);

        $reply = new DiscussionReply();
        $reply->thread_id = (int) $threadId;
        $reply->parent_id = $data['parent_id'] ?? null;
        $reply->content = $data['content'];
        $reply->created_by = $this->userId();
        $reply->save();

        $res->created(['reply' => $reply->toArray()]);
    }
}
