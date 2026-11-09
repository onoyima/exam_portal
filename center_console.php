<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
require_once 'includes/center_sync.php';

$name = $_SESSION['user'] ?? 'Admin';
$self = 'center_console';

function cc_flash(string $type, string $msg): void
{
    $_SESSION['cc_flash'] = [$type, $msg];
}

function cc_back(string $anchor = ''): void
{
    header('Location: center_console' . ($anchor ? '#' . $anchor : ''));
    exit;
}

function cc_link(PDO $db, int $id): ?array
{
    $st = $db->prepare("SELECT l.*, g.name AS group_name FROM center_links l JOIN exam_groups g ON g.id = l.group_id WHERE l.id = ?");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$TYPE_LABEL = ['objective' => 'Objective', 'practical' => 'Practical', 'viva' => 'Viva', 'research' => 'Research'];

/* ---------------- results file download ---------------- */

if (isset($_GET['results'])) {
    $link = cc_link($db, (int) $_GET['results']);
    if (!$link) {
        http_response_code(404);
        exit('Not found.');
    }
    $file = cs_results_file($db, $link);
    cs_log($db, null, (int) $link['id'], 'results-file', true, 'Results file downloaded by ' . $name);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="results_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', ($link['center_code'] ?: 'centre') . '_' . date('Ymd_Hi')) . '.cbtres"');
    header('Content-Length: ' . strlen($file));
    echo $file;
    exit;
}

/* ---------------- actions ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $act = (string) ($_POST['act'] ?? '');
    try {
        switch ($act) {
            case 'set_role':
                $role = ($_POST['role'] ?? '') === 'center' ? 'center' : 'main';
                cs_set_setting($db, 'server_role', $role);
                cc_flash('success', $role === 'center'
                    ? 'This server is now an exam centre. Activate a token to receive a group of exams.'
                    : 'This server is now the main server. Create exam groups under Exam Groups.');
                header('Location: ' . ($role === 'center' ? 'center_console' : 'admin_exam_groups'));
                exit;

            case 'activate_online':
                $token = cs_normalise_token((string) $_POST['token']);
                $url = cs_clean_url((string) $_POST['main_url']);
                if (!$token) {
                    cc_flash('danger', 'That does not look like a centre token. It has the form CBT-XXXXXX-XXXXX-XXXXX-XXXXX-XXXXX.');
                    cc_back('activate');
                }
                if ($url === '') {
                    cc_flash('danger', 'Enter the address of the main server, for example https://exams.example.edu.ng/CBT');
                    cc_back('activate');
                }
                cs_set_setting($db, 'main_server_url', $url);
                $prefix = cs_token_prefix($token);
                $key = cs_key_for($token);
                $reply = cs_call_main($url, $prefix, $key, 'activate');
                $pkg = cs_open((string) $reply['package'], $key);
                if (!$pkg) {
                    throw new RuntimeException('The package from the main server could not be opened.');
                }
                $sum = cs_import_package($db, $pkg, $key, $url);
                cc_flash('success', $pkg['group']['name'] . ' is installed: ' . cs_summary_text($sum) . '. Now create examiners and assign them to the exams.');
                cc_back('group-' . $sum['link_id']);

            case 'activate_file':
                $token = cs_normalise_token((string) $_POST['token']);
                $f = $_FILES['package'] ?? null;
                if (!$token || !$f || $f['error'] !== UPLOAD_ERR_OK) {
                    cc_flash('danger', 'Enter the token and choose the package file (.cbtpkg) you received.');
                    cc_back('activate');
                }
                $wrap = cs_unwrap_file((string) file_get_contents($f['tmp_name']));
                if (!$wrap || $wrap['kind'] !== 'package') {
                    throw new RuntimeException('That file is not an exam group package.');
                }
                if ($wrap['prefix'] !== cs_token_prefix($token)) {
                    throw new RuntimeException('This package was made for another token (CBT-' . $wrap['prefix'] . '-…). Check that you have the right file and token.');
                }
                $key = cs_key_for($token);
                $pkg = cs_open($wrap['sealed'], $key);
                if (!$pkg) {
                    throw new RuntimeException('The token does not open this package. Check the token for typing mistakes.');
                }
                $problem = cs_window_problem($pkg['group'] + ['status' => 'published']);
                if ($problem) {
                    throw new RuntimeException($problem . ' Check that this computer\'s date and time are correct.');
                }
                $sum = cs_import_package($db, $pkg, $key, cs_setting($db, 'main_server_url', '') ?? '');
                cc_flash('success', $pkg['group']['name'] . ' is installed from the file: ' . cs_summary_text($sum) . '.');
                cc_back('group-' . $sum['link_id']);

            case 'refresh':
                $link = cc_link($db, (int) $_POST['link_id']);
                if (!$link || !$link['main_url']) {
                    throw new RuntimeException('No main server address is saved for this group. Use a new package file instead.');
                }
                $reply = cs_call_main($link['main_url'], $link['prefix'], $link['pkg_key'], 'activate');
                $pkg = cs_open((string) $reply['package'], $link['pkg_key']);
                if (!$pkg) {
                    throw new RuntimeException('The package from the main server could not be opened.');
                }
                $sum = cs_import_package($db, $pkg, $link['pkg_key'], $link['main_url']);
                cc_flash('success', 'Package refreshed: ' . cs_summary_text($sum) . '. Answers already given here were kept.');
                cc_back('group-' . $link['id']);

            case 'set_url':
                $link = cc_link($db, (int) $_POST['link_id']);
                $url = cs_clean_url((string) $_POST['main_url']);
                if ($link) {
                    $db->prepare("UPDATE center_links SET main_url = ? WHERE id = ?")->execute([$url ?: null, $link['id']]);
                    cc_flash('success', 'Main server address saved.');
                }
                cc_back('group-' . (int) $_POST['link_id']);

            case 'sync':
                $link = cc_link($db, (int) $_POST['link_id']);
                if (!$link) {
                    cc_back();
                }
                $done = cs_sync_link($db, $link);
                cc_flash('success', 'Results sent to the main server. ' . implode('; ', $done));
                cc_back('group-' . $link['id']);

            case 'toggle_exam':
                $st = $db->prepare("UPDATE exams SET is_active = 1 - is_active WHERE id = ? AND group_id IS NOT NULL AND deleted_at IS NULL");
                $st->execute([(int) $_POST['exam_id']]);
                $st = $db->prepare("SELECT name, is_active, group_id FROM exams WHERE id = ?");
                $st->execute([(int) $_POST['exam_id']]);
                $e = $st->fetch(PDO::FETCH_ASSOC);
                if ($e) {
                    cc_flash('success', $e['name'] . ($e['is_active'] ? ' is switched on: students and examiners can see it.' : ' is switched off.'));
                }
                $st = $db->prepare("SELECT id FROM center_links WHERE group_id = ?");
                $st->execute([(int) ($e['group_id'] ?? 0)]);
                cc_back('group-' . (int) $st->fetchColumn());

            case 'add_examiner':
                $first = trim((string) $_POST['firstname']);
                $last = trim((string) $_POST['lastname']);
                $user = strtolower(trim((string) $_POST['username']));
                $pass = (string) $_POST['password'];
                $gid = (int) $_POST['group_id'];
                $st = $db->prepare("SELECT l.id FROM center_links l JOIN exam_groups g ON g.id = l.group_id AND g.deleted_at IS NULL WHERE l.group_id = ?");
                $st->execute([$gid]);
                if (!$st->fetchColumn()) {
                    cc_flash('danger', 'Choose the exam group this examiner works on.');
                    cc_back('examiners');
                }
                if ($first === '' || $last === '' || !preg_match('/^[a-z0-9_.\-]{3,40}$/', $user) || strlen($pass) < 6) {
                    cc_flash('danger', 'Give the first name, last name, a username of 3–40 letters or numbers, and a password of at least 6 characters.');
                    cc_back('examiners');
                }
                $st = $db->prepare("SELECT 1 FROM examiners WHERE username = ?");
                $st->execute([$user]);
                if ($st->fetchColumn()) {
                    cc_flash('danger', 'The username "' . $user . '" is already taken on this server.');
                    cc_back('examiners');
                }
                $db->prepare("INSERT INTO examiners (name, firstname, lastname, username, password, role, group_id) VALUES (?, ?, ?, ?, ?, 'examiner', ?)")
                    ->execute([$first . ' ' . $last, $first, $last, $user, password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), $gid]);
                $xid = (int) $db->lastInsertId();
                $ins = $db->prepare("INSERT INTO exam_examiners (exam_id, examiner_id) SELECT id, ? FROM exams WHERE id = ? AND group_id = ? AND deleted_at IS NULL");
                foreach (array_map('intval', (array) ($_POST['exam_ids'] ?? [])) as $eid) {
                    $ins->execute([$xid, $eid, $gid]);
                }
                cc_flash('success', "$first $last can now sign in on the staff login page as \"$user\" — only for this group's exams, on one computer at a time.");
                cc_back('examiners');

            case 'assign':
                $xid = (int) $_POST['examiner_id'];
                $st = $db->prepare("SELECT group_id FROM examiners WHERE id = ? AND group_id IS NOT NULL AND deleted_at IS NULL");
                $st->execute([$xid]);
                $gid = (int) $st->fetchColumn();
                if (!$gid) {
                    cc_back('examiners');
                }
                $db->prepare("DELETE ee FROM exam_examiners ee JOIN exams e ON e.id = ee.exam_id WHERE ee.examiner_id = ? AND e.group_id = ?")->execute([$xid, $gid]);
                $ins = $db->prepare("INSERT INTO exam_examiners (exam_id, examiner_id) SELECT id, ? FROM exams WHERE id = ? AND group_id = ? AND deleted_at IS NULL");
                foreach (array_map('intval', (array) ($_POST['exam_ids'] ?? [])) as $eid) {
                    $ins->execute([$xid, $eid, $gid]);
                }
                cc_flash('success', 'Assignments saved.');
                cc_back('examiners');

            case 'reset_password':
                $pass = (string) $_POST['password'];
                if (strlen($pass) < 6) {
                    cc_flash('danger', 'The new password must have at least 6 characters.');
                    cc_back('examiners');
                }
                $db->prepare("UPDATE examiners SET password = ?, session_token = NULL WHERE id = ? AND group_id IS NOT NULL")
                    ->execute([password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), (int) $_POST['examiner_id']]);
                cc_flash('success', 'Password changed. Any open session of this examiner was closed.');
                cc_back('examiners');

            case 'reset_session':
                $db->prepare("UPDATE examiners SET session_token = NULL, last_seen_at = NULL WHERE id = ? AND group_id IS NOT NULL")->execute([(int) $_POST['examiner_id']]);
                cc_flash('success', 'Session reset. The examiner is signed out everywhere and can sign in again on any computer.');
                cc_back('examiners');

            case 'remove_examiner':
                $db->prepare("UPDATE examiners SET deleted_at = NOW(), session_token = NULL WHERE id = ? AND group_id IS NOT NULL")->execute([(int) $_POST['examiner_id']]);
                $db->prepare("DELETE FROM exam_examiners WHERE examiner_id = ?")->execute([(int) $_POST['examiner_id']]);
                cc_flash('success', 'Examiner removed. Scores they already gave are kept.');
                cc_back('examiners');

            case 'end_group':
            case 'reopen_group':
                $link = cc_link($db, (int) $_POST['link_id']);
                if ($link) {
                    $end = $act === 'end_group';
                    $db->prepare("UPDATE exam_groups SET ended_at = " . ($end ? 'NOW()' : 'NULL') . " WHERE id = ?")->execute([$link['group_id']]);
                    if ($end) {
                        $db->prepare("UPDATE examiners SET session_token = NULL WHERE group_id = ?")->execute([$link['group_id']]);
                    }
                    cc_flash('success', $end
                        ? $link['group_name'] . ' is marked as over. Its examiners are signed out and cannot sign in again. Remember to send the results.'
                        : $link['group_name'] . ' is open again; its examiners can sign in.');
                    cc_back('group-' . $link['id']);
                }
                cc_back();
        }
    } catch (Throwable $ex) {
        error_log('center console: ' . $ex->getMessage());
        cc_flash('danger', $ex instanceof RuntimeException ? $ex->getMessage() : 'That did not work: ' . $ex->getMessage());
        cc_back();
    }
    cc_back();
}

$flash = $_SESSION['cc_flash'] ?? null;
unset($_SESSION['cc_flash']);
$is_center = cs_is_center($db);
$main_url = cs_setting($db, 'main_server_url', '');

if ($is_center) {
    $links = $db->query("SELECT l.*, g.name AS group_name, g.code AS group_code, g.description, g.activation_opens, g.activation_closes, g.exam_ends, g.ended_at
        FROM center_links l JOIN exam_groups g ON g.id = l.group_id AND g.deleted_at IS NULL ORDER BY l.activated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $group_exams = [];
    foreach ($links as $l) {
        $st = $db->prepare("SELECT e.*,
                (SELECT COUNT(*) FROM exam_students es WHERE es.exam_id = e.id) AS n_students,
                CASE WHEN e.type = 'objective' THEN (SELECT COUNT(*) FROM objective_questions q WHERE q.exam_id = e.id AND q.deleted_at IS NULL)
                     ELSE (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) END AS n_questions,
                CASE WHEN e.type = 'objective' THEN (SELECT COUNT(DISTINCT a.student_id) FROM objective_attempts a WHERE a.exam_id = e.id AND a.finished_at IS NOT NULL)
                     ELSE (SELECT COUNT(DISTINCT a.student_id) FROM answers a WHERE a.exam_id = e.id) END AS n_done,
                (SELECT GROUP_CONCAT(x.name ORDER BY x.name SEPARATOR '||') FROM exam_examiners ee JOIN examiners x ON x.id = ee.examiner_id AND x.deleted_at IS NULL WHERE ee.exam_id = e.id) AS examiners
            FROM exams e WHERE e.group_id = ? AND e.deleted_at IS NULL ORDER BY FIELD(e.type, 'objective', 'practical', 'viva', 'research'), e.name");
        $st->execute([$l['group_id']]);
        $group_exams[(int) $l['id']] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $all_grouped = [];
    foreach ($links as $l) {
        foreach ($group_exams[(int) $l['id']] as $e) {
            $all_grouped[] = $e + ['group_name' => $l['group_name']];
        }
    }
    $examiners = $db->query("SELECT x.id, x.name, x.firstname, x.lastname, x.username, x.group_id, x.session_token, x.last_seen_at,
            (SELECT GROUP_CONCAT(ee.exam_id) FROM exam_examiners ee WHERE ee.examiner_id = x.id) AS exam_ids
        FROM examiners x WHERE x.role = 'examiner' AND x.deleted_at IS NULL AND x.group_id IS NOT NULL ORDER BY x.group_id, x.lastname, x.name")->fetchAll(PDO::FETCH_ASSOC);
    $group_names = [];
    $group_state = [];
    foreach ($links as $l) {
        $group_names[(int) $l['group_id']] = $l['group_name'];
        $group_state[(int) $l['group_id']] = ($l['ended_at'] || ($l['exam_ends'] && strtotime($l['exam_ends']) < time())) ? 'ended' : 'open';
    }
}

$work_url = function (array $e) {
    if ($e['type'] === 'objective') {
        return ['exam_overview?exam_id=' . (int) $e['id'], 'Monitor'];
    }
    return ['report?exam_id=' . (int) $e['id'], 'Scores'];
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Center Console</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="assets/css/groups.css?v=1" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <a href="admin_dashboard" class="admin-back-btn" aria-label="Back"><i class="bi bi-arrow-left"></i></a>
          <h1 class="admin-navbar-title">Center Console</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge"><span class="admin-user-avatar"><?= htmlspecialchars(strtoupper(substr($name, 0, 1))) ?></span><span class="admin-user-name"><?= htmlspecialchars($name) ?></span></div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <?php if ($flash): ?>
        <div class="admin-alert admin-alert-<?= $flash[0] ?>"><i class="bi bi-<?= $flash[0] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i><span><?= htmlspecialchars($flash[1]) ?></span></div>
      <?php endif; ?>

      <div class="cc-role">
        <div>
          <div class="cc-role-name"><i class="bi bi-<?= $is_center ? 'building' : 'globe2' ?> me-1"></i> This server is <?= $is_center ? 'an exam centre' : 'the main server' ?></div>
          <div class="small text-muted"><?= $is_center
            ? 'It receives exam groups from the main server, runs them on this network and sends the results back.'
            : 'It creates exam groups and gives tokens to centres. Switch only on a computer installed at an exam centre.' ?></div>
        </div>
        <form method="post" data-swal="<?= $is_center ? 'Make this server the main server again? Groups already received stay here.' : 'Make this server an exam centre? Use this only on a centre\'s own server.' ?>">
          <?php csrf_field(); ?><input type="hidden" name="act" value="set_role"><input type="hidden" name="role" value="<?= $is_center ? 'main' : 'center' ?>">
          <button class="admin-btn admin-btn-outline"><?= $is_center ? 'Switch to main server' : 'Switch to exam centre' ?></button>
        </form>
      </div>

      <?php if (!$is_center): ?>
        <div class="eg-empty">
          <i class="bi bi-diagram-3"></i>
          <p class="mt-2 mb-3">On the main server, grouped exams are prepared under <b>Exam Groups</b>.</p>
          <a class="admin-btn admin-btn-primary" href="admin_exam_groups">Open Exam Groups</a>
        </div>
      <?php else: ?>

        <section id="activate" class="cc-activate">
          <form method="post" class="admin-card mb-0">
            <?php csrf_field(); ?><input type="hidden" name="act" value="activate_online">
            <div class="admin-card-header"><h3 class="admin-card-title"><i class="bi bi-cloud-arrow-down me-2"></i>Activate online</h3></div>
            <div class="admin-card-body">
              <label class="admin-form-label">Main server address</label>
              <input name="main_url" class="admin-input mb-2" value="<?= htmlspecialchars($main_url) ?>" placeholder="https://exams.example.edu.ng/CBT" required>
              <label class="admin-form-label">Centre token</label>
              <input name="token" class="admin-input cc-token-input mb-3" placeholder="CBT-XXXXXX-XXXXX-XXXXX-XXXXX-XXXXX" autocomplete="off" required>
              <button class="admin-btn admin-btn-primary w-100"><i class="bi bi-box-arrow-in-down"></i> Download and install the exams</button>
              <p class="small text-muted mt-2 mb-0">Needs internet only for this step and when sending results. The main server accepts it only inside the activation time it set.</p>
            </div>
          </form>
          <form method="post" enctype="multipart/form-data" class="admin-card mb-0">
            <?php csrf_field(); ?><input type="hidden" name="act" value="activate_file">
            <div class="admin-card-header"><h3 class="admin-card-title"><i class="bi bi-usb-drive me-2"></i>Activate from a package file</h3></div>
            <div class="admin-card-body">
              <label class="admin-form-label">Package file (.cbtpkg)</label>
              <input type="file" name="package" accept=".cbtpkg" class="admin-input mb-2" required>
              <label class="admin-form-label">Centre token</label>
              <input name="token" class="admin-input cc-token-input mb-3" placeholder="CBT-XXXXXX-XXXXX-XXXXX-XXXXX-XXXXX" autocomplete="off" required>
              <button class="admin-btn admin-btn-outline w-100"><i class="bi bi-file-earmark-lock"></i> Open and install</button>
              <p class="small text-muted mt-2 mb-0">For centres without internet. The file is locked; only the matching token opens it.</p>
            </div>
          </form>
        </section>

        <?php if (!$links): ?>
          <div class="eg-empty"><i class="bi bi-inboxes"></i><p class="mt-2 mb-0">No exam group has been received yet. Activate a token above.</p></div>
        <?php endif; ?>

        <?php foreach ($links as $l): $exs = $group_exams[(int) $l['id']]; ?>
          <section class="cc-group" id="group-<?= (int) $l['id'] ?>">
            <div class="cc-group-head">
              <div>
                <span class="eg-code"><?= htmlspecialchars($l['group_code']) ?></span>
                <h3><?= htmlspecialchars($l['group_name']) ?></h3>
                <div class="cc-group-meta">
                  <span><i class="bi bi-building"></i> <?= htmlspecialchars($l['center_name']) ?></span>
                  <span><i class="bi bi-key"></i> CBT-<?= htmlspecialchars($l['prefix']) ?>-•••••</span>
                  <span><i class="bi bi-box-seam"></i> Package of <?= $l['package_version'] ? date('j M, g:i A', (int) $l['package_version']) : '—' ?></span>
                  <span><i class="bi bi-hourglass-split"></i> <?= $l['ended_at'] ? 'Ended ' . date('j M, g:i A', strtotime($l['ended_at'])) : ($l['exam_ends'] ? 'Ends ' . date('j M, g:i A', strtotime($l['exam_ends'])) : 'No end time — end it here when done') ?></span>
                  <span><i class="bi bi-hdd-network"></i> <?= $l['main_url'] ? htmlspecialchars($l['main_url']) : 'no main server address' ?></span>
                </div>
              </div>
              <div class="cc-group-actions">
                <form method="post" data-swal="Send this centre's results for every exam in the group to the main server now?">
                  <?php csrf_field(); ?><input type="hidden" name="act" value="sync"><input type="hidden" name="link_id" value="<?= (int) $l['id'] ?>">
                  <button class="admin-btn admin-btn-primary" <?= $l['main_url'] ? '' : 'disabled' ?>><i class="bi bi-cloud-arrow-up"></i> Send results</button>
                </form>
                <a class="admin-btn admin-btn-outline" href="<?= $self ?>?results=<?= (int) $l['id'] ?>"><i class="bi bi-download"></i> Results file</a>
                <?php $time_over = $l['exam_ends'] && strtotime($l['exam_ends']) < time(); $is_over = $l['ended_at'] || $time_over; ?>
                <?php if (!$time_over): ?>
                <form method="post" data-swal="<?= $is_over ? 'Open this group exam again so its examiners can sign in?' : 'Mark this group exam as over? Its examiners are signed out at once and cannot sign in again.' ?>">
                  <?php csrf_field(); ?><input type="hidden" name="act" value="<?= $is_over ? 'reopen_group' : 'end_group' ?>"><input type="hidden" name="link_id" value="<?= (int) $l['id'] ?>">
                  <button class="admin-btn <?= $is_over ? 'admin-btn-outline' : 'admin-btn-ghost text-danger' ?>"><?= $is_over ? '<i class="bi bi-unlock"></i> Reopen' : '<i class="bi bi-flag"></i> End group exam' ?></button>
                </form>
                <?php endif; ?>
                <form method="post">
                  <?php csrf_field(); ?><input type="hidden" name="act" value="refresh"><input type="hidden" name="link_id" value="<?= (int) $l['id'] ?>">
                  <button class="admin-btn admin-btn-outline" <?= $l['main_url'] ? '' : 'disabled' ?> title="Get corrections the main server made to questions or students"><i class="bi bi-arrow-repeat"></i> Refresh package</button>
                </form>
              </div>
            </div>
            <div class="cc-sync-line">
              <i class="bi bi-clock-history"></i>
              <?= $l['last_sync_at'] ? 'Last sent: ' . htmlspecialchars((string) $l['last_sync_status']) : 'Results not sent yet. Send them after each paper, and again at the end of the day.' ?>
              <?php if (!$l['main_url']): ?>
                <form method="post" class="d-flex gap-2 mt-2" style="max-width:560px">
                  <?php csrf_field(); ?><input type="hidden" name="act" value="set_url"><input type="hidden" name="link_id" value="<?= (int) $l['id'] ?>">
                  <input name="main_url" class="admin-input" placeholder="Main server address, to send results online">
                  <button class="admin-btn admin-btn-outline">Save</button>
                </form>
              <?php endif; ?>
            </div>
            <?php foreach ($exs as $e): [$wu, $wl] = $work_url($e); $names = array_filter(explode('||', (string) $e['examiners'])); ?>
              <div class="cc-exam-row">
                <div>
                  <div class="cc-exam-name"><?= htmlspecialchars($e['name']) ?> <span class="admin-badge admin-badge-<?= $e['is_active'] ? 'success' : 'secondary' ?>"><?= $e['is_active'] ? 'on' : 'off' ?></span></div>
                  <div class="cc-exam-counts"><?= $e['start_time'] ? date('j M, g:i A', strtotime($e['start_time'])) : 'Any time' ?><?= $e['type'] === 'objective' ? ' · ' . (int) $e['duration_minutes'] . ' min' : '' ?></div>
                </div>
                <div><span class="eg-type eg-t-<?= $e['type'] ?>"><?= $TYPE_LABEL[$e['type']] ?></span></div>
                <div class="cc-exam-counts"><b><?= (int) $e['n_students'] ?></b> students · <b><?= (int) $e['n_questions'] ?></b> <?= $e['type'] === 'objective' ? 'questions' : 'items' ?><br>
                  <b><?= (int) $e['n_done'] ?></b> <?= $e['type'] === 'objective' ? 'submitted' : 'scored' ?></div>
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                  <div class="cc-examiners">
                    <?php if (!$names): ?><span class="cc-chip is-none">No examiner yet</span><?php endif; ?>
                    <?php foreach ($names as $n): ?><span class="cc-chip"><?= htmlspecialchars($n) ?></span><?php endforeach; ?>
                  </div>
                  <div class="d-flex gap-1">
                    <form method="post">
                      <?php csrf_field(); ?><input type="hidden" name="act" value="toggle_exam"><input type="hidden" name="exam_id" value="<?= (int) $e['id'] ?>">
                      <button class="admin-btn admin-btn-sm <?= $e['is_active'] ? 'admin-btn-outline' : 'admin-btn-primary' ?>" title="<?= $e['is_active'] ? 'Hide it from students and examiners' : 'Let students and examiners see it' ?>"><?= $e['is_active'] ? 'Switch off' : 'Switch on' ?></button>
                    </form>
                    <a class="admin-btn admin-btn-sm admin-btn-outline" href="<?= $wu ?>"><?= $wl ?></a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>

        <section class="admin-card" id="examiners">
          <div class="admin-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h3 class="admin-card-title"><i class="bi bi-person-badge me-2"></i>Group examiners</h3>
              <div class="small text-muted">Each account works only on the exams of its group, on one computer at a time, and closes when that group exam is over.</div>
            </div>
            <button class="admin-btn admin-btn-sm admin-btn-primary" data-bs-toggle="modal" data-bs-target="#examinerModal" <?= $all_grouped ? '' : 'disabled' ?>><i class="bi bi-person-plus"></i> New examiner</button>
          </div>
          <div class="admin-card-body-flush">
            <div class="table-responsive">
              <table class="admin-table mb-0">
                <thead><tr><th>Examiner</th><th>Username</th><th>Group</th><th>Exams</th><th>Session</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                  <?php if (!$examiners): ?><tr><td colspan="6" class="text-center text-muted py-4">No group examiners yet. Create one for each person who will mark or supervise.</td></tr><?php endif; ?>
                  <?php foreach ($examiners as $x):
                    $mine = array_map('intval', array_filter(explode(',', (string) $x['exam_ids'])));
                    $gid = (int) $x['group_id'];
                    $ended = ($group_state[$gid] ?? 'ended') === 'ended';
                    $online = examiner_session_busy($x); ?>
                    <tr>
                      <td><b><?= htmlspecialchars(trim(($x['firstname'] ?? '') . ' ' . ($x['lastname'] ?? '')) ?: $x['name']) ?></b></td>
                      <td><code><?= htmlspecialchars($x['username']) ?></code></td>
                      <td class="small"><?= htmlspecialchars($group_names[$gid] ?? '—') ?></td>
                      <td>
                        <div class="cc-examiners">
                          <?php $any = false; foreach ($all_grouped as $e): if (in_array((int) $e['id'], $mine, true)): $any = true; ?>
                            <span class="cc-chip"><?= htmlspecialchars($e['name']) ?></span>
                          <?php endif; endforeach; ?>
                          <?php if (!$any): ?><span class="cc-chip is-none">none</span><?php endif; ?>
                        </div>
                      </td>
                      <td class="small text-nowrap">
                        <?php if ($ended): ?><span class="eg-state eg-s-off">Group over</span>
                        <?php elseif ($online): ?><span class="eg-state eg-s-done">Signed in</span><div class="text-muted">seen <?= date('g:i A', strtotime($x['last_seen_at'])) ?></div>
                        <?php else: ?><span class="eg-state eg-s-wait">Signed out</span><?php endif; ?>
                      </td>
                      <td class="text-end text-nowrap">
                        <button class="admin-btn admin-btn-sm admin-btn-outline" data-bs-toggle="modal" data-bs-target="#assignModal"
                          data-examiner="<?= (int) $x['id'] ?>" data-group="<?= $gid ?>" data-name="<?= htmlspecialchars($x['name']) ?>" data-exams="<?= htmlspecialchars(implode(',', $mine)) ?>">Exams</button>
                        <button class="admin-btn admin-btn-sm admin-btn-outline" data-bs-toggle="modal" data-bs-target="#passModal"
                          data-examiner="<?= (int) $x['id'] ?>" data-name="<?= htmlspecialchars($x['name']) ?>">Password</button>
                        <?php if (!empty($x['session_token'])): ?>
                          <form method="post" class="d-inline" data-swal="Sign this examiner out everywhere? Use it when their computer failed and they need to sign in on another one.">
                            <?php csrf_field(); ?><input type="hidden" name="act" value="reset_session"><input type="hidden" name="examiner_id" value="<?= (int) $x['id'] ?>">
                            <button class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Reset session</button>
                          </form>
                        <?php endif; ?>
                        <form method="post" class="d-inline" data-swal="Remove this examiner? Scores they already gave are kept.">
                          <?php csrf_field(); ?><input type="hidden" name="act" value="remove_examiner"><input type="hidden" name="examiner_id" value="<?= (int) $x['id'] ?>">
                          <button class="admin-btn admin-btn-sm admin-btn-ghost text-danger" title="Remove"><i class="bi bi-trash"></i></button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <?php $exam_checks = function () use ($all_grouped, $TYPE_LABEL) {
          $out = '<div class="cc-assign">';
          foreach ($all_grouped as $e) {
            $out .= '<label data-group="' . (int) $e['group_id'] . '"><input type="checkbox" name="exam_ids[]" value="' . (int) $e['id'] . '"> <span class="eg-type eg-t-' . $e['type'] . '">'
              . $TYPE_LABEL[$e['type']] . '</span> ' . htmlspecialchars($e['name']) . '</label>';
          }
          return $out . '</div>';
        }; ?>

        <div class="modal fade" id="examinerModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content" autocomplete="off">
            <?php csrf_field(); ?><input type="hidden" name="act" value="add_examiner">
            <div class="modal-header"><h5 class="modal-title">New group examiner</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <div class="row g-2">
                <div class="col-12">
                  <label class="admin-form-label">Exam group</label>
                  <select name="group_id" class="admin-input admin-select" data-group-pick required>
                    <?php foreach ($links as $l): ?><option value="<?= (int) $l['group_id'] ?>"><?= htmlspecialchars($l['group_name']) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="col-6"><label class="admin-form-label">First name</label><input name="firstname" class="admin-input" required></div>
                <div class="col-6"><label class="admin-form-label">Last name</label><input name="lastname" class="admin-input" required></div>
                <div class="col-6"><label class="admin-form-label">Username</label><input name="username" class="admin-input" required pattern="[A-Za-z0-9_.\-]{3,40}" data-username></div>
                <div class="col-6"><label class="admin-form-label">Password</label><input name="password" type="text" class="admin-input" required minlength="6" value="<?= htmlspecialchars(strtolower(cs_random_chars(8))) ?>"></div>
              </div>
              <label class="admin-form-label mt-3">Exams this examiner handles</label>
              <?= $exam_checks() ?>
            </div>
            <div class="modal-footer"><button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-primary">Create examiner</button></div>
          </form></div>
        </div>

        <div class="modal fade" id="assignModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
            <?php csrf_field(); ?><input type="hidden" name="act" value="assign"><input type="hidden" name="examiner_id" value="">
            <div class="modal-header"><h5 class="modal-title">Exams for <span data-name></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><?= $exam_checks() ?></div>
            <div class="modal-footer"><button class="admin-btn admin-btn-primary">Save</button></div>
          </form></div>
        </div>

        <div class="modal fade" id="passModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered modal-sm"><form method="post" class="modal-content" autocomplete="off">
            <?php csrf_field(); ?><input type="hidden" name="act" value="reset_password"><input type="hidden" name="examiner_id" value="">
            <div class="modal-header"><h5 class="modal-title">New password for <span data-name></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><input name="password" type="text" class="admin-input" minlength="6" required></div>
            <div class="modal-footer"><button class="admin-btn admin-btn-primary">Change</button></div>
          </form></div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
  <script>
    (function () {
      ['assignModal', 'passModal'].forEach(function (id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.addEventListener('show.bs.modal', function (ev) {
          var b = ev.relatedTarget;
          m.querySelector('[name=examiner_id]').value = b.getAttribute('data-examiner');
          m.querySelector('[data-name]').textContent = b.getAttribute('data-name');
          var mine = (b.getAttribute('data-exams') || '').split(',');
          var g = b.getAttribute('data-group');
          m.querySelectorAll('.cc-assign label').forEach(function (l) {
            var show = !g || l.getAttribute('data-group') === g;
            l.style.display = show ? '' : 'none';
            var c = l.querySelector('input'); c.checked = show && mine.indexOf(c.value) > -1; c.disabled = !show;
          });
        });
      });
      var pick = document.querySelector('[data-group-pick]');
      if (pick) {
        var filter = function () {
          pick.form.querySelectorAll('.cc-assign label').forEach(function (l) {
            var show = l.getAttribute('data-group') === pick.value;
            l.style.display = show ? '' : 'none';
            var c = l.querySelector('input'); c.disabled = !show; if (!show) c.checked = false;
          });
        };
        pick.addEventListener('change', filter); filter();
        var f = pick.form, u = f.querySelector('[data-username]');
        ['firstname', 'lastname'].forEach(function (n) {
          f.querySelector('[name=' + n + ']').addEventListener('input', function () {
            if (u.dataset.touched) return;
            u.value = (f.firstname.value.trim() + '.' + f.lastname.value.trim()).toLowerCase().replace(/[^a-z0-9.]/g, '').replace(/^\.|\.$/g, '');
          });
        });
        u.addEventListener('input', function () { u.dataset.touched = '1'; });
      }
      document.querySelectorAll('.cc-token-input').forEach(function (i) {
        i.addEventListener('input', function () {
          var raw = i.value.toUpperCase().replace(/[^A-Z0-9]/g, '').replace(/^CBT/, '').slice(0, 26);
          var parts = [raw.slice(0, 6)];
          for (var p = 6; p < raw.length; p += 5) parts.push(raw.slice(p, p + 5));
          i.value = raw ? 'CBT-' + parts.join('-') : '';
        });
      });
    })();
  </script>
</body>

</html>
