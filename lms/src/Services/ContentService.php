<?php
namespace Lms\Services;

use Lms\Core\Event;
use Lms\Core\Logger;
use Lms\Models\ContentLibrary;
use Lms\Models\LessonAttachment;
use Lms\Models\Course;

class ContentService
{
    public static function upload(array $data, int $userId): ?ContentLibrary
    {
        $content = new ContentLibrary();
        $content->uploader_id = $userId;
        foreach ($data as $key => $value) {
            if (in_array($key, ['course_id', 'title', 'file_type', 'file_url', 'file_size', 'mime_type', 'description', 'is_public', 'duration_seconds', 'thumbnail_url', 'tags'])) {
                $content->$key = $value;
            }
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $content->tags = json_encode($data['tags']);
        }

        $content->save();

        Event::dispatch('content.uploaded', [
            'content_id' => $content->getKey(),
            'user_id' => $userId,
            'course_id' => $data['course_id'] ?? null,
            'file_type' => $data['file_type'],
        ]);

        Logger::info('Content uploaded', [
            'content_id' => $content->getKey(),
            'user_id' => $userId,
            'file_type' => $data['file_type'],
        ]);

        return $content;
    }

    public static function getCourseContent(int $courseId, array $filters = []): array
    {
        $query = ContentLibrary::where('course_id', $courseId)
            ->where('deleted_at', 'IS', null);

        if (!empty($filters['type'])) {
            $query->where('file_type', $filters['type']);
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where('title', 'LIKE', "%{$s}%");
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        $total = $query->count();
        $items = $query->orderBy('created_at', 'DESC')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        foreach ($items as $item) {
            $item->setRelation('uploader', $item->uploader());
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / max($perPage, 1)),
        ];
    }

    public static function deleteContent(int $contentId): bool
    {
        $content = ContentLibrary::find($contentId);
        if (!$content) return false;

        $content->delete();

        Event::dispatch('content.deleted', [
            'content_id' => $contentId,
        ]);

        return true;
    }

    public static function attachToLesson(int $lessonId, string $title, string $fileUrl, int $fileSize, string $mimeType = null, int $sortOrder = 0): ?LessonAttachment
    {
        $attachment = new LessonAttachment();
        $attachment->lesson_id = $lessonId;
        $attachment->title = $title;
        $attachment->file_url = $fileUrl;
        $attachment->file_size = $fileSize;
        $attachment->mime_type = $mimeType;
        $attachment->sort_order = $sortOrder;
        $attachment->save();

        return $attachment;
    }

    public static function getLessonAttachments(int $lessonId): array
    {
        return LessonAttachment::where('lesson_id', $lessonId)
            ->orderBy('sort_order')
            ->get();
    }

    public static function deleteAttachment(int $attachmentId): bool
    {
        $attachment = LessonAttachment::find($attachmentId);
        if (!$attachment) return false;

        $db = $attachment::db();
        $stmt = $db->prepare("DELETE FROM lms_lesson_attachments WHERE id = ?");
        return $stmt->execute([$attachmentId]);
    }

    public static function getStorageUsage(int $userId = null): array
    {
        $query = ContentLibrary::where('id', '>', 0);

        if ($userId) {
            $query->where('uploader_id', $userId);
        }

        $items = $query->get();
        $totalSize = 0;
        $countByType = [];

        foreach ($items as $item) {
            $totalSize += (int) ($item->file_size ?? 0);
            $type = $item->file_type ?? 'other';
            $countByType[$type] = ($countByType[$type] ?? 0) + 1;
        }

        return [
            'total_files' => count($items),
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1048576, 2),
            'by_type' => $countByType,
        ];
    }
}
