<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
require_once 'includes/center_sync.php';

$name = $_SESSION['user'] ?? 'Admin';
$self = 'admin_exam_groups';
$group_id = (int) ($_GET['id'] ?? $_POST['group_id'] ?? 0);

if (cs_is_center($db)) {
    header('Location: center_console');
    exit;
}

function eg_flash(string $type, string $msg): void
{
    $_SESSION['eg_flash'] = [$type, $msg];
}

function eg_back(int $group_id = 0, string $anchor = ''): void
{
    header('Location: admin_exam_groups' . ($group_id ? '?id=' . $group_id : '') . ($anchor ? '#' . $anchor : ''));
    exit;
}

function eg_code(string $s): string
{
    $c = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return substr($c !== '' ? $c : 'GROUP', 0, 40);
}

function eg_dt(?string $v): ?string
{
    $v = trim((string) $v);
    return $v === '' ? null : date('Y-m-d H:i:s', strtotime($v));
}

$TYPE_LABEL = ['objective' => 'Objective', 'practical' => 'Practical', 'viva' => 'Viva', 'research' => 'Research'];

/* ---------------- downloads ---------------- */

if (isset($_GET['package'])) {
    $token = cs_token_by_prefix($db, strtoupper((string) $_GET['package']));
    if (!$token || $token['status'] === 'revoked') {
        http_response_code(404);
        exit('Token not found.');
    }
    $pkg = cs_build_package($db, $token);
    $group = cs_group($db, (int) $token['group_id']);
    $db->prepare("UPDATE center_tokens SET last_download_at = NOW() WHERE id = ?")->execute([(int) $token['id']]);
    cs_log($db, (int) $token['id'], null, 'package', true, 'Package file downloaded by ' . $name . ' (' . count($pkg['exams']) . ' exams, ' . count($pkg['students']) . ' students)');
    $file = cs_wrap_file('package', $token['prefix'], $group['name'] . ' — ' . $token['center_name'], cs_seal($pkg, $token['pkg_key']));
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $group['code'] . '_' . $token['center_code']) . '.cbtpkg"');
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
            case 'save_group':
                $gname = trim((string) ($_POST['name'] ?? ''));
                if ($gname === '') {
                    eg_flash('danger', 'Give the group a name.');
                    eg_back($group_id);
                }
                $code = eg_code((string) (($_POST['code'] ?? '') ?: $gname));
                $fields = [$gname, $code, trim((string) ($_POST['description'] ?? '')), eg_dt($_POST['activation_opens'] ?? ''), eg_dt($_POST['activation_closes'] ?? ''),
                    in_array($_POST['status'] ?? '', ['draft', 'published', 'closed'], true) ? $_POST['status'] : 'draft', eg_dt($_POST['exam_ends'] ?? '')];
                if ($fields[3] && $fields[4] && strtotime($fields[4]) <= strtotime($fields[3])) {
                    eg_flash('danger', 'Activation must close after it opens.');
                    eg_back($group_id);
                }
                if ($group_id) {
                    $db->prepare("UPDATE exam_groups SET name = ?, code = ?, description = ?, activation_opens = ?, activation_closes = ?, status = ?, exam_ends = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([...$fields, $group_id]);
                    eg_flash('success', 'Group saved.');
                } else {
                    $db->prepare("INSERT INTO exam_groups (name, code, description, activation_opens, activation_closes, status, exam_ends, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())")
                        ->execute($fields);
                    $group_id = (int) $db->lastInsertId();
                    eg_flash('success', 'Group created. Add its exams below, then issue a token for each centre.');
                }
                eg_back($group_id);

            case 'delete_group':
                $db->prepare("UPDATE exam_groups SET deleted_at = NOW() WHERE id = ?")->execute([$group_id]);
                $db->prepare("UPDATE exams SET group_id = NULL WHERE group_id = ?")->execute([$group_id]);
                $db->prepare("UPDATE center_tokens SET status = 'revoked', revoked_at = NOW() WHERE group_id = ? AND revoked_at IS NULL")->execute([$group_id]);
                eg_flash('success', 'Group deleted. Its exams are kept as ordinary exams and its tokens were withdrawn.');
                eg_back();

            case 'add_exams':
                $ids = array_filter(array_map('intval', (array) ($_POST['exam_ids'] ?? [])));
                if ($ids) {
                    $db->prepare("UPDATE exams SET group_id = ? WHERE id IN (" . implode(',', $ids) . ") AND group_id IS NULL AND deleted_at IS NULL")->execute([$group_id]);
                    cs_touch_group($db, $group_id);
                }
                eg_flash('success', count($ids) . ' exam' . (count($ids) === 1 ? '' : 's') . ' added to the group.');
                eg_back($group_id, 'exams');

            case 'remove_exam':
                $db->prepare("UPDATE exams SET group_id = NULL WHERE id = ? AND group_id = ?")->execute([(int) $_POST['exam_id'], $group_id]);
                cs_touch_group($db, $group_id);
                eg_flash('success', 'Exam taken out of the group. Centres drop it the next time they refresh their package.');
                eg_back($group_id, 'exams');

            case 'issue_token':
                $center_id = (int) ($_POST['center_id'] ?? 0);
                if (!$center_id) {
                    $cname = trim((string) ($_POST['center_name'] ?? ''));
                    if ($cname === '') {
                        eg_flash('danger', 'Choose a centre or type the name of a new one.');
                        eg_back($group_id, 'centres');
                    }
                    $ccode = eg_code((string) (($_POST['center_code'] ?? '') ?: $cname));
                    $st = $db->prepare("SELECT id FROM exam_centers WHERE code = ?");
                    $st->execute([$ccode]);
                    $center_id = (int) $st->fetchColumn();
                    if (!$center_id) {
                        $db->prepare("INSERT INTO exam_centers (name, code, location, manager_name, manager_contact) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$cname, $ccode, trim((string) ($_POST['location'] ?? '')), trim((string) ($_POST['manager_name'] ?? '')), trim((string) ($_POST['manager_contact'] ?? ''))]);
                        $center_id = (int) $db->lastInsertId();
                    }
                }
                $st = $db->prepare("SELECT prefix FROM center_tokens WHERE group_id = ? AND center_id = ? AND revoked_at IS NULL");
                $st->execute([$group_id, $center_id]);
                if ($p = $st->fetchColumn()) {
                    eg_flash('warning', 'That centre already has a live token for this group (CBT-' . $p . '-…). Withdraw it first if the centre lost it.');
                    eg_back($group_id, 'centres');
                }
                do {
                    $t = cs_new_token();
                    $exists = $db->prepare("SELECT 1 FROM center_tokens WHERE prefix = ?");
                    $exists->execute([$t['prefix']]);
                } while ($exists->fetchColumn());
                $db->prepare("INSERT INTO center_tokens (group_id, center_id, prefix, pkg_key, status, issued_at, issued_by) VALUES (?, ?, ?, ?, 'issued', NOW(), ?)")
                    ->execute([$group_id, $center_id, $t['prefix'], $t['pkg_key'], $name]);
                $tid = (int) $db->lastInsertId();
                cs_log($db, $tid, null, 'issue', true, 'Token issued by ' . $name);
                // shown once: the server keeps only the derived key
                $_SESSION['eg_new_token'] = ['token' => $t['token'], 'prefix' => $t['prefix']];
                eg_back($group_id, 'centres');

            case 'revoke_token':
                $db->prepare("UPDATE center_tokens SET status = 'revoked', revoked_at = NOW() WHERE id = ? AND group_id = ?")->execute([(int) $_POST['token_id'], $group_id]);
                cs_log($db, (int) $_POST['token_id'], null, 'revoke', true, 'Withdrawn by ' . $name);
                eg_flash('success', 'Token withdrawn. The centre can no longer download or send results with it.');
                eg_back($group_id, 'centres');

            case 'allocate':
                $tid = (int) $_POST['token_id'];
                $st = $db->prepare("SELECT id FROM center_tokens WHERE id = ? AND group_id = ?");
                $st->execute([$tid, $group_id]);
                if (!$st->fetchColumn()) {
                    eg_back($group_id, 'centres');
                }
                $mode = (string) ($_POST['mode'] ?? 'list');
                $enrolled = "SELECT DISTINCT es.student_id FROM exam_students es JOIN exams e ON e.id = es.exam_id AND e.group_id = $group_id AND e.deleted_at IS NULL";
                if ($mode === 'clear') {
                    $db->prepare("DELETE FROM center_students WHERE token_id = ?")->execute([$tid]);
                    eg_flash('success', 'Allocation cleared. The centre will receive every student of the group until you allocate again.');
                } elseif ($mode === 'remaining') {
                    $st = $db->prepare("INSERT IGNORE INTO center_students (token_id, student_id)
                        SELECT ?, x.student_id FROM ($enrolled) x
                        WHERE x.student_id NOT IN (SELECT cs.student_id FROM center_students cs JOIN center_tokens t ON t.id = cs.token_id AND t.group_id = ? AND t.revoked_at IS NULL)");
                    $st->execute([$tid, $group_id]);
                    eg_flash('success', $st->rowCount() . ' unallocated students given to this centre.');
                } else {
                    $list = preg_split('/[\s,;]+/', strtoupper((string) $_POST['matrics']), -1, PREG_SPLIT_NO_EMPTY);
                    $added = 0;
                    $unknown = [];
                    $find = $db->prepare("SELECT s.id FROM students s WHERE UPPER(s.matric_no) = ? AND s.deleted_at IS NULL AND s.id IN ($enrolled)");
                    $ins = $db->prepare("INSERT IGNORE INTO center_students (token_id, student_id) VALUES (?, ?)");
                    foreach (array_unique($list) as $m) {
                        $find->execute([$m]);
                        $sid = (int) $find->fetchColumn();
                        if ($sid) {
                            $ins->execute([$tid, $sid]);
                            $added += $ins->rowCount();
                        } else {
                            $unknown[] = $m;
                        }
                    }
                    $msg = "$added student" . ($added === 1 ? '' : 's') . ' allocated.';
                    if ($unknown) {
                        $msg .= ' Not found among the group\'s students: ' . implode(', ', array_slice($unknown, 0, 15)) . (count($unknown) > 15 ? ' …' : '');
                    }
                    eg_flash($unknown ? 'warning' : 'success', $msg);
                }
                eg_back($group_id, 'centres');

            case 'upload_results':
                $tid = (int) $_POST['token_id'];
                $st = $db->prepare("SELECT prefix FROM center_tokens WHERE id = ? AND group_id = ?");
                $st->execute([$tid, $group_id]);
                $token = ($p = $st->fetchColumn()) ? cs_token_by_prefix($db, $p) : null;
                $f = $_FILES['results'] ?? null;
                if (!$token || !$f || $f['error'] !== UPLOAD_ERR_OK) {
                    eg_flash('danger', 'Choose the results file the centre sent.');
                    eg_back($group_id, 'centres');
                }
                $wrap = cs_unwrap_file((string) file_get_contents($f['tmp_name']));
                if (!$wrap || $wrap['kind'] !== 'results') {
                    eg_flash('danger', 'That file is not a centre results file.');
                    eg_back($group_id, 'centres');
                }
                if ($wrap['prefix'] !== $token['prefix']) {
                    eg_flash('danger', 'That results file belongs to another centre (token CBT-' . htmlspecialchars($wrap['prefix']) . '). Upload it on that centre\'s row.');
                    eg_back($group_id, 'centres');
                }
                $bundle = cs_open($wrap['sealed'], $token['pkg_key']);
                if (!$bundle) {
                    eg_flash('danger', 'The results file could not be opened. It may be damaged or altered.');
                    eg_back($group_id, 'centres');
                }
                $parts = [];
                foreach ($bundle['exams'] ?? [] as $one) {
                    $sum = cs_apply_results($db, $token, $one);
                    $parts[] = $sum['exam'] . ': ' . cs_summary_text($sum);
                }
                eg_flash('success', 'Results received from ' . $token['center_name'] . '. ' . ($parts ? implode('; ', $parts) : 'The file had no exams.'));
                eg_back($group_id, 'centres');
        }
    } catch (Throwable $ex) {
        error_log('exam groups: ' . $ex->getMessage());
        eg_flash('danger', $ex instanceof RuntimeException ? $ex->getMessage() : 'That did not work. Nothing was changed.');
        eg_back($group_id);
    }
    eg_back($group_id);
}

$flash = $_SESSION['eg_flash'] ?? null;
unset($_SESSION['eg_flash']);
$new_token = $_SESSION['eg_new_token'] ?? null;
unset($_SESSION['eg_new_token']);

/* ---------------- data ---------------- */

$group = $group_id ? cs_group($db, $group_id) : null;
if ($group_id && !$group) {
    eg_back();
}

$fmt_dt = fn($v) => $v ? date('j M Y, g:i A', strtotime($v)) : '—';
$window_text = function (array $g) use ($fmt_dt) {
    if (!$g['activation_opens'] && !$g['activation_closes']) {
        return 'Any time once published';
    }
    return ($g['activation_opens'] ? $fmt_dt($g['activation_opens']) : 'now') . ' → ' . ($g['activation_closes'] ? $fmt_dt($g['activation_closes']) : 'no end');
};
$status_badge = fn($s) => ['draft' => ['Draft', 'secondary'], 'published' => ['Published', 'success'], 'closed' => ['Closed', 'warning']][$s] ?? [$s, 'secondary'];

if (!$group) {
    $groups = $db->query("SELECT g.*,
            (SELECT COUNT(*) FROM exams e WHERE e.group_id = g.id AND e.deleted_at IS NULL) AS n_exams,
            (SELECT GROUP_CONCAT(e.type) FROM exams e WHERE e.group_id = g.id AND e.deleted_at IS NULL) AS types,
            (SELECT COUNT(*) FROM center_tokens t WHERE t.group_id = g.id AND t.revoked_at IS NULL) AS n_centres,
            (SELECT COUNT(*) FROM center_tokens t WHERE t.group_id = g.id AND t.revoked_at IS NULL AND t.activated_at IS NOT NULL) AS n_active,
            (SELECT MAX(t.last_sync_at) FROM center_tokens t WHERE t.group_id = g.id) AS last_sync
        FROM exam_groups g WHERE g.deleted_at IS NULL ORDER BY g.status = 'published' DESC, g.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $exams = $db->prepare("SELECT e.*,
            (SELECT COUNT(*) FROM exam_students es WHERE es.exam_id = e.id) AS n_students,
            CASE WHEN e.type = 'objective' THEN (SELECT COUNT(*) FROM objective_questions q WHERE q.exam_id = e.id AND q.deleted_at IS NULL)
                 ELSE (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) END AS n_questions,
            CASE WHEN e.type = 'objective' THEN (SELECT COUNT(DISTINCT a.student_id) FROM objective_attempts a WHERE a.exam_id = e.id AND a.finished_at IS NOT NULL)
                 ELSE (SELECT COUNT(DISTINCT a.student_id) FROM answers a WHERE a.exam_id = e.id) END AS n_results
        FROM exams e WHERE e.group_id = ? AND e.deleted_at IS NULL ORDER BY FIELD(e.type, 'objective', 'practical', 'viva', 'research'), e.name");
    $exams->execute([$group_id]);
    $exams = $exams->fetchAll(PDO::FETCH_ASSOC);

    $free = $db->query("SELECT id, name, type, is_active FROM exams WHERE group_id IS NULL AND deleted_at IS NULL ORDER BY type, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

    $tokens = $db->prepare("SELECT t.*, c.name AS center_name, c.code AS center_code, c.location, c.manager_name, c.manager_contact,
            (SELECT COUNT(*) FROM center_students cs WHERE cs.token_id = t.id) AS n_alloc
        FROM center_tokens t JOIN exam_centers c ON c.id = t.center_id WHERE t.group_id = ? ORDER BY t.revoked_at IS NOT NULL, c.name");
    $tokens->execute([$group_id]);
    $tokens = $tokens->fetchAll(PDO::FETCH_ASSOC);

    $centres = $db->query("SELECT id, name, code, location FROM exam_centers WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $used = array_map(fn($t) => (int) $t['center_id'], array_filter($tokens, fn($t) => !$t['revoked_at']));

    $st = $db->prepare("SELECT COUNT(DISTINCT es.student_id) FROM exam_students es JOIN exams e ON e.id = es.exam_id WHERE e.group_id = ? AND e.deleted_at IS NULL");
    $st->execute([$group_id]);
    $n_group_students = (int) $st->fetchColumn();
    $st = $db->prepare("SELECT COUNT(DISTINCT cs.student_id) FROM center_students cs JOIN center_tokens t ON t.id = cs.token_id WHERE t.group_id = ? AND t.revoked_at IS NULL");
    $st->execute([$group_id]);
    $n_allocated = (int) $st->fetchColumn();

    $log = $db->prepare("SELECT l.*, c.name AS center_name FROM sync_log l JOIN center_tokens t ON t.id = l.token_id JOIN exam_centers c ON c.id = t.center_id
        WHERE t.group_id = ? ORDER BY l.id DESC LIMIT 40");
    $log->execute([$group_id]);
    $log = $log->fetchAll(PDO::FETCH_ASSOC);

    $by_type = [];
    foreach ($exams as $e) {
        $by_type[$e['type']] = ($by_type[$e['type']] ?? 0) + 1;
    }
    $package_stale = function (array $t) use ($group) {
        return $t['last_download_at'] && $group['updated_at'] && strtotime($group['updated_at']) > strtotime($t['last_download_at']);
    };
}

$manage_url = fn($e) => ['objective' => 'admin_objective_questions?exam_id=' . (int) $e['id'], 'practical' => 'admin_questions?exam_id=' . (int) $e['id'],
    'viva' => 'admin_viva_exams', 'research' => 'admin_research_exams'][$e['type']];
$results_url = fn($e) => ($e['type'] === 'objective' ? 'exam_overview?exam_id=' : 'report?exam_id=') . (int) $e['id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $group ? htmlspecialchars($group['name']) . ' — ' : '' ?>Exam Groups</title>
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
          <a href="<?= $group ? $self : 'admin_dashboard' ?>" class="admin-back-btn" aria-label="Back"><i class="bi bi-arrow-left"></i></a>
          <h1 class="admin-navbar-title">Exam Groups</h1>
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

      <?php if (!$group): ?>
        <div class="eg-intro">
          <div>
            <h2>Grouped examinations</h2>
            <p>A group holds the papers of one sitting — for example <em>Nursing Council Mock Exam</em> with its objective, practical and viva papers.
              Each exam centre gets a token, downloads the group on its own server, writes the exams offline and sends the results back here.</p>
          </div>
          <button class="admin-btn admin-btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal"><i class="bi bi-plus-lg"></i> New group</button>
        </div>

        <ol class="eg-steps">
          <li><b>Create the group</b> and add its exams</li>
          <li><b>Issue a token</b> to every centre</li>
          <li><b>Publish</b> and set the activation time</li>
          <li><b>Centres activate</b>, write, and sync results</li>
        </ol>

        <?php if (!$groups): ?>
          <div class="eg-empty">
            <i class="bi bi-collection"></i>
            <p>No exam groups yet.</p>
            <button class="admin-btn admin-btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal">Create the first group</button>
          </div>
        <?php else: ?>
          <div class="eg-grid">
            <?php foreach ($groups as $g): [$sl, $sc] = $status_badge($g['status']); $types = array_count_values(array_filter(explode(',', (string) $g['types']))); ?>
              <a class="eg-card" href="<?= $self ?>?id=<?= (int) $g['id'] ?>">
                <div class="eg-card-top">
                  <span class="eg-code"><?= htmlspecialchars($g['code']) ?></span>
                  <span class="admin-badge admin-badge-<?= $sc ?>"><?= $sl ?></span>
                </div>
                <h3><?= htmlspecialchars($g['name']) ?></h3>
                <div class="eg-types">
                  <?php if (!$types): ?><span class="eg-type eg-t-none">No exams yet</span><?php endif; ?>
                  <?php foreach ($types as $t => $n): ?><span class="eg-type eg-t-<?= $t ?>"><?= $n ?> <?= $TYPE_LABEL[$t] ?? $t ?></span><?php endforeach; ?>
                </div>
                <dl class="eg-facts">
                  <div><dt>Centres</dt><dd><?= (int) $g['n_active'] ?> / <?= (int) $g['n_centres'] ?> activated</dd></div>
                  <div><dt>Activation</dt><dd><?= htmlspecialchars($window_text($g)) ?></dd></div>
                  <div><dt>Last results</dt><dd><?= $g['last_sync'] ? date('j M, g:i A', strtotime($g['last_sync'])) : 'none yet' ?></dd></div>
                </dl>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php else: [$sl, $sc] = $status_badge($group['status']); $wp = cs_window_problem($group); ?>
        <nav class="admin-breadcrumb"><ol><li><a href="<?= $self ?>">Exam groups</a></li><li class="sep">/</li><li class="current"><?= htmlspecialchars($group['name']) ?></li></ol></nav>

        <?php if ($new_token): ?>
          <div class="eg-token-box">
            <div>
              <div class="eg-token-label">New centre token — copy it now, it will not be shown again</div>
              <code id="newToken"><?= htmlspecialchars($new_token['token']) ?></code>
              <p>Give it to the centre manager privately (not in a group chat). On the centre server they open <b>Center Console → Activate</b> and paste it.</p>
            </div>
            <button type="button" class="admin-btn admin-btn-primary" data-copy="#newToken"><i class="bi bi-clipboard"></i> Copy</button>
          </div>
        <?php endif; ?>

        <div class="eg-head">
          <div>
            <div class="eg-code mb-1"><?= htmlspecialchars($group['code']) ?></div>
            <h2><?= htmlspecialchars($group['name']) ?> <span class="admin-badge admin-badge-<?= $sc ?> align-middle"><?= $sl ?></span></h2>
            <?php if ($group['description']): ?><p class="text-muted mb-0"><?= nl2br(htmlspecialchars($group['description'])) ?></p><?php endif; ?>
          </div>
          <div class="admin-page-actions">
            <button class="admin-btn admin-btn-outline" data-bs-toggle="modal" data-bs-target="#groupModal"><i class="bi bi-pencil"></i> Edit</button>
            <form method="post" data-swal="Delete this group? Its exams stay as ordinary exams and every centre token is withdrawn.">
              <?php csrf_field(); ?><input type="hidden" name="act" value="delete_group"><input type="hidden" name="group_id" value="<?= $group_id ?>">
              <button class="admin-btn admin-btn-danger"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>

        <div class="eg-strip">
          <div><span>Activation window</span><b><?= htmlspecialchars($window_text($group)) ?></b>
            <em class="<?= $wp ? 'is-off' : 'is-on' ?>"><?= $wp ? htmlspecialchars($wp) : 'Open — centres can activate now' ?></em></div>
          <div><span>Exam ends</span><b><?= $group['exam_ends'] ? $fmt_dt($group['exam_ends']) : 'Set by each centre' ?></b>
            <em>centre examiner accounts close then</em></div>
          <div><span>Exams</span><b><?= count($exams) ?></b>
            <em><?= $by_type ? implode(' · ', array_map(fn($t, $n) => $n . ' ' . strtolower($TYPE_LABEL[$t]), array_keys($by_type), $by_type)) : 'add some below' ?></em></div>
          <div><span>Centres</span><b><?= count(array_filter($tokens, fn($t) => !$t['revoked_at'])) ?></b>
            <em><?= count(array_filter($tokens, fn($t) => !$t['revoked_at'] && $t['activated_at'])) ?> activated</em></div>
          <div><span>Students</span><b><?= $n_group_students ?></b>
            <em><?= $n_allocated ?> allocated to a centre</em></div>
        </div>

        <!-- exams -->
        <section class="admin-card" id="exams">
          <div class="admin-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="admin-card-title"><i class="bi bi-journal-text me-2"></i>Exams in this group</h3>
            <?php if ($free): ?><button class="admin-btn admin-btn-sm admin-btn-primary" data-bs-toggle="collapse" data-bs-target="#addExams"><i class="bi bi-plus-lg"></i> Add exams</button><?php endif; ?>
          </div>
          <div class="collapse" id="addExams">
            <form method="post" class="eg-add">
              <?php csrf_field(); ?><input type="hidden" name="act" value="add_exams"><input type="hidden" name="group_id" value="<?= $group_id ?>">
              <input type="search" class="admin-input mb-2" placeholder="Filter exams…" data-filter-list="#freeList">
              <div class="eg-free" id="freeList">
                <?php foreach ($free as $f): ?>
                  <label data-text="<?= htmlspecialchars(mb_strtolower($f['name'] . ' ' . $f['type'])) ?>">
                    <input type="checkbox" name="exam_ids[]" value="<?= (int) $f['id'] ?>">
                    <span class="eg-type eg-t-<?= $f['type'] ?>"><?= $TYPE_LABEL[$f['type']] ?? $f['type'] ?></span>
                    <span><?= htmlspecialchars($f['name']) ?></span>
                    <?php if (!$f['is_active']): ?><small class="text-muted">inactive</small><?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="mt-2 d-flex gap-2 align-items-center"><button class="admin-btn admin-btn-primary">Add selected</button>
                <span class="small text-muted">Create new papers first under Manage Objective, Practical, Viva or Research, then add them here.</span></div>
            </form>
          </div>
          <div class="admin-card-body-flush">
            <div class="table-responsive">
              <table class="admin-table mb-0">
                <thead><tr><th>Exam</th><th>Type</th><th class="text-end">Questions</th><th class="text-end">Students</th><th class="text-end">Results in</th><th>Schedule</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                  <?php if (!$exams): ?><tr><td colspan="7" class="text-center text-muted py-4">No exams in this group yet.</td></tr><?php endif; ?>
                  <?php foreach ($exams as $e): ?>
                    <tr>
                      <td><b><?= htmlspecialchars($e['name']) ?></b><?php if (!$e['is_active']): ?> <span class="admin-badge admin-badge-secondary">inactive</span><?php endif; ?></td>
                      <td><span class="eg-type eg-t-<?= $e['type'] ?>"><?= $TYPE_LABEL[$e['type']] ?></span></td>
                      <td class="text-end <?= (int) $e['n_questions'] === 0 ? 'text-danger fw-bold' : '' ?>"><?= (int) $e['n_questions'] ?></td>
                      <td class="text-end <?= (int) $e['n_students'] === 0 ? 'text-danger fw-bold' : '' ?>"><?= (int) $e['n_students'] ?></td>
                      <td class="text-end"><?= (int) $e['n_results'] ?></td>
                      <td class="small"><?= $e['start_time'] ? date('j M, g:i A', strtotime($e['start_time'])) : 'Any time' ?></td>
                      <td class="text-end text-nowrap">
                        <a class="admin-btn admin-btn-sm admin-btn-outline" href="<?= $manage_url($e) ?>">Manage</a>
                        <a class="admin-btn admin-btn-sm admin-btn-outline" href="admin_assign_students?exam_id=<?= (int) $e['id'] ?>">Students</a>
                        <a class="admin-btn admin-btn-sm admin-btn-outline" href="<?= $results_url($e) ?>">Results</a>
                        <form method="post" class="d-inline" data-swal="Take this exam out of the group?">
                          <?php csrf_field(); ?><input type="hidden" name="act" value="remove_exam"><input type="hidden" name="group_id" value="<?= $group_id ?>"><input type="hidden" name="exam_id" value="<?= (int) $e['id'] ?>">
                          <button class="admin-btn admin-btn-sm admin-btn-ghost" title="Remove from group"><i class="bi bi-x-lg"></i></button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- centres -->
        <section class="admin-card" id="centres">
          <div class="admin-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="admin-card-title"><i class="bi bi-buildings me-2"></i>Exam centres</h3>
            <button class="admin-btn admin-btn-sm admin-btn-primary" data-bs-toggle="modal" data-bs-target="#tokenModal"><i class="bi bi-key"></i> Issue token</button>
          </div>
          <div class="admin-card-body-flush">
            <?php if (!$tokens): ?>
              <p class="text-muted text-center py-4 mb-0">No centre has a token yet. Issue one for each centre that will write this group.</p>
            <?php else: ?>
              <div class="eg-centres">
                <?php foreach ($tokens as $t):
                  $state = $t['revoked_at'] ? ['Withdrawn', 'off'] : ($t['last_sync_at'] ? ['Results received', 'done'] : ($t['activated_at'] ? ['Activated', 'on'] : ['Waiting for centre', 'wait'])); ?>
                  <div class="eg-centre <?= $t['revoked_at'] ? 'is-revoked' : '' ?>">
                    <div class="eg-centre-main">
                      <div class="eg-centre-name"><?= htmlspecialchars($t['center_name']) ?> <small><?= htmlspecialchars($t['center_code']) ?></small></div>
                      <div class="eg-centre-sub">
                        <?= $t['location'] ? htmlspecialchars($t['location']) . ' · ' : '' ?><?= $t['manager_name'] ? 'Manager: ' . htmlspecialchars($t['manager_name']) : '' ?><?= $t['manager_contact'] ? ' (' . htmlspecialchars($t['manager_contact']) . ')' : '' ?>
                      </div>
                      <div class="eg-centre-token"><i class="bi bi-key"></i> CBT-<?= htmlspecialchars($t['prefix']) ?>-•••••</div>
                    </div>
                    <div class="eg-centre-facts">
                      <span class="eg-state eg-s-<?= $state[1] ?>"><?= $state[0] ?></span>
                      <span><b><?= (int) $t['n_alloc'] ?: 'All' ?></b> students</span>
                      <span>Activated <b><?= $t['activated_at'] ? date('j M, g:i A', strtotime($t['activated_at'])) : '—' ?></b></span>
                      <span>Last results <b><?= $t['last_sync_at'] ? date('j M, g:i A', strtotime($t['last_sync_at'])) : '—' ?></b></span>
                      <?php if (!$t['revoked_at'] && $package_stale($t)): ?><span class="text-warning fw-semibold"><i class="bi bi-arrow-repeat"></i> Group changed since the centre's download — ask them to refresh</span><?php endif; ?>
                    </div>
                    <?php if (!$t['revoked_at']): ?>
                      <div class="eg-centre-actions">
                        <button class="admin-btn admin-btn-sm admin-btn-outline" data-bs-toggle="modal" data-bs-target="#allocModal" data-token="<?= (int) $t['id'] ?>" data-centre="<?= htmlspecialchars($t['center_name']) ?>"><i class="bi bi-people"></i> Students</button>
                        <a class="admin-btn admin-btn-sm admin-btn-outline" href="<?= $self ?>?package=<?= htmlspecialchars($t['prefix']) ?>" title="For centres without internet: carry this file to the centre"><i class="bi bi-download"></i> Package file</a>
                        <button class="admin-btn admin-btn-sm admin-btn-outline" data-bs-toggle="modal" data-bs-target="#resultsModal" data-token="<?= (int) $t['id'] ?>" data-centre="<?= htmlspecialchars($t['center_name']) ?>"><i class="bi bi-upload"></i> Results file</button>
                        <form method="post" class="d-inline" data-swal="Withdraw this token? The centre will not be able to download again or send results with it.">
                          <?php csrf_field(); ?><input type="hidden" name="act" value="revoke_token"><input type="hidden" name="group_id" value="<?= $group_id ?>"><input type="hidden" name="token_id" value="<?= (int) $t['id'] ?>">
                          <button class="admin-btn admin-btn-sm admin-btn-ghost text-danger">Withdraw</button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <section class="admin-card" id="activity">
          <div class="admin-card-header"><h3 class="admin-card-title"><i class="bi bi-clock-history me-2"></i>Activity</h3></div>
          <div class="admin-card-body-flush">
            <?php if (!$log): ?><p class="text-muted text-center py-4 mb-0">Nothing has happened yet.</p><?php else: ?>
              <ul class="eg-log">
                <?php foreach ($log as $l): ?>
                  <li class="<?= $l['ok'] ? '' : 'is-bad' ?>">
                    <time><?= date('j M, g:i A', strtotime($l['created_at'])) ?></time>
                    <span class="eg-log-act"><?= htmlspecialchars(ucfirst($l['action'])) ?></span>
                    <b><?= htmlspecialchars($l['center_name']) ?></b>
                    <span><?= htmlspecialchars($l['summary']) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </section>

        <!-- issue token -->
        <div class="modal fade" id="tokenModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
            <?php csrf_field(); ?><input type="hidden" name="act" value="issue_token"><input type="hidden" name="group_id" value="<?= $group_id ?>">
            <div class="modal-header"><h5 class="modal-title">Issue a centre token</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <?php $avail = array_filter($centres, fn($c) => !in_array((int) $c['id'], $used, true)); ?>
              <?php if ($avail): ?>
                <label class="admin-form-label">Existing centre</label>
                <select name="center_id" class="admin-input admin-select mb-3" id="centreSelect">
                  <option value="0">— a new centre —</option>
                  <?php foreach ($avail as $c): ?><option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name'] . ($c['location'] ? ' — ' . $c['location'] : '')) ?></option><?php endforeach; ?>
                </select>
              <?php endif; ?>
              <div id="newCentre">
                <div class="row g-2">
                  <div class="col-8"><label class="admin-form-label">Centre name</label><input name="center_name" class="admin-input" placeholder="e.g. Veritas University Abuja CBT Centre"></div>
                  <div class="col-4"><label class="admin-form-label">Short code</label><input name="center_code" class="admin-input" placeholder="VUA" maxlength="20"></div>
                  <div class="col-12"><label class="admin-form-label">Location</label><input name="location" class="admin-input" placeholder="Town / campus"></div>
                  <div class="col-6"><label class="admin-form-label">Centre manager</label><input name="manager_name" class="admin-input"></div>
                  <div class="col-6"><label class="admin-form-label">Phone or e-mail</label><input name="manager_contact" class="admin-input"></div>
                </div>
              </div>
              <p class="small text-muted mt-3 mb-0">The token is shown once. Anyone holding it can download this group's papers inside the activation window, so share it only with the centre manager.</p>
            </div>
            <div class="modal-footer"><button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-primary">Issue token</button></div>
          </form></div>
        </div>

        <!-- allocate students -->
        <div class="modal fade" id="allocModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
            <?php csrf_field(); ?><input type="hidden" name="act" value="allocate"><input type="hidden" name="group_id" value="<?= $group_id ?>"><input type="hidden" name="token_id" value="">
            <div class="modal-header"><h5 class="modal-title">Students for <span data-centre-name></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <p class="small text-muted">A centre with no allocation receives every student of the group. Allocate when several centres share the group, so each centre gets only the students sitting there.</p>
              <label class="admin-form-label">Matric numbers</label>
              <textarea name="matrics" class="admin-input" rows="6" placeholder="Paste one per line, or separated by commas"></textarea>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="admin-btn admin-btn-primary" name="mode" value="list">Add these students</button>
                <button class="admin-btn admin-btn-outline" name="mode" value="remaining" title="Students of the group not yet given to any centre">Add all unallocated (<?= max(0, $n_group_students - $n_allocated) ?>)</button>
                <button class="admin-btn admin-btn-ghost text-danger" name="mode" value="clear">Clear allocation</button>
              </div>
            </div>
          </form></div>
        </div>

        <!-- upload results -->
        <div class="modal fade" id="resultsModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered"><form method="post" enctype="multipart/form-data" class="modal-content">
            <?php csrf_field(); ?><input type="hidden" name="act" value="upload_results"><input type="hidden" name="group_id" value="<?= $group_id ?>"><input type="hidden" name="token_id" value="">
            <div class="modal-header"><h5 class="modal-title">Results file from <span data-centre-name></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <p class="small text-muted">For centres without internet: the centre manager downloads a <code>.cbtres</code> file from the Center Console and brings it here. Uploading the same file again is safe.</p>
              <input type="file" name="results" accept=".cbtres" class="admin-input" required>
            </div>
            <div class="modal-footer"><button class="admin-btn admin-btn-primary">Receive results</button></div>
          </form></div>
        </div>
      <?php endif; ?>

      <!-- create / edit group -->
      <div class="modal fade" id="groupModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
          <?php csrf_field(); ?><input type="hidden" name="act" value="save_group"><input type="hidden" name="group_id" value="<?= $group ? $group_id : 0 ?>">
          <div class="modal-header"><h5 class="modal-title"><?= $group ? 'Edit group' : 'New exam group' ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="row g-2">
              <div class="col-8"><label class="admin-form-label">Name</label><input name="name" class="admin-input" required value="<?= htmlspecialchars($group['name'] ?? '') ?>" placeholder="Nursing Council Mock Exam"></div>
              <div class="col-4"><label class="admin-form-label">Code</label><input name="code" class="admin-input" maxlength="40" value="<?= htmlspecialchars($group['code'] ?? '') ?>" placeholder="NMCN-MOCK-26"></div>
              <div class="col-12"><label class="admin-form-label">Description</label><textarea name="description" class="admin-input" rows="2"><?= htmlspecialchars($group['description'] ?? '') ?></textarea></div>
              <div class="col-6"><label class="admin-form-label">Activation opens</label><input type="datetime-local" name="activation_opens" class="admin-input" value="<?= !empty($group['activation_opens']) ? date('Y-m-d\TH:i', strtotime($group['activation_opens'])) : '' ?>"></div>
              <div class="col-6"><label class="admin-form-label">Activation closes</label><input type="datetime-local" name="activation_closes" class="admin-input" value="<?= !empty($group['activation_closes']) ? date('Y-m-d\TH:i', strtotime($group['activation_closes'])) : '' ?>"></div>
              <div class="col-12">
                <label class="admin-form-label">Exam ends</label>
                <input type="datetime-local" name="exam_ends" class="admin-input" value="<?= !empty($group['exam_ends']) ? date('Y-m-d\TH:i', strtotime($group['exam_ends'])) : '' ?>">
                <div class="small text-muted mt-1">After this time the examiner accounts centres created for this group stop working. Leave blank to let each centre manager end it.</div>
              </div>
              <div class="col-12">
                <label class="admin-form-label">Status</label>
                <div class="eg-status-pick">
                  <?php foreach (['draft' => 'Draft — centres cannot activate', 'published' => 'Published — centres may activate inside the window', 'closed' => 'Closed — no new activations; results still accepted'] as $v => $l): ?>
                    <label><input type="radio" name="status" value="<?= $v ?>" <?= ($group['status'] ?? 'draft') === $v ? 'checked' : '' ?>> <?= $l ?></label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer"><button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-primary">Save</button></div>
        </form></div>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
  <script>
    (function () {
      document.querySelectorAll('[data-copy]').forEach(function (b) {
        b.addEventListener('click', function () {
          var t = document.querySelector(b.getAttribute('data-copy')).textContent.trim();
          var done = function () { b.innerHTML = '<i class="bi bi-check2"></i> Copied'; };
          if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(done); return; }
          var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); done(); } catch (e) {} ta.remove();
        });
      });
      ['allocModal', 'resultsModal'].forEach(function (id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.addEventListener('show.bs.modal', function (ev) {
          var b = ev.relatedTarget;
          m.querySelector('[name=token_id]').value = b.getAttribute('data-token');
          m.querySelector('[data-centre-name]').textContent = b.getAttribute('data-centre');
        });
      });
      var sel = document.getElementById('centreSelect');
      if (sel) {
        var box = document.getElementById('newCentre');
        var sync = function () { box.style.display = sel.value === '0' ? '' : 'none'; };
        sel.addEventListener('change', sync); sync();
      }
      document.querySelectorAll('[data-filter-list]').forEach(function (inp) {
        var list = document.querySelector(inp.getAttribute('data-filter-list'));
        inp.addEventListener('input', function () {
          var q = inp.value.toLowerCase();
          list.querySelectorAll('label').forEach(function (l) { l.style.display = l.getAttribute('data-text').indexOf(q) > -1 ? '' : 'none'; });
        });
      });
    })();
  </script>
</body>

</html>
