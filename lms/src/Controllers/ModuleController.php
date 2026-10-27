<?php
namespace Lms\Controllers;

use Lms\Core\Controller;
use Lms\Core\Request;
use Lms\Core\Response;
use Lms\Models\CourseModule;
use Lms\Models\Course;
use Lms\Models\ActivityLog;

class ModuleController extends Controller
{
    public function index(Request $req, Response $res, string $courseId): void
    {
        $course = Course::findBy('uuid', $courseId) ?? Course::find((int) $courseId);
        if (!$course) $res->notFound('Course not found');

        $modules = CourseModule::where('course_id', $course->getKey())
            ->orderBy('order_index')
            ->get();
        $res->success(['modules' => $modules]);
    }

    public function show(Request $req, Response $res, string $id): void
    {
        $module = CourseModule::find((int) $id);
        if (!$module) $res->notFound('Module not found');
        $module->setRelation('lessons', $module->lessons());
        $res->success(['module' => $module->toArray()]);
    }

    public function store(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'course_id' => 'required|integer|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'description' => 'string',
        ]);

        $maxOrder = CourseModule::where('course_id', $data['course_id'])->max('order_index');
        $data['order_index'] = ($maxOrder ?? -1) + 1;

        $module = new CourseModule();
        foreach ($data as $key => $value) {
            $module->$key = $value;
        }
        $module->save();

        ActivityLog::log('module.created', $this->userId(), $data['course_id'], 'module', $module->getKey());
        $res->created(['module' => $module->toArray()]);
    }

    public function update(Request $req, Response $res, string $id): void
    {
        $module = CourseModule::find((int) $id);
        if (!$module) $res->notFound('Module not found');

        $data = $this->validate($req->body(), [
            'title' => 'string|max:255',
            'description' => 'string',
            'order_index' => 'integer',
            'is_visible' => 'boolean',
        ]);

        foreach ($data as $key => $value) {
            $module->$key = $value;
        }
        $module->save();
        $res->success(['module' => $module->toArray()]);
    }

    public function destroy(Request $req, Response $res, string $id): void
    {
        $module = CourseModule::find((int) $id);
        if (!$module) $res->notFound('Module not found');
        $module->delete();
        $res->success(['message' => 'Module deleted']);
    }

    public function reorder(Request $req, Response $res): void
    {
        $data = $this->validate($req->body(), [
            'course_id' => 'required|integer',
            'module_ids' => 'required|array',
        ]);

        $db = CourseModule::db();
        $stmt = $db->prepare("UPDATE lms_course_modules SET order_index = ? WHERE id = ? AND course_id = ?");
        foreach ($data['module_ids'] as $index => $id) {
            $stmt->execute([$index, $id, $data['course_id']]);
        }
        $res->success(['message' => 'Modules reordered']);
    }
}
