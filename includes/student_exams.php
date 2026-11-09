<?php
/**
 * What a student can do with each of their objective exams right now.
 * Shared by the dashboard, the sidebar and the result page so the button a
 * student sees is always the same everywhere.
 */
require_once __DIR__ . '/question_engine.php';

function sd_student_exams(PDO $db, int $student_id): array
{
    $st = $db->prepare("SELECT e.*, g.name AS group_name FROM exams e JOIN exam_students es ON es.exam_id = e.id
        LEFT JOIN exam_groups g ON g.id = e.group_id AND g.deleted_at IS NULL
        WHERE es.student_id = ? AND e.type = 'objective' AND e.is_active = 1 AND e.deleted_at IS NULL
           AND (e.group_id IS NULL OR NOT EXISTS (SELECT 1 FROM center_tokens ct WHERE ct.group_id = e.group_id AND ct.revoked_at IS NULL))
        ORDER BY COALESCE(e.start_time, e.created_at) DESC, e.id DESC");
    $st->execute([$student_id]);
    $exams = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$exams) {
        return [];
    }
    $ids = array_map(fn($e) => (int) $e['id'], $exams);
    $in = implode(',', $ids);

    $atts = [];
    $q = $db->prepare("SELECT * FROM objective_attempts WHERE student_id = ? AND exam_id IN ($in) ORDER BY attempt_no");
    $q->execute([$student_id]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $atts[(int) $a['exam_id']][] = $a;
    }
    $counts = $db->query("SELECT exam_id, COUNT(*) FROM objective_questions WHERE exam_id IN ($in) AND deleted_at IS NULL GROUP BY exam_id")->fetchAll(PDO::FETCH_KEY_PAIR);

    $now = time();
    $out = [];
    foreach ($exams as $e) {
        $eid = (int) $e['id'];
        $list = $atts[$eid] ?? [];
        $finished = array_values(array_filter($list, fn($a) => $a['finished_at']));
        $open = null;
        foreach ($list as $a) {
            if (!$a['finished_at']) {
                $open = $a;
            }
        }
        $locked = $open && (int) $open['is_locked'] === 1;
        $max = max(1, (int) $e['max_attempts']);
        $start = !empty($e['start_time']) ? strtotime($e['start_time']) : null;
        $end = !empty($e['end_time']) ? strtotime($e['end_time']) : null;
        $late = qe_late_entry_deadline($e);
        $bank = (int) ($counts[$eid] ?? 0);
        $qcount = (int) $e['questions_to_show'] > 0 ? min($bank, (int) $e['questions_to_show']) : $bank;
        $can_view = (int) $e['student_can_view_score'] === 1;
        $last_finished = $finished ? end($finished) : null;

        $card = [
            'exam' => $e,
            'questions' => $qcount,
            'attempts_used' => count($finished),
            'attempts_max' => $max,
            'can_view' => $can_view,
            'open_attempt' => $open,
            'remaining' => $open ? qe_remaining_seconds($e, $open) : null,
            'score' => null,
            'state' => 'open',
            'label' => 'Open now',
            'primary' => null,
            'secondary' => null,
        ];
        $url = 'student_objective_exam?exam_id=' . $eid;
        $result_url = $can_view
            ? $url . '&view_result=1'
            : ($last_finished ? $url . '&review_attempt=' . (int) $last_finished['attempt_no'] : $url . '&view_result=1');
        $result_btn = $last_finished ? ['label' => $can_view ? 'View result' : 'Review', 'href' => $result_url, 'kind' => 'ghost'] : null;

        if ($locked) {
            $card['state'] = 'locked';
            $card['label'] = 'Locked';
            $card['primary'] = ['label' => 'Locked — call the invigilator', 'kind' => 'ghost', 'disabled' => true];
        } elseif ($open) {
            $card['state'] = 'progress';
            $card['label'] = 'In progress';
            $card['primary'] = ['label' => 'Continue exam', 'href' => $url . '&resume=1', 'kind' => 'continue'];
        } elseif ($start && $now < $start) {
            $card['state'] = 'wait';
            $card['label'] = 'Opens ' . date('j M, g:i A', $start);
            $card['primary'] = ['label' => 'Opens at ' . date('g:i A', $start), 'kind' => 'ghost', 'disabled' => true];
            $card['secondary'] = $result_btn;
        } elseif ($end && $now > $end) {
            $card['state'] = $finished ? 'done' : 'closed';
            $card['label'] = 'Closed';
            $card['primary'] = $result_btn ?? ['label' => 'Exam closed', 'kind' => 'ghost', 'disabled' => true];
        } elseif (count($finished) >= $max) {
            $card['state'] = 'done';
            $card['label'] = 'Completed';
            $card['primary'] = $result_btn;
            if ($result_btn) {
                $card['primary']['kind'] = 'start';
            }
        } elseif ($late && $now > $late) {
            $card['state'] = $finished ? 'done' : 'closed';
            $card['label'] = 'Late entry closed';
            $card['primary'] = $result_btn ?? ['label' => 'Late entry closed at ' . date('g:i A', $late), 'kind' => 'ghost', 'disabled' => true];
        } elseif ($finished) {
            $card['state'] = 'done';
            $card['label'] = 'Attempted';
            $card['primary'] = ['label' => 'Re-attempt', 'href' => $url . '&start_new=1', 'kind' => 'reattempt', 'confirm' => true];
            $card['secondary'] = $result_btn;
        } else {
            $card['primary'] = ['label' => 'Start exam', 'href' => $url . '&start=1', 'kind' => 'start', 'confirm' => true];
        }
        $out[] = $card;
    }

    // what the student can act on first: in progress, open, re-attempt, upcoming, then finished/closed
    $rank = ['progress' => 0, 'locked' => 1, 'open' => 2, 'wait' => 4, 'done' => 5, 'closed' => 6];
    usort($out, function ($a, $b) use ($rank) {
        $ra = $rank[$a['state']] + (($a['primary']['kind'] ?? '') === 'reattempt' ? -2 : 0);
        $rb = $rank[$b['state']] + (($b['primary']['kind'] ?? '') === 'reattempt' ? -2 : 0);
        return $ra <=> $rb;
    });

    // best/counted score for exams that show scores
    foreach ($out as &$c) {
        if ($c['can_view'] && $c['attempts_used'] > 0) {
            $r = qe_exam_results($db, $c['exam'], null, $student_id)[$student_id] ?? null;
            if ($r) {
                $c['score'] = ['value' => $r['score'], 'total' => $r['total']];
            }
        }
    }
    unset($c);
    return $out;
}

function sd_fmt_minutes(?int $secs): string
{
    if ($secs === null) {
        return 'no limit';
    }
    $m = (int) ceil($secs / 60);
    return $m >= 60 ? floor($m / 60) . ' h ' . ($m % 60 ? ($m % 60) . ' min' : '') : $m . ' min';
}
