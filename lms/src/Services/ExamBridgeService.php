<?php
namespace Lms\Services;

use Lms\Core\Database;
use Lms\Core\Event;
use Lms\Core\Logger;
use Lms\Models\ExamLink;
use Lms\Models\ExamSyncLog;
use Lms\Models\Quiz;
use Lms\Models\Course;
use Lms\Models\Enrollment;

class ExamBridgeService
{
    public static function syncCourseToPortal(int $courseId): ?array
    {
        $course = Course::find($courseId);
        if (!$course) {
            Logger::error('Course not found for sync', ['course_id' => $courseId]);
            return null;
        }

        $portalUrl = rtrim(lms_config('integration.exam_portal.base_url', 'http://localhost/exam_portal'), '/');
        $apiKey = lms_config('integration.exam_portal.api_key', '');
        $endpoint = lms_config('integration.exam_portal.endpoints.sync_exam', '/api/exams/sync');

        $payload = [
            'course_id' => $course->getKey(),
            'uuid' => $course->uuid,
            'code' => $course->code,
            'title' => $course->title,
            'description' => $course->description,
            'level' => $course->level,
            'credits' => $course->credits,
            'department_id' => $course->department_id,
            'status' => $course->status,
            'timestamp' => date('c'),
        ];

        $log = ExamSyncLog::record(
            'lms_to_portal',
            'course',
            $courseId,
            'sync_create',
            $payload
        );

        try {
            $url = $portalUrl . $endpoint;
            $jsonPayload = json_encode($payload);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                    'X-LMS-Request-ID: ' . lms_uuid(),
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \RuntimeException("cURL error: {$error}");
            }

            $log->response_payload = $response;
            $log->status_code = $statusCode;
            $log->status = ($statusCode >= 200 && $statusCode < 300) ? 'success' : 'failed';
            $log->completed_at = date('Y-m-d H:i:s');
            $log->save();

            Event::dispatch('exam.synced', [
                'course_id' => $courseId,
                'portal_response' => json_decode($response, true),
                'status' => $log->status,
            ]);

            Logger::info('Course synced to portal', [
                'course_id' => $courseId,
                'status_code' => $statusCode,
            ]);

            return [
                'success' => $log->status === 'success',
                'status_code' => $statusCode,
                'response' => json_decode($response, true),
            ];
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->error_message = $e->getMessage();
            $log->save();

            Logger::error('Course sync failed: ' . $e->getMessage(), [
                'course_id' => $courseId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function syncEnrollmentsToPortal(int $courseId, array $userIds = []): array
    {
        $results = [];
        $portalUrl = rtrim(lms_config('integration.exam_portal.base_url', 'http://localhost/exam_portal'), '/');
        $apiKey = lms_config('integration.exam_portal.api_key', '');
        $endpoint = lms_config('integration.exam_portal.endpoints.enroll_students', '/api/exams/enroll');

        $course = Course::find($courseId);
        if (!$course) {
            return ['success' => false, 'error' => 'Course not found'];
        }

        $examLinks = ExamLink::where('course_id', $courseId)->get();
        if (empty($examLinks)) {
            return ['success' => false, 'error' => 'No exam links found for course'];
        }

        $students = [];
        if (!empty($userIds)) {
            foreach ($userIds as $uid) {
                $enrollment = Enrollment::where('user_id', $uid)
                    ->where('course_id', $courseId)
                    ->first();
                if ($enrollment) {
                    $user = $enrollment->student();
                    if ($user) {
                        $students[] = [
                            'user_id' => $user->getKey(),
                            'uuid' => $user->uuid,
                            'matric_no' => $user->matric_no,
                            'email' => $user->email,
                            'name' => $user->first_name . ' ' . $user->last_name,
                        ];
                    }
                }
            }
        } else {
            $enrollments = Enrollment::where('course_id', $courseId)
                ->where('enrollment_type', 'student')
                ->where('status', 'active')
                ->get();
            foreach ($enrollments as $enrollment) {
                $user = $enrollment->student();
                if ($user) {
                    $students[] = [
                        'user_id' => $user->getKey(),
                        'uuid' => $user->uuid,
                        'matric_no' => $user->matric_no,
                        'email' => $user->email,
                        'name' => $user->first_name . ' ' . $user->last_name,
                    ];
                }
            }
        }

        if (empty($students)) {
            return ['success' => true, 'message' => 'No students to sync', 'synced' => 0];
        }

        foreach ($examLinks as $link) {
            $payload = [
                'exam_id' => $link->portal_exam_id,
                'course_id' => $courseId,
                'students' => $students,
                'timestamp' => date('c'),
            ];

            $log = ExamSyncLog::record(
                'lms_to_portal',
                'exam_link',
                $link->getKey(),
                'push_enrollments',
                $payload
            );

            try {
                $url = $portalUrl . $endpoint;
                $jsonPayload = json_encode($payload);

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $jsonPayload,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey,
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                ]);
                $response = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $log->response_payload = $response;
                $log->status_code = $statusCode;
                $log->status = ($statusCode >= 200 && $statusCode < 300) ? 'success' : 'failed';
                $log->completed_at = date('Y-m-d H:i:s');
                $log->save();

                $results[] = [
                    'exam_link_id' => $link->getKey(),
                    'success' => $log->status === 'success',
                    'status_code' => $statusCode,
                ];
            } catch (\Throwable $e) {
                $log->status = 'failed';
                $log->error_message = $e->getMessage();
                $log->save();

                $results[] = [
                    'exam_link_id' => $link->getKey(),
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'course_id' => $courseId,
            'students_synced' => count($students),
            'results' => $results,
        ];
    }

    public static function pushGradesToPortal(int $examLinkId, array $grades = []): ?array
    {
        $link = ExamLink::find($examLinkId);
        if (!$link) return null;

        $portalUrl = rtrim(lms_config('integration.exam_portal.base_url', 'http://localhost/exam_portal'), '/');
        $apiKey = lms_config('integration.exam_portal.api_key', '');
        $endpoint = '/api/exams/grades';

        $payload = [
            'exam_id' => $link->portal_exam_id,
            'course_id' => $link->course_id,
            'grades' => $grades,
            'timestamp' => date('c'),
        ];

        $log = ExamSyncLog::record(
            'lms_to_portal',
            'exam_link',
            $examLinkId,
            'push_grades',
            $payload
        );

        try {
            $url = $portalUrl . $endpoint;
            $jsonPayload = json_encode($payload);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $log->response_payload = $response;
            $log->status_code = $statusCode;
            $log->status = ($statusCode >= 200 && $statusCode < 300) ? 'success' : 'failed';
            $log->completed_at = date('Y-m-d H:i:s');
            $log->save();

            return [
                'success' => $log->status === 'success',
                'status_code' => $statusCode,
                'response' => json_decode($response, true),
            ];
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->error_message = $e->getMessage();
            $log->save();

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function generateSSOToken(int $userId, int $examId, int $courseId): ?array
    {
        $user = \Lms\Models\User::find($userId);
        if (!$user) return null;

        $ttl = lms_config('integration.exam_portal.sso.token_ttl_seconds', 300);
        $payload = [
            'sub' => $userId,
            'uuid' => $user->uuid,
            'email' => $user->email,
            'name' => $user->first_name . ' ' . $user->last_name,
            'exam_id' => $examId,
            'course_id' => $courseId,
            'iat' => time(),
            'exp' => time() + $ttl,
        ];

        $secret = lms_config('auth.jwt_secret', 'lms-secret-key-change-in-production');
        $token = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

        $portalUrl = rtrim(lms_config('integration.exam_portal.base_url', 'http://localhost/exam_portal'), '/');
        $redirectUrl = $portalUrl . '/sso/launch?token=' . $token;

        Logger::info('SSO token generated', [
            'user_id' => $userId,
            'exam_id' => $examId,
        ]);

        return [
            'token' => $token,
            'redirect_url' => $redirectUrl,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl),
        ];
    }

    public static function handleWebhook(array $payload): array
    {
        $event = $payload['event'] ?? null;
        $data = $payload['payload'] ?? [];

        if (!$event) {
            return ['success' => false, 'error' => 'Missing event type'];
        }

        $log = ExamSyncLog::record(
            'portal_to_lms',
            'webhook',
            null,
            "webhook.{$event}",
            $payload
        );

        try {
            return match ($event) {
                'exam.completed' => self::processExamCompleted($data, $log),
                'exam.started' => self::processExamStarted($data, $log),
                'exam.violation' => self::processExamViolation($data, $log),
                default => throw new \RuntimeException("Unknown webhook event: {$event}"),
            };
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->error_message = $e->getMessage();
            $log->save();

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private static function processExamCompleted(array $data, ExamSyncLog $log): array
    {
        $examId = $data['exam_id'] ?? null;
        $studentId = $data['student_id'] ?? null;
        $score = $data['score'] ?? 0;
        $total = $data['total'] ?? 100;
        $percentage = $data['percentage'] ?? 0;

        $link = ExamLink::where('portal_exam_id', $examId)->first();
        if ($link) {
            $gradeService = new GradebookService();
            $entry = \Lms\Models\GradebookEntry::where('user_id', $studentId)
                ->where('course_id', $link->course_id)
                ->first();

            if (!$entry) {
                $entry = new \Lms\Models\GradebookEntry();
                $entry->user_id = $studentId;
                $entry->course_id = $link->course_id;
                $entry->points_possible = $total;
            }

            $entry->points_earned = $score;
            $entry->percentage = $percentage;
            $entry->graded_at = date('Y-m-d H:i:s');
            $entry->save();
        }

        $log->status = 'success';
        $log->completed_at = date('Y-m-d H:i:s');
        $log->save();

        Event::dispatch('exam.completed', [
            'exam_id' => $examId,
            'student_id' => $studentId,
            'score' => $score,
            'percentage' => $percentage,
        ]);

        return ['success' => true, 'message' => 'Exam completion processed'];
    }

    private static function processExamStarted(array $data, ExamSyncLog $log): array
    {
        $log->status = 'success';
        $log->completed_at = date('Y-m-d H:i:s');
        $log->save();

        return ['success' => true, 'message' => 'Exam start recorded'];
    }

    private static function processExamViolation(array $data, ExamSyncLog $log): array
    {
        $log->status = 'success';
        $log->completed_at = date('Y-m-d H:i:s');
        $log->save();

        Event::dispatch('exam.violation', $data);

        return ['success' => true, 'message' => 'Violation recorded'];
    }
}
