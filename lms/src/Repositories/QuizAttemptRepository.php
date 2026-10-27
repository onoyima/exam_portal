<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\QuizAttempt;

class QuizAttemptRepository extends Repository
{
    protected function getTable(): string { return 'quiz_attempts'; }
    protected function getModelClass(): string { return QuizAttempt::class; }

    public function getByQuiz(int $quizId): array
    {
        return QuizAttempt::where('quiz_id', '=', $quizId)
            ->orderBy('started_at', 'DESC')
            ->get();
    }

    public function getByUser(int $userId): array
    {
        return QuizAttempt::where('user_id', '=', $userId)
            ->orderBy('started_at', 'DESC')
            ->get();
    }

    public function getByQuizAndUser(int $quizId, int $userId): array
    {
        return QuizAttempt::where('quiz_id', '=', $quizId)
            ->where('user_id', '=', $userId)
            ->orderBy('started_at', 'DESC')
            ->get();
    }

    public function getInProgress(): array
    {
        return QuizAttempt::where('status', '=', 'in_progress')
            ->orderBy('started_at', 'ASC')
            ->get();
    }

    public function getExpiredAttempts(): array
    {
        return QuizAttempt::where('status', '=', 'in_progress')
            ->whereRaw("DATE_ADD(started_at, INTERVAL (SELECT time_limit_minutes FROM lms_quizzes WHERE id = quiz_id) MINUTE) <= NOW()")
            ->get();
    }

    public function countByQuiz(int $quizId): int
    {
        return QuizAttempt::where('quiz_id', '=', $quizId)->count();
    }

    public function getAverageScore(int $quizId): float
    {
        $result = QuizAttempt::where('quiz_id', '=', $quizId)
            ->where('status', '=', 'completed')
            ->get();
        if (empty($result)) {
            return 0.0;
        }
        $total = array_sum(array_map(fn($a) => $a->score_percentage ?? 0, $result));
        return round($total / count($result), 2);
    }
}
