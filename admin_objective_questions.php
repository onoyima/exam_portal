<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if (!$exam_id)
  die('No exam selected.');
$exam = $db->prepare("SELECT * FROM exams WHERE id=? AND type='objective'");
$exam->execute([$exam_id]);
$exam = $exam->fetch(PDO::FETCH_ASSOC);
if (!$exam)
  die('Invalid exam.');

// Handle Export CSV
if (isset($_GET['export'])) {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="questions_' . str_replace(' ', '_', $exam['name']) . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Question', 'Question_Image', 'Option A', 'Option_A_Image', 'Option B', 'Option_B_Image', 'Option C', 'Option_C_Image', 'Option D', 'Option_D_Image', 'Correct Option (A-D)', 'Mark']);
  $q_stmt = $db->prepare("SELECT question_text, question_image, option_a, option_a_image, option_b, option_b_image, option_c, option_c_image, option_d, option_d_image, correct_option, mark FROM objective_questions WHERE exam_id=? ORDER BY id");
  $q_stmt->execute([$exam_id]);
  while ($row = $q_stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row);
  }
  fclose($out);
  exit;
}

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['add'])) {
    $stmt = $db->prepare("INSERT INTO objective_questions (exam_id, question_text, question_image, option_a, option_a_image, option_b, option_b_image, option_c, option_c_image, option_d, option_d_image, correct_option, mark) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
      $exam_id,
      $_POST['question_text'],
      $_POST['question_image'],
      $_POST['option_a'],
      $_POST['option_a_image'],
      $_POST['option_b'],
      $_POST['option_b_image'],
      $_POST['option_c'],
      $_POST['option_c_image'],
      $_POST['option_d'],
      $_POST['option_d_image'],
      $_POST['correct_option'],
      floatval($_POST['mark'])
    ]);
    header("Location: admin_objective_questions?exam_id=$exam_id&success=added");
    exit;
  }
  if (isset($_POST['edit']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $db->prepare("UPDATE objective_questions SET question_text=?, question_image=?, option_a=?, option_a_image=?, option_b=?, option_b_image=?, option_c=?, option_c_image=?, option_d=?, option_d_image=?, correct_option=?, mark=? WHERE id=? AND exam_id=?");
    $stmt->execute([
      $_POST['question_text'],
      $_POST['question_image'],
      $_POST['option_a'],
      $_POST['option_a_image'],
      $_POST['option_b'],
      $_POST['option_b_image'],
      $_POST['option_c'],
      $_POST['option_c_image'],
      $_POST['option_d'],
      $_POST['option_d_image'],
      $_POST['correct_option'],
      floatval($_POST['mark']),
      $id,
      $exam_id
    ]);
    header("Location: admin_objective_questions?exam_id=$exam_id&success=edited");
    exit;
  }
  if (isset($_POST['delete']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $db->prepare("DELETE FROM objective_answers WHERE question_id=? AND exam_id=?")->execute([$id, $exam_id]);
    $db->prepare("DELETE FROM objective_questions WHERE id=? AND exam_id=?")->execute([$id, $exam_id]);
    header("Location: admin_objective_questions?exam_id=$exam_id&success=deleted");
    exit;
  }
  if (isset($_POST['clear_all'])) {
    $db->prepare("DELETE FROM objective_answers WHERE exam_id=?")->execute([$exam_id]);
    $db->prepare("DELETE FROM objective_attempts WHERE exam_id=?")->execute([$exam_id]);
    $db->prepare("DELETE FROM objective_questions WHERE exam_id=?")->execute([$exam_id]);
    header("Location: admin_objective_questions?exam_id=$exam_id&success=cleared");
    exit;
  }

  // Multi-Format Upload Handle (.csv, .txt/aiken)
  if (isset($_POST['upload_file']) && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $filename = $_FILES['file']['name'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);

    if ($ext === 'csv') {
      if ($file = fopen($_FILES['file']['tmp_name'], 'r')) {
        fgetcsv($file); // Skip header
        while ($row = fgetcsv($file)) {
          if (count($row) < 6)
            continue;
          if (count($row) >= 11) { // Extended Format
            $data = [
              $exam_id,
              trim($row[0]),
              trim($row[1]),
              trim($row[2]),
              trim($row[3]),
              trim($row[4]),
              trim($row[5]),
              trim($row[6]),
              trim($row[7]),
              trim($row[8]),
              trim($row[9]),
              strtoupper(trim($row[10])),
              floatval($row[11] ?? 1)
            ];
          } else { // Simple Format
            $data = [
              $exam_id,
              trim($row[0]),
              '',
              trim($row[1]),
              '',
              trim($row[2]),
              '',
              trim($row[3]),
              '',
              trim($row[4]),
              '',
              strtoupper(trim($row[5])),
              floatval($row[6] ?? 1)
            ];
          }
          $stmt = $db->prepare("INSERT INTO objective_questions (exam_id, question_text, question_image, option_a, option_a_image, option_b, option_b_image, option_c, option_c_image, option_d, option_d_image, correct_option, mark) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
          $stmt->execute($data);
        }
        fclose($file);
      }
    } elseif ($ext === 'txt') {
      $lines = file($_FILES['file']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $q = null;
      $options = [];
      $correct = null;
      $mark = 1.0;

      foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^[A-D]\. /i', $line)) {
          $options[strtoupper($line[0])] = trim(substr($line, 2));
        } elseif (preg_match('/^ANSWER: /i', $line)) {
          $correct = strtoupper(trim(substr($line, 7)));
          if ($q && !empty($options) && $correct) {
            $stmt = $db->prepare("INSERT INTO objective_questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option, mark) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([
              $exam_id,
              $q,
              $options['A'] ?? '',
              $options['B'] ?? '',
              $options['C'] ?? '',
              $options['D'] ?? '',
              $correct,
              $mark
            ]);
          }
          $q = null;
          $options = [];
          $correct = null;
        } else {
          if ($q === null)
            $q = $line;
          else
            $q .= "\n" . $line;
        }
      }
    }
    header("Location: admin_objective_questions?exam_id=$exam_id&success=uploaded");
    exit;
  }

  // ZIP Image Upload
  if (isset($_POST['upload_images']) && isset($_FILES['zip']) && $_FILES['zip']['error'] === UPLOAD_ERR_OK) {
    if (!is_dir('uploads'))
      mkdir('uploads', 0777, true);
    $zip = new ZipArchive;
    if ($zip->open($_FILES['zip']['tmp_name']) === TRUE) {
      $zip->extractTo('uploads/');
      $zip->close();
      header("Location: admin_objective_questions?exam_id=$exam_id&success=images");
      exit;
    }
  }
}

// Fetch all questions
$questions = $db->prepare("SELECT * FROM objective_questions WHERE exam_id=? ORDER BY id");
$questions->execute([$exam_id]);
$questions = $questions->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Manage Questions - <?= htmlspecialchars($exam['name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="style_enhanced.css" rel="stylesheet">
  <script>
    window.MathJax = { tex: { inlineMath: [['$', '$'], ['\\(', '\\)']] }, svg: { fontCache: 'global' } };
  </script>
  <script type="text/javascript" id="MathJax-script" async
    src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <div class="container-xl py-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Questions: <span class="text-primary"><?= htmlspecialchars($exam['name']) ?></span></h2>
        <div>
          <a href="admin_objective_exams" class="btn btn-outline-secondary rounded-pill px-4 me-2">Back</a>
          <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">+ Add
            Question</button>
        </div>
      </div>

      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> Success! Action completed.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="row g-4 mb-4">
        <div class="col-md-7">
          <div class="card p-4 shadow-sm border-0 rounded-4 h-100">
            <h5 class="fw-bold mb-3"><i class="bi bi-cloud-arrow-up me-2"></i>Bulk Question Upload</h5>
            <p class="text-muted small">Upload <strong>.csv</strong> or <strong>.txt (Aiken)</strong> files. Formulas ($...$) and chemical symbols are supported.</p>
            <div class="mb-3 small">
              <span class="text-muted fw-bold me-2">Templates:</span>
              <a href="download_template.php?type=objective_csv_simple" class="btn btn-sm btn-link p-0 text-success fw-bold me-3"><i class="bi bi-download me-1"></i>Simple CSV</a>
              <a href="download_template.php?type=objective_csv_extended" class="btn btn-sm btn-link p-0 text-success fw-bold me-3"><i class="bi bi-download me-1"></i>Extended CSV</a>
              <a href="download_template.php?type=objective_aiken" class="btn btn-sm btn-link p-0 text-success fw-bold"><i class="bi bi-download me-1"></i>Aiken TXT</a>
            </div>
            <form method="post" enctype="multipart/form-data" class="row g-2">
              <div class="col">
                <input type="file" name="file" accept=".csv,.txt" class="form-control rounded-pill" required>
              </div>
              <div class="col-auto">
                <button type="submit" name="upload_file" class="btn btn-success rounded-pill px-4">Upload</button>
              </div>
            </form>
            <div class="mt-3">
              <a href="?exam_id=<?= $exam_id ?>&export=1"
                class="btn btn-sm btn-outline-success rounded-pill me-2">Export CSV</a>
              <form method="post" class="d-inline" onsubmit="return confirm('Clear ALL questions in this bank?')">
                <button type="submit" name="clear_all" class="btn btn-sm btn-outline-danger rounded-pill">Clear
                  Bank</button>
              </form>
            </div>
          </div>
        </div>
        <div class="col-md-5">
          <div class="card p-4 shadow-sm border-0 rounded-4 h-100 text-white bg-primary">
            <h5 class="fw-bold mb-3"><i class="bi bi-images me-2"></i>Image Library (.zip)</h5>
            <p class="small opacity-75">Upload a ZIP of diagrams. Reference filenames in your CSV/TXT.</p>
            <div class="mb-3 small">
              <a href="download_template.php?type=objective_images_zip" class="btn btn-sm btn-link p-0 text-white fw-bold opacity-90"><i class="bi bi-download me-1"></i>Download Sample Image ZIP</a>
            </div>
            <form method="post" enctype="multipart/form-data" class="row g-2">
              <div class="col">
                <input type="file" name="zip" accept=".zip" class="form-control form-control-sm rounded-pill" required>
              </div>
              <div class="col-auto">
                <button type="submit" name="upload_images"
                  class="btn btn-light btn-sm rounded-pill font-weight-bold">Upload ZIP</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle bg-white rounded-4 shadow-sm">
          <thead class="bg-light">
            <tr>
              <th width="50">#</th>
              <th>Question Content</th>
              <th>Options (A-D)</th>
              <th width="100">Correct</th>
              <th width="80">Mark</th>
              <th width="120">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($questions as $i => $q): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td>
                  <div class="fw-bold"><?= htmlspecialchars($q['question_text']) ?></div>
                  <?php if ($q['question_image']): ?>
                    <div class="mt-1 badge bg-info text-dark"><i
                        class="bi bi-image me-1"></i><?= htmlspecialchars($q['question_image']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="small">
                  <div>A: <?= htmlspecialchars($q['option_a']) ?>   <?php if ($q['option_a_image']): ?><i
                        class="bi bi-image text-muted ms-1"></i><?php endif; ?></div>
                  <div>B: <?= htmlspecialchars($q['option_b']) ?>   <?php if ($q['option_b_image']): ?><i
                        class="bi bi-image text-muted ms-1"></i><?php endif; ?></div>
                  <div>C: <?= htmlspecialchars($q['option_c']) ?>   <?php if ($q['option_c_image']): ?><i
                        class="bi bi-image text-muted ms-1"></i><?php endif; ?></div>
                  <div>D: <?= htmlspecialchars($q['option_d']) ?>   <?php if ($q['option_d_image']): ?><i
                        class="bi bi-image text-muted ms-1"></i><?php endif; ?></div>
                </td>
                <td><span class="badge bg-success px-3"><?= strtoupper($q['correct_option']) ?></span></td>
                <td><?= $q['mark'] ?></td>
                <td>
                  <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal"
                    data-bs-target="#editModal<?= $q['id'] ?>"><i class="bi bi-pencil"></i></button>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete?')">
                    <input type="hidden" name="id" value="<?= $q['id'] ?>">
                    <button type="submit" name="delete" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>

              <!-- Edit Modal -->
              <div class="modal fade" id="editModal<?= $q['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content rounded-4 border-0">
                    <form method="post">
                      <input type="hidden" name="id" value="<?= $q['id'] ?>">
                      <div class="modal-header border-0 pb-0">
                        <h5 class="fw-bold">Edit Question</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label small fw-bold">Question Text (MathJax: $...$)</label>
                          <textarea name="question_text" class="form-control mb-2" rows="3"
                            required><?= htmlspecialchars($q['question_text']) ?></textarea>
                          <input type="text" name="question_image" class="form-control form-control-sm"
                            placeholder="Image filename (optional)"
                            value="<?= htmlspecialchars($q['question_image'] ?? '') ?>">
                        </div>
                        <div class="row g-3">
                          <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                            <div class="col-md-6">
                              <label class="form-label small fw-bold text-uppercase">Option <?= strtoupper($opt) ?></label>
                              <input type="text" name="option_<?= $opt ?>" class="form-control mb-1"
                                value="<?= htmlspecialchars($q['option_' . $opt]) ?>" required>
                              <input type="text" name="option_<?= $opt ?>_image" class="form-control form-control-sm"
                                placeholder="Option <?= strtoupper($opt) ?> Image"
                                value="<?= htmlspecialchars($q['option_' . $opt . '_image'] ?? '') ?>">
                            </div>
                          <?php endforeach; ?>
                          <div class="col-md-6">
                            <label class="form-label small fw-bold">Correct Option</label>
                            <select name="correct_option" class="form-select" required>
                              <?php foreach (['A', 'B', 'C', 'D'] as $o): ?>
                                <option value="<?= $o ?>" <?= $q['correct_option'] == $o ? 'selected' : '' ?>><?= $o ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small fw-bold">Mark</label>
                            <input type="number" name="mark" class="form-control" step="0.01" value="<?= $q['mark'] ?>"
                              required>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit" class="btn btn-success px-4">Save Changes</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Add Modal -->
  <div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content rounded-4 border-0">
        <form method="post">
          <div class="modal-header border-0 pb-0">
            <h5 class="fw-bold">Create New Question</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label small fw-bold">Question Text</label>
              <textarea name="question_text" class="form-control mb-2" rows="3" placeholder="Ex: What is $\sqrt{16}$?"
                required></textarea>
              <input type="text" name="question_image" class="form-control form-control-sm"
                placeholder="Image filename (e.g. diag1.png)">
            </div>
            <div class="row g-3">
              <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                <div class="col-md-6">
                  <label class="form-label small fw-bold text-uppercase">Option <?= strtoupper($opt) ?></label>
                  <input type="text" name="option_<?= $opt ?>" class="form-control mb-1" required>
                  <input type="text" name="option_<?= $opt ?>_image" class="form-control form-control-sm"
                    placeholder="Image filename (optional)">
                </div>
              <?php endforeach; ?>
              <div class="col-md-6">
                <label class="form-label small fw-bold">Correct Option</label>
                <select name="correct_option" class="form-select" required>
                  <option value="A">A</option>
                  <option value="B">B</option>
                  <option value="C">C</option>
                  <option value="D">D</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">Mark</label>
                <input type="number" name="mark" class="form-control" step="0.01" value="1" required>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="add" class="btn btn-primary px-4">Add Question</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
<!-- sweetalert_custom_injected_v1 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Override global alert
        window.alert = function(msg) {
            Swal.fire({
                title: 'Message',
                text: msg,
                icon: 'info',
                confirmButtonColor: '#092c19'
            });
        };

        // Intercept inline onsubmit confirms
        document.querySelectorAll('form[onsubmit*="return confirm"]').forEach(el => {
            let match = el.getAttribute('onsubmit').match(/return confirm\(['"`](.*?)['"`]\)/);
            if(match) {
                let msg = match[1];
                el.removeAttribute('onsubmit');
                el.addEventListener('submit', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: msg,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#092c19',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, proceed'
                    }).then((result) => {
                        if(result.isConfirmed) {
                            let btn = el.querySelector('button[type="submit"]:hover, input[type="submit"]:hover') 
                                      || el.querySelector('button[type="submit"], input[type="submit"]');
                            if(btn && btn.name) {
                                let hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = btn.name;
                                hidden.value = btn.value || '1';
                                el.appendChild(hidden);
                            }
                            HTMLFormElement.prototype.submit.call(el);
                        }
                    });
                });
            }
        });

        // Intercept inline onclick confirms for anchors
        document.querySelectorAll('a[onclick*="return confirm"], button[onclick*="return confirm"]').forEach(el => {
            let match = el.getAttribute('onclick').match(/return confirm\(['"`](.*?)['"`]\)/);
            if(match) {
                let msg = match[1];
                el.removeAttribute('onclick');
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: msg,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#092c19',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, proceed'
                    }).then((result) => {
                        if(result.isConfirmed) {
                            if(el.tagName === 'A' && el.href) {
                                window.location.href = el.href;
                            } else if(el.tagName === 'BUTTON') {
                                // if it's a submit button inside a form
                                if(el.type === 'submit' && el.form) {
                                    if(el.name) {
                                        let hidden = document.createElement('input');
                                        hidden.type = 'hidden';
                                        hidden.name = el.name;
                                        hidden.value = el.value || '1';
                                        el.form.appendChild(hidden);
                                    }
                                    HTMLFormElement.prototype.submit.call(el.form);
                                }
                            }
                        }
                    });
                });
            }
        });
    });
</script>
</body>


</html>
