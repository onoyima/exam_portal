<?php
/**
 * Main-server endpoint that exam centres talk to.
 *
 *   hello     check a token and show what it is for
 *   activate  download the sealed package (only inside the activation window)
 *   sync      upload sealed results for one exam, or a bundle of exams
 *
 * There is no login: every request is signed with the key derived from the
 * centre's token (see includes/center_sync.php), and the payloads are sealed
 * with the same key.
 */
require __DIR__ . '/db.php';
require_once __DIR__ . '/includes/center_sync.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_out(['ok' => false, 'error' => 'This address is used by exam centres, not by people.'], 405);
}
if (cs_is_center($db)) {
    api_out(['ok' => false, 'error' => 'This server is set up as an exam centre, not as the main server.'], 409);
}

$action = (string) ($_POST['action'] ?? '');
$prefix = strtoupper((string) ($_POST['prefix'] ?? ''));
$ts = (int) ($_POST['ts'] ?? 0);
$sig = (string) ($_POST['sig'] ?? '');
$body = (string) ($_POST['body'] ?? '');

$token = preg_match('/^[A-Z0-9]{6}$/', $prefix) ? cs_token_by_prefix($db, $prefix) : null;
if (!$token) {
    api_out(['ok' => false, 'error' => 'This token is not known to the main server. Check that it was typed correctly.'], 404);
}
if (abs(time() - $ts) > CS_CLOCK_SKEW) {
    api_out(['ok' => false, 'error' => 'The centre computer\'s clock is wrong by more than 15 minutes. Correct the date and time, then try again.'], 400);
}
if (!hash_equals(cs_sign($token['pkg_key'], $prefix, $action, $ts, $body), $sig)) {
    cs_log($db, (int) $token['id'], null, 'refused', false, 'Bad signature from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    api_out(['ok' => false, 'error' => 'The token is not correct.'], 403);
}
if ($token['revoked_at'] || $token['status'] === 'revoked') {
    api_out(['ok' => false, 'error' => 'This token has been withdrawn by the main server. Contact the examination office.'], 403);
}
$group = cs_group($db, (int) $token['group_id']);
if (!$group) {
    api_out(['ok' => false, 'error' => 'The exam group for this token no longer exists.'], 410);
}

try {
    switch ($action) {
        case 'hello':
            $exams = cs_group_exams($db, (int) $group['id']);
            api_out([
                'ok' => true,
                'group' => ['name' => $group['name'], 'code' => $group['code'], 'description' => $group['description'],
                    'activation_opens' => $group['activation_opens'], 'activation_closes' => $group['activation_closes']],
                'center' => ['name' => $token['center_name'], 'code' => $token['center_code']],
                'exams' => array_map(fn($e) => ['name' => $e['name'], 'type' => $e['type']], $exams),
                'window_problem' => cs_window_problem($group),
                'status' => $token['status'],
            ]);

        case 'activate':
            if ($problem = cs_window_problem($group)) {
                api_out(['ok' => false, 'error' => $problem], 423);
            }
            $pkg = cs_build_package($db, $token);
            $db->prepare("UPDATE center_tokens SET status = CASE WHEN status = 'issued' THEN 'activated' ELSE status END,
                    activated_at = COALESCE(activated_at, NOW()), activated_ip = ?, last_download_at = NOW() WHERE id = ?")
                ->execute([substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64), (int) $token['id']]);
            cs_log($db, (int) $token['id'], null, 'activate', true, count($pkg['exams']) . ' exams, ' . count($pkg['students']) . ' students sent to '
                . $token['center_name'] . ' (' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ')');
            api_out(['ok' => true, 'package' => cs_seal($pkg, $token['pkg_key'])]);

        case 'sync':
            $res = cs_open($body, $token['pkg_key']);
            if (!$res) {
                api_out(['ok' => false, 'error' => 'The results could not be read. They may have been damaged on the way.'], 400);
            }
            $list = ($res['format'] ?? '') === 'cbt-group-results-bundle' ? ($res['exams'] ?? []) : [$res];
            $total = ['attempts' => 0, 'answers' => 0, 'scores' => 0, 'skipped' => 0];
            foreach ($list as $one) {
                foreach (cs_apply_results($db, $token, $one) as $k => $v) {
                    if (isset($total[$k])) {
                        $total[$k] += $v;
                    }
                }
            }
            api_out(['ok' => true, 'summary' => $total]);

        default:
            api_out(['ok' => false, 'error' => 'Unknown request.'], 400);
    }
} catch (Throwable $ex) {
    error_log('center_api ' . $action . ': ' . $ex->getMessage());
    cs_log($db, (int) $token['id'], null, $action, false, $ex->getMessage());
    api_out(['ok' => false, 'error' => $ex instanceof RuntimeException ? $ex->getMessage() : 'The main server could not complete the request. Try again shortly.'], 500);
}
