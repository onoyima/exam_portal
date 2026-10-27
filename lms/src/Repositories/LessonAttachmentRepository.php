<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\LessonAttachment;

class LessonAttachmentRepository extends Repository
{
    protected function getTable(): string { return 'lesson_attachments'; }
    protected function getModelClass(): string { return LessonAttachment::class; }

    public function getByLesson(int $lessonId): array
    {
        return LessonAttachment::where('lesson_id', '=', $lessonId)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }
}
