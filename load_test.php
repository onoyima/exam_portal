<?php
/**
 * CBT Portal Load Testing Script
 * Simulates concurrent student exam sessions to test performance under load
 * 
 * Usage: php load_test.php [concurrent_students] [exam_id] [duration_seconds]
 * Example: php load_test.php 100 138 60
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$concurrentStudents = $argv[1] ?? 50;
$examId = $argv[2] ?? 138;
$durationSeconds = $argv[3] ?? 30;

echo "=== CBT Portal Load Test ===\n";
echo "Concurrent students: $concurrentStudents\n";
echo "Exam ID: $examId\n";
echo "Duration: ${durationSeconds}s\n\n";

// Verify exam exists
$exam = $db->prepare("SELECT id, name, duration_minutes, is_active FROM exams WHERE id = ? AND deleted_at IS NULL");
$exam->execute([$examId]);
$exam = $exam->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die("Exam not found or inactive\n");
}
if (!$exam['is_active']) {
    die("Exam is not active\n");
}

echo "Exam: {$exam['name']} (ID: {$exam['id']})\n";
echo "Duration: {$exam['duration_minutes']} minutes\n\n";

// Get questions for this exam
$questions = $db->prepare("SELECT id, question_type, mark FROM objective_questions WHERE exam_id = ? AND deleted_at IS NULL");
$questions->execute([$examId]);
$questions = $questions->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    die("No questions found for this exam\n");
}

echo "Questions in exam: " . count($questions) . "\n\n";

// Get available students
$students = $db->query("SELECT id, matric_no FROM students WHERE deleted_at IS NULL LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
if (count($students) < $concurrentStudents) {
    die("Not enough students in database (need $concurrentStudents, have " . count($students) . ")\n");
}

$selectedStudents = array_slice($students, 0, $concurrentStudents);

echo "\n=== Starting Load Test ===\n";
echo "Simulating $concurrentStudents concurrent students for {$durationSeconds}s...\n\n";

$startTime = microtime(true);
$results = [
    'total_requests' => 0,
    'successful' => 0,
    'failed' => 0,
    'errors' => [],
    'response_times' => [],
];

// Create test processes using proc_open for true concurrency
$processes = [];
$studentsPerProcess = ceil($concurrentStudents / 10); // 10 processes max
$processesCount = min(10, $concurrentStudents);

for ($p = 0; $p < $processesCount; $p++) {
    $startIdx = $p * $studentsPerProcess;
    $endIdx = min($startIdx + $studentsPerProcess, count($selectedStudents));
    $batchStudents = array_slice($selectedStudents, $startIdx, $studentsPerProcess);
    
    if (empty($batchStudents)) continue;
    
    $studentsJson = json_encode(array_values($batchStudents));
    $cmd = "php " . __DIR__ . "/simulate_student.php $examId $durationSeconds '$studentsJson' 2>&1";
    
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];
    
    $process = proc_open($cmd, $descriptors, $pipes);
    if (is_resource($process)) {
        $processes[] = [
            'process' => $process,
            'pipes' => $pipes,
            'students' => $batchStudents,
        ];
    }
}

echo "Started " . count($processes) . " worker processes\n\n";

// Monitor processes
$completed = 0;
$totalStudents = count($selectedStudents);

while ($completed < count($processes)) {
    foreach ($processes as $i => $proc) {
        if ($proc === null) continue;
        
        $status = proc_get_status($proc['process']);
        if (!$status['running']) {
            $output = stream_get_contents($proc['pipes'][1]);
            $errors = stream_get_contents($proc['pipes'][2]);
            proc_close($proc['process']);
            
            $processes[$i] = null;
            $completed++;
            
            // Parse results
            if (!empty($output)) {
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    if (strpos($line, 'RESULT:') === 0) {
                        $data = json_decode(substr($line, 7), true);
                        if ($data) {
                            $results['total_requests'] += $data['requests'] ?? 0;
                            $results['successful'] += $data['success'] ?? 0;
                            $results['failed'] += $data['failed'] ?? 0;
                            $results['response_times'] = array_merge($results['response_times'], $data['times'] ?? []);
                            if (!empty($data['errors'])) {
                                $results['errors'] = array_merge($results['errors'], $data['errors']);
                            }
                        }
                    }
                }
            }
            
            if (!empty($errors)) {
                $results['errors'] = array_merge($results['errors'], array_map(fn($e) => "Process $i: $e", array_filter(explode("\n", $errors))));
            }
            
            echo "Process $completed/" . count($processes) . " completed\n";
        }
    }
    
    if ($completed < count($processes)) {
        usleep(500000); // 0.5s
    }
}

$totalTime = microtime(true) - $startTime;

// Summary
echo "\n=== Load Test Results ===\n";
echo "Total time: " . round($totalTime, 2) . "s\n";
echo "Target concurrent students: $concurrentStudents\n";
echo "Actual students simulated: $totalStudents\n";
echo "Duration: ${durationSeconds}s\n\n";

echo "Requests:\n";
echo "  Total: " . $results['total_requests'] . "\n";
echo "  Successful: " . $results['successful'] . "\n";
echo "  Failed: " . $results['failed'] . "\n";

if (!empty($results['response_times'])) {
    sort($results['response_times']);
    $count = count($results['response_times']);
    echo "\nResponse times (ms):\n";
    echo "  Min: " . round($results['response_times'][0], 2) . "\n";
    echo "  Avg: " . round(array_sum($results['response_times']) / count($results['response_times']), 2) . "\n";
    echo "  Max: " . round($results['response_times'][$count - 1], 2) . "\n";
    echo "  P50: " . round($results['response_times'][intval($count * 0.5)], 2) . "\n";
    echo "  P95: " . round($results['response_times'][intval($count * 0.95)], 2) . "\n";
    echo "  P99: " . round($results['response_times'][intval($count * 0.99)], 2) . "\n";
}

if (!empty($results['errors'])) {
    echo "\nErrors (" . count($results['errors']) . "):\n";
    foreach (array_slice($results['errors'], 0, 10) as $err) {
        echo "  - $err\n";
    }
    if (count($results['errors']) > 10) {
        echo "  ... and " . (count($results['errors']) - 10) . " more\n";
    }
}

// Throughput
$rps = $results['total_requests'] / $totalTime;
echo "\nThroughput: " . round($rps, 2) . " req/s\n";
echo "Success rate: " . ($results['total_requests'] > 0 ? round(100 * $results['successful'] / $results['total_requests'], 2) : 0) . "%\n";

// Health assessment
echo "\n=== Health Assessment ===\n";
if ($rps > 50 && $results['failed'] == 0) {
    echo "✓ EXCELLENT - System handles load well\n";
} elseif ($rps > 20 && $results['failed'] < 5) {
    echo "✓ GOOD - System handles load with minor issues\n";
} elseif ($rps > 10) {
    echo "⚠ FAIR - System under stress, may need optimization\n";
} else {
    echo "✗ POOR - System cannot handle this load\n";
}

if (!empty($results['errors'])) {
    echo "⚠ Errors detected - investigate\n";
}

echo "\n=== Test Complete ===\n";