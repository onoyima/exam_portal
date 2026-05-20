<?php
/**
 * scratch/test_parser.php
 * Programmatically validates that both standard Aiken and custom parenthesis/explained formats parse perfectly.
 */

function parseTxtQuestions($filePath) {
    if (!file_exists($filePath)) {
        echo "Error: File $filePath does not exist!\n";
        return [];
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $questions = [];
    $q = null;
    $options = [];
    $correct = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (preg_match('/^[A-D][\.\)]\s+/i', $line)) {
            $opt_letter = strtoupper($line[0]);
            $opt_text = trim(preg_replace('/^[A-D][\.\)]\s+/i', '', $line));
            $options[$opt_letter] = $opt_text;
        } elseif (preg_match('/^ANSWER:\s*([A-D])/i', $line, $ans_matches)) {
            $correct = strtoupper($ans_matches[1]);
            if ($q && !empty($options) && $correct) {
                $questions[] = [
                    'question_text' => trim($q),
                    'option_a' => $options['A'] ?? '',
                    'option_b' => $options['B'] ?? '',
                    'option_c' => $options['C'] ?? '',
                    'option_d' => $options['D'] ?? '',
                    'correct_option' => $correct
                ];
            }
            $q = null;
            $options = [];
            $correct = null;
        } else {
            if ($q === null) {
                $q = $line;
            } else {
                $q .= "\n" . $line;
            }
        }
    }
    return $questions;
}

echo "=== Testing 500L_QUESTION.txt ===\n";
$q500 = parseTxtQuestions(__DIR__ . '/../500L_QUESTION.txt');
echo "Parsed " . count($q500) . " questions from 500L_QUESTION.txt.\n";

$failed = false;
foreach ($q500 as $idx => $question) {
    $num = $idx + 1;
    echo "$num: " . substr($question['question_text'], 0, 50) . "...\n";
    if (empty($question['question_text'])) {
        echo "❌ Question $num: Question text is empty!\n";
        $failed = true;
    }
    foreach (['a', 'b', 'c', 'd'] as $o) {
        if (empty($question['option_' . $o])) {
            echo "❌ Question $num: Option " . strtoupper($o) . " is empty!\n";
            $failed = true;
        }
    }
    if (strlen($question['correct_option']) !== 1 || !in_array($question['correct_option'], ['A', 'B', 'C', 'D'])) {
        echo "❌ Question $num: Correct option '{$question['correct_option']}' is invalid!\n";
        $failed = true;
    }
}

if (!$failed && count($q500) === 20) {
    echo "✔ 500L_QUESTION.txt validation PASSED successfully!\n";
} else {
    echo "❌ 500L_QUESTION.txt validation FAILED!\n";
}

echo "\n=== Testing sample_objective_questions_custom.txt ===\n";
$qCustom = parseTxtQuestions(__DIR__ . '/../sample_objective_questions_custom.txt');
echo "Parsed " . count($qCustom) . " questions from sample_objective_questions_custom.txt.\n";

$failedCustom = false;
foreach ($qCustom as $idx => $question) {
    $num = $idx + 1;
    if (empty($question['question_text'])) {
        echo "❌ Custom Question $num: Question text is empty!\n";
        $failedCustom = true;
    }
    foreach (['a', 'b', 'c', 'd'] as $o) {
        if (empty($question['option_' . $o])) {
            echo "❌ Custom Question $num: Option " . strtoupper($o) . " is empty!\n";
            $failedCustom = true;
        }
    }
    if (strlen($question['correct_option']) !== 1 || !in_array($question['correct_option'], ['A', 'B', 'C', 'D'])) {
        echo "❌ Custom Question $num: Correct option '{$question['correct_option']}' is invalid!\n";
        $failedCustom = true;
    }
}

if (!$failedCustom && count($qCustom) === 3) {
    echo "✔ sample_objective_questions_custom.txt validation PASSED successfully!\n";
} else {
    echo "❌ sample_objective_questions_custom.txt validation FAILED!\n";
}

echo "\n=== Sample Question Output (Q7 with explanation in answer line) ===\n";
$q7 = $q500[6] ?? null;
if ($q7) {
    echo "Question 7: " . $q7['question_text'] . "\n";
    echo "A: " . $q7['option_a'] . "\n";
    echo "B: " . $q7['option_b'] . "\n";
    echo "C: " . $q7['option_c'] . "\n";
    echo "D: " . $q7['option_d'] . "\n";
    echo "Correct Option: " . $q7['correct_option'] . "\n";
}
