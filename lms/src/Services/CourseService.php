<?php
namespace Lms\Services;

use Lms\Core\Database;
use Lms\Core\Event;
use Lms\Core\Logger;
use Lms\Models\Assignment;
use Lms\Models\Course;
use Lms\Models\CourseModule;
use Lms\Models\Lesson;
use Lms\Models\Quiz;

class CourseService
{
    public static function cloneCourse(int $courseId, int $userId, ?array $overrides = null): ?Course
    {
        $original = Course::find($courseId);
        if (!$original) {
            Logger::error('Course not found for cloning', ['course_id' => $courseId]);
            return null;
        }

        $db = Database::connection('lms');

        try {
            $db->beginTransaction();

            $uuid = lms_uuid();
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $overrides['title'] ?? $original->title)) . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

            $stmt = $db->prepare("
                INSERT INTO lms_courses (uuid, code, title, slug, description, short_description, category_id,
                    thumbnail_url, banner_url, level, credits, duration_weeks, prerequisites, learning_outcomes,
                    syllabus, status, is_required, max_students, start_date, end_date, enrollment_start,
                    enrollment_end, created_by, portal_course_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $status = $overrides['status'] ?? 'draft';
            $title = $overrides['title'] ?? $original->title . ' (Copy)';
            $code = $overrides['code'] ?? ($original->code ? $original->code . '-COPY' : null);

            $stmt->execute([
                $uuid,
                $code,
                $title,
                $slug,
                $original->description,
                $original->short_description ?? null,
                $original->category_id ?? null,
                $original->thumbnail_url ?? null,
                $original->banner_url ?? null,
                $original->level ?? null,
                $original->credits ?? 3,
                $original->duration_weeks ?? null,
                $original->prerequisites ?? null,
                $original->learning_outcomes ?? null,
                $original->syllabus ?? null,
                $status,
                $original->is_required ?? 0,
                $original->max_students ?? null,
                $original->start_date ?? null,
                $original->end_date ?? null,
                $original->enrollment_start ?? null,
                $original->enrollment_end ?? null,
                $userId,
                $original->portal_course_id ?? null,
            ]);

            $newCourseId = (int) $db->lastInsertId();

            $modules = CourseModule::where('course_id', $courseId)->orderBy('order_index')->get();
            foreach ($modules as $module) {
                $stmt = $db->prepare("
                    INSERT INTO lms_course_modules (course_id, title, description, order_index, is_visible, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $newCourseId,
                    $module->title,
                    $module->description ?? null,
                    $module->order_index ?? 0,
                    $module->is_visible ?? 1,
                ]);
                $newModuleId = (int) $db->lastInsertId();

                self::cloneLessons($db, $module->id, $newModuleId, $userId);
                self::cloneQuizzes($db, $courseId, $newCourseId, $module->id, $newModuleId, $userId);
                self::cloneAssignments($db, $courseId, $newCourseId, $module->id, $newModuleId, $userId);
            }

            self::cloneOrphanQuizzes($db, $courseId, $newCourseId, $userId);
            self::cloneOrphanAssignments($db, $courseId, $newCourseId, $userId);

            $db->commit();

            Event::dispatch('course.cloned', [
                'original_id' => $courseId,
                'new_course_id' => $newCourseId,
                'user_id' => $userId,
            ]);

            Logger::info('Course cloned', [
                'original_id' => $courseId,
                'new_course_id' => $newCourseId,
                'user_id' => $userId,
            ]);

            return Course::find($newCourseId);
        } catch (\Throwable $e) {
            $db->rollBack();
            Logger::error('Course cloning failed: ' . $e->getMessage(), [
                'course_id' => $courseId,
                'user_id' => $userId,
            ]);
            return null;
        }
    }

    public static function addPrerequisite(int $courseId, int $prerequisiteCourseId): bool
    {
        if ($courseId === $prerequisiteCourseId) return false;

        $course = Course::find($courseId);
        if (!$course) return false;

        $prereqs = json_decode($course->prerequisites ?? '[]', true) ?: [];
        if (in_array($prerequisiteCourseId, $prereqs)) return true;

        $prereqs[] = $prerequisiteCourseId;
        $db = Database::connection('lms');
        $stmt = $db->prepare("UPDATE lms_courses SET prerequisites = ? WHERE id = ?");
        return $stmt->execute([json_encode($prereqs), $courseId]);
    }

    public static function removePrerequisite(int $courseId, int $prerequisiteCourseId): bool
    {
        $course = Course::find($courseId);
        if (!$course) return false;

        $prereqs = json_decode($course->prerequisites ?? '[]', true) ?: [];
        $index = array_search($prerequisiteCourseId, $prereqs);
        if ($index === false) return true;

        array_splice($prereqs, $index, 1);
        $db = Database::connection('lms');
        $stmt = $db->prepare("UPDATE lms_courses SET prerequisites = ? WHERE id = ?");
        return $stmt->execute([json_encode($prereqs), $courseId]);
    }

    public static function getPrerequisites(int $courseId): array
    {
        $course = Course::find($courseId);
        if (!$course) return [];

        $ids = json_decode($course->prerequisites ?? '[]', true) ?: [];
        if (empty($ids)) return [];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db = Database::connection('lms');
        $stmt = $db->prepare("SELECT id, uuid, code, title, credits, level FROM lms_courses WHERE id IN ({$placeholders}) AND deleted_at IS NULL");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    public static function checkPrerequisites(int $courseId, int $userId): array
    {
        $course = Course::find($courseId);
        if (!$course) return ['met' => false, 'missing' => []];

        $prereqIds = json_decode($course->prerequisites ?? '[]', true) ?: [];
        if (empty($prereqIds)) return ['met' => true, 'missing' => []];

        $missing = [];
        $db = Database::connection('lms');

        foreach ($prereqIds as $prereqId) {
            $stmt = $db->prepare("
                SELECT 1 FROM lms_enrollments
                WHERE user_id = ? AND course_id = ? AND enrollment_type = 'student' AND status IN ('active', 'completed')
            ");
            $stmt->execute([$userId, $prereqId]);
            if (!$stmt->fetch()) {
                $stmt2 = $db->prepare("SELECT id, code, title FROM lms_courses WHERE id = ? AND deleted_at IS NULL");
                $stmt2->execute([$prereqId]);
                $prereq = $stmt2->fetch();
                if ($prereq) {
                    $missing[] = $prereq;
                }
            }
        }

        return [
            'met' => empty($missing),
            'missing' => $missing,
        ];
    }

    public static function getSyllabus(int $courseId): ?array
    {
        $course = Course::find($courseId);
        if (!$course) return null;

        $modules = CourseModule::where('course_id', $courseId)->orderBy('order_index')->get();
        $syllabus = [];

        foreach ($modules as $module) {
            $lessons = Lesson::where('module_id', $module->id)->orderBy('order_index')->get();
            $moduleData = [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description ?? null,
                'order_index' => $module->order_index ?? 0,
                'lessons' => [],
            ];

            foreach ($lessons as $lesson) {
                $moduleData['lessons'][] = [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'content_type' => $lesson->content_type ?? 'text',
                    'duration_minutes' => (int) ($lesson->duration_minutes ?? 0),
                    'order_index' => $lesson->order_index ?? 0,
                ];
            }

            $syllabus[] = $moduleData;
        }

        return [
            'course_id' => $courseId,
            'course_title' => $course->title,
            'total_modules' => count($modules),
            'modules' => $syllabus,
        ];
    }

    public static function updateSyllabus(int $courseId, array $syllabus): bool
    {
        $db = Database::connection('lms');
        $stmt = $db->prepare("UPDATE lms_courses SET syllabus = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        return $stmt->execute([json_encode($syllabus), $courseId]);
    }

    public static function addLearningOutcome(int $courseId, string $outcome): bool
    {
        $course = Course::find($courseId);
        if (!$course) return false;

        $outcomes = json_decode($course->learning_outcomes ?? '[]', true) ?: [];
        $outcomes[] = $outcome;

        $db = Database::connection('lms');
        $stmt = $db->prepare("UPDATE lms_courses SET learning_outcomes = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([json_encode($outcomes), $courseId]);
    }

    public static function removeLearningOutcome(int $courseId, int $index): bool
    {
        $course = Course::find($courseId);
        if (!$course) return false;

        $outcomes = json_decode($course->learning_outcomes ?? '[]', true) ?: [];
        if (!isset($outcomes[$index])) return false;

        array_splice($outcomes, $index, 1);

        $db = Database::connection('lms');
        $stmt = $db->prepare("UPDATE lms_courses SET learning_outcomes = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([json_encode($outcomes), $courseId]);
    }

    public static function getLearningOutcomes(int $courseId): array
    {
        $course = Course::find($courseId);
        if (!$course) return [];

        return json_decode($course->learning_outcomes ?? '[]', true) ?: [];
    }

    public static function autoCreateFromAcademic(int $portalCourseId, int $userId): ?int
    {
        try {
            Exeat2::init();
            if (!Exeat2::available()) {
                Logger::error('Exeat2 not available for auto-create');
                return null;
            }

            $portalCourse = Exeat2::getCourse($portalCourseId);
            if (!$portalCourse) {
                Logger::error('Portal course not found', ['portal_course_id' => $portalCourseId]);
                return null;
            }

            $db = Database::connection('lms');
            $stmt = $db->prepare("SELECT id FROM lms_courses WHERE portal_course_id = ? AND deleted_at IS NULL");
            $stmt->execute([$portalCourseId]);
            $existing = $stmt->fetch();
            if ($existing) {
                Logger::info('Course already exists from portal', ['portal_course_id' => $portalCourseId, 'lms_course_id' => $existing['id']]);
                return (int) $existing['id'];
            }

            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $portalCourse['code'] ?? substr($portalCourse['title'] ?? '', 0, 20))) . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

            $stmt = $db->prepare("
                INSERT INTO lms_courses (uuid, code, title, slug, level, credits, status, portal_course_id,
                    department_id, course_study_id, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'published', ?, ?, ?, ?, NOW(), NOW())
            ");
            $uuid = lms_uuid();
            $stmt->execute([
                $uuid,
                $portalCourse['code'] ?? '',
                $portalCourse['title'] ?? '',
                $slug,
                $portalCourse['level_name'] ?? $portalCourse['level'] ?? '',
                $portalCourse['credit_load'] ?? 3,
                $portalCourseId,
                $portalCourse['department_id'] ?? null,
                $portalCourse['course_study_id'] ?? null,
                $userId,
            ]);

            $newId = (int) $db->lastInsertId();

            Event::dispatch('course.created_from_academic', [
                'course_id' => $newId,
                'portal_course_id' => $portalCourseId,
                'user_id' => $userId,
            ]);

            Logger::info('Course auto-created from academic', [
                'course_id' => $newId,
                'portal_course_id' => $portalCourseId,
            ]);

            return $newId;
        } catch (\Throwable $e) {
            Logger::error('Course auto-create failed: ' . $e->getMessage(), [
                'portal_course_id' => $portalCourseId,
            ]);
            return null;
        }
    }

    private static function cloneLessons(\PDO $db, int $originalModuleId, int $newModuleId, int $userId): void
    {
        $lessons = Lesson::where('module_id', $originalModuleId)->orderBy('order_index')->get();
        foreach ($lessons as $lesson) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $lesson->title)) . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
            $stmt = $db->prepare("
                INSERT INTO lms_lessons (module_id, title, slug, content_type, content, video_url, video_duration,
                    file_url, duration_minutes, order_index, is_visible, is_required, allow_download, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $newModuleId,
                $lesson->title,
                $slug,
                $lesson->content_type ?? 'text',
                $lesson->content ?? null,
                $lesson->video_url ?? null,
                $lesson->video_duration ?? null,
                $lesson->file_url ?? null,
                $lesson->duration_minutes ?? 0,
                $lesson->order_index ?? 0,
                $lesson->is_visible ?? 1,
                $lesson->is_required ?? 1,
                $lesson->allow_download ?? 1,
                $userId,
            ]);
        }
    }

    private static function cloneQuizzes(\PDO $db, int $originalCourseId, int $newCourseId, int $originalModuleId, int $newModuleId, int $userId): void
    {
        $quizzes = Quiz::where('course_id', $originalCourseId)->where('module_id', $originalModuleId)->get();
        foreach ($quizzes as $quiz) {
            $stmt = $db->prepare("
                INSERT INTO lms_quizzes (course_id, module_id, title, description, instructions, quiz_type,
                    max_attempts, time_limit_minutes, passing_score, shuffle_questions, shuffle_options,
                    show_results_after, show_correct_answers, show_points, random_question_count,
                    due_at, available_from, available_until, is_visible, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $newCourseId,
                $newModuleId,
                $quiz->title,
                $quiz->description ?? null,
                $quiz->instructions ?? null,
                $quiz->quiz_type ?? 'graded',
                $quiz->max_attempts ?? 1,
                $quiz->time_limit_minutes ?? null,
                $quiz->passing_score ?? null,
                $quiz->shuffle_questions ?? 0,
                $quiz->shuffle_options ?? 0,
                $quiz->show_results_after ?? 'immediately',
                $quiz->show_correct_answers ?? 1,
                $quiz->show_points ?? 1,
                $quiz->random_question_count ?? null,
                $quiz->due_at ?? null,
                $quiz->available_from ?? null,
                $quiz->available_until ?? null,
                $quiz->is_visible ?? 1,
                $userId,
            ]);
            $newQuizId = (int) $db->lastInsertId();

            self::cloneQuizQuestions($db, $quiz->id, $newQuizId);
        }
    }

    private static function cloneQuizQuestions(\PDO $db, int $originalQuizId, int $newQuizId): void
    {
        $stmt = $db->prepare("SELECT * FROM lms_quiz_questions WHERE quiz_id = ? ORDER BY order_index");
        $stmt->execute([$originalQuizId]);
        $questions = $stmt->fetchAll();

        foreach ($questions as $question) {
            $stmt2 = $db->prepare("
                INSERT INTO lms_quiz_questions (quiz_id, question_type, question_text, question_image,
                    options, correct_answer, points, feedback_correct, feedback_incorrect, order_index, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt2->execute([
                $newQuizId,
                $question['question_type'],
                $question['question_text'],
                $question['question_image'] ?? null,
                $question['options'] ?? null,
                $question['correct_answer'] ?? null,
                $question['points'],
                $question['feedback_correct'] ?? null,
                $question['feedback_incorrect'] ?? null,
                $question['order_index'],
            ]);
        }
    }

    private static function cloneAssignments(\PDO $db, int $originalCourseId, int $newCourseId, int $originalModuleId, int $newModuleId, int $userId): void
    {
        $assignments = Assignment::where('course_id', $originalCourseId)->where('module_id', $originalModuleId)->get();
        foreach ($assignments as $assignment) {
            $stmt = $db->prepare("
                INSERT INTO lms_assignments (course_id, module_id, title, description, instructions,
                    max_points, passing_points, allow_late_submission, late_penalty_percent, max_attempts,
                    due_at, available_from, available_until, submission_type, allowed_extensions,
                    max_file_size, allow_group_submission, group_size, is_visible, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $newCourseId,
                $newModuleId,
                $assignment->title,
                $assignment->description ?? null,
                $assignment->instructions ?? null,
                $assignment->max_points ?? 100.00,
                $assignment->passing_points ?? null,
                $assignment->allow_late_submission ?? 0,
                $assignment->late_penalty_percent ?? 0.00,
                $assignment->max_attempts ?? 1,
                $assignment->due_at ?? null,
                $assignment->available_from ?? null,
                $assignment->available_until ?? null,
                $assignment->submission_type ?? 'file',
                $assignment->allowed_extensions ?? null,
                $assignment->max_file_size ?? 52428800,
                $assignment->allow_group_submission ?? 0,
                $assignment->group_size ?? null,
                $assignment->is_visible ?? 1,
                $userId,
            ]);
        }
    }

    private static function cloneOrphanQuizzes(\PDO $db, int $originalCourseId, int $newCourseId, int $userId): void
    {
        $quizzes = Quiz::where('course_id', $originalCourseId)->whereNull('module_id')->get();
        foreach ($quizzes as $quiz) {
            $stmt = $db->prepare("
                INSERT INTO lms_quizzes (course_id, module_id, title, description, instructions, quiz_type,
                    max_attempts, time_limit_minutes, passing_score, shuffle_questions, shuffle_options,
                    show_results_after, show_correct_answers, show_points, random_question_count,
                    due_at, available_from, available_until, is_visible, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $newCourseId,
                null,
                $quiz->title,
                $quiz->description ?? null,
                $quiz->instructions ?? null,
                $quiz->quiz_type ?? 'graded',
                $quiz->max_attempts ?? 1,
                $quiz->time_limit_minutes ?? null,
                $quiz->passing_score ?? null,
                $quiz->shuffle_questions ?? 0,
                $quiz->shuffle_options ?? 0,
                $quiz->show_results_after ?? 'immediately',
                $quiz->show_correct_answers ?? 1,
                $quiz->show_points ?? 1,
                $quiz->random_question_count ?? null,
                $quiz->due_at ?? null,
                $quiz->available_from ?? null,
                $quiz->available_until ?? null,
                $quiz->is_visible ?? 1,
                $userId,
            ]);
            $newQuizId = (int) $db->lastInsertId();
            self::cloneQuizQuestions($db, $quiz->id, $newQuizId);
        }
    }

    private static function cloneOrphanAssignments(\PDO $db, int $originalCourseId, int $newCourseId, int $userId): void
    {
        $assignments = Assignment::where('course_id', $originalCourseId)->whereNull('module_id')->get();
        foreach ($assignments as $assignment) {
            $stmt = $db->prepare("
                INSERT INTO lms_assignments (course_id, module_id, title, description, instructions,
                    max_points, passing_points, allow_late_submission, late_penalty_percent, max_attempts,
                    due_at, available_from, available_until, submission_type, allowed_extensions,
                    max_file_size, allow_group_submission, group_size, is_visible, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $newCourseId,
                null,
                $assignment->title,
                $assignment->description ?? null,
                $assignment->instructions ?? null,
                $assignment->max_points ?? 100.00,
                $assignment->passing_points ?? null,
                $assignment->allow_late_submission ?? 0,
                $assignment->late_penalty_percent ?? 0.00,
                $assignment->max_attempts ?? 1,
                $assignment->due_at ?? null,
                $assignment->available_from ?? null,
                $assignment->available_until ?? null,
                $assignment->submission_type ?? 'file',
                $assignment->allowed_extensions ?? null,
                $assignment->max_file_size ?? 52428800,
                $assignment->allow_group_submission ?? 0,
                $assignment->group_size ?? null,
                $assignment->is_visible ?? 1,
                $userId,
            ]);
        }
    }
}
