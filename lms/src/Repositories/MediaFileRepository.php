<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\MediaFile;

class MediaFileRepository extends Repository
{
    protected function getTable(): string { return 'content_libraries'; }
    protected function getModelClass(): string { return MediaFile::class; }

    public function findByCourse(int $courseId): array
    {
        return MediaFile::findByCourse($courseId);
    }

    public function findByType(string $type): array
    {
        return MediaFile::findByType($type);
    }

    public function getByUploader(int $uploaderId): array
    {
        return MediaFile::where('uploader_id', '=', $uploaderId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->get();
    }
}
