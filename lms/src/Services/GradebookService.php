<?php
namespace Lms\Services;

use Lms\Core\Database;
use Lms\Core\Event;
use Lms\Core\Logger;
use Lms\Models\GradeItem;
use Lms\Models\GradebookEntry;
use Lms\Models\Course;
use Lms\Models\Enrollment;

class GradebookService
{
    public static function calculateFinalGrade(int $userId, int $courseId): ?array
    {
        $items = GradeItem::where('course_id', $courseId)->where('is_active', 1)->get();
        if (empty($items)) return null;

        $totalWeight = 0;
        $weightedScore = 0;

        foreach ($items as $item) {
            $entry = GradebookEntry::where('user_id', $userId)
                ->where('grade_item_id', $item->getKey())
                ->first();

            $weight = (float) ($item->weight ?? 1.0);
            $maxPoints = (float) $item->max_points;
            $earnedPoints = $entry ? (float) ($entry->points_earned ?? 0) : 0;

            if ($maxPoints > 0) {
                $percentage = ($earnedPoints / $maxPoints) * 100;
                $weightedScore += $percentage * $weight;
                $totalWeight += $weight;
            }
        }

        if ($totalWeight <= 0) return null;

        $finalPercentage = round($weightedScore / $totalWeight, 2);
        $letterGrade = self::percentageToLetter($finalPercentage);
        $gpa = self::letterToGpa($letterGrade);

        $enrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if ($enrollment) {
            $enrollment->grade = $finalPercentage;
            $enrollment->letter_grade = $letterGrade;
            $enrollment->gpa_value = $gpa;
            $enrollment->save();

            Event::dispatch('grade.calculated', [
                'user_id' => $userId,
                'course_id' => $courseId,
                'percentage' => $finalPercentage,
                'letter_grade' => $letterGrade,
                'gpa' => $gpa,
            ]);
        }

        Logger::info('Final grade calculated', [
            'user_id' => $userId,
            'course_id' => $courseId,
            'percentage' => $finalPercentage,
            'letter' => $letterGrade,
        ]);

        return [
            'percentage' => $finalPercentage,
            'letter_grade' => $letterGrade,
            'gpa' => $gpa,
            'total_weight' => $totalWeight,
            'items_count' => count($items),
        ];
    }

    public static function recalculateCourseGrades(int $courseId): int
    {
        $enrollments = Enrollment::where('course_id', $courseId)
            ->whereIn('status', ['active', 'completed'])
            ->get();

        $count = 0;
        foreach ($enrollments as $enrollment) {
            $result = self::calculateFinalGrade($enrollment->user_id, $courseId);
            if ($result) $count++;
        }

        Logger::info('Course grades recalculated', [
            'course_id' => $courseId,
            'students_updated' => $count,
        ]);

        return $count;
    }

    public static function getStudentGradeSummary(int $userId, int $courseId): array
    {
        $items = GradeItem::where('course_id', $courseId)->where('is_active', 1)->get();
        $grades = [];

        foreach ($items as $item) {
            $entry = GradebookEntry::where('user_id', $userId)
                ->where('grade_item_id', $item->getKey())
                ->first();

            $grades[] = [
                'item_id' => $item->getKey(),
                'item_title' => $item->title,
                'item_type' => $item->item_type,
                'category' => $item->category,
                'max_points' => (float) $item->max_points,
                'weight' => (float) ($item->weight ?? 1.0),
                'points_earned' => $entry ? (float) ($entry->points_earned ?? 0) : null,
                'percentage' => $entry ? $entry->percentage : null,
                'comments' => $entry ? $entry->comments : null,
                'graded_at' => $entry ? $entry->graded_at : null,
            ];
        }

        $finalGrade = self::calculateFinalGrade($userId, $courseId);

        return [
            'grades' => $grades,
            'final' => $finalGrade,
        ];
    }

    public static function getCourseGradebook(int $courseId): array
    {
        $items = GradeItem::where('course_id', $courseId)->where('is_active', 1)->get();
        $enrollments = Enrollment::where('course_id', $courseId)
            ->where('enrollment_type', 'student')
            ->whereIn('status', ['active', 'completed'])
            ->get();

        $results = [];
        foreach ($enrollments as $enrollment) {
            $user = $enrollment->student();
            if (!$user) continue;

            $studentGrades = self::getStudentGradeSummary($user->getKey(), $courseId);
            $results[] = [
                'user_id' => $user->getKey(),
                'name' => $user->first_name . ' ' . $user->last_name,
                'matric_no' => $user->matric_no,
                'grades' => $studentGrades['grades'],
                'final' => $studentGrades['final'],
            ];
        }

        return [
            'items' => $items,
            'students' => $results,
        ];
    }

    public static function percentageToLetter(float $percentage): string
    {
        return match (true) {
            $percentage >= 70 => 'A',
            $percentage >= 60 => 'B',
            $percentage >= 50 => 'C',
            $percentage >= 45 => 'D',
            $percentage >= 40 => 'E',
            default => 'F',
        };
    }

    public static function letterToGpa(string $letter): float
    {
        return match ($letter) {
            'A' => 4.00,
            'B' => 3.50,
            'C' => 3.00,
            'D' => 2.50,
            'E' => 2.00,
            'F' => 0.00,
            default => 0.00,
        };
    }
}
