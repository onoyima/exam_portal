<?php
/**
 * Unit checks for includes/question_engine.php (no database needed).
 * Run from the project folder:  php tests/question_engine_test.php
 */
require __DIR__ . '/../includes/question_engine.php';

$pass = 0;
$fail = 0;
function check($label, $cond)
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
    } else {
        $fail++;
        echo "FAIL: $label\n";
    }
}
function q(array $row)
{
    return qe_hydrate($row + ['id' => 1, 'mark' => 1, 'question_text' => 'x']);
}

/* ---- TXT parsing ---- */

$aiken = "What is the capital of France?\nA. Paris\nB. London\nC. Berlin\nD. Madrid\nANSWER: A\n\n"
       . "Which is the largest planet?\nA) Earth\nB) Mars\nC) Jupiter\nD) Venus\nANSWER: C";
$r = qe_parse_txt($aiken);
check('aiken: two questions', count($r['questions']) === 2);
check('aiken: no errors', !$r['errors']);
check('aiken: type single', $r['questions'][0]['type'] === 'mcq_single');
check('aiken: answer', $r['questions'][1]['correct'] === 'C');
check('aiken: option text', $r['questions'][0]['options']['A']['text'] === 'Paris');

// The sample that already ships with the project (no blank-line discipline, numbered stems)
$legacy = file_get_contents(__DIR__ . '/../sample_objective_questions_custom.txt');
$r = qe_parse_txt($legacy);
check('legacy sample parses: 3 questions', count($r['questions']) === 3);
check('legacy sample: D answer', ($r['questions'][2]['correct'] ?? '') === 'D');

$mixed = <<<TXT
TYPE: MTF
MARK: 4
Concerning the human heart:
a) It has four chambers. [T]
b) The right ventricle pumps blood into the aorta. [F]
c) The mitral valve has two cusps. (T)
d) The SA node is found in the left atrium. - FALSE

TYPE: MTF
SCORING: ALL
Regarding malaria:
1. It is caused by a protozoan.
2. It is transmitted by the male Anopheles mosquito.
3. Quinine is used in its treatment.
ANSWER: T F T

TYPE: TF
Water boils at 100 degrees Celsius at sea level.
ANSWER: TRUE

TYPE: MULTI
MARK: 2
Which of these are prime numbers?
A. 2
B. 4
C. 7
D. 9
ANSWER: A, C

1. Plain MCQ after the typed ones
A. one
B. two
ANSWER: B

The kidney filters blood.
ANSWER: FALSE

Untyped MTF with inline marks:
a. statement one  T
b. statement two  F
TXT;
$r = qe_parse_txt($mixed);
check('mixed: no errors (' . json_encode($r['errors']) . ')', !$r['errors']);
check('mixed: 7 questions', count($r['questions']) === 7);
[$m1, $m2, $tf, $multi, $plain, $tf2, $m3] = array_pad($r['questions'], 7, []);
check('mtf1 type', ($m1['type'] ?? '') === 'mtf');
check('mtf1 4 statements', count($m1['statements'] ?? []) === 4);
check('mtf1 answers', implode('', array_column($m1['statements'] ?? [], 'answer')) === 'TFTF');
check('mtf1 mark', ($m1['mark'] ?? null) == 4);
check('mtf1 marker stripped', ($m1['statements'][0]['text'] ?? '') === 'It has four chambers.');
check('mtf2 answer line', implode('', array_column($m2['statements'] ?? [], 'answer')) === 'TFT');
check('mtf2 scoring all', ($m2['scoring'] ?? null) === 'all');
check('tf type', ($tf['type'] ?? '') === 'true_false' && $tf['correct'] === 'TRUE');
check('multi', ($multi['type'] ?? '') === 'mcq_multi' && qe_parse_letters($multi['correct']) === ['A', 'C']);
check('plain mcq number stripped', ($plain['text'] ?? '') === 'Plain MCQ after the typed ones');
check('untyped tf detected', ($tf2['type'] ?? '') === 'true_false');
check('untyped mtf detected', ($m3['type'] ?? '') === 'mtf' && implode('', array_column($m3['statements'], 'answer')) === 'TF');

$bad = "TYPE: MTF\nStem\na) one [T]\nb) two\nc) three\nANSWER: TF\n\nNo answer here\nA. x\nB. y\n";
$r = qe_parse_txt($bad);
check('bad: two errors', count($r['errors']) === 2);
check('bad: error has line', ($r['errors'][0]['line'] ?? 0) === 1);

$r = qe_parse_txt("Pick one\nA. x\nB. y\nANSWER: B. y\n");
check('answer letter with echo', ($r['questions'][0]['correct'] ?? '') === 'B');

/* ---- building ---- */

$b = qe_build_question(['type' => 'mtf', 'text' => 'S', 'statements' => [['text' => 'a', 'answer' => 'T'], ['text' => 'b', 'answer' => 'F'], ['text' => 'c', 'answer' => 'F']]]);
check('build mtf ok', $b['ok'] && $b['row']['correct_option'] === 'TFF');
check('build mtf default mark = statements', $b['row']['mark'] == 3);
$b = qe_build_question(['type' => 'mtf', 'text' => 'S', 'statements' => [['text' => 'a', 'answer' => 'T']]]);
check('build mtf too few', !$b['ok']);
$b = qe_build_question(['type' => 'mcq_single', 'text' => 'S', 'options' => ['A' => 'x', 'B' => 'y'], 'correct' => 'A,B']);
check('single with two answers rejected', !$b['ok']);
$b = qe_build_question(['type' => 'mcq_single', 'text' => 'S', 'options' => ['A' => 'x', 'B' => 'y'], 'correct' => 'C']);
check('answer without option rejected', !$b['ok']);
$b = qe_build_question(['type' => 'true_false', 'text' => 'S', 'correct' => 'false']);
check('tf build', $b['ok'] && $b['row']['correct_option'] === 'B' && $b['row']['option_a'] === 'True');

/* ---- answers & marking ---- */

$mtf = q(['question_type' => 'mtf', 'correct_option' => 'TFTF', 'mark' => 4,
          'question_data' => json_encode(['statements' => [['text' => '1'], ['text' => '2'], ['text' => '3'], ['text' => '4']]])]);
check('normalise mtf', qe_normalise_answer($mtf, 'tf-t') === 'TF-T');
check('normalise mtf wrong length', qe_normalise_answer($mtf, 'TF') === null);
check('normalise mtf all blank', qe_normalise_answer($mtf, '----') === '');
$s = qe_score($mtf, 'TFTF', ['partial_scoring' => 1]);
check('mtf all right', $s['awarded'] == 4 && $s['status'] === 'correct');
$s = qe_score($mtf, 'TFFF', ['partial_scoring' => 1]);
check('mtf 3/4 partial', abs($s['awarded'] - 3) < 1e-9 && $s['status'] === 'partial');
$s = qe_score($mtf, 'TF--', ['partial_scoring' => 1]);
check('mtf blanks count as not earned', abs($s['awarded'] - 2) < 1e-9 && $s['statements'][2] === null);
$s = qe_score($mtf, 'TFFF', ['partial_scoring' => 0]);
check('mtf all-or-nothing', $s['awarded'] == 0 && $s['status'] === 'wrong');
$s = qe_score($mtf, '', ['partial_scoring' => 1]);
check('mtf unanswered', $s['status'] === 'unanswered');
$mtfAll = q(['question_type' => 'mtf', 'correct_option' => 'TF', 'mark' => 2,
             'question_data' => json_encode(['statements' => ['a', 'b'], 'scoring' => 'all'])]);
check('per-question override beats exam setting', qe_score($mtfAll, 'TT', ['partial_scoring' => 1])['awarded'] == 0);

$multi = q(['question_type' => 'mcq_multi', 'correct_option' => 'A,C', 'mark' => 2]);
check('multi exact', qe_score($multi, 'C,A', ['partial_scoring' => 1])['awarded'] == 2);
check('multi half', qe_score($multi, 'A', ['partial_scoring' => 1])['awarded'] == 1);
check('multi wrong cancels', qe_score($multi, 'A,B', ['partial_scoring' => 1])['awarded'] == 0);
check('multi tick all = 0', qe_score($multi, 'A,B,C,D', ['partial_scoring' => 1])['awarded'] == 0);
check('multi strict', qe_score($multi, 'A', ['partial_scoring' => 0])['awarded'] == 0);

$single = q(['question_type' => 'mcq_single', 'correct_option' => 'b', 'mark' => 1.5]);
check('legacy single (lowercase key)', qe_score($single, 'B')['awarded'] == 1.5);
check('single wrong', qe_score($single, 'A')['awarded'] == 0);
check('single invalid answer rejected', qe_normalise_answer($single, 'E') === null);
$legacyRow = q(['correct_option' => 'C']);   // row from before the migration: no question_type
check('legacy row defaults to single', $legacyRow['question_type'] === 'mcq_single');

/* ---- attempt scoring ---- */

$questions = [1 => q(['id' => 1, 'correct_option' => 'A', 'mark' => 1]), 2 => $mtf + ['id' => 2], 3 => $multi + ['id' => 3]];
$questions[2]['id'] = 2;
$questions[3]['id'] = 3;
$exam = ['total_mark' => 0, 'partial_scoring' => 1];
$res = qe_score_attempt($exam, $questions, ['question_order' => '2,1'], [1 => 'A', 2 => 'TFFF', 3 => 'A,C']);
check('attempt only counts assigned questions', $res['possible'] == 5 && $res['raw'] == 4);
$res = qe_score_attempt(['total_mark' => 50, 'partial_scoring' => 1], $questions, ['question_order' => ''], [1 => 'A', 2 => 'TFTF', 3 => 'A']);
check('attempt scaled to total mark', abs($res['scaled'] - (6 / 7 * 50)) < 1e-3);

$final = qe_pick_final('average', [['scaled' => 10, 'raw' => 10, 'scaled_total' => 20, 'items' => []], ['scaled' => 20, 'raw' => 20, 'scaled_total' => 20, 'items' => []]], []);
check('average grading', $final['score'] == 15);
$final = qe_pick_final('highest', [['scaled' => 10, 'raw' => 10, 'scaled_total' => 20, 'items' => [], 'attempt_no' => 1], ['scaled' => 20, 'raw' => 20, 'scaled_total' => 20, 'items' => [], 'attempt_no' => 2]], []);
check('highest grading', $final['score'] == 20 && $final['attempt_no'] === 2);

/* ---- public payload ---- */

$pub = qe_public_question($mtf + ['question_image' => ''], 77, true);
check('public mtf has no key', !isset($pub['correct_option']) && count($pub['statements']) === 4);
$again = qe_public_question($mtf + ['question_image' => ''], 77, true);
check('stable shuffle', array_column($pub['statements'], 'key') === array_column($again['statements'], 'key'));
$xss = q(['question_text' => 'H<sub>2</sub>O <script>alert(1)</script>', 'option_a' => 'a', 'option_b' => 'b', 'option_c' => '', 'option_d' => '', 'question_image' => '../../etc/passwd']);
$pub = qe_public_question($xss, 1, false);
check('safe html keeps sub', strpos($pub['text'], '<sub>2</sub>') !== false);
check('safe html drops script', strpos($pub['text'], '<script>') === false);
check('image path traversal blocked', $pub['image'] === null);
check('empty options hidden', count($pub['options']) === 2);

/* ---- export round trip ---- */

$bank = [];
$r = qe_parse_txt($mixed);
foreach ($r['questions'] as $i => $in) {
    $b = qe_build_question($in);
    $bank[] = qe_hydrate($b['row'] + ['id' => $i + 1]);
}
$back = qe_parse_txt(qe_export_txt($bank));
check('export round trip count', count($back['questions']) === count($bank) && !$back['errors']);
check('export round trip mtf key', implode('', array_column($back['questions'][0]['statements'], 'answer')) === 'TFTF');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
