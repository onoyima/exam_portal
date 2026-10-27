<?php
namespace Lms\Models;

use Lms\Core\Model;

class ContentLibrary extends Model
{
    protected static string $table = 'content_libraries';
    protected static bool $useSoftDeletes = true;

    public function course(): ?Course
    {
        if (!$this->attributes['course_id']) return null;
        return Course::find($this->attributes['course_id']);
    }

    public function uploader(): ?User
    {
        return User::find($this->attributes['uploader_id']);
    }

    public function incrementDownloads(): void
    {
        $this->download_count = (int) ($this->attributes['download_count'] ?? 0) + 1;
        $this->save();
    }

    public function incrementViews(): void
    {
        $this->view_count = (int) ($this->attributes['view_count'] ?? 0) + 1;
        $this->save();
    }

    public function getTags(): array
    {
        $tags = $this->attributes['tags'] ?? null;
        if (is_string($tags)) {
            return json_decode($tags, true) ?? [];
        }
        return $tags ?? [];
    }
}
