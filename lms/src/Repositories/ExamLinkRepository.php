<?php
namespace Lms\Repositories;

use Lms\Core\Repository;
use Lms\Models\ExamLink;

class ExamLinkRepository extends Repository
{
    protected function getTable(): string { return 'exam_links'; }
    protected function getModelClass(): string { return ExamLink::class; }

    public function getByCourse(int $courseId): array
    {
        return ExamLink::where('course_id', '=', $courseId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function getByQuiz(int $quizId): ?ExamLink
    {
        return ExamLink::findBy('lms_quiz_id', $quizId);
    }

    public function getByPortalExamId(string $portalExamId): ?ExamLink
    {
        return ExamLink::findBy('portal_exam_id', $portalExamId);
    }
}
