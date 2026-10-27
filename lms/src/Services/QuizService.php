<?php
namespace Lms\Services;

use Lms\Core\Database;
use Lms\Core\Event;
use Lms\Core\Logger;
use Lms\Models\Quiz;
use Lms\Models\QuizAttempt;
use Lms\Models\QuizQuestion;
use Lms\Models\QuizAnswer;

class QuizService
{
    public static function startAttempt(int $quizId, int $userId, string $ipAddress = null, string $userAgent = null): ?QuizAttempt
    {
        $quiz = Quiz::find($quizId);
        if (!$quiz) {
            Logger::error('Quiz not found for attempt', ['quiz_id' => $quizId]);
            return null;
        }

        if (!$quiz->isAvailable()) {
            Logger::warning('Quiz not available', ['quiz_id' => $quizId]);
            return null;
        }

        $remaining = $quiz->remainingAttempts($userId);
        if ($remaining <= 0) {
            Logger::warning('No remaining attempts', ['quiz_id' => $quizId, 'user_id' => $userId]);
            return null;
        }

        $maxAttempts = (int) ($quiz->max_attempts ?? 1);
        $usedAttempts = QuizAttempt::where('quiz_id', $quizId)
            ->where('user_id', $userId)
            ->count();
        $attemptNumber = $usedAttempts + 1;

        $db = Database::connection('lms');
        $stmt = $db->prepare("
            SELECT COALESCE(MAX(attempt_number), 0) + 1
            FROM lms_quiz_attempts
            WHERE quiz_id = ? AND user_id = ?
        ");
        $stmt->execute([$quizId, $userId]);
        $attemptNumber = (int) $stmt->fetchColumn();

        $attempt = new QuizAttempt();
        $attempt->quiz_id = $quizId;
        $attempt->user_id = $userId;
        $attempt->attempt_number = $attemptNumber;
        $attempt->started_at = date('Y-m-d H:i:s');
        $attempt->status = 'in_progress';
        $attempt->ip_address = $ipAddress;
        $attempt->user_agent = $userAgent;
        $attempt->save();

        if ($quiz->hasTimeLimit()) {
            $ttl = $quiz->time_limit_minutes * 60;
            self::setTimer($attempt->getKey(), $ttl);
        }

        Event::dispatch('quiz.started', [
            'quiz_id' => $quizId,
            'attempt_id' => $attempt->getKey(),
            'user_id' => $userId,
            'attempt_number' => $attemptNumber,
        ]);

        Logger::info('Quiz attempt started', [
            'quiz_id' => $quizId,
            'attempt_id' => $attempt->getKey(),
            'user_id' => $userId,
        ]);

        return $attempt;
    }

    public static function submitAttempt(int $attemptId, array $answers): ?QuizAttempt
    {
        $attempt = QuizAttempt::find($attemptId);
        if (!$attempt || $attempt->isCompleted()) return null;

        $quiz = Quiz::find($attempt->quiz_id);
        if (!$quiz) return null;

        $questions = $quiz->questions();
        $questionMap = [];
        foreach ($questions as $q) {
            $questionMap[$q->getKey()] = $q;
        }

        $totalScore = 0;
        $maxScore = 0;
        foreach ($questions as $q) {
            $maxScore += (float) $q->points;
        }

        foreach ($answers as $ans) {
            $question = $questionMap[$ans['question_id'] ?? 0] ?? null;
            if (!$question) continue;

            $givenAnswer = is_array($ans['answer'] ?? null)
                ? json_encode($ans['answer'])
                : (string) ($ans['answer'] ?? '');
            $isCorrect = $question->isCorrect($ans['answer'] ?? null);
            $pointsEarned = $isCorrect ? (float) $question->points : 0;
            $totalScore += $pointsEarned;

            $answer = new QuizAnswer();
            $answer->attempt_id = $attemptId;
            $answer->question_id = $question->getKey();
            $answer->given_answer = $givenAnswer;
            $answer->is_correct = $isCorrect ? 1 : 0;
            $answer->points_earned = $pointsEarned;
            $answer->points_possible = (float) $question->points;
            $answer->time_spent_seconds = (int) ($ans['time_spent'] ?? 0);
            $answer->save();
        }

        $attempt->finished_at = date('Y-m-d H:i:s');
        $attempt->time_taken_seconds = strtotime($attempt->finished_at) - strtotime($attempt->started_at);
        $attempt->score = $totalScore;
        $attempt->max_score = $maxScore;
        $attempt->percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $attempt->passed = $quiz->passing_score ? $attempt->percentage >= (float) $quiz->passing_score : null;
        $attempt->status = 'completed';
        $attempt->save();

        Event::dispatch('quiz.completed', [
            'quiz_id' => $quiz->getKey(),
            'attempt_id' => $attemptId,
            'user_id' => $attempt->user_id,
            'score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $attempt->percentage,
            'passed' => $attempt->passed,
        ]);

        Logger::info('Quiz attempt completed', [
            'attempt_id' => $attemptId,
            'score' => $totalScore,
            'percentage' => $attempt->percentage,
        ]);

        return $attempt;
    }

    public static function gradeAttempt(int $attemptId, ?int $graderId = null): ?QuizAttempt
    {
        $attempt = QuizAttempt::find($attemptId);
        if (!$attempt) return null;

        if ($attempt->status !== 'in_progress') return $attempt;

        $answers = QuizAnswer::where('attempt_id', $attemptId)->get();
        $totalScore = 0;
        $maxScore = 0;

        foreach ($answers as $answer) {
            $totalScore += (float) ($answer->points_earned ?? 0);
            $maxScore += (float) ($answer->points_possible ?? 0);
        }

        $attempt->score = $totalScore;
        $attempt->max_score = $maxScore;
        $attempt->percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $attempt->status = 'reviewed';
        $attempt->save();

        Event::dispatch('quiz.graded', [
            'attempt_id' => $attemptId,
            'score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $attempt->percentage,
            'grader_id' => $graderId,
        ]);

        return $attempt;
    }

    public static function getRemainingTime(int $attemptId): ?int
    {
        $attempt = QuizAttempt::find($attemptId);
        if (!$attempt) return null;

        $quiz = Quiz::find($attempt->quiz_id);
        if (!$quiz || !$quiz->hasTimeLimit()) return null;

        $startTime = strtotime($attempt->started_at);
        $timeLimit = $quiz->time_limit_minutes * 60;
        $elapsed = time() - $startTime;

        return max(0, $timeLimit - $elapsed);
    }

    public static function getAttemptResults(int $attemptId): ?array
    {
        $attempt = QuizAttempt::find($attemptId);
        if (!$attempt) return null;

        $quiz = Quiz::find($attempt->quiz_id);
        $answers = QuizAnswer::where('attempt_id', $attemptId)->get();

        $questions = [];
        foreach ($answers as $answer) {
            $question = QuizQuestion::find($answer->question_id);
            if (!$question) continue;

            $questions[] = [
                'question' => $question->question_text,
                'question_type' => $question->question_type,
                'points' => (float) $question->points,
                'given_answer' => $answer->given_answer,
                'correct_answer' => $question->correct_answer,
                'is_correct' => (bool) $answer->is_correct,
                'points_earned' => (float) ($answer->points_earned ?? 0),
                'points_possible' => (float) ($answer->points_possible ?? 0),
                'feedback' => $answer->is_correct ? $question->feedback_correct : $question->feedback_incorrect,
            ];
        }

        return [
            'attempt_id' => $attemptId,
            'quiz_title' => $quiz ? $quiz->title : null,
            'score' => (float) ($attempt->score ?? 0),
            'max_score' => (float) ($attempt->max_score ?? 0),
            'percentage' => (float) ($attempt->percentage ?? 0),
            'passed' => (bool) $attempt->passed,
            'time_taken_seconds' => (int) ($attempt->time_taken_seconds ?? 0),
            'finished_at' => $attempt->finished_at,
            'questions' => $questions,
        ];
    }

    public static function autoSubmitExpiredAttempts(): int
    {
        $db = Database::connection('lms');
        $stmt = $db->prepare("
            SELECT qa.id, q.time_limit_minutes
            FROM lms_quiz_attempts qa
            JOIN lms_quizzes q ON qa.quiz_id = q.id
            WHERE qa.status = 'in_progress'
            AND q.time_limit_minutes IS NOT NULL
            AND TIMESTAMPDIFF(SECOND, qa.started_at, NOW()) > q.time_limit_minutes * 60
        ");
        $stmt->execute();
        $expired = $stmt->fetchAll();

        $count = 0;
        foreach ($expired as $row) {
            $attempt = QuizAttempt::find((int) $row['id']);
            if ($attempt) {
                $answers = QuizAnswer::where('attempt_id', $attempt->getKey())->get();
                self::gradeAttempt($attempt->getKey());
                $count++;
            }
        }

        if ($count > 0) {
            Logger::info('Auto-submitted expired attempts', ['count' => $count]);
        }

        return $count;
    }

    private static function setTimer(int $attemptId, int $ttl): void
    {
        $cache = \Lms\Core\Cache::instance();
        $cache->set("quiz_timer:{$attemptId}", time(), $ttl);
    }
}
