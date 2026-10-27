<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\ExamLink;
use Lms\Models\ExamSyncLog;
use Lms\Models\Quiz;
use Lms\Models\Course;
use Lms\Models\User;
use Lms\Models\ActivityLog;

class ExamBridgeController extends Controller
{
    public function link(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'lms_quiz_id' => 'integer|exists:lms_quizzes,id',
            'portal_exam_id' => 'required|integer',
            'course_id' => 'required|integer|exists:lms_courses,id',
            'module_id' => 'integer|exists:lms_course_modules,id',
        ]);

        $existing = ExamLink::where('portal_exam_id', $data['portal_exam_id'])
            ->where('course_id', $data['course_id'])
            ->first();
        if ($existing) $res->conflict('Exam already linked to this course');

        $link = new ExamLink();
        foreach ($data as $key => $value) {
            $link->$key = $value;
        }
        $link->save();
        $res->created(['exam_link' => $link->toArray()]);
    }

    public function unlink(Request $req, Response $res, string $id): void
    {
        $link = ExamLink::find((int) $id);
        if (!$link) $res->notFound('Exam link not found');
        $link->sync_status = 'unlinked';
        $link->save();
        $res->success(['message' => 'Exam unlinked']);
    }

    public function sync(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'exam_link_id' => 'required|integer|exists:lms_exam_links,id',
            'action' => 'required|in:push_grades,push_enrollments,pull_results',
        ]);

        $link = ExamLink::find($data['exam_link_id']);
        if (!$link) $res->notFound('Exam link not found');

        $log = ExamSyncLog::record(
            'lms_to_portal',
            'exam_link',
            $link->getKey(),
            $data['action'],
            ['exam_link_id' => $link->getKey(), 'action' => $data['action']],
            null,
            null,
            'pending'
        );

        $integrationUrl = lms_config('integration.exam_portal_url', 'http://localhost/exam_portal');
        $apiKey = lms_config('integration.api_key', '');

        try {
            $payload = json_encode([
                'exam_id' => $link->portal_exam_id,
                'course_id' => $link->course_id,
                'timestamp' => date('c'),
            ]);
            $url = rtrim($integrationUrl, '/') . "/api/v1/lms-sync/{$data['action']}";

            if (class_exists('\GuzzleHttp\Client')) {
                $client = new \GuzzleHttp\Client(['base_uri' => $integrationUrl, 'timeout' => 30]);
                $response = $client->post("/api/v1/lms-sync/{$data['action']}", [
                    'headers' => ['Authorization' => "Bearer $apiKey", 'Content-Type' => 'application/json'],
                    'json' => json_decode($payload, true),
                ]);
                $body = (string) $response->getBody();
                $statusCode = $response->getStatusCode();
            } else {
                $ctx = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Authorization: Bearer $apiKey\r\nContent-Type: application/json\r\n",
                        'content' => $payload,
                        'timeout' => 30,
                        'ignore_errors' => true,
                    ],
                ]);
                $body = file_get_contents($url, false, $ctx);
                $statusCode = $http_response_header ? (int) explode(' ', $http_response_header[0])[1] : 500;
            }

            $log->status_code = $statusCode;
            $log->response_payload = $body;
            $log->status = $statusCode >= 200 && $statusCode < 300 ? 'success' : 'failed';
            $log->completed_at = date('Y-m-d H:i:s');
            $log->save();

            $link->last_synced_at = date('Y-m-d H:i:s');
            $link->save();

            ActivityLog::log('exam.synced', $this->userId(), $link->course_id, 'exam_link', $link->getKey());
            $res->success(['message' => 'Sync completed', 'response' => json_decode($body, true)]);
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->error_message = $e->getMessage();
            $log->save();
            $res->error('SYNC_FAILED', 'Sync failed: ' . $e->getMessage(), 502);
        }
    }

    public function links(Request $req, Response $res, string $courseId): void
    {
        $course = Course::findBy('uuid', $courseId) ?? Course::find((int) $courseId);
        if (!$course) $res->notFound('Course not found');

        $links = ExamLink::where('course_id', $course->getKey())->get();
        $results = [];
        foreach ($links as $l) {
            $data = $l->toArray();
            $quiz = $l->quiz();
            $data['quiz_title'] = $quiz ? $quiz->title : null;
            $results[] = $data;
        }
        $res->success(['exam_links' => $results]);
    }

    public function syncLog(Request $req, Response $res): void
    {
        $this->paginatedQuery(function ($page, $perPage) use ($req) {
            $query = ExamSyncLog::where('id', '>', 0);
            if ($req->query('status')) {
                $query->where('status', $req->query('status'));
            }
            $total = $query->count();
            $items = $query->orderBy('created_at', 'DESC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        });
    }

    public function ssoRedirect(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'exam_id' => 'required|integer',
            'course_id' => 'required|integer',
            'return_url' => 'string',
        ]);

        $user = User::find($this->userId());
        if (!$user) $res->unauthorized();

        $payload = [
            'sub' => $user->getKey(),
            'uuid' => $user->uuid,
            'email' => $user->email,
            'name' => $user->first_name . ' ' . $user->last_name,
            'exam_id' => (int) $data['exam_id'],
            'course_id' => (int) $data['course_id'],
            'iat' => time(),
            'exp' => time() + 300,
        ];

        $header = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])), '+/', '-_'), '=');
        $payloadEnc = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $secret = lms_config('auth.jwt_secret', 'lms-secret-key-change-in-production');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payloadEnc", $secret, true)), '+/', '-_'), '=');
        $token = "$header.$payloadEnc.$signature";

        $portalUrl = rtrim(lms_config('integration.exam_portal_url', 'http://localhost/exam_portal'), '/');
        $redirectUrl = $data['return_url'] ?? "$portalUrl/sso/launch";

        ActivityLog::log('exam.sso_redirect', $user->getKey(), (int) $data['course_id'], 'exam_link', (int) $data['exam_id']);
        $res->success([
            'token' => $token,
            'redirect_url' => $redirectUrl . '?token=' . $token,
        ]);
    }
}
