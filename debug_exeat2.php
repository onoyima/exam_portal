<?php
require 'auth.php';
check_auth('admin');
require 'db_exeat2.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>exeat2 Connection Debug</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container">
    <h1>exeat2 Connection Diagnostic</h1>
    <?php if ($exeat2_db): ?>
      <div class="alert alert-success">✅ exeat2 PDO connection established.</div>
      <?php
      try {
        $students = $exeat2_db->query("SELECT COUNT(*) AS cnt FROM students WHERE status=1")->fetch();
        echo "<p>Active students: <strong>{$students['cnt']}</strong></p>";

        $staff = $exeat2_db->query("SELECT COUNT(*) AS cnt FROM staff WHERE status=1")->fetch();
        echo "<p>Active staff: <strong>{$staff['cnt']}</strong></p>";

        $depts = $exeat2_db->query("SELECT id, name FROM departments ORDER BY name LIMIT 20")->fetchAll();
        echo "<p>Departments (" . count($depts) . " shown):</p><ul>";
        foreach ($depts as $d) {
          echo "<li>#{$d['id']} — " . htmlspecialchars($d['name']) . "</li>";
        }
        echo "</ul>";

        $levels = $exeat2_db->query("SELECT id, level FROM levels ORDER BY level")->fetchAll();
        echo "<p>Levels:</p><ul>";
        foreach ($levels as $l) {
          echo "<li>#{$l['id']} — {$l['level']}</li>";
        }
        echo "</ul>";
      } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Query error: " . htmlspecialchars($e->getMessage()) . "</div>";
      }
      ?>
    <?php else: ?>
      <div class="alert alert-danger">❌ exeat2 connection failed. Check EXEAT2_DB_* constants in .env and config.php.</div>
      <h3>Defined constants:</h3>
      <pre><?php
        $checks = ['EXEAT2_DB_HOST','EXEAT2_DB_PORT','EXEAT2_DB_NAME','EXEAT2_DB_USER','EXEAT2_DB_PASS','EXEAT2_DB_CHARSET'];
        foreach ($checks as $c) {
          echo "$c = " . (defined($c) ? constant($c) : 'NOT DEFINED') . "\n";
        }
      ?></pre>
    <?php endif; ?>
    <a href="admin_dashboard" class="btn btn-secondary mt-3">Back to Dashboard</a>
  </div>
</body>
</html>
