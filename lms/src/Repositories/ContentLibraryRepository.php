<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\ContentLibrary;

class ContentLibraryRepository extends Repository
{
    protected function getTable(): string { return 'content_libraries'; }
    protected function getModelClass(): string { return ContentLibrary::class; }

    public function getByCourse(int $courseId): array
    {
        return ContentLibrary::where('course_id', '=', $courseId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByType(string $type): array
    {
        return ContentLibrary::where('file_type', '=', $type)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByUploader(int $uploaderId): array
    {
        return ContentLibrary::where('uploader_id', '=', $uploaderId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function search(string $term, int $limit = 20): array
    {
        return ContentLibrary::where('file_name', 'LIKE', "%{$term}%")
            ->orWhere('alt_text', 'LIKE', "%{$term}%")
            ->whereNull('deleted_at')
            ->limit($limit)
            ->get();
    }
}
