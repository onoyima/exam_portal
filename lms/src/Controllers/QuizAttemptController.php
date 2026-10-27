<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\Quiz;
use Lms\Models\QuizAttempt;
use Lms\Models\QuizAnswer;
use Lms\Models\ActivityLog;
use Lms\Services\QuizService;

class QuizAttemptController extends Controller
{
    public function index(Request $req, Response $res, string $quizId): void
    {
        $quiz = Quiz::find((int) $quizId);
        if (!$quiz) $res->notFound('Quiz not found');

        $attempts = $quiz->attemptsByUser($this->userId());
        foreach ($attempts as $attempt) {
            $attempt->setRelation('answers', $attempt->answers());
        }
        $res->success(['attempts' => $attempts]);
    }

    public function show(Request $req, Response $res, string $quizId, string $attemptId): void
    {
        $attempt = QuizAttempt::find((int) $attemptId);
        if (!$attempt || $attempt->quiz_id != (int) $quizId) $res->notFound('Attempt not found');

        $attempt->setRelation('answers', $attempt->answers());
        $attempt->setRelation('quiz', $attempt->quiz());

        if ($attempt->isCompleted()) {
            $questions = [];
            foreach ($attempt->answers() as $answer) {
                $question = $answer->question();
                if ($question) {
                    $questions[] = [
                        'question' => $question->question_text,
                        'question_type' => $question->question_type,
                        'points' => (float) $question->points,
                        'given_answer' => $answer->given_answer,
                        'is_correct' => (bool) $answer->is_correct,
                        'points_earned' => (float) ($answer->points_earned ?? 0),
                    ];
                }
            }
            $attempt->setRelation('questions', $questions);
        }

        $res->success(['attempt' => $attempt->toArray()]);
    }

    public function start(Request $req, Response $res, string $quizId): void
    {
        $quiz = Quiz::find((int) $quizId);
        if (!$quiz) $res->notFound('Quiz not found');
        if (!$quiz->isAvailable()) $res->error('NOT_AVAILABLE', 'Quiz is not available at this time');

        $remaining = $quiz->remainingAttempts($this->userId());
        if ($remaining <= 0) $res->error('NO_ATTEMPTS', 'No attempts remaining');

        $attempt = QuizService::startAttempt($quiz->getKey(), $this->userId(), $req->ip(), $req->userAgent());
        if (!$attempt) $res->error('ATTEMPT_FAILED', 'Failed to start attempt');

        $questions = $quiz->questions();
        $questionData = [];
        foreach ($questions as $q) {
            $qData = [
                'id' => $q->getKey(),
                'question_type' => $q->question_type,
                'question_text' => $q->question_text,
                'points' => (float) $q->points,
                'order_index' => (int) ($q->order_index ?? 0),
            ];
            if (in_array($q->question_type, ['mcq', 'multiple_answer', 'true_false', 'matching', 'ordering'])) {
                $options = $q->getOptions();
                if ($q->shuffle_options) {
                    shuffle($options);
                }
                $qData['options'] = $options;
            }
            $questionData[] = $qData;
        }

        if ($quiz->shuffle_questions) {
            shuffle($questionData);
        }

        $attempt->setRelation('questions', $questionData);
        ActivityLog::log('quiz.started', $this->userId(), $quiz->course_id, 'quiz_attempt', $attempt->getKey());
        $res->created(['attempt' => $attempt->toArray()]);
    }

    public function submit(Request $req, Response $res, string $quizId, string $attemptId): void
    {
        $quiz = Quiz::find((int) $quizId);
        if (!$quiz) $res->notFound('Quiz not found');

        $attempt = QuizAttempt::find((int) $attemptId);
        if (!$attempt || $attempt->quiz_id != $quiz->getKey()) $res->notFound('Attempt not found');
        if ($attempt->isCompleted()) $res->error('ALREADY_COMPLETED', 'Attempt already completed');
        if ($attempt->user_id != $this->userId()) $res->unauthorized('Not your attempt');

        $data = $this->validate($req->body(), [
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.answer' => 'required',
            'answers.*.time_spent' => 'integer',
        ]);

        $result = QuizService::submitAttempt($attempt->getKey(), $data['answers']);
        if (!$result) $res->error('SUBMIT_FAILED', 'Failed to submit attempt');

        $result->setRelation('answers', $result->answers());
        ActivityLog::log('quiz.completed', $this->userId(), $quiz->course_id, 'quiz_attempt', $attempt->getKey(), [
            'score' => $result->score,
            'percentage' => $result->percentage,
        ]);
        $res->success(['attempt' => $result->toArray()]);
    }

    public function results(Request $req, Response $res, string $quizId, string $attemptId): void
    {
        $results = QuizService::getAttemptResults((int) $attemptId);
        if (!$results) $res->notFound('Attempt not found');
        $res->success(['results' => $results]);
    }

    public function remainingTime(Request $req, Response $res, string $quizId, string $attemptId): void
    {
        $remaining = QuizService::getRemainingTime((int) $attemptId);
        if ($remaining === null) $res->error('NO_TIME_LIMIT', 'No time limit for this quiz');
        $res->success(['remaining_seconds' => $remaining]);
    }
}
