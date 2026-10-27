<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\ContentLibrary;
use Lms\Models\LessonAttachment;
use Lms\Models\Course;
use Lms\Models\ActivityLog;

class ContentController extends Controller
{
    public function library(Request $req, Response $res, string $courseId = null): void
    {
        $this->paginatedQuery(function ($page, $perPage) use ($req, $courseId) {
            $query = ContentLibrary::where('id', '>', 0);
            if ($courseId) {
                $query->where('course_id', (int) $courseId);
            }
            if ($req->query('type')) {
                $query->where('file_type', $req->query('type'));
            }
            if ($req->query('search')) {
                $s = $req->query('search');
                $query->where('title', 'LIKE', "%$s%");
            }
            $total = $query->count();
            $items = $query->orderBy('created_at', 'DESC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            foreach ($items as $item) {
                $item->setRelation('uploader', $item->uploader());
            }
            return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        });
    }

    public function upload(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'course_id' => 'integer|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'file_type' => 'required|string|max:100',
            'file_url' => 'required|string|max:500',
            'file_size' => 'required|integer',
            'mime_type' => 'string|max:100',
            'description' => 'string',
            'is_public' => 'boolean',
        ]);

        $content = new ContentLibrary();
        $content->uploader_id = $this->userId();
        foreach ($data as $key => $value) {
            $content->$key = $value;
        }
        $content->save();

        ActivityLog::log('content.uploaded', $this->userId(), $data['course_id'] ?? null, 'content', $content->getKey());
        $res->created(['content' => $content->toArray()]);
    }

    public function showContent(Request $req, Response $res, string $id): void
    {
        $content = ContentLibrary::find((int) $id);
        if (!$content) $res->notFound('Content not found');
        $content->incrementViews();
        $content->setRelation('uploader', $content->uploader());
        $res->success(['content' => $content->toArray()]);
    }

    public function destroyContent(Request $req, Response $res, string $id): void
    {
        $content = ContentLibrary::find((int) $id);
        if (!$content) $res->notFound('Content not found');
        $content->delete();
        $res->success(['message' => 'Content deleted']);
    }

    public function download(Request $req, Response $res, string $id): void
    {
        $content = ContentLibrary::find((int) $id);
        if (!$content) $res->notFound('Content not found');
        $content->incrementDownloads();
        $res->success([
            'file_url' => $content->file_url,
            'file_name' => $content->title,
            'file_size' => $content->file_size,
            'mime_type' => $content->mime_type,
        ]);
    }

    public function attachToLesson(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'lesson_id' => 'required|integer|exists:lms_lessons,id',
            'title' => 'required|string|max:255',
            'file_url' => 'required|string|max:500',
            'file_size' => 'required|integer',
            'mime_type' => 'string|max:100',
        ]);

        $attachment = new LessonAttachment();
        foreach ($data as $key => $value) {
            $attachment->$key = $value;
        }
        $attachment->save();
        $res->created(['attachment' => $attachment->toArray()]);
    }
}
