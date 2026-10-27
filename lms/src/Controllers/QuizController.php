<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\Quiz;
use Lms\Models\QuizAttempt;
use Lms\Models\QuizQuestion;
use Lms\Models\QuizAnswer;
use Lms\Models\ActivityLog;

class QuizController extends Controller
{
    public function index(Request $req, Response $res, string $courseId): void
    {
        $quizzes = Quiz::where('course_id', (int) $courseId)
            ->where('is_visible', 1)
            ->get();
        $res->success(['quizzes' => $quizzes]);
    }

    public function show(Request $req, Response $res, string $id): void
    {
        $quiz = Quiz::find((int) $id);
        if (!$quiz) $res->notFound('Quiz not found');

        $questions = $quiz->questions();
        $userAttempts = $quiz->attemptsByUser($this->userId());

        $quiz->setRelation('question_count', count($questions));
        $quiz->setRelation('total_points', $quiz->totalPoints());
        $quiz->setRelation('attempts', $userAttempts);
        $quiz->setRelation('remaining_attempts', $quiz->remainingAttempts($this->userId()));

        $res->success(['quiz' => $quiz->toArray()]);
    }

    public function store(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'course_id' => 'required|integer|exists:lms_courses,id',
            'module_id' => 'integer|exists:lms_course_modules,id',
            'title' => 'required|string|max:255',
            'description' => 'string',
            'quiz_type' => 'required|in:graded,practice,survey',
            'max_attempts' => 'integer|min:1',
            'time_limit_minutes' => 'integer|min:1',
            'passing_score' => 'numeric|between:0,100',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'show_results_after' => 'in:never,immediately,after_due,after_attempt',
            'show_correct_answers' => 'boolean',
            'due_at' => 'date',
            'available_from' => 'date',
            'available_until' => 'date',
        ]);

        $data['created_by'] = $this->userId();
        $quiz = new Quiz();
        foreach ($data as $key => $value) {
            $quiz->$key = $value;
        }
        $quiz->save();
        $res->created(['quiz' => $quiz->toArray()]);
    }

    public function update(Request $req, Response $res, string $id): void
    {
        $quiz = Quiz::find((int) $id);
        if (!$quiz) $res->notFound('Quiz not found');

        $data = $this->validate($req->body(), [
            'title' => 'string|max:255',
            'description' => 'string',
            'time_limit_minutes' => 'integer|min:1',
            'passing_score' => 'numeric|between:0,100',
            'shuffle_questions' => 'boolean',
            'due_at' => 'date',
        ]);

        foreach ($data as $key => $value) {
            $quiz->$key = $value;
        }
        $quiz->save();
        $res->success(['quiz' => $quiz->toArray()]);
    }

    public function destroy(Request $req, Response $res, string $id): void
    {
        $quiz = Quiz::find((int) $id);
        if (!$quiz) $res->notFound('Quiz not found');
        $quiz->delete();
        $res->success(['message' => 'Quiz deleted']);
    }

    public function addQuestion(Request $req, Response $res, string $quizId): void
    {
        $quiz = Quiz::find((int) $quizId);
        if (!$quiz) $res->notFound('Quiz not found');

        $data = $this->validate($req->body(), [
            'question_type' => 'required|in:mcq,multiple_answer,true_false,short_answer,essay,matching,ordering,fill_blank',
            'question_text' => 'required|string',
            'options' => 'array',
            'correct_answer' => 'string',
            'points' => 'required|numeric|min:0',
        ]);

        $maxOrder = QuizQuestion::where('quiz_id', $quiz->getKey())->max('order_index');
        $data['quiz_id'] = $quiz->getKey();
        $data['order_index'] = ($maxOrder ?? -1) + 1;
        if (isset($data['options']) && is_array($data['options'])) {
            $data['options'] = json_encode($data['options']);
        }

        $question = new QuizQuestion();
        foreach ($data as $key => $value) {
            $question->$key = $value;
        }
        $question->save();
        $res->created(['question' => $question->toArray()]);
    }

    public function startAttempt(Request $req, Response $res, string $id): void
    {
        $quiz = Quiz::find((int) $id);
        if (!$quiz) $res->notFound('Quiz not found');
        if (!$quiz->isAvailable()) $res->error('NOT_AVAILABLE', 'Quiz is not available');

        $remaining = $quiz->remainingAttempts($this->userId());
        if ($remaining <= 0) $res->error('NO_ATTEMPTS', 'No attempts remaining');

        $attemptNumber = $quiz->max_attempts - $remaining + 1;

        $attempt = new QuizAttempt();
        $attempt->quiz_id = $quiz->getKey();
        $attempt->user_id = $this->userId();
        $attempt->attempt_number = $attemptNumber;
        $attempt->status = 'in_progress';
        $attempt->ip_address = $req->ip();
        $attempt->user_agent = $req->header('User-Agent');
        $attempt->save();

        ActivityLog::log('quiz.started', $this->userId(), $quiz->course_id, 'quiz_attempt', $attempt->getKey());
        $res->created(['attempt' => $attempt->toArray()]);
    }

    public function submitAttempt(Request $req, Response $res, string $id, string $attemptId): void
    {
        $quiz = Quiz::find((int) $id);
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

        $questions = $quiz->questions();
        $questionMap = [];
        foreach ($questions as $q) {
            $questionMap[$q->getKey()] = $q;
        }

        foreach ($data['answers'] as $ans) {
            $question = $questionMap[$ans['question_id']] ?? null;
            if (!$question) continue;

            $givenAnswer = is_array($ans['answer']) ? json_encode($ans['answer']) : (string) $ans['answer'];
            $isCorrect = $question->isCorrect($ans['answer']);
            $pointsEarned = $isCorrect ? (float) $question->points : 0;

            $answer = new QuizAnswer();
            $answer->attempt_id = $attempt->getKey();
            $answer->question_id = (int) $ans['question_id'];
            $answer->given_answer = $givenAnswer;
            $answer->is_correct = $isCorrect ? 1 : 0;
            $answer->points_earned = $pointsEarned;
            $answer->points_possible = (float) $question->points;
            $answer->time_spent_seconds = (int) ($ans['time_spent'] ?? 0);
            $answer->save();
        }

        $attempt->complete();
        ActivityLog::log('quiz.completed', $this->userId(), $quiz->course_id, 'quiz_attempt', $attempt->getKey(), [
            'score' => $attempt->score,
            'percentage' => $attempt->percentage,
        ]);

        $attempt->setRelation('answers', $attempt->answers());
        $res->success(['attempt' => $attempt->toArray()]);
    }
}
