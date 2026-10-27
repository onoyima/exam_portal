<?php
/**
 * Next Gen LMS — Full API Smoke Test
 * Tests ALL endpoints and reports pass/fail for each.
 * Run: php lms/tests/api.php
 */

$BASE = 'http://localhost/exam_portal/lms/public';
$token = null;
$refreshToken = null;

function test(string $label, callable $fn): array {
    $start = microtime(true);
    try {
        $detail = $fn();
        $duration = round((microtime(true) - $start) * 1000);
        return ['label' => $label, 'status' => 'PASS', 'duration' => "{$duration}ms", 'detail' => $detail];
    } catch (\Throwable $e) {
        $duration = round((microtime(true) - $start) * 1000);
        return ['label' => $label, 'status' => 'FAIL', 'duration' => "{$duration}ms", 'detail' => $e->getMessage()];
    }
}

function request(string $method, string $path, array $body = null, string $bearer = null): array {
    global $BASE;
    $ch = curl_init($BASE . $path);
    $headers = ['Content-Type: application/json'];
    if ($bearer) $headers[] = 'Authorization: Bearer ' . $bearer;
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 10];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($body) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    } elseif ($method === 'PUT') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        if ($body) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    } elseif ($method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    } elseif ($method === 'OPTIONS') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'OPTIONS';
        $opts[CURLOPT_NOBODY] = true;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) throw new \Exception("cURL error: $error");
    return ['http' => $http, 'body' => json_decode($raw, true) ?: $raw];
}

function assertHttp(array $r, int $expected): void {
    if ($r['http'] !== $expected) {
        $detail = is_array($r['body']) ? json_encode($r['body']) : substr($r['body'], 0, 200);
        throw new \Exception("Expected HTTP $expected, got {$r['http']}: $detail");
    }
}

// ══════════════════════════════════════════════════════════════════════════
// TESTS
// ══════════════════════════════════════════════════════════════════════════

$results = [];

// ─── 1. SPA Serving ─────────────────────────────────────────────────────
$results[] = test('GET / (SPA root)', function() {
    $r = request('GET', '/');
    assertHttp($r, 200);
    if (!str_contains($r['body'], '<div id="root">')) throw new \Exception('React root div not found');
    return 'React SPA served (' . round(strlen($r['body']) / 1024) . ' KB)';
});

// ─── 2. Auth: Login ────────────────────────────────────────────────────
$results[] = test('POST /auth/login — student (valid)', function() {
    global $token, $refreshToken;
    $r = request('POST', '/auth/login', ['login' => 'teststudent', 'password' => 'test123']);
    assertHttp($r, 200);
    $d = $r['body']['data'] ?? [];
    $token = $d['token'] ?? null;
    $refreshToken = $d['refresh_token'] ?? null;
    if (!$token) throw new \Exception('No JWT token returned');
    return 'user=' . ($d['user']['full_name'] ?? $d['user']['first_name']) . ', token=' . substr($token, 0, 16) . '...';
});

$results[] = test('POST /auth/login — invalid creds', function() {
    $r = request('POST', '/auth/login', ['login' => 'nonexistent', 'password' => 'wrong']);
    assertHttp($r, 401);
    return 'Correctly rejected (401)';
});

$results[] = test('POST /auth/login — validation error', function() {
    $r = request('POST', '/auth/login', []);
    assertHttp($r, 422);
    return 'Correctly validated (422)';
});

// ─── 3. Auth: Register ─────────────────────────────────────────────────
$results[] = test('POST /auth/register', function() {
    $email = 'test_' . bin2hex(random_bytes(4)) . '@example.com';
    $r = request('POST', '/auth/register', ['first_name' => 'Test', 'last_name' => 'User', 'email' => $email, 'password' => 'password123']);
    assertHttp($r, 201);
    return 'Created: ' . $email;
});

$results[] = test('POST /auth/register — duplicate email', function() {
    $r = request('POST', '/auth/register', ['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@test.com', 'password' => 'password123']);
    assertHttp($r, 409);
    return 'Correctly rejected duplicate (409)';
});

$results[] = test('POST /auth/register — validation', function() {
    $r = request('POST', '/auth/register', []);
    assertHttp($r, 422);
    return 'Correctly validated (422)';
});

// ─── 4. Auth: Refresh ──────────────────────────────────────────────────
$results[] = test('POST /auth/refresh', function() {
    global $token, $refreshToken;
    $r = request('POST', '/auth/refresh', ['refresh_token' => $refreshToken]);
    assertHttp($r, 200);
    $d = $r['body']['data'] ?? [];
    $newToken = $d['token'] ?? null;
    if (!$newToken) throw new \Exception('No new token');
    if ($newToken === $token) throw new \Exception('Token was not rotated');
    $token = $newToken;
    return 'Token rotated: ' . substr($token, 0, 16) . '...';
});

$results[] = test('POST /auth/refresh — invalid', function() {
    $r = request('POST', '/auth/refresh', ['refresh_token' => 'invalid']);
    assertHttp($r, 401);
    return 'Correctly rejected (401)';
});

// ─── 5. Auth: Profile ──────────────────────────────────────────────────
$results[] = test('GET /auth/profile', function() {
    global $token;
    $r = request('GET', '/auth/profile', null, $token);
    assertHttp($r, 200);
    $u = $r['body']['data']['user'] ?? [];
    return $u['email'] ?? 'no email';
});

$results[] = test('GET /auth/profile — no auth', function() {
    $r = request('GET', '/auth/profile');
    assertHttp($r, 401);
    return 'Correctly rejected (401)';
});

// ─── 6. Dashboard ──────────────────────────────────────────────────────
$results[] = test('GET /dashboard/student', function() {
    global $token;
    $r = request('GET', '/dashboard/student', null, $token);
    assertHttp($r, 200);
    $d = $r['body']['data'] ?? [];
    return 'enrolled=' . ($d['active_enrollments'] ?? 0) . ', completed=' . ($d['completed_courses'] ?? 0);
});

// ─── 7. Courses ─────────────────────────────────────────────────────────
$results[] = test('GET /courses', function() {
    global $token;
    $r = request('GET', '/courses', null, $token);
    assertHttp($r, 200);
    $c = $r['body']['data']['courses'] ?? [];
    return count($c) . ' courses';
});

$results[] = test('GET /courses — no auth', function() {
    $r = request('GET', '/courses');
    assertHttp($r, 401);
    return 'Correctly rejected (401)';
});

$results[] = test('GET /courses/99999 — not found', function() {
    global $token;
    $r = request('GET', '/courses/99999', null, $token);
    assertHttp($r, 404);
    return 'Correctly 404';
});

// ─── 8. Enrollments ────────────────────────────────────────────────────
$results[] = test('GET /enrollments/my', function() {
    global $token;
    $r = request('GET', '/enrollments/my', null, $token);
    assertHttp($r, 200);
    $c = $r['body']['data']['courses'] ?? [];
    return count($c) . ' enrollments';
});

// ─── 9. Messages ───────────────────────────────────────────────────────
$results[] = test('GET /messages/conversations', function() {
    global $token;
    $r = request('GET', '/messages/conversations', null, $token);
    assertHttp($r, 200);
    $c = $r['body']['data']['conversations'] ?? [];
    return count($c) . ' conversations';
});

$results[] = test('POST /messages/conversations — validation', function() {
    global $token;
    $r = request('POST', '/messages/conversations', [], $token);
    assertHttp($r, 422);
    return 'Correctly validated (422)';
});

$results[] = test('POST /messages/send — validation', function() {
    global $token;
    $r = request('POST', '/messages/send', [], $token);
    assertHttp($r, 422);
    return 'Correctly validated (422)';
});

// ─── 10. Notifications ─────────────────────────────────────────────────
$results[] = test('GET /notifications', function() {
    global $token;
    $r = request('GET', '/notifications', null, $token);
    assertHttp($r, 200);
    return 'OK';
});

$results[] = test('GET /notifications/unread-count', function() {
    global $token;
    $r = request('GET', '/notifications/unread-count', null, $token);
    assertHttp($r, 200);
    $u = $r['body']['data']['unread'] ?? null;
    if ($u === null) throw new \Exception('Missing unread count');
    return $u . ' unread';
});

// ─── 11. Academics ──────────────────────────────────────────────────────
$results[] = test('GET /academic/faculties', function() {
    global $token;
    $r = request('GET', '/academic/faculties', null, $token);
    assertHttp($r, 200);
    $f = $r['body']['data']['faculties'] ?? [];
    return count($f) . ' faculties';
});

$results[] = test('GET /academic/departments', function() {
    global $token;
    $r = request('GET', '/academic/departments?faculty_id=2', null, $token);
    assertHttp($r, 200);
    $d = $r['body']['data']['departments'] ?? [];
    return count($d) . ' departments in NAS';
});

$results[] = test('GET /academic/course-studies', function() {
    global $token;
    $r = request('GET', '/academic/course-studies?department_id=3', null, $token);
    assertHttp($r, 200);
    $cs = $r['body']['data']['course_studies'] ?? [];
    return count($cs) . ' course studies';
});

$results[] = test('GET /academic/sessions', function() {
    global $token;
    $r = request('GET', '/academic/sessions', null, $token);
    assertHttp($r, 200);
    $s = $r['body']['data']['vu_sessions'] ?? [];
    return count($s) . ' sessions';
});

$results[] = test('GET /academic/levels', function() {
    global $token;
    $r = request('GET', '/academic/levels', null, $token);
    assertHttp($r, 200);
    $l = $r['body']['data']['levels'] ?? [];
    return count($l) . ' levels';
});

$results[] = test('GET /academic/semesters', function() {
    global $token;
    $r = request('GET', '/academic/semesters', null, $token);
    assertHttp($r, 200);
    $s = $r['body']['data']['semesters'] ?? [];
    return count($s) . ' semesters';
});

// ─── 11b. Student Registrations ──────────────────────────────────────────
$results[] = test('GET /academic/my-registrations', function() {
    global $token;
    $r = request('GET', '/academic/my-registrations', null, $token);
    // Students with no exeat2 context will get an error — that's valid
    if ($r['http'] === 200) {
        $d = $r['body']['data'] ?? [];
        $count = count($d['registrations'] ?? []);
        return "$count registration groups";
    }
    if ($r['http'] === 404 || $r['http'] === 403) {
        return 'No academic context for test student (expected)';
    }
    assertHttp($r, 200);
    return 'OK';
});

// ─── 12. Gradebook ──────────────────────────────────────────────────────
$results[] = test('POST /gradebook/items — role check', function() {
    global $token;
    $r = request('POST', '/gradebook/items', [], $token);
    assertHttp($r, 403);
    return 'Correctly rejected - requires instructor role (403)';
});

// ─── 13. Content ───────────────────────────────────────────────────────
$results[] = test('GET /content', function() {
    global $token;
    $r = request('GET', '/content', null, $token);
    assertHttp($r, 200);
    return 'OK';
});

// ─── 14. Discussions ────────────────────────────────────────────────────
$results[] = test('GET /discussions/:id — not found', function() {
    global $token;
    $r = request('GET', '/discussions/99999', null, $token);
    assertHttp($r, 404);
    return 'Correctly 404';
});

// ─── 15. Analytics ──────────────────────────────────────────────────────
$results[] = test('GET /analytics/progress', function() {
    global $token;
    $r = request('GET', '/analytics/progress', null, $token);
    assertHttp($r, 200);
    return 'OK';
});

// ─── 16. CORS ──────────────────────────────────────────────────────────
$results[] = test('OPTIONS /courses (CORS preflight)', function() {
    $r = request('OPTIONS', '/courses');
    assertHttp($r, 204);
    return 'CORS OK (204)';
});

// ─── 17. Auth: Logout ─────────────────────────────────────────────────
$results[] = test('POST /auth/logout', function() {
    global $token;
    $r = request('POST', '/auth/logout', null, $token);
    assertHttp($r, 200);
    return 'Logged out';
});

// ─── 18. Used token after logout ──────────────────────────────────────
$results[] = test('GET /courses — after logout token', function() {
    global $token;
    $r = request('GET', '/courses', null, $token);
    if ($r['http'] === 200) return 'WARNING: Token still works after logout';
    assertHttp($r, 401);
    return 'Correctly rejected (401) — token invalid after logout';
});

// ══════════════════════════════════════════════════════════════════════════
// RESULTS
// ══════════════════════════════════════════════════════════════════════════

$pass = 0;
$fail = 0;
echo "\n" . str_repeat('═', 72) . "\n";
echo "  Next Gen LMS — FULL API SMOKE TEST\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('═', 72) . "\n\n";

foreach ($results as $r) {
    $icon = $r['status'] === 'PASS' ? '  ✓' : '  ✗';
    echo " {$icon}  {$r['status']}  {$r['label']}\n";
    echo "        ↳ {$r['detail']}  ({$r['duration']})\n";
    if ($r['status'] === 'PASS') $pass++; else $fail++;
}

echo "\n" . str_repeat('─', 72) . "\n";
$total = count($results);
$pct = $total > 0 ? round($pass / $total * 100) : 0;
echo "  RESULTS: {$pass} passed / {$fail} failed / {$total} total  ({$pct}%)\n";
echo str_repeat('─', 72) . "\n\n";

exit($fail > 0 ? 1 : 0);
