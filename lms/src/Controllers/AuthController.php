<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\User;
use Lms\Models\RefreshToken;
use Lms\Models\ActivityLog;
use Lms\Services\PortalAuthBridge;
use Lms\Services\PortalSyncService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends Controller
{
    private PortalAuthBridge $portal;
    private PortalSyncService $sync;

    public function __construct()
    {
        $this->portal = new PortalAuthBridge();
        $this->sync = new PortalSyncService();
    }

    public function login(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'login' => 'required|string',
            'password' => 'required|string',
            'type' => 'in:student,staff',
        ]);

        $portalUser = $this->portal->authenticate($data['login'], $data['password'], $data['type'] ?? null);
        if (!$portalUser) {
            $res->unauthorized('Invalid credentials');
        }

        $lmsUserId = $this->portal->findOrCreateLmsUser($portalUser);
        $user = User::find($lmsUserId);
        if (!$user) {
            $res->error('SESSION_ERROR', 'Failed to create user session');
        }

        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $req->ip();
        $user->save();

        $this->portal->setPortalSession($portalUser);

        if ($portalUser['portal_user_type'] === 'student') {
            $this->sync->syncStudentAcademicContext($lmsUserId, $portalUser['portal_id']);
            $this->sync->syncUserCoursesFromPortal($lmsUserId, $portalUser['portal_id'], 'student');
        } elseif ($portalUser['portal_user_type'] === 'examiner') {
            $this->sync->syncStaffCourses($lmsUserId, $portalUser['portal_id']);
        }

        $user = User::find($lmsUserId);

        $token = $this->generateToken($user, $portalUser);
        $refreshToken = RefreshToken::createForUser($user->getKey());

        ActivityLog::log('login', $user->getKey(), null, 'user', $user->getKey(), [
            'portal_id' => $portalUser['portal_id'],
            'portal_type' => $portalUser['portal_user_type'],
            'role' => $portalUser['role'],
        ], $req->ip());

        $res->success([
            'user' => array_merge($user->toArray(), [
                'portal_user_id' => $portalUser['portal_id'],
                'portal_user_type' => $portalUser['portal_user_type'],
                'role' => $portalUser['role'],
                'full_name' => $portalUser['full_name'] ?: $user->getDisplayName(),
                'matric_no' => $portalUser['matric_no'] ?? $user->matric_no ?? null,
            ]),
            'token' => $token,
            'refresh_token' => $refreshToken->token_hash,
        ]);
    }

    public function portalLogin(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'portal_user_id' => 'required|integer',
            'portal_user_type' => 'required|in:student,examiner',
            'token' => 'required|string',
        ]);

        $expectedKey = lms_config('integration.exam_portal.api_secret', '');
        $expected = hash_hmac('sha256', $data['portal_user_id'] . '|' . $data['portal_user_type'], $expectedKey);
        if (!hash_equals($expected, $data['token'])) {
            $res->unauthorized('Invalid portal token');
        }

        $portalRecord = $this->portal->getPortalRecord((int) $data['portal_user_id'], $data['portal_user_type']);
        if (!$portalRecord) {
            $res->notFound('Portal user not found');
        }

        $sourceTable = $data['portal_user_type'] === 'student' ? 'students' : 'examiners';
        $role = $data['portal_user_type'] === 'student' ? 'student' : ($portalRecord['role'] ?? 'examiner');
        $parts = explode(' ', $portalRecord['name'] ?? 'User');
        $portalUser = [
            'portal_id' => (int) $portalRecord['id'],
            'portal_user_type' => $data['portal_user_type'],
            'first_name' => $data['portal_user_type'] === 'student' ? ($portalRecord['firstname'] ?: $parts[0]) : $parts[0],
            'last_name' => $data['portal_user_type'] === 'student' ? ($portalRecord['lastname'] ?: ($parts[1] ?? '')) : ($parts[1] ?? ''),
            'full_name' => $portalRecord['name'] ?? '',
            'email' => $portalRecord['email'] ?? '',
            'username' => $portalRecord['username'] ?? '',
            'role' => $role,
            'matric_no' => $portalRecord['matric_no'] ?? '',
            'source_table' => $sourceTable,
        ];

        $lmsUserId = $this->portal->findOrCreateLmsUser($portalUser);
        $user = User::find($lmsUserId);
        if (!$user) $res->error('SESSION_ERROR', 'Failed to create user session');

        $token = $this->generateToken($user, $portalUser);
        $refreshToken = RefreshToken::createForUser($user->getKey());

        $res->success([
            'user' => $user->toArray(),
            'token' => $token,
            'refresh_token' => $refreshToken->token_hash,
        ]);
    }

    public function refresh(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), ['refresh_token' => 'required']);
        $token = RefreshToken::findValid($data['refresh_token']);
        if (!$token) $res->unauthorized('Invalid or expired refresh token');

        $user = $token->user();
        if (!$user) $res->unauthorized('User not found');

        $token->revoke();
        $portalUser = $this->buildPortalContext($user);
        $newToken = $this->generateToken($user, $portalUser);
        $newRefresh = RefreshToken::createForUser($user->getKey());

        $res->success(['token' => $newToken, 'refresh_token' => $newRefresh->token_hash]);
    }

    public function logout(Request $req, Response $res): void
    {
        $userId = $this->userId();
        RefreshToken::revokeAllForUser($userId);
        ActivityLog::log('logout', $userId);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        $res->success(['message' => 'Logged out successfully']);
    }

    public function profile(Request $req, Response $res): void
    {
        $user = User::find($this->userId());
        if (!$user) $res->notFound('User not found');

        $portalUser = $this->buildPortalContext($user);
        $res->success(['user' => array_merge($user->toArray(), [
            'portal_user_type' => $portalUser['portal_user_type'],
            'role' => $portalUser['role'],
        ])]);
    }

    public function updateProfile(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'phone' => 'string|max:30',
            'timezone' => 'string|max:50',
            'locale' => 'string|max:10',
        ]);

        $user = User::find($this->userId());
        if (!$user) $res->notFound('User not found');

        foreach ($data as $key => $value) {
            $user->$key = $value;
        }
        $user->save();
        $res->success(['user' => $user->toArray(), 'message' => 'Profile updated']);
    }

    public function register(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|max:255',
            'password'   => 'required|string|min:8',
        ]);

        $existing = User::findBy('email', $data['email']);
        if ($existing) {
            $res->error('EMAIL_EXISTS', 'An account with this email already exists', 409);
        }

        $user = new User();
        $user->uuid = lms_uuid();
        $user->email = $data['email'];
        $user->first_name = $data['first_name'];
        $user->last_name = $data['last_name'];
        $user->password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $user->is_active = 1;
        $user->save();

        $res->created(['user' => $user->toArray()]);
    }

    private function generateToken(User $user, array $portalUser): string
    {
        $payload = [
            'sub' => $user->getKey(),
            'uuid' => $user->uuid,
            'email' => $user->email,
            'portal_user_id' => $portalUser['portal_id'],
            'portal_user_type' => $portalUser['portal_user_type'],
            'portal_role' => $portalUser['role'],
            'iat' => time(),
            'exp' => time() + 3600,
        ];
        $secret = lms_config('auth.jwt_secret', 'lms-secret-key-change-in-production');
        return JWT::encode($payload, $secret, 'HS256');
    }

    private function buildPortalContext(User $user): array
    {
        $portalId = $user->portal_user_id ? (int) $user->portal_user_id : $user->getKey();
        $portalType = $user->portal_user_type ?: 'student';
        $record = $this->portal->getPortalRecord($portalId, $portalType);
        return [
            'portal_id' => $portalId,
            'portal_user_type' => $portalType,
            'role' => $record['role'] ?? ($portalType === 'student' ? 'student' : 'examiner'),
            'matric_no' => $record['matric_no'] ?? '',
        ];
    }
}
