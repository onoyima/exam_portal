<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\AnalyticsEvent;

class AnalyticsEventRepository extends Repository
{
    protected function getTable(): string { return 'analytics_events'; }
    protected function getModelClass(): string { return AnalyticsEvent::class; }

    public function getEvents(string $event, ?int $userId = null, ?string $since = null): array
    {
        return AnalyticsEvent::getEvents($event, $userId, $since);
    }

    public function getCourseAnalytics(int $courseId, ?string $since = null): array
    {
        return AnalyticsEvent::getCourseAnalytics($courseId, $since);
    }

    public function countByEvent(string $event, ?string $since = null): int
    {
        return AnalyticsEvent::countByEvent($event, $since);
    }

    public function getRecent(int $limit = 100): array
    {
        return AnalyticsEvent::where('1', '=', '1')
            ->orderBy('occurred_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function getByUser(int $userId, int $limit = 50): array
    {
        return AnalyticsEvent::where('user_id', '=', $userId)
            ->orderBy('occurred_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function getByCourseAndEvent(int $courseId, string $event): array
    {
        return AnalyticsEvent::where('course_id', '=', $courseId)
            ->where('event', '=', $event)
            ->orderBy('occurred_at', 'DESC')
            ->get();
    }
}
