<?php
require 'auth.php';
check_auth(['admin', 'examiner']);
require 'db.php';
require_once 'includes/access.php';

$name = $_SESSION['user'] ?? 'Admin';
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if (!$exam_id) {
  die('No exam selected.');
}
acc_require_exam($db, $exam_id);
$exam = $db->prepare("SELECT * FROM exams WHERE id=? AND type='objective' AND deleted_at IS NULL");
$exam->execute([$exam_id]);
$exam = $exam->fetch(PDO::FETCH_ASSOC);
if (!$exam) {
  die('Invalid exam.');
}
qe_notation($exam);
$back_url = acc_is_admin() ? 'admin_objective_exams' : 'exam_overview?exam_id=' . $exam_id;
$self = 'admin_objective_questions?exam_id=' . $exam_id;
$file_slug = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $exam['name']);

function oq_flash(string $type, string $message, array $details = []): void
{
  $_SESSION['oq_flash'] = ['type' => $type, 'message' => $message, 'details' => $details];
}

/* ---------- exports ---------- */

if (isset($_GET['export'])) {
  $bank = qe_load_questions($db, $exam_id);
  if ($_GET['export'] === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="questions_' . $file_slug . '.txt"');
    echo qe_export_txt($bank);
    exit;
  }
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="questions_' . $file_slug . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Question', 'Question_Image', 'Option A', 'Option_A_Image', 'Option B', 'Option_B_Image', 'Option C', 'Option_C_Image', 'Option D', 'Option_D_Image', 'Correct Option', 'Mark', 'Type', 'Statements']);
  foreach ($bank as $q) {
    $statements = '';
    if ($q['question_type'] === 'mtf') {
      $parts = [];
      foreach ($q['statements'] as $i => $s) {
        $parts[] = $s . ' [' . ($q['correct_option'][$i] ?? '?') . ']';
      }
      $statements = implode(' || ', $parts);
    }
    fputcsv($out, [
      $q['question_text'], $q['question_image'],
      $q['option_a'], $q['option_a_image'], $q['option_b'], $q['option_b_image'],
      $q['option_c'], $q['option_c_image'], $q['option_d'], $q['option_d_image'],
      $q['correct_option'], $q['mark'], $q['question_type'], $statements,
    ]);
  }
  fclose($out);
  exit;
}

/* ---------- actions ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  cs_guard_exam($db, $exam_id, $self);
  validate_csrf();

  if (isset($_POST['save_question'])) {
    $id = intval($_POST['id'] ?? 0);
    $type = $_POST['question_type'] ?? 'mcq_single';
    $input = [
      'type' => $type,
      'text' => $_POST['question_text'] ?? '',
      'image' => $_POST['question_image'] ?? '',
      'mark' => $_POST['mark'] ?? '',
      'scoring' => $_POST['scoring'] ?? null,
      'options' => [],
      'statements' => [],
    ];
    foreach (QE_OPTION_KEYS as $k) {
      $lk = strtolower($k);
      $input['options'][$k] = ['text' => $_POST["option_$lk"] ?? '', 'image' => $_POST["option_{$lk}_image"] ?? ''];
    }
    if ($type === 'mcq_multi') {
      $input['correct'] = $_POST['correct_multi'] ?? [];
    } elseif ($type === 'true_false') {
      $input['correct'] = $_POST['correct_tf'] ?? '';
    } else {
      $input['correct'] = $_POST['correct_single'] ?? '';
    }
    $texts = $_POST['statement_text'] ?? [];
    $answers = $_POST['statement_answer'] ?? [];
    foreach ($texts as $i => $t) {
      $input['statements'][] = ['text' => $t, 'answer' => $answers[$i] ?? ''];
    }

    $built = qe_build_question($input);
    if (!$built['ok']) {
      oq_flash('danger', 'Question not saved: ' . $built['error']);
    } elseif ($id > 0) {
      qe_update_question($db, $exam_id, $id, $built['row']);
      oq_flash('success', 'Question updated.');
    } else {
      qe_insert_question($db, $exam_id, $built['row']);
      oq_flash('success', 'Question added.');
    }
    header("Location: $self");
    exit;
  }

  if (isset($_POST['delete']) && isset($_POST['id'])) {
    // Soft delete keeps existing answers intact for audit
    $db->prepare("UPDATE objective_questions SET deleted_at=NOW() WHERE id=? AND exam_id=?")->execute([intval($_POST['id']), $exam_id]);
    oq_flash('success', 'Question removed.');
    header("Location: $self");
    exit;
  }

  if (isset($_POST['clear_all'])) {
    $db->prepare("UPDATE objective_questions SET deleted_at=NOW() WHERE exam_id=? AND deleted_at IS NULL")->execute([$exam_id]);
    oq_flash('success', 'Question bank cleared.');
    header("Location: $self");
    exit;
  }

  if (isset($_POST['upload_file'])) {
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
      oq_flash('danger', 'Upload failed. Please choose a .docx, .txt or .csv file.');
      header("Location: $self");
      exit;
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['txt', 'csv', 'docx'], true)) {
      oq_flash('danger', 'Unsupported file type ".' . htmlspecialchars($ext) . '". Use .docx, .txt or .csv' . ($ext === 'doc' ? ' (save the .doc again as .docx in Word).' : '.'));
      header("Location: $self");
      exit;
    }
    $result = qe_import_file($db, $exam_id, $f['tmp_name'], $ext);
    if ($result === null) {
      oq_flash('danger', 'Import failed and nothing was saved. Check the file and try again.');
      header("Location: $self");
      exit;
    }
    [$added, $errors] = $result;
    usort($errors, fn($a, $b) => $a['line'] <=> $b['line']);
    $skipped = count(array_filter($errors, fn($e) => $e['line'] > 0));
    $msg = "$added question" . ($added === 1 ? '' : 's') . ' imported';
    $msg .= $skipped ? ", $skipped skipped." : '.';
    if ($ext === 'docx' && $skipped) {
      $msg .= ' Line numbers count paragraphs in the Word file.';
    }
    oq_flash($errors ? ($added ? 'warning' : 'danger') : 'success', $msg, array_slice($errors, 0, 50));
    header("Location: $self");
    exit;
  }

  if (isset($_POST['upload_images'])) {
    $f = $_FILES['zip'] ?? null;
    $saved = 0;
    $skipped = 0;
    if ($f && $f['error'] === UPLOAD_ERR_OK && class_exists('ZipArchive')) {
      $zip = new ZipArchive();
      if ($zip->open($f['tmp_name']) === true) {
        $dir = __DIR__ . '/uploads';
        if (!is_dir($dir)) {
          mkdir($dir, 0755, true);
        }
        // Only flat image files are taken out of the archive; anything else
        // (scripts, nested paths, ../ tricks) is ignored.
        for ($i = 0; $i < $zip->numFiles; $i++) {
          $entry = $zip->getNameIndex($i);
          $base = basename(str_replace('\\', '/', $entry));
          if ($base === '' || substr($entry, -1) === '/') {
            continue;
          }
          if (!preg_match('/^[A-Za-z0-9 _\-\.\(\)]+\.(png|jpe?g|gif|webp|bmp)$/i', $base)) {
            $skipped++;
            continue;
          }
          $data = $zip->getFromIndex($i);
          if ($data !== false && @getimagesizefromstring($data)) {
            file_put_contents("$dir/$base", $data);
            $saved++;
          } else {
            $skipped++;
          }
        }
        $zip->close();
      }
    }
    oq_flash($saved ? 'success' : 'warning', "$saved image" . ($saved === 1 ? '' : 's') . ' added to the library' . ($skipped ? ", $skipped file(s) ignored." : '.'));
    header("Location: $self");
    exit;
  }
}

/* ---------- page data ---------- */

$questions = qe_load_questions($db, $exam_id);
$type_counts = array_fill_keys(array_keys(qe_types()), 0);
$total_marks = 0;
foreach ($questions as $q) {
  $type_counts[$q['question_type']]++;
  $total_marks += $q['mark'];
}
$flash = $_SESSION['oq_flash'] ?? null;
unset($_SESSION['oq_flash']);

$edit_data = [];
foreach ($questions as $q) {
  $edit_data[$q['id']] = [
    'id' => (int) $q['id'],
    'type' => $q['question_type'],
    'text' => $q['question_text'],
    'image' => (string) $q['question_image'],
    'mark' => $q['mark'],
    'scoring' => $q['scoring'],
    'options' => [
      'A' => [$q['option_a'], (string) $q['option_a_image']], 'B' => [$q['option_b'], (string) $q['option_b_image']],
      'C' => [$q['option_c'], (string) $q['option_c_image']], 'D' => [$q['option_d'], (string) $q['option_d_image']],
    ],
    'correct' => $q['correct_option'],
    'statements' => $q['statements'],
  ];
}
$type_badge = ['mcq_single' => 'admin-badge-neutral', 'mcq_multi' => 'admin-badge-info', 'true_false' => 'admin-badge-warning', 'mtf' => 'admin-badge-primary'];
$fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
$page_actions = '<a href="' . $back_url . '" class="admin-btn admin-btn-outline"><i class="bi bi-arrow-left"></i> Back</a> <button type="button" class="admin-btn admin-btn-primary" data-new-question><i class="bi bi-plus-lg"></i> Add Question</button>';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Manage Questions - <?= htmlspecialchars($exam['name']) ?> - Assessment Portal</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <style>
    .oq-summary { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
    .oq-chip { border: 1px solid #dfe5e2; background: #fff; border-radius: 999px; padding: .3rem .8rem; font-size: .8rem; cursor: pointer; color: #334155; }
    .oq-chip b { margin-left: .25rem; }
    .oq-chip.is-active { background: #198754; border-color: #198754; color: #fff; }
    .oq-toolbar { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
    .oq-toolbar .admin-input { max-width: 260px; }
    .oq-stem { font-weight: 600; white-space: pre-line; }
    .oq-opts { font-size: .82rem; color: #475569; margin-top: .35rem; }
    .oq-opts div { padding: 1px 0; }
    .oq-opts .is-key { color: #0f5132; font-weight: 600; }
    .oq-stmt { display: flex; gap: .5rem; align-items: baseline; font-size: .82rem; padding: 1px 0; }
    .oq-tf { display: inline-block; min-width: 1.4rem; text-align: center; border-radius: 4px; font-size: .7rem; font-weight: 700; padding: 0 .25rem; }
    .oq-tf.t { background: #d1e7dd; color: #0f5132; }
    .oq-tf.f { background: #f8d7da; color: #842029; }
    .oq-type-switch { display: flex; flex-wrap: wrap; gap: .4rem; }
    .oq-type-switch label { border: 1px solid #d7dfdb; border-radius: 8px; padding: .45rem .8rem; cursor: pointer; font-size: .85rem; background: #fff; }
    .oq-type-switch input { display: none; }
    .oq-type-switch input:checked + span { color: #198754; font-weight: 700; }
    .oq-type-switch label:has(input:checked) { border-color: #198754; background: #f0f8f4; }
    .oq-opt-row { display: grid; grid-template-columns: 34px 1fr 180px 44px; gap: .5rem; align-items: center; margin-bottom: .5rem; }
    .oq-opt-row .oq-letter { font-weight: 700; text-align: center; color: #64748b; }
    .oq-stmt-row { display: grid; grid-template-columns: 28px 1fr auto 34px; gap: .5rem; align-items: center; margin-bottom: .45rem; }
    .oq-tf-pick { display: inline-flex; border: 1px solid #d7dfdb; border-radius: 8px; overflow: hidden; }
    .oq-tf-pick label { padding: .35rem .7rem; cursor: pointer; font-size: .8rem; font-weight: 600; margin: 0; }
    .oq-tf-pick input { display: none; }
    .oq-tf-pick label:has(input[value="T"]:checked) { background: #198754; color: #fff; }
    .oq-tf-pick label:has(input[value="F"]:checked) { background: #dc3545; color: #fff; }
    .oq-help code { background: #f1f5f3; padding: 0 .25rem; border-radius: 3px; }
    .oq-thumb { max-height: 70px; max-width: 160px; border: 1px solid #e3e9e6; border-radius: 6px; background: #fff; }
    .oq-errors { max-height: 180px; overflow: auto; margin: .5rem 0 0; padding-left: 1.1rem; font-size: .82rem; }
    @media (max-width: 640px) { .oq-opt-row { grid-template-columns: 28px 1fr 40px; } .oq-opt-row .oq-img { display: none; } }
  </style>
  <?= qe_mathjax_head($exam) ?>
</head>

<body>
  <?php include acc_sidebar(); ?>
  <div class="main-content">

    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <a href="<?= $back_url ?>" class="admin-back-btn" aria-label="Go back"><i class="bi bi-arrow-left"></i></a>
          <h1 class="admin-navbar-title">Objective Questions</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():&quot;?&quot;}}"></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="<?= acc_home() ?>"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li><a href="<?= htmlspecialchars($back_url) ?>"><?= acc_is_admin() ? 'Objective Exams' : 'Exam overview' ?></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page"><?= htmlspecialchars($exam['name']) ?></li>
        </ol>
      </nav>

      <?php if ($flash): ?>
        <div class="admin-alert admin-alert-<?= htmlspecialchars($flash['type']) ?>">
          <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
          <div>
            <span><?= htmlspecialchars($flash['message']) ?></span>
            <?php if (!empty($flash['details'])): ?>
              <ul class="oq-errors">
                <?php foreach ($flash['details'] as $e): ?>
                  <li><?= $e['line'] ? 'Line ' . (int) $e['line'] . ': ' : '' ?><?= htmlspecialchars($e['message']) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <div class="admin-page-header">
        <h2 class="admin-page-title"><?= htmlspecialchars($exam['name']) ?> — Questions</h2>
        <div class="admin-page-actions"><?= $page_actions ?></div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-lg-7">
          <div class="admin-card h-100">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-cloud-arrow-up me-2"></i>Import Questions</h3>
            </div>
            <div class="admin-card-body">
              <p class="text-muted small mb-2">
                <strong>.docx</strong> (Word) keeps equations, subscripts, superscripts, Greek letters and pictures, so use it for maths, physics and chemistry.
                <strong>.txt</strong> handles every question type and accepts plain Aiken files.
                <strong>.csv</strong> handles single and multiple-answer questions (use <code>A,C</code> as the answer for multiple).
                <a href="#" data-bs-toggle="collapse" data-bs-target="#txtHelp">Format guide</a>
              </p>
              <div class="collapse oq-help small mb-3" id="txtHelp">
                <div class="p-3 rounded-3" style="background:#f8faf9;border:1px solid #e5ece8;">
                  <div class="mb-2"><strong>Word (.docx)</strong> — type the paper exactly as below, one item per paragraph. Write formulas with
                    <em>Insert &gt; Equation</em>; use Word's own subscript/superscript buttons (H₂O, cm³) and Insert &gt; Symbol for Greek.
                    Pictures placed under a question are attached to it. Numbered lists (1. / A.) work too.
                    Equations made with the old Equation Editor 3.0 or MathType cannot be read — retype them with Insert &gt; Equation.</div>
                  Separate questions with a blank line. Put optional directives on the lines above a question:
                  <code>TYPE: MCQ | MULTI | TF | MTF</code>, <code>MARK: 2</code>, <code>IMAGE: file.png</code>, <code>SCORING: PARTIAL | ALL</code>.
                  <div class="mt-2"><strong>Multiple True/False</strong> — one statement per line, each ending with <code>[T]</code> or <code>[F]</code>
                    (or list them all on an <code>ANSWER: TFTF</code> line):</div>
<pre class="mb-0 mt-1" style="font-size:.78rem;">TYPE: MTF
MARK: 4
Concerning the human heart:
a) It has four chambers. [T]
b) The right ventricle pumps blood into the aorta. [F]
c) The mitral valve has two cusps. [T]
d) The SA node lies in the left atrium. [F]</pre>
                </div>
              </div>
              <div class="mb-3 small">
                <span class="text-muted fw-bold me-2">Templates:</span>
                <a href="download_template.php?type=objective_docx" class="fw-bold text-success me-3"><i class="bi bi-file-earmark-word me-1"></i>Word (science)</a>
                <a href="download_template.php?type=objective_mixed_txt" class="fw-bold text-success me-3"><i class="bi bi-download me-1"></i>All types TXT</a>
                <a href="download_template.php?type=objective_mtf_txt" class="fw-bold text-success me-3"><i class="bi bi-download me-1"></i>MTF TXT</a>
                <a href="download_template.php?type=objective_aiken" class="fw-bold text-success me-3"><i class="bi bi-download me-1"></i>Aiken TXT</a>
                <a href="download_template.php?type=objective_csv_simple" class="fw-bold text-success me-3"><i class="bi bi-download me-1"></i>Simple CSV</a>
                <a href="download_template.php?type=objective_csv_extended" class="fw-bold text-success"><i class="bi bi-download me-1"></i>Extended CSV</a>
              </div>
              <form method="post" enctype="multipart/form-data" class="row g-2">
                <?php csrf_field(); ?>
                <div class="col"><input type="file" name="file" accept=".docx,.csv,.txt" class="admin-input" required></div>
                <div class="col-auto"><button type="submit" name="upload_file" value="1" class="admin-btn admin-btn-primary">Import</button></div>
              </form>
              <div class="mt-3 d-flex gap-2 flex-wrap">
                <a href="?exam_id=<?= $exam_id ?>&export=txt" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-filetype-txt"></i> Export TXT</a>
                <a href="?exam_id=<?= $exam_id ?>&export=csv" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-filetype-csv"></i> Export CSV</a>
                <form method="post" class="d-inline" data-swal="Remove ALL questions from this bank? Students' past answers are kept.">
                  <?php csrf_field(); ?>
                  <button type="submit" name="clear_all" value="1" class="admin-btn admin-btn-sm admin-btn-danger"><i class="bi bi-trash"></i> Clear Bank</button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="admin-card admin-card-primary h-100">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-images me-2"></i>Image Library (.zip)</h3>
            </div>
            <div class="admin-card-body">
              <p class="small opacity-75">Upload a ZIP of diagrams (PNG, JPG, GIF, WEBP) and reference the file names in your questions.</p>
              <div class="mb-3 small">
                <a href="download_template.php?type=objective_images_zip" class="fw-bold opacity-90"><i class="bi bi-download me-1"></i>Sample image ZIP</a>
              </div>
              <form method="post" enctype="multipart/form-data" class="row g-2">
                <?php csrf_field(); ?>
                <div class="col"><input type="file" name="zip" accept=".zip" class="admin-input" required></div>
                <div class="col-auto"><button type="submit" name="upload_images" value="1" class="admin-btn admin-btn-light">Upload ZIP</button></div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card">
        <div class="admin-card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
          <h3 class="admin-card-title mb-0">Questions (<?= count($questions) ?>) · <?= $fmt($total_marks) ?> marks</h3>
          <div class="oq-toolbar">
            <input type="search" class="admin-input" id="qSearch" placeholder="Search questions…">
          </div>
        </div>
        <?php if (empty($questions)): ?>
          <div class="admin-card-body">
            <div class="admin-empty">
              <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
              <h4 class="admin-empty-title">No questions yet</h4>
              <p class="admin-empty-desc">Add a question or import a TXT/CSV file. Different question types can be mixed in one exam.</p>
            </div>
          </div>
        <?php else: ?>
          <div class="admin-card-body pb-0">
            <div class="oq-summary" id="typeFilter">
              <button type="button" class="oq-chip is-active" data-type="">All<b><?= count($questions) ?></b></button>
              <?php foreach (qe_types() as $t => $label): if (!$type_counts[$t]) continue; ?>
                <button type="button" class="oq-chip" data-type="<?= $t ?>"><?= $label ?><b><?= $type_counts[$t] ?></b></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="admin-card-body-flush">
            <div class="table-responsive">
              <table class="admin-table" id="qTable">
                <thead>
                  <tr>
                    <th width="44">#</th>
                    <th>Question</th>
                    <th width="150">Type</th>
                    <th width="70">Mark</th>
                    <th width="170" class="actions-cell">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $n = 0; foreach ($questions as $q): $n++; $t = $q['question_type']; ?>
                    <tr data-type="<?= $t ?>" data-search="<?= htmlspecialchars(mb_strtolower($q['question_text'] . ' ' . implode(' ', $q['statements']) . ' ' . $q['option_a'] . ' ' . $q['option_b'] . ' ' . $q['option_c'] . ' ' . $q['option_d'])) ?>">
                      <td><?= $n ?></td>
                      <td>
                        <div class="oq-stem"><?= qe_safe_html($q['question_text']) ?></div>
                        <?php if ($q['question_image']): $src = qe_safe_image($q['question_image']); ?>
                          <div class="mt-1 d-flex align-items-center gap-2">
                            <?php if ($src && is_file(__DIR__ . '/' . rawurldecode($src))): ?><img src="<?= htmlspecialchars($src) ?>" alt="" class="oq-thumb" loading="lazy"><?php endif; ?>
                            <span class="admin-badge admin-badge-info"><i class="bi bi-image me-1"></i><?= htmlspecialchars($q['question_image']) ?></span>
                          </div>
                        <?php endif; ?>
                        <div class="oq-opts">
                          <?php if ($t === 'mtf'): ?>
                            <?php foreach ($q['statements'] as $i => $s): $k = $q['correct_option'][$i] ?? '?'; ?>
                              <div class="oq-stmt"><span class="oq-tf <?= strtolower($k) ?>"><?= $k ?></span><span><?= qe_safe_html($s) ?></span></div>
                            <?php endforeach; ?>
                          <?php elseif ($t === 'true_false'): ?>
                            <div>Answer: <span class="oq-tf <?= $q['correct_option'] === 'A' ? 't' : 'f' ?>"><?= $q['correct_option'] === 'A' ? 'TRUE' : 'FALSE' ?></span></div>
                          <?php else: $keys = explode(',', $q['correct_option']); ?>
                            <?php foreach (QE_OPTION_KEYS as $k): $text = $q['option_' . strtolower($k)]; $img = $q['option_' . strtolower($k) . '_image'];
                              if (trim((string) $text) === '' && !$img) continue; ?>
                              <div class="<?= in_array($k, $keys, true) ? 'is-key' : '' ?>">
                                <?= $k ?>. <?= qe_safe_html((string) $text) ?>
                                <?php if ($img): ?><i class="bi bi-image text-muted ms-1" title="<?= htmlspecialchars($img) ?>"></i><?php endif; ?>
                                <?php if (in_array($k, $keys, true)): ?><i class="bi bi-check2"></i><?php endif; ?>
                              </div>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <span class="admin-badge <?= $type_badge[$t] ?>"><?= qe_type_label($t) ?></span>
                        <?php if ($q['scoring']): ?><div class="small text-muted mt-1"><?= $q['scoring'] === 'all' ? 'All-or-nothing' : 'Partial credit' ?></div><?php endif; ?>
                      </td>
                      <td><?= $fmt($q['mark']) ?></td>
                      <td class="actions-cell">
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-outline" data-edit="<?= (int) $q['id'] ?>" title="Edit"><i class="bi bi-pencil"></i> Edit</button>
                        <form method="post" class="d-inline" data-swal="Delete this question?">
                          <?php csrf_field(); ?>
                          <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                          <button type="submit" name="delete" value="1" class="admin-btn admin-btn-sm admin-btn-danger" title="Delete"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Add / edit question -->
  <div class="modal fade" id="questionModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <form method="post" id="questionForm">
          <?php csrf_field(); ?>
          <input type="hidden" name="id" value="0">
          <div class="modal-header">
            <h5 class="modal-title" id="questionModalTitle">New Question</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="admin-form-group">
              <label class="admin-form-label">Question type</label>
              <div class="oq-type-switch">
                <?php foreach (qe_types() as $t => $label): ?>
                  <label><input type="radio" name="question_type" value="<?= $t ?>"><span><?= $label ?></span></label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="admin-form-group">
              <label class="admin-form-label" id="stemLabel">Question text</label>
              <textarea name="question_text" class="admin-input" rows="3" required placeholder="Formulas: $\sqrt{16}$ · subscripts: H&lt;sub&gt;2&lt;/sub&gt;O"></textarea>
            </div>
            <div class="row g-3">
              <div class="col-md-8">
                <div class="admin-form-group">
                  <label class="admin-form-label">Question image (optional)</label>
                  <input type="text" name="question_image" class="admin-input" placeholder="File name from the image library, e.g. diagram1.png">
                </div>
              </div>
              <div class="col-md-4">
                <div class="admin-form-group">
                  <label class="admin-form-label">Mark</label>
                  <input type="number" name="mark" class="admin-input" step="0.01" min="0" value="1" required>
                </div>
              </div>
            </div>

            <div data-section="mcq">
              <label class="admin-form-label d-block">Options <span class="text-muted fw-normal" id="mcqHint">— tick the correct option</span></label>
              <?php foreach (QE_OPTION_KEYS as $k): $lk = strtolower($k); ?>
                <div class="oq-opt-row">
                  <span class="oq-letter"><?= $k ?></span>
                  <input type="text" name="option_<?= $lk ?>" class="admin-input" placeholder="Option <?= $k ?>">
                  <input type="text" name="option_<?= $lk ?>_image" class="admin-input oq-img" placeholder="Image (optional)">
                  <span class="text-center">
                    <input class="form-check-input" type="radio" name="correct_single" value="<?= $k ?>" title="Correct answer">
                    <input class="form-check-input d-none" type="checkbox" name="correct_multi[]" value="<?= $k ?>" title="Correct answer">
                  </span>
                </div>
              <?php endforeach; ?>
            </div>

            <div data-section="true_false">
              <label class="admin-form-label d-block">Correct answer</label>
              <div class="oq-tf-pick">
                <label><input type="radio" name="correct_tf" value="T"> TRUE</label>
                <label><input type="radio" name="correct_tf" value="F"> FALSE</label>
              </div>
            </div>

            <div data-section="mtf">
              <label class="admin-form-label d-block">Statements <span class="text-muted fw-normal">— each one is marked on its own</span></label>
              <div id="stmtList"></div>
              <button type="button" class="admin-btn admin-btn-sm admin-btn-outline" id="addStmt"><i class="bi bi-plus"></i> Add statement</button>
            </div>

            <div data-section="scoring" class="admin-form-group mt-3">
              <label class="admin-form-label">Marking for this question</label>
              <select name="scoring" class="admin-input admin-select" style="max-width:340px;">
                <option value="">Use exam setting (<?= !isset($exam['partial_scoring']) || $exam['partial_scoring'] ? 'partial credit' : 'all-or-nothing' ?>)</option>
                <option value="partial">Partial credit</option>
                <option value="all">All-or-nothing</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="admin-btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="save_question" value="1" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i> Save Question</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <template id="stmtTpl">
    <div class="oq-stmt-row">
      <span class="oq-letter fw-bold text-muted text-center"></span>
      <input type="text" name="statement_text[]" class="admin-input" placeholder="Statement">
      <span class="oq-tf-pick">
        <label><input type="radio" value="T"> T</label>
        <label><input type="radio" value="F"> F</label>
      </span>
      <button type="button" class="admin-btn admin-btn-sm admin-btn-outline" data-remove title="Remove statement" aria-label="Remove statement">&times;</button>
      <input type="hidden" name="statement_answer[]" value="">
    </div>
  </template>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
  <script>
    (function () {
      const bank = <?= json_encode($edit_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
      const form = document.getElementById('questionForm');
      const modalEl = document.getElementById('questionModal');
      const modal = new bootstrap.Modal(modalEl);
      const list = document.getElementById('stmtList');
      const tpl = document.getElementById('stmtTpl');
      const MAX = <?= QE_MTF_MAX ?>;

      function renumber() {
        [...list.children].forEach((row, i) => {
          row.querySelector('.oq-letter').textContent = String.fromCharCode(97 + i);
          row.querySelectorAll('input[type=radio]').forEach(r => r.name = 'stmt_pick_' + i);
        });
        document.getElementById('addStmt').disabled = list.children.length >= MAX;
      }

      function addStatement(text = '', answer = '') {
        if (list.children.length >= MAX) return;
        const row = tpl.content.firstElementChild.cloneNode(true);
        row.querySelector('input[type=text]').value = text;
        row.querySelector('input[type=hidden]').value = answer;
        list.appendChild(row);
        renumber();
        row.querySelectorAll('input[type=radio]').forEach(r => {
          r.checked = r.value === answer;
          r.addEventListener('change', () => row.querySelector('input[type=hidden]').value = r.value);
        });
        row.querySelector('[data-remove]').onclick = () => { row.remove(); renumber(); };
        return row;
      }

      function setType(type) {
        form.querySelectorAll('input[name=question_type]').forEach(r => r.checked = r.value === type);
        const isMcq = type === 'mcq_single' || type === 'mcq_multi';
        form.querySelector('[data-section=mcq]').hidden = !isMcq;
        form.querySelector('[data-section=true_false]').hidden = type !== 'true_false';
        form.querySelector('[data-section=mtf]').hidden = type !== 'mtf';
        form.querySelector('[data-section=scoring]').hidden = !(type === 'mtf' || type === 'mcq_multi');
        form.querySelectorAll('input[name=correct_single]').forEach(i => i.classList.toggle('d-none', type !== 'mcq_single'));
        form.querySelectorAll('input[name="correct_multi[]"]').forEach(i => i.classList.toggle('d-none', type !== 'mcq_multi'));
        document.getElementById('mcqHint').textContent = type === 'mcq_multi' ? '— tick every correct option' : '— tick the correct option';
        document.getElementById('stemLabel').textContent = type === 'mtf' ? 'Question stem (e.g. "Concerning the liver:")' : 'Question text';
        form.querySelectorAll('[data-section=mcq] input[name^=option_]:not(.oq-img)').forEach((i, n) => i.required = isMcq && n < 2);
        if (type === 'mtf' && list.children.length === 0) { for (let i = 0; i < 4; i++) addStatement(); }
      }

      function open(q) {
        form.reset();
        list.innerHTML = '';
        form.elements['id'].value = q ? q.id : 0;
        document.getElementById('questionModalTitle').textContent = q ? 'Edit Question' : 'New Question';
        if (q) {
          form.question_text.value = q.text;
          form.question_image.value = q.image || '';
          form.mark.value = q.mark;
          form.scoring.value = q.scoring || '';
          ['A', 'B', 'C', 'D'].forEach(k => {
            form['option_' + k.toLowerCase()].value = q.options[k][0] || '';
            form['option_' + k.toLowerCase() + '_image'].value = q.options[k][1] || '';
          });
          const keys = (q.correct || '').split(',');
          form.querySelectorAll('input[name=correct_single]').forEach(i => i.checked = keys[0] === i.value);
          form.querySelectorAll('input[name="correct_multi[]"]').forEach(i => i.checked = keys.includes(i.value));
          form.querySelectorAll('input[name=correct_tf]').forEach(i => i.checked = (q.correct === 'A' ? 'T' : 'F') === i.value);
          if (q.type === 'mtf') q.statements.forEach((s, i) => addStatement(s, q.correct[i] || ''));
        }
        setType(q ? q.type : 'mcq_single');
        modal.show();
      }

      form.querySelectorAll('input[name=question_type]').forEach(r => r.addEventListener('change', () => setType(r.value)));
      document.getElementById('addStmt').onclick = () => addStatement().querySelector('input[type=text]').focus();
      document.querySelectorAll('[data-new-question]').forEach(b => b.onclick = () => open(null));
      document.querySelectorAll('[data-edit]').forEach(b => b.onclick = () => open(bank[b.dataset.edit]));

      form.addEventListener('submit', e => {
        const type = form.querySelector('input[name=question_type]:checked').value;
        let problem = '';
        if (type === 'mcq_single' && !form.querySelector('input[name=correct_single]:checked')) problem = 'Select the correct option.';
        if (type === 'mcq_multi' && !form.querySelector('input[name="correct_multi[]"]:checked')) problem = 'Tick at least one correct option.';
        if (type === 'true_false' && !form.querySelector('input[name=correct_tf]:checked')) problem = 'Choose TRUE or FALSE.';
        if (type === 'mtf') {
          const rows = [...list.children].filter(r => r.querySelector('input[type=text]').value.trim() !== '');
          if (rows.length < 2) problem = 'Add at least two statements.';
          else if (rows.some(r => !r.querySelector('input[type=hidden]').value)) problem = 'Set TRUE or FALSE for every statement.';
        }
        if (problem) { e.preventDefault(); alert(problem); }
      });

      // filters
      const rows = [...document.querySelectorAll('#qTable tbody tr')];
      let typeFilter = '';
      function applyFilter() {
        const term = (document.getElementById('qSearch')?.value || '').trim().toLowerCase();
        rows.forEach(r => r.hidden = (typeFilter && r.dataset.type !== typeFilter) || (term && !r.dataset.search.includes(term)));
      }
      document.querySelectorAll('#typeFilter .oq-chip').forEach(c => c.onclick = () => {
        document.querySelectorAll('#typeFilter .oq-chip').forEach(x => x.classList.remove('is-active'));
        c.classList.add('is-active');
        typeFilter = c.dataset.type;
        applyFilter();
      });
      document.getElementById('qSearch')?.addEventListener('input', applyFilter);
    })();
  </script>
</body>

</html>
