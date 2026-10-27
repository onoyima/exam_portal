<?php
namespace Lms\Services;

use Lms\Core\Event;
use Lms\Core\Logger;
use Lms\Models\Notification;
use Lms\Models\User;

class NotificationService
{
    public static function send(int $userId, string $type, string $title, string $message, ?array $data = null, ?string $link = null, string $channel = 'in_app'): ?Notification
    {
        $notification = Notification::send($userId, $type, $title, $message, $data, $link);

        if ($channel === 'email' || $channel === 'all') {
            self::sendEmail($userId, $title, $message, $data);
        }

        Event::dispatch('notification.sent', [
            'notification_id' => $notification->getKey(),
            'user_id' => $userId,
            'type' => $type,
            'channel' => $channel,
        ]);

        Logger::info('Notification sent', [
            'user_id' => $userId,
            'type' => $type,
            'channel' => $channel,
        ]);

        return $notification;
    }

    public static function sendBulk(array $userIds, string $type, string $title, string $message, ?array $data = null, ?string $link = null): int
    {
        $sent = 0;
        foreach ($userIds as $userId) {
            $notif = self::send($userId, $type, $title, $message, $data, $link);
            if ($notif) $sent++;
        }
        return $sent;
    }

    public static function sendToCourse(int $courseId, string $type, string $title, string $message, ?array $data = null, ?string $link = null): int
    {
        $enrollments = \Lms\Models\Enrollment::where('course_id', $courseId)
            ->where('enrollment_type', 'student')
            ->where('status', 'active')
            ->get();

        $userIds = [];
        foreach ($enrollments as $enrollment) {
            $userIds[] = $enrollment->user_id;
        }

        return self::sendBulk($userIds, $type, $title, $message, $data, $link);
    }

    public static function sendToRole(string $role, string $type, string $title, string $message, ?array $data = null, ?string $link = null): int
    {
        $users = User::where('role', $role)->where('is_active', 1)->get();
        $userIds = [];
        foreach ($users as $user) {
            $userIds[] = $user->getKey();
        }
        return self::sendBulk($userIds, $type, $title, $message, $data, $link);
    }

    public static function sendEnrollmentNotification(int $userId, int $courseId): ?Notification
    {
        $course = \Lms\Models\Course::find($courseId);
        if (!$course) return null;

        return self::send(
            $userId,
            'enrollment',
            'Course Enrollment',
            "You have been enrolled in {$course->title} ({$course->code})",
            ['course_id' => $courseId, 'course_code' => $course->code],
            "/courses/{$course->uuid}"
        );
    }

    public static function sendGradeNotification(int $userId, int $courseId, string $grade, string $letterGrade): ?Notification
    {
        $course = \Lms\Models\Course::find($courseId);
        if (!$course) return null;

        return self::send(
            $userId,
            'grade',
            'Grade Posted',
            "Your grade for {$course->title} has been posted: {$grade}% ({$letterGrade})",
            ['course_id' => $courseId, 'grade' => $grade, 'letter_grade' => $letterGrade],
            "/gradebook/{$courseId}"
        );
    }

    public static function sendAssignmentNotification(int $userId, int $assignmentId, string $assignmentTitle, string $courseTitle): ?Notification
    {
        return self::send(
            $userId,
            'assignment',
            'New Assignment',
            "A new assignment '{$assignmentTitle}' has been posted in {$courseTitle}",
            ['assignment_id' => $assignmentId],
            "/assignments/{$assignmentId}"
        );
    }

    public static function sendQuizNotification(int $userId, int $quizId, string $quizTitle, string $courseTitle): ?Notification
    {
        return self::send(
            $userId,
            'quiz',
            'New Quiz',
            "A new quiz '{$quizTitle}' is available in {$courseTitle}",
            ['quiz_id' => $quizId],
            "/quizzes/{$quizId}"
        );
    }

    public static function sendDiscussionNotification(int $userId, int $threadId, string $threadTitle, string $authorName): ?Notification
    {
        return self::send(
            $userId,
            'discussion',
            'New Discussion Reply',
            "{$authorName} replied to '{$threadTitle}'",
            ['thread_id' => $threadId],
            "/discussions/{$threadId}"
        );
    }

    public static function sendMessageNotification(int $userId, string $senderName, string $preview): ?Notification
    {
        return self::send(
            $userId,
            'message',
            "New Message from {$senderName}",
            $preview,
            null,
            '/messages'
        );
    }

    public static function sendSystemNotification(string $title, string $message, ?array $data = null): int
    {
        $users = User::where('is_active', 1)->get();
        $userIds = [];
        foreach ($users as $user) {
            $userIds[] = $user->getKey();
        }
        return self::sendBulk($userIds, 'system', $title, $message, $data);
    }

    public static function getUserNotifications(int $userId, int $page = 1, int $perPage = 20): array
    {
        $query = Notification::where('user_id', $userId);
        $total = $query->count();
        $items = $query->orderBy('created_at', 'DESC')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / max($perPage, 1)),
            'unread_count' => Notification::unreadCount($userId),
        ];
    }

    public static function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::find($notificationId);
        if (!$notification || $notification->user_id !== $userId) return false;

        $notification->markAsRead();
        return true;
    }

    public static function markAllAsRead(int $userId): void
    {
        Notification::markAllAsRead($userId);
    }

    public static function getUnreadCount(int $userId): int
    {
        return Notification::unreadCount($userId);
    }

    public static function deleteNotification(int $notificationId, int $userId): bool
    {
        $notification = Notification::find($notificationId);
        if (!$notification || $notification->user_id !== $userId) return false;

        $notification->delete();
        return true;
    }

    private static function sendEmail(int $userId, string $subject, string $body, ?array $data = null): void
    {
        try {
            $user = User::find($userId);
            if ($user && $user->email) {
                EmailService::send($user->email, $subject, $body);
            }
        } catch (\Throwable $e) {
            Logger::error('Failed to send email notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
