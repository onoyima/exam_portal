<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\QuizQuestion;

class QuizQuestionRepository extends Repository
{
    protected function getTable(): string { return 'quiz_questions'; }
    protected function getModelClass(): string { return QuizQuestion::class; }

    public function getByQuiz(int $quizId): array
    {
        return QuizQuestion::where('quiz_id', '=', $quizId)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    public function countByQuiz(int $quizId): int
    {
        return QuizQuestion::where('quiz_id', '=', $quizId)->count();
    }
}
