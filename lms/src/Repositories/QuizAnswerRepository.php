<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\QuizAnswer;

class QuizAnswerRepository extends Repository
{
    protected function getTable(): string { return 'quiz_answers'; }
    protected function getModelClass(): string { return QuizAnswer::class; }

    public function getByAttempt(int $attemptId): array
    {
        return QuizAnswer::where('attempt_id', '=', $attemptId)
            ->orderBy('id', 'ASC')
            ->get();
    }

    public function getByQuestion(int $questionId): array
    {
        return QuizAnswer::where('question_id', '=', $questionId)
            ->orderBy('id', 'ASC')
            ->get();
    }
}
