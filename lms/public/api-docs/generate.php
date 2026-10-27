<?php
// ─── Tags ───────────────────────────────────────────────────────────────────
$tags = [
    ['name' => 'Authentication', 'description' => 'Auth, registration, profile'],
    ['name' => 'Dashboard', 'description' => 'Student, instructor, admin dashboards'],
    ['name' => 'Courses', 'description' => 'Course CRUD, categories'],
    ['name' => 'Modules', 'description' => 'Course module CRUD and reorder'],
    ['name' => 'Lessons', 'description' => 'Lesson CRUD, progress, completion'],
    ['name' => 'Enrollments', 'description' => 'Enroll, unenroll, my courses'],
    ['name' => 'Assignments', 'description' => 'Assignment CRUD, submit, grade'],
    ['name' => 'Quizzes', 'description' => 'Quiz CRUD, questions, attempts'],
    ['name' => 'Discussions', 'description' => 'Discussion threads and replies'],
    ['name' => 'Messages / Notifications', 'description' => 'Conversations, messages, notifications'],
    ['name' => 'Gradebook', 'description' => 'Grade items and entries'],
    ['name' => 'Content', 'description' => 'Content library, uploads, attachments'],
    ['name' => 'Analytics', 'description' => 'Platform and user analytics'],
    ['name' => 'Academic', 'description' => 'Academic structure (exeat2 integration)'],
    ['name' => 'Exam Bridge', 'description' => 'Portal exam linking, sync, SSO'],
    ['name' => 'Admin', 'description' => 'Admin user management, settings, features'],
    ['name' => 'Sync', 'description' => 'Portal data synchronization'],
];

// ─── Parameters ─────────────────────────────────────────────────────────────
$pageParam = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1, 'minimum' => 1], 'description' => 'Page number'];
$perPageParam = ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100], 'description' => 'Items per page'];

// ─── Schemas ────────────────────────────────────────────────────────────────
$S = [];
$ref = fn($n) => ['$ref' => "#/components/schemas/$n"];
$obj = fn($p) => ['type' => 'object', 'properties' => $p];
$str = fn() => ['type' => 'string'];
$int = fn() => ['type' => 'integer'];
$bool = fn() => ['type' => 'boolean'];
$num = fn() => ['type' => 'number'];
$arr = fn($i) => ['type' => 'array', 'items' => $i];
$dt = fn() => ['type' => 'string', 'format' => 'date-time'];
$date = fn() => ['type' => 'string', 'format' => 'date'];
$nullable = fn($s) => $s + ['nullable' => true];

// ─── Response builders ──────────────────────────────────────────────────────
$respObj = fn($props) => ['description' => 'OK', 'content' => ['application/json' => ['schema' => $obj(['success' => $bool(), 'data' => $obj($props)])]]];
$respObjMeta = fn($d, $m) => ['description' => 'OK', 'content' => ['application/json' => ['schema' => $obj(['success' => $bool(), 'data' => $d, 'meta' => $m])]]];
$respErr = fn($code, $msg) => ['description' => $msg, 'content' => ['application/json' => ['schema' => $obj(['success' => $bool(), 'error' => $obj(['code' => $str() + ['example' => $code], 'message' => $str() + ['example' => $msg]])])]]];

$unauthorized = ['description' => 'Missing or invalid authentication', 'content' => ['application/json' => ['schema' => $obj(['success' => $bool(), 'error' => $obj(['code' => $str() + ['example' => 'AUTH_ERROR'], 'message' => $str()])])]]];
$forbidden = ['description' => 'Insufficient permissions', 'content' => ['application/json' => ['schema' => $obj(['success' => $bool(), 'error' => $obj(['code' => $str() + ['example' => 'FORBIDDEN'], 'message' => $str()])])]]];
$notFound = ['description' => 'Resource not found', 'content' => ['application/json' => ['schema' => $obj(['success' => $bool(), 'error' => $obj(['code' => $str() + ['example' => 'NOT_FOUND'], 'message' => $str()])])]]];
$validationError = ['description' => 'Validation failed', 'content' => ['application/json' => ['schema' => $obj(['success' => $bool(), 'error' => $obj(['code' => $str() + ['example' => 'VALIDATION_ERROR'], 'message' => $str(), 'details' => ['type' => 'object', 'additionalProperties' => $str()] + ['example' => ['field' => 'error message']]])])]]];
$conflict = ['description' => 'Resource conflict', 'content' => ['application/json' => ['schema' => $obj(['success' => $bool(), 'error' => $obj(['code' => $str() + ['example' => 'CONFLICT'], 'message' => $str()])])]]];
$authSuccess = ['description' => 'Authentication successful', 'content' => ['application/json' => ['schema' => $obj(['success' => $bool(), 'data' => $obj(['user' => $ref('User'), 'token' => $str(), 'refresh_token' => $str()])])]]];
$successMessage = ['description' => 'Success', 'content' => ['application/json' => ['schema' => $obj(['success' => $bool(), 'data' => $obj(['message' => $str()])])]]];

$S['ApiResponse'] = $obj(['success' => $bool(), 'data' => $obj([]), 'meta' => $ref('Pagination'), 'error' => $ref('Error')]);
$S['Pagination'] = $obj(['page' => $int(), 'per_page' => $int(), 'total' => $int(), 'total_pages' => $int()]);
$S['Error'] = $obj(['code' => $str(), 'message' => $str(), 'details' => ['type' => 'object', 'additionalProperties' => true]]);

$S['User'] = $obj([
    'id' => $int(), 'uuid' => $str(), 'first_name' => $str(), 'last_name' => $str(),
    'email' => ['type' => 'string', 'format' => 'email'], 'phone' => $str(),
    'is_active' => $bool(), 'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'suspended']],
    'portal_user_id' => $nullable($int()), 'portal_user_type' => $nullable($str()),
    'matric_no' => $str(), 'department' => $str(), 'faculty' => $str(),
    'course_study' => $str(), 'current_level' => $str(),
    'last_login_at' => $nullable($dt()), 'last_login_ip' => $str(),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['Course'] = $obj([
    'id' => $int(), 'uuid' => $str(), 'code' => $str(), 'title' => $str(), 'slug' => $str(),
    'description' => $str(), 'short_description' => $str(), 'category_id' => $nullable($int()),
    'level' => $str(), 'credits' => $int(), 'duration_weeks' => $int(),
    'status' => ['type' => 'string', 'enum' => ['draft', 'published', 'archived']],
    'max_students' => $nullable($int()),
    'start_date' => $nullable($date()), 'end_date' => $nullable($date()),
    'portal_course_id' => $nullable($int()),
    'created_by' => $int(), 'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['CourseCategory'] = $obj([
    'id' => $int(), 'name' => $str(), 'slug' => $str(),
    'parent_id' => $nullable($int()), 'sort_order' => $int(),
]);

$S['Module'] = $obj([
    'id' => $int(), 'course_id' => $int(), 'title' => $str(), 'description' => $str(),
    'order_index' => $int(), 'is_visible' => $bool(),
    'lessons' => $arr($ref('Lesson')), 'lesson_count' => $int(),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['Lesson'] = $obj([
    'id' => $int(), 'module_id' => $int(), 'title' => $str(), 'slug' => $str(),
    'content_type' => ['type' => 'string', 'enum' => ['text', 'video', 'h5p', 'scorm', 'pdf', 'quiz', 'assignment', 'discussion']],
    'content' => $str(), 'video_url' => $str(), 'video_duration' => $int(),
    'duration_minutes' => $int(), 'order_index' => $int(), 'is_visible' => $bool(),
    'is_required' => $bool(), 'allow_download' => $bool(), 'created_by' => $int(),
    'attachments' => $arr($ref('Attachment')),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['LessonProgress'] = $obj([
    'id' => $int(), 'user_id' => $int(), 'lesson_id' => $int(),
    'status' => ['type' => 'string', 'enum' => ['not_started', 'in_progress', 'completed']],
    'progress_percent' => $int(), 'time_spent_seconds' => $int(),
    'completed_at' => $nullable($dt()), 'last_accessed_at' => $dt(),
]);

$S['Attachment'] = $obj([
    'id' => $int(), 'lesson_id' => $int(), 'title' => $str(),
    'file_url' => $str(), 'file_size' => $int(), 'mime_type' => $str(), 'sort_order' => $int(),
]);

$S['Enrollment'] = $obj([
    'id' => $int(), 'user_id' => $int(), 'course_id' => $int(),
    'enrollment_type' => ['type' => 'string', 'enum' => ['student', 'instructor', 'ta']],
    'status' => ['type' => 'string', 'enum' => ['active', 'completed', 'dropped']],
    'progress_percent' => $num(), 'grade' => $str(), 'letter_grade' => $str(),
    'enrolled_at' => $dt(), 'completed_at' => $nullable($dt()), 'dropped_at' => $nullable($dt()),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['Assignment'] = $obj([
    'id' => $int(), 'course_id' => $int(), 'module_id' => $nullable($int()),
    'title' => $str(), 'description' => $str(), 'instructions' => $str(),
    'max_points' => $num(), 'passing_points' => $num(),
    'submission_type' => ['type' => 'string', 'enum' => ['file', 'text', 'url', 'online_text']],
    'allow_late_submission' => $bool(), 'late_penalty_percent' => $num(), 'max_attempts' => $int(),
    'is_visible' => $bool(), 'due_at' => $nullable($dt()),
    'available_from' => $nullable($dt()), 'available_until' => $nullable($dt()),
    'created_by' => $int(), 'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['Submission'] = $obj([
    'id' => $int(), 'assignment_id' => $int(), 'user_id' => $int(), 'attempt_number' => $int(),
    'content' => $str(), 'file_url' => $str(), 'is_late' => $bool(),
    'status' => ['type' => 'string', 'enum' => ['submitted', 'graded', 'returned']],
    'grade' => $nullable($num()), 'feedback' => $nullable($str()),
    'graded_by' => $nullable($int()), 'graded_at' => $nullable($dt()),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['Quiz'] = $obj([
    'id' => $int(), 'course_id' => $int(), 'module_id' => $nullable($int()),
    'title' => $str(), 'description' => $str(),
    'quiz_type' => ['type' => 'string', 'enum' => ['graded', 'practice', 'survey']],
    'max_attempts' => $int(), 'time_limit_minutes' => $nullable($int()),
    'passing_score' => $nullable($num()), 'shuffle_questions' => $bool(),
    'shuffle_options' => $bool(),
    'show_results_after' => ['type' => 'string', 'enum' => ['never', 'immediately', 'after_due', 'after_attempt']],
    'show_correct_answers' => $bool(), 'is_visible' => $bool(),
    'due_at' => $nullable($dt()), 'available_from' => $nullable($dt()), 'available_until' => $nullable($dt()),
    'created_by' => $int(),
    'question_count' => $int(), 'total_points' => $num(), 'remaining_attempts' => $int(),
    'attempts' => $arr($ref('QuizAttempt')),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['QuizQuestion'] = $obj([
    'id' => $int(), 'quiz_id' => $int(),
    'question_type' => ['type' => 'string', 'enum' => ['mcq', 'multiple_answer', 'true_false', 'short_answer', 'essay', 'matching', 'ordering', 'fill_blank']],
    'question_text' => $str(), 'options' => $arr($str()), 'correct_answer' => $str(),
    'points' => $num(), 'order_index' => $int(),
]);

$S['QuizAttempt'] = $obj([
    'id' => $int(), 'quiz_id' => $int(), 'user_id' => $int(), 'attempt_number' => $int(),
    'status' => ['type' => 'string', 'enum' => ['in_progress', 'completed']],
    'score' => $num(), 'max_score' => $num(), 'percentage' => $num(),
    'passed' => $nullable($bool()), 'time_taken_seconds' => $int(),
    'ip_address' => $str(), 'user_agent' => $str(),
    'started_at' => $dt(), 'finished_at' => $nullable($dt()),
    'answers' => $arr($ref('QuizAnswer')),
]);

$S['QuizAnswer'] = $obj([
    'id' => $int(), 'attempt_id' => $int(), 'question_id' => $int(),
    'given_answer' => $str(), 'is_correct' => $bool(),
    'points_earned' => $num(), 'points_possible' => $num(), 'time_spent_seconds' => $int(),
]);

$S['GradeItem'] = $obj([
    'id' => $int(), 'course_id' => $int(),
    'item_type' => ['type' => 'string', 'enum' => ['assignment', 'quiz', 'participation', 'manual', 'final_exam', 'lesson_completion']],
    'item_id' => $nullable($int()), 'title' => $str(), 'max_points' => $num(),
    'weight' => $num(), 'category' => $str(), 'is_active' => $bool(), 'average_score' => $num(),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['GradebookEntry'] = $obj([
    'id' => $int(), 'user_id' => $int(), 'course_id' => $int(), 'grade_item_id' => $int(),
    'points_earned' => $nullable($num()), 'points_possible' => $nullable($num()),
    'percentage' => $nullable($num()), 'comments' => $str(),
    'graded_by' => $nullable($int()), 'graded_at' => $nullable($dt()),
    'item_title' => $str(), 'max_points' => $num(),
]);

$S['DiscussionThread'] = $obj([
    'id' => $int(), 'course_id' => $int(), 'title' => $str(), 'content' => $str(),
    'is_pinned' => $bool(), 'is_closed' => $bool(), 'is_announcement' => $bool(), 'is_visible' => $bool(),
    'created_by' => $int(), 'author' => $ref('User'),
    'replies' => $arr($ref('DiscussionReply')), 'reply_count' => $int(),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['DiscussionReply'] = $obj([
    'id' => $int(), 'thread_id' => $int(), 'parent_id' => $nullable($int()),
    'content' => $str(), 'is_solution' => $bool(), 'created_by' => $int(),
    'author' => $ref('User'), 'children' => $arr($ref('DiscussionReply')),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['Conversation'] = $obj([
    'id' => $int(), 'type' => ['type' => 'string', 'enum' => ['direct', 'group']],
    'subject' => $nullable($str()), 'created_by' => $int(),
    'last_message' => $str(), 'last_message_at' => $dt(), 'last_read_at' => $dt(),
    'participants' => $arr($ref('User')), 'created_at' => $dt(),
]);

$S['Message'] = $obj([
    'id' => $int(), 'conversation_id' => $int(), 'sender_id' => $int(),
    'sender' => $ref('User'), 'message' => $str(),
    'message_type' => ['type' => 'string', 'enum' => ['text', 'image', 'file', 'notification']],
    'created_at' => $dt(),
]);

$S['Notification'] = $obj([
    'id' => $int(), 'user_id' => $int(), 'type' => $str(), 'title' => $str(),
    'message' => $str(), 'data' => $obj([]), 'link' => $str(),
    'is_read' => $bool(), 'read_at' => $nullable($dt()), 'created_at' => $dt(),
]);

$S['ContentLibrary'] = $obj([
    'id' => $int(), 'course_id' => $nullable($int()), 'uploader_id' => $int(),
    'uploader' => $ref('User'), 'title' => $str(), 'file_type' => $str(),
    'file_url' => $str(), 'file_size' => $int(), 'mime_type' => $str(),
    'description' => $str(), 'is_public' => $bool(),
    'view_count' => $int(), 'download_count' => $int(), 'tags' => $arr($str()),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['ExamLink'] = $obj([
    'id' => $int(), 'lms_quiz_id' => $nullable($int()), 'portal_exam_id' => $int(),
    'course_id' => $int(), 'module_id' => $nullable($int()), 'sync_status' => $str(),
    'last_synced_at' => $nullable($dt()), 'quiz_title' => $str(),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['ExamSyncLog'] = $obj([
    'id' => $int(),
    'direction' => ['type' => 'string', 'enum' => ['lms_to_portal', 'portal_to_lms']],
    'entity_type' => $str(), 'entity_id' => $nullable($int()), 'action' => $str(),
    'request_payload' => $obj([]), 'response_payload' => $obj([]),
    'status_code' => $nullable($int()),
    'status' => ['type' => 'string', 'enum' => ['pending', 'success', 'failed']],
    'error_message' => $nullable($str()),
    'created_at' => $dt(), 'completed_at' => $nullable($dt()),
]);

$S['ActivityLog'] = $obj([
    'id' => $int(), 'user_id' => $nullable($int()), 'course_id' => $nullable($int()),
    'action' => $str(), 'entity_type' => $nullable($str()), 'entity_id' => $nullable($int()),
    'metadata' => $obj([]), 'ip_address' => $str(), 'user_agent' => $str(),
    'session_id' => $str(), 'duration_ms' => $int(), 'created_at' => $dt(),
]);

$S['FeatureFlag'] = $obj([
    'id' => $int(), 'feature_name' => $str(), 'is_enabled' => $bool(),
    'rules' => $nullable($obj([])), 'created_at' => $dt(), 'updated_at' => $dt(),
]);

$S['Permission'] = $obj([
    'id' => $int(), 'name' => $str(), 'slug' => $str(), 'perm_group' => $str(), 'description' => $str(),
]);

$S['SystemSetting'] = $obj([
    'id' => $int(), 'setting_key' => $str(), 'setting_value' => $str(),
    'setting_group' => $str(), 'setting_type' => $str(),
    'created_at' => $dt(), 'updated_at' => $dt(),
]);

// ─── Response specs ─────────────────────────────────────────────────────────
$ok = fn($d) => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => $d]]]]];
$okMsg = ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]]]]]]];
$created = fn($d) => ['description' => 'Created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => $d]]]]];
$R = [
    '200' => $okMsg, '201' => $okMsg,
    '401' => ['$ref' => '#/components/responses/Unauthorized'],
    '403' => ['$ref' => '#/components/responses/Forbidden'],
    '404' => ['$ref' => '#/components/responses/NotFound'],
    '422' => ['$ref' => '#/components/responses/ValidationError'],
    '409' => ['$ref' => '#/components/responses/Conflict'],
    '503' => ['description' => 'Service unavailable'],
];

// ─── Build paths ─────────────────────────────────────────────────────────────
$p = [];

// Helper to create a route spec
$route = function($tag, $summary, $body = null, $extra = null) use ($R) {
    $spec = ['tags' => [$tag], 'summary' => $summary, 'security' => [['BearerAuth' => []]]];
    if ($body) {
        $spec['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $body]]];
    }
    $spec['responses'] = [];
    if ($extra) {
        $spec = array_merge($spec, $extra);
    }
    return $spec;
};

// ─── Authentication ─────────────────────────────────────────────────────────
// These are public (no security)
$p['/auth/register']['post'] = [
    'tags' => ['Authentication'], 'summary' => 'Register a new user via portal',
    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['login', 'password'], 'properties' => ['login' => ['type' => 'string'], 'password' => ['type' => 'string', 'format' => 'password'], 'type' => ['type' => 'string', 'enum' => ['student', 'staff']]]]]]],
    'responses' => ['201' => ['$ref' => '#/components/responses/AuthSuccess'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '422' => ['$ref' => '#/components/responses/ValidationError']],
];
$p['/auth/login']['post'] = [
    'tags' => ['Authentication'], 'summary' => 'Authenticate user via portal and receive JWT',
    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['login', 'password'], 'properties' => ['login' => ['type' => 'string'], 'password' => ['type' => 'string', 'format' => 'password'], 'type' => ['type' => 'string', 'enum' => ['student', 'staff']]]]]]],
    'responses' => ['200' => ['$ref' => '#/components/responses/AuthSuccess'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '422' => ['$ref' => '#/components/responses/ValidationError']],
];
$p['/auth/refresh']['post'] = [
    'tags' => ['Authentication'], 'summary' => 'Refresh expired JWT using refresh token',
    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['refresh_token'], 'properties' => ['refresh_token' => ['type' => 'string']]]]]],
    'responses' => ['200' => ['description' => 'New tokens issued', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['token' => ['type' => 'string'], 'refresh_token' => ['type' => 'string']]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized']],
];
$p['/auth/logout']['post'] = ['tags' => ['Authentication'], 'summary' => 'Logout and revoke refresh tokens', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized']]];
$p['/auth/profile']['get'] = ['tags' => ['Authentication'], 'summary' => 'Get authenticated user profile', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => ['description' => 'User profile', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['user' => $ref('User')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized']]];
$p['/auth/profile']['put'] = ['tags' => ['Authentication'], 'summary' => 'Update profile fields', 'security' => [['BearerAuth' => []]], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['phone' => ['type' => 'string', 'maxLength' => 30], 'timezone' => ['type' => 'string', 'maxLength' => 50], 'locale' => ['type' => 'string', 'maxLength' => 10]]]]]], 'responses' => ['200' => ['description' => 'Profile updated', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['user' => $ref('User'), 'message' => ['type' => 'string']]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

// ─── Dashboard ──────────────────────────────────────────────────────────────
$dashboardSpec = function($summary, $props) { return ['tags' => ['Dashboard'], 'summary' => $summary, 'security' => [['BearerAuth' => []]], 'responses' => ['200' => ['description' => 'Dashboard data', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => $props]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized']]; };
$p['/dashboard/student']['get'] = $dashboardSpec('Student dashboard with enrollments and activity', [
    'active_enrollments' => $int(), 'completed_courses' => $int(), 'unread_notifications' => $int(),
    'recent_activity' => $arr($ref('ActivityLog')),
]);
$p['/dashboard/instructor']['get'] = $dashboardSpec('Instructor dashboard with stats', [
    'stats' => $obj(['total_courses' => $int(), 'total_enrollments' => $int(), 'active_enrollments' => $int()]),
    'recent_courses' => $arr($ref('Course')),
]);
$p['/dashboard/admin']['get'] = $dashboardSpec('Admin dashboard with platform stats', [
    'total_users' => $int(), 'total_courses' => $int(), 'total_enrollments' => $int(), 'active_enrollments' => $int(),
    'recent_activity' => $arr($ref('ActivityLog')),
]);

// ─── Categories ─────────────────────────────────────────────────────────────
$p['/categories']['get'] = ['tags' => ['Courses'], 'summary' => 'List course categories', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => $ok($obj(['categories' => $arr($ref('CourseCategory'))])), '401' => ['$ref' => '#/components/responses/Unauthorized']]];

// ─── Course routes helper ───────────────────────────────────────────────────
$cid = [['name' => 'courseId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]];
$id = [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]];

$p['/courses']['get'] = ['tags' => ['Courses'], 'summary' => 'Paginated course list with search and filters', 'security' => [['BearerAuth' => []]], 'parameters' => [$pageParam, $perPageParam, ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']], ['name' => 'category_id', 'in' => 'query', 'schema' => ['type' => 'integer']], ['name' => 'level', 'in' => 'query', 'schema' => ['type' => 'string']]], 'responses' => ['200' => ['description' => 'Paginated course list', 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/ApiResponse'], ['type' => 'object', 'properties' => ['data' => $arr($ref('Course')), 'meta' => $ref('Pagination')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized']]];
$p['/courses']['post'] = ['tags' => ['Courses'], 'summary' => 'Create a new course', 'security' => [['BearerAuth' => []]], 'parameters' => [], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['title'], 'properties' => ['code' => $str(), 'title' => $str(), 'description' => $str(), 'short_description' => $str(), 'category_id' => $int(), 'level' => $str(), 'credits' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12], 'duration_weeks' => $int(), 'status' => ['type' => 'string', 'enum' => ['draft', 'published', 'archived']]]]]]], 'responses' => ['201' => ['description' => 'Course created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['course' => $ref('Course')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/courses/{id}']['get'] = ['tags' => ['Courses'], 'summary' => 'Get course details with modules, instructors, category', 'security' => [['BearerAuth' => []]], 'parameters' => $id, 'responses' => ['200' => $ok($obj(['course' => $ref('Course')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/courses/{id}']['put'] = ['tags' => ['Courses'], 'summary' => 'Update course fields', 'security' => [['BearerAuth' => []]], 'parameters' => $id, 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['title' => $str(), 'description' => $str(), 'short_description' => $str(), 'category_id' => $int(), 'level' => $str(), 'credits' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12], 'duration_weeks' => $int(), 'status' => ['type' => 'string', 'enum' => ['draft', 'published', 'archived']], 'max_students' => $int(), 'start_date' => $date(), 'end_date' => $date()]]]]], 'responses' => ['200' => ['description' => 'Course updated', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['course' => $ref('Course')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/courses/{id}']['delete'] = ['tags' => ['Courses'], 'summary' => 'Soft-delete a course', 'security' => [['BearerAuth' => []]], 'parameters' => $id, 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound']]];

// Nested course resources
$nestedCourse = fn($tag, $summary, $pathSuffix) => ['tags' => [$tag], 'summary' => $summary, 'security' => [['BearerAuth' => []]], 'parameters' => [['name' => 'courseId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => $ok($obj([])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];

// Course sub-resources
$studentWithEnrollment = ['allOf' => [
    $ref('User'),
    ['type' => 'object', 'properties' => [
        'enrollment_status' => $str(), 'progress_percent' => $num(), 'grade' => $str(), 'enrolled_at' => $dt()
    ]]
]];
$p['/courses/{courseId}/students']['get'] = ['tags' => ['Enrollments'], 'summary' => 'Paginated enrolled students in a course', 'security' => [['BearerAuth' => []]], 'parameters' => array_merge($cid, [$pageParam, $perPageParam]), 'responses' => ['200' => ['description' => 'Student list', 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/ApiResponse'], ['type' => 'object', 'properties' => ['data' => $arr($studentWithEnrollment), 'meta' => $ref('Pagination')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound']]];

$p['/courses/{courseId}/modules']['get'] = ['tags' => ['Modules'], 'summary' => 'List modules for a course', 'security' => [['BearerAuth' => []]], 'parameters' => $cid, 'responses' => ['200' => $ok($obj(['modules' => $arr($ref('Module'))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/courses/{courseId}/assignments']['get'] = ['tags' => ['Assignments'], 'summary' => 'List visible assignments for a course', 'security' => [['BearerAuth' => []]], 'parameters' => $cid, 'responses' => ['200' => $ok($obj(['assignments' => $arr($ref('Assignment'))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/courses/{courseId}/quizzes']['get'] = ['tags' => ['Quizzes'], 'summary' => 'List visible quizzes for a course', 'security' => [['BearerAuth' => []]], 'parameters' => $cid, 'responses' => ['200' => $ok($obj(['quizzes' => $arr($ref('Quiz'))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/courses/{courseId}/gradebook']['get'] = ['tags' => ['Gradebook'], 'summary' => 'List grade items for a course', 'security' => [['BearerAuth' => []]], 'parameters' => $cid, 'responses' => ['200' => $ok($obj(['grade_items' => $arr($ref('GradeItem'))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/courses/{courseId}/gradebook/{userId}']['get'] = ['tags' => ['Gradebook'], 'summary' => 'Get student grades in a course', 'security' => [['BearerAuth' => []]], 'parameters' => array_merge($cid, [['name' => 'userId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]]), 'responses' => ['200' => $ok($obj(['grades' => $arr($ref('GradebookEntry'))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/courses/{courseId}/exam-links']['get'] = ['tags' => ['Exam Bridge'], 'summary' => 'List exam links for a course', 'security' => [['BearerAuth' => []]], 'parameters' => $cid, 'responses' => ['200' => $ok($obj(['exam_links' => $arr($ref('ExamLink'))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/courses/{courseId}/stats']['get'] = ['tags' => ['Analytics'], 'summary' => 'Course-level statistics (enrollments, completion, activity)', 'security' => [['BearerAuth' => []]], 'parameters' => $cid, 'responses' => ['200' => ['description' => 'Course stats', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['course' => $obj(['id' => $int(), 'title' => $str()]), 'enrollments' => $obj(['total' => $int(), 'active' => $int(), 'completed' => $int()]), 'completion_rate' => $num(), 'lessons' => $obj(['total' => $int(), 'completed_count' => $int()]), 'recent_activity' => $arr($ref('ActivityLog'))]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/courses/{courseId}/discussions']['get'] = ['tags' => ['Discussions'], 'summary' => 'Paginated discussion threads for a course', 'security' => [['BearerAuth' => []]], 'parameters' => array_merge($cid, [$pageParam, $perPageParam]), 'responses' => ['200' => ['description' => 'Paginated threads', 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/ApiResponse'], ['type' => 'object', 'properties' => ['data' => $arr($ref('DiscussionThread')), 'meta' => $ref('Pagination')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/courses/{courseId}/content']['get'] = ['tags' => ['Content'], 'summary' => 'Paginated content library for a course', 'security' => [['BearerAuth' => []]], 'parameters' => array_merge($cid, [$pageParam, $perPageParam, ['name' => 'type', 'in' => 'query', 'schema' => ['type' => 'string']], ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']]]), 'responses' => ['200' => ['description' => 'Paginated content', 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/ApiResponse'], ['type' => 'object', 'properties' => ['data' => $arr($ref('ContentLibrary')), 'meta' => $ref('Pagination')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized']]];

// ─── Modules ────────────────────────────────────────────────────────────────
$mid = [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]];
$p['/modules/{id}']['get'] = ['tags' => ['Modules'], 'summary' => 'Get module with lessons', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => $ok($obj(['module' => $ref('Module')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/modules/{id}']['put'] = ['tags' => ['Modules'], 'summary' => 'Update module', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['title' => $str(), 'description' => $str(), 'order_index' => $int(), 'is_visible' => $bool()]]]]], 'responses' => ['200' => $ok($obj(['module' => $ref('Module')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/modules/{id}']['delete'] = ['tags' => ['Modules'], 'summary' => 'Delete module', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/modules']['post'] = ['tags' => ['Modules'], 'summary' => 'Create a new module', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['course_id', 'title'], 'properties' => ['course_id' => $int(), 'title' => $str(), 'description' => $str()]]]]], 'responses' => ['201' => ['description' => 'Module created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['module' => $ref('Module')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/modules/reorder']['post'] = ['tags' => ['Modules'], 'summary' => 'Reorder modules in a course', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['course_id', 'module_ids'], 'properties' => ['course_id' => $int(), 'module_ids' => ['type' => 'array', 'items' => $int()]]]]]], 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/modules/{moduleId}/lessons']['get'] = ['tags' => ['Lessons'], 'summary' => 'List lessons in a module', 'security' => [['BearerAuth' => []]], 'parameters' => [['name' => 'moduleId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => $ok($obj(['lessons' => $arr($ref('Lesson'))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];

// ─── Lessons ────────────────────────────────────────────────────────────────
$lid = [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]];
$p['/lessons/{id}']['get'] = ['tags' => ['Lessons'], 'summary' => 'Get lesson with attachments', 'security' => [['BearerAuth' => []]], 'parameters' => $lid, 'responses' => ['200' => $ok($obj(['lesson' => $ref('Lesson')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/lessons/{id}']['put'] = ['tags' => ['Lessons'], 'summary' => 'Update lesson', 'security' => [['BearerAuth' => []]], 'parameters' => $lid, 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['title' => $str(), 'content' => $str(), 'video_url' => $str(), 'video_duration' => $int(), 'duration_minutes' => $int(), 'is_visible' => $bool(), 'is_required' => $bool(), 'allow_download' => $bool(), 'order_index' => $int()]]]]], 'responses' => ['200' => $ok($obj(['lesson' => $ref('Lesson')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/lessons/{id}']['delete'] = ['tags' => ['Lessons'], 'summary' => 'Delete lesson', 'security' => [['BearerAuth' => []]], 'parameters' => $lid, 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/lessons']['post'] = ['tags' => ['Lessons'], 'summary' => 'Create a new lesson', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['module_id', 'title', 'content_type'], 'properties' => ['module_id' => $int(), 'title' => $str(), 'content_type' => ['type' => 'string', 'enum' => ['text', 'video', 'h5p', 'scorm', 'pdf', 'quiz', 'assignment', 'discussion']], 'content' => $str(), 'video_url' => $str(), 'video_duration' => $int(), 'duration_minutes' => $int(), 'is_visible' => $bool(), 'is_required' => $bool(), 'allow_download' => $bool()]]]]], 'responses' => ['201' => ['description' => 'Lesson created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['lesson' => $ref('Lesson')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/lessons/{id}/progress']['post'] = ['tags' => ['Lessons'], 'summary' => 'Update lesson progress', 'security' => [['BearerAuth' => []]], 'parameters' => $lid, 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['progress_percent' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100], 'time_spent_seconds' => $int()]]]]], 'responses' => ['200' => $ok($obj(['progress' => $ref('LessonProgress')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/lessons/{id}/complete']['post'] = ['tags' => ['Lessons'], 'summary' => 'Mark lesson completed with grade sync', 'security' => [['BearerAuth' => []]], 'parameters' => $lid, 'responses' => ['200' => $ok($obj(['progress' => $ref('LessonProgress')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];

// ─── Enrollments ────────────────────────────────────────────────────────────
$p['/enrollments/my']['get'] = ['tags' => ['Enrollments'], 'summary' => 'Get my enrolled courses with progress', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => ['description' => 'Enrolled courses', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['courses' => $arr(['allOf' => [[$ref('Course')], ['type' => 'object', 'properties' => ['enrollment_status' => $str(), 'progress_percent' => $num(), 'enrolled_at' => $dt()]]]])]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized']]];
$p['/enrollments']['post'] = ['tags' => ['Enrollments'], 'summary' => 'Enroll a user in a course', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['course_id'], 'properties' => ['course_id' => $int(), 'user_id' => $int()]]]]], 'responses' => ['201' => ['description' => 'Enrolled', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['enrollment' => $ref('Enrollment')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '409' => ['$ref' => '#/components/responses/Conflict'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/enrollments/{id}']['delete'] = ['tags' => ['Enrollments'], 'summary' => 'Drop/unenroll from a course', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];

// ─── Assignments ────────────────────────────────────────────────────────────
$aid = [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]];
$p['/assignments/{id}']['get'] = ['tags' => ['Assignments'], 'summary' => 'Get assignment details', 'security' => [['BearerAuth' => []]], 'parameters' => $aid, 'responses' => ['200' => $ok($obj(['assignment' => $ref('Assignment')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/assignments/{id}']['put'] = ['tags' => ['Assignments'], 'summary' => 'Update assignment', 'security' => [['BearerAuth' => []]], 'parameters' => $aid, 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['title' => $str(), 'description' => $str(), 'max_points' => $num(), 'due_at' => $dt(), 'available_until' => $dt()]]]]], 'responses' => ['200' => $ok($obj(['assignment' => $ref('Assignment')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/assignments/{id}']['delete'] = ['tags' => ['Assignments'], 'summary' => 'Delete assignment', 'security' => [['BearerAuth' => []]], 'parameters' => $aid, 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/assignments']['post'] = ['tags' => ['Assignments'], 'summary' => 'Create a new assignment', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['course_id', 'title', 'max_points', 'submission_type'], 'properties' => ['course_id' => $int(), 'module_id' => $int(), 'title' => $str(), 'description' => $str(), 'instructions' => $str(), 'max_points' => $num(), 'passing_points' => $num(), 'allow_late_submission' => $bool(), 'late_penalty_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100], 'max_attempts' => $int(), 'due_at' => $dt(), 'available_from' => $dt(), 'available_until' => $dt(), 'submission_type' => ['type' => 'string', 'enum' => ['file', 'text', 'url', 'online_text']]]]]]], 'responses' => ['201' => ['description' => 'Assignment created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['assignment' => $ref('Assignment')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/assignments/{id}/submit']['post'] = ['tags' => ['Assignments'], 'summary' => 'Submit an assignment attempt', 'security' => [['BearerAuth' => []]], 'parameters' => $aid, 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['content' => $str(), 'file_url' => $str()]]]]], 'responses' => ['201' => ['description' => 'Submission created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['submission' => $ref('Submission')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/assignments/{id}/grade']['post'] = ['tags' => ['Assignments'], 'summary' => 'Grade a submission', 'security' => [['BearerAuth' => []]], 'parameters' => $aid, 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['points'], 'properties' => ['points' => ['type' => 'number', 'minimum' => 0], 'feedback' => $str()]]]]], 'responses' => ['200' => $ok($obj(['submission' => $ref('Submission')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

// ─── Quizzes ────────────────────────────────────────────────────────────────
$p['/quizzes/{id}']['get'] = ['tags' => ['Quizzes'], 'summary' => 'Get quiz with questions and user attempts', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => $ok($obj(['quiz' => $ref('Quiz')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/quizzes/{id}']['put'] = ['tags' => ['Quizzes'], 'summary' => 'Update quiz', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['title' => $str(), 'description' => $str(), 'time_limit_minutes' => $int(), 'passing_score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100], 'shuffle_questions' => $bool(), 'due_at' => $dt()]]]]], 'responses' => ['200' => $ok($obj(['quiz' => $ref('Quiz')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/quizzes/{id}']['delete'] = ['tags' => ['Quizzes'], 'summary' => 'Delete a quiz', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/quizzes']['post'] = ['tags' => ['Quizzes'], 'summary' => 'Create a new quiz', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['course_id', 'title', 'quiz_type'], 'properties' => ['course_id' => $int(), 'module_id' => $int(), 'title' => $str(), 'description' => $str(), 'quiz_type' => ['type' => 'string', 'enum' => ['graded', 'practice', 'survey']], 'max_attempts' => $int(), 'time_limit_minutes' => $int(), 'passing_score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100], 'shuffle_questions' => $bool(), 'shuffle_options' => $bool(), 'show_results_after' => ['type' => 'string', 'enum' => ['never', 'immediately', 'after_due', 'after_attempt']], 'show_correct_answers' => $bool(), 'due_at' => $dt(), 'available_from' => $dt(), 'available_until' => $dt()]]]]], 'responses' => ['201' => ['description' => 'Quiz created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['quiz' => $ref('Quiz')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/quizzes/{id}/questions']['post'] = ['tags' => ['Quizzes'], 'summary' => 'Add a question to a quiz', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['question_type', 'question_text', 'points'], 'properties' => ['question_type' => ['type' => 'string', 'enum' => ['mcq', 'multiple_answer', 'true_false', 'short_answer', 'essay', 'matching', 'ordering', 'fill_blank']], 'question_text' => $str(), 'options' => $arr($str()), 'correct_answer' => $str(), 'points' => ['type' => 'number', 'minimum' => 0]]]]]], 'responses' => ['201' => ['description' => 'Question added', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['question' => $ref('QuizQuestion')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/quizzes/{id}/attempts']['post'] = ['tags' => ['Quizzes'], 'summary' => 'Start a new quiz attempt', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['201' => ['description' => 'Attempt started', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['attempt' => $ref('QuizAttempt')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/quizzes/{id}/attempts/{attemptId}/submit']['post'] = ['tags' => ['Quizzes'], 'summary' => 'Submit quiz answers and grade attempt', 'security' => [['BearerAuth' => []]], 'parameters' => array_merge($mid, [['name' => 'attemptId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]]), 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['answers'], 'properties' => ['answers' => $arr(['type' => 'object', 'required' => ['question_id', 'answer'], 'properties' => ['question_id' => $int(), 'answer' => $str(), 'time_spent' => $int()]])]]]]], 'responses' => ['200' => $ok($obj(['attempt' => $ref('QuizAttempt')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

// ─── Discussions ────────────────────────────────────────────────────────────
$tid = [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]];
$p['/discussions/{id}']['get'] = ['tags' => ['Discussions'], 'summary' => 'Get discussion thread with replies', 'security' => [['BearerAuth' => []]], 'parameters' => $tid, 'responses' => ['200' => $ok($obj(['thread' => $ref('DiscussionThread')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/discussions']['post'] = ['tags' => ['Discussions'], 'summary' => 'Create a new discussion thread', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['course_id', 'title', 'content'], 'properties' => ['course_id' => $int(), 'title' => $str(), 'content' => $str(), 'is_announcement' => $bool()]]]]], 'responses' => ['201' => ['description' => 'Thread created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['thread' => $ref('DiscussionThread')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/discussions/{threadId}/replies']['post'] = ['tags' => ['Discussions'], 'summary' => 'Reply to a discussion thread', 'security' => [['BearerAuth' => []]], 'parameters' => [['name' => 'threadId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['content'], 'properties' => ['content' => $str(), 'parent_id' => $int()]]]]], 'responses' => ['201' => ['description' => 'Reply created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['reply' => $ref('DiscussionReply')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

// ─── Messages / Notifications ───────────────────────────────────────────────
$p['/messages/conversations']['get'] = ['tags' => ['Messages / Notifications'], 'summary' => 'List my conversations', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => $ok($obj(['conversations' => $arr($ref('Conversation'))])), '401' => ['$ref' => '#/components/responses/Unauthorized']]];
$p['/messages/conversations']['post'] = ['tags' => ['Messages / Notifications'], 'summary' => 'Create a conversation', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['participants'], 'properties' => ['participants' => ['type' => 'array', 'items' => $int(), 'minItems' => 1], 'subject' => $str()]]]]], 'responses' => ['201' => ['description' => 'Conversation created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['conversation' => $ref('Conversation')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/messages/conversations/{convId}']['get'] = ['tags' => ['Messages / Notifications'], 'summary' => 'Get messages in a conversation', 'security' => [['BearerAuth' => []]], 'parameters' => array_merge([['name' => 'convId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], [$pageParam, $perPageParam]), 'responses' => ['200' => ['description' => 'Paginated messages', 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/ApiResponse'], ['type' => 'object', 'properties' => ['data' => $arr($ref('Message')), 'meta' => $ref('Pagination')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/messages/send']['post'] = ['tags' => ['Messages / Notifications'], 'summary' => 'Send a message in a conversation', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['conversation_id', 'message'], 'properties' => ['conversation_id' => $int(), 'message' => $str(), 'message_type' => ['type' => 'string', 'enum' => ['text', 'image', 'file', 'notification']]]]]]], 'responses' => ['201' => ['description' => 'Message sent', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['message' => $ref('Message')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

$p['/notifications']['get'] = ['tags' => ['Messages / Notifications'], 'summary' => 'Paginated notifications for current user', 'security' => [['BearerAuth' => []]], 'parameters' => [$pageParam, $perPageParam], 'responses' => ['200' => ['description' => 'Paginated notifications', 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/ApiResponse'], ['type' => 'object', 'properties' => ['data' => $arr($ref('Notification')), 'meta' => $ref('Pagination')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized']]];
$p['/notifications/unread-count']['get'] = ['tags' => ['Messages / Notifications'], 'summary' => 'Get unread notification count', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => $ok($obj(['unread' => $int()])), '401' => ['$ref' => '#/components/responses/Unauthorized']]];
$p['/notifications/{id}/read']['post'] = ['tags' => ['Messages / Notifications'], 'summary' => 'Mark notification as read', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => $ok($obj(['notification' => $ref('Notification')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/notifications/mark-all-read']['post'] = ['tags' => ['Messages / Notifications'], 'summary' => 'Mark all notifications as read', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized']]];

// ─── Gradebook ──────────────────────────────────────────────────────────────
$p['/gradebook/items']['post'] = ['tags' => ['Gradebook'], 'summary' => 'Create a grade item', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['course_id', 'item_type', 'title', 'max_points'], 'properties' => ['course_id' => $int(), 'item_type' => ['type' => 'string', 'enum' => ['assignment', 'quiz', 'participation', 'manual', 'final_exam']], 'item_id' => $int(), 'title' => $str(), 'max_points' => $num(), 'weight' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100], 'category' => $str()]]]]], 'responses' => ['201' => ['description' => 'Grade item created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['grade_item' => $ref('GradeItem')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/gradebook/grades']['post'] = ['tags' => ['Gradebook'], 'summary' => 'Update or create a grade entry', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['user_id', 'grade_item_id', 'points_earned'], 'properties' => ['user_id' => $int(), 'grade_item_id' => $int(), 'points_earned' => ['type' => 'number', 'minimum' => 0], 'comments' => $str()]]]]], 'responses' => ['200' => $ok($obj(['grade_entry' => $ref('GradebookEntry')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

// ─── Content ────────────────────────────────────────────────────────────────
$p['/content']['get'] = ['tags' => ['Content'], 'summary' => 'Paginated content library', 'security' => [['BearerAuth' => []]], 'parameters' => [$pageParam, $perPageParam, ['name' => 'type', 'in' => 'query', 'schema' => ['type' => 'string']], ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']]], 'responses' => ['200' => ['description' => 'Paginated content', 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/ApiResponse'], ['type' => 'object', 'properties' => ['data' => $arr($ref('ContentLibrary')), 'meta' => $ref('Pagination')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized']]];
$p['/content']['post'] = ['tags' => ['Content'], 'summary' => 'Upload content to library', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['title', 'file_type', 'file_url', 'file_size'], 'properties' => ['course_id' => $int(), 'title' => $str(), 'file_type' => $str(), 'file_url' => $str(), 'file_size' => $int(), 'mime_type' => $str(), 'description' => $str(), 'is_public' => $bool()]]]]], 'responses' => ['201' => ['description' => 'Content uploaded', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['content' => $ref('ContentLibrary')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/content/{id}']['get'] = ['tags' => ['Content'], 'summary' => 'Get content item details', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => $ok($obj(['content' => $ref('ContentLibrary')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/content/{id}']['delete'] = ['tags' => ['Content'], 'summary' => 'Delete a content item', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/content/{id}/download']['get'] = ['tags' => ['Content'], 'summary' => 'Get download info for content', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => $ok($obj(['file_url' => $str(), 'file_name' => $str(), 'file_size' => $int(), 'mime_type' => $str()])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/content/attachments']['post'] = ['tags' => ['Content'], 'summary' => 'Attach a file to a lesson', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['lesson_id', 'title', 'file_url', 'file_size'], 'properties' => ['lesson_id' => $int(), 'title' => $str(), 'file_url' => $str(), 'file_size' => $int(), 'mime_type' => $str()]]]]], 'responses' => ['201' => ['description' => 'Attachment created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['attachment' => $ref('Attachment')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

// ─── Analytics ──────────────────────────────────────────────────────────────
$p['/analytics/platform']['get'] = ['tags' => ['Analytics'], 'summary' => 'Platform-wide statistics', 'security' => [['BearerAuth' => []]], 'parameters' => [['name' => 'period', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['7d', '30d', '90d', '1y'], 'default' => '30d']]], 'responses' => ['200' => ['description' => 'Platform stats', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['courses' => $obj(['total' => $int(), 'published' => $int()]), 'enrollments' => $obj(['total' => $int(), 'recent' => $int()]), 'activity' => $obj(['count' => $int()]), 'period' => $str()]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden']]];
$p['/analytics/progress']['get'] = ['tags' => ['Analytics'], 'summary' => 'Get current user progress across enrollments', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => ['description' => 'User progress', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['progress' => $arr($obj(['course_id' => $int(), 'course_title' => $str(), 'course_code' => $str(), 'progress_percent' => $num(), 'status' => $str(), 'grade' => $str(), 'letter_grade' => $str(), 'total_points' => $num(), 'earned_points' => $num()]))]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized']]];
$p['/analytics/progress/{userId}']['get'] = ['tags' => ['Analytics'], 'summary' => 'Get progress for a specific user', 'security' => [['BearerAuth' => []]], 'parameters' => [['name' => 'userId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'User progress', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['progress' => $arr($obj(['course_id' => $int(), 'course_title' => $str(), 'course_code' => $str(), 'progress_percent' => $num(), 'status' => $str(), 'grade' => $str(), 'letter_grade' => $str(), 'total_points' => $num(), 'earned_points' => $num()]))]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden']]];

// ─── Academic ───────────────────────────────────────────────────────────────
$academic = fn($summary, $key) => ['tags' => ['Academic'], 'summary' => $summary, 'security' => [['BearerAuth' => []]], 'responses' => ['200' => $ok($obj([$key => ['type' => 'array', 'items' => ['type' => 'object']]])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '503' => ['description' => 'Academic records not available']]];
$p['/academic/faculties']['get'] = $academic('List faculties from the academic system', 'faculties');
$p['/academic/departments']['get'] = ['tags' => ['Academic'], 'summary' => 'List departments filtered by faculty', 'security' => [['BearerAuth' => []]], 'parameters' => [['name' => 'faculty_id', 'in' => 'query', 'schema' => ['type' => 'integer']]], 'responses' => ['200' => $ok($obj(['departments' => $arr($obj([]))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '503' => ['description' => 'Academic records not available']]];
$p['/academic/course-studies']['get'] = ['tags' => ['Academic'], 'summary' => 'List course studies', 'security' => [['BearerAuth' => []]], 'parameters' => [['name' => 'department_id', 'in' => 'query', 'schema' => ['type' => 'integer']]], 'responses' => ['200' => $ok($obj(['course_studies' => $arr($obj([]))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '503' => ['description' => 'Academic records not available']]];
$p['/academic/courses']['get'] = ['tags' => ['Academic'], 'summary' => 'List academic courses', 'security' => [['BearerAuth' => []]], 'parameters' => [['name' => 'department_id', 'in' => 'query', 'schema' => ['type' => 'integer']], ['name' => 'course_study_id', 'in' => 'query', 'schema' => ['type' => 'integer']], ['name' => 'level', 'in' => 'query', 'schema' => ['type' => 'integer']]], 'responses' => ['200' => $ok($obj(['courses' => $arr($obj([]))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '503' => ['description' => 'Academic records not available']]];
$p['/academic/courses/{id}']['get'] = ['tags' => ['Academic'], 'summary' => 'Get academic course detail', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => $ok($obj(['course' => $obj([])])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '404' => ['$ref' => '#/components/responses/NotFound'], '503' => ['description' => 'Academic records not available']]];
$p['/academic/sessions']['get'] = $academic('List academic sessions', 'vu_sessions');
$p['/academic/sessions/active']['get'] = ['tags' => ['Academic'], 'summary' => 'Get active academic session', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => $ok($obj(['session' => $obj([])])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '503' => ['description' => 'Academic records not available']]];
$p['/academic/semesters']['get'] = ['tags' => ['Academic'], 'summary' => 'List semesters', 'security' => [['BearerAuth' => []]], 'parameters' => [['name' => 'academic_session_id', 'in' => 'query', 'schema' => ['type' => 'integer']]], 'responses' => ['200' => $ok($obj(['semesters' => $arr($obj([]))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '503' => ['description' => 'Academic records not available']]];
$p['/academic/levels']['get'] = $academic('List academic levels', 'levels');
$p['/academic/my-context']['get'] = ['tags' => ['Academic'], 'summary' => 'Get current student academic context', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => ['description' => 'Academic context', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['academic' => $obj([]), 'course_study' => $obj([]), 'current_courses' => $arr($obj([])), 'registrations' => $arr($obj([]))]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '503' => ['description' => 'Academic records not available']]];

// ─── Exam Bridge ────────────────────────────────────────────────────────────
$p['/exam-bridge/sync-log']['get'] = ['tags' => ['Exam Bridge'], 'summary' => 'Exam sync log', 'security' => [['BearerAuth' => []]], 'parameters' => [$pageParam, $perPageParam, ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['pending', 'success', 'failed']]]], 'responses' => ['200' => ['description' => 'Paginated sync log', 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/ApiResponse'], ['type' => 'object', 'properties' => ['data' => $arr($ref('ExamSyncLog')), 'meta' => $ref('Pagination')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden']]];
$p['/exam-bridge/link']['post'] = ['tags' => ['Exam Bridge'], 'summary' => 'Link portal exam to LMS', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['portal_exam_id', 'course_id'], 'properties' => ['lms_quiz_id' => $int(), 'portal_exam_id' => $int(), 'course_id' => $int(), 'module_id' => $int()]]]]], 'responses' => ['201' => ['description' => 'Exam link created', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['exam_link' => $ref('ExamLink')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '409' => ['$ref' => '#/components/responses/Conflict'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/exam-bridge/unlink/{id}']['post'] = ['tags' => ['Exam Bridge'], 'summary' => 'Unlink an exam', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound']]];
$p['/exam-bridge/sync']['post'] = ['tags' => ['Exam Bridge'], 'summary' => 'Trigger sync action', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['exam_link_id', 'action'], 'properties' => ['exam_link_id' => $int(), 'action' => ['type' => 'string', 'enum' => ['push_grades', 'push_enrollments', 'pull_results']]]]]]], 'responses' => ['200' => ['description' => 'Sync completed', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['message' => $str(), 'response' => $obj([])]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '502' => ['description' => 'Upstream sync error']]];
$p['/exam-bridge/sso']['post'] = ['tags' => ['Exam Bridge'], 'summary' => 'Generate SSO token for portal exam launch', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['exam_id', 'course_id'], 'properties' => ['exam_id' => $int(), 'course_id' => $int(), 'return_url' => $str()]]]]], 'responses' => ['200' => $ok($obj(['token' => $str(), 'redirect_url' => $str()])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

// ─── Sync ───────────────────────────────────────────────────────────────────
$p['/sync/enrollments']['post'] = ['tags' => ['Sync'], 'summary' => 'Trigger bulk enrollment sync from portal', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => ['description' => 'Sync stats', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'object', 'properties' => ['message' => $str(), 'stats' => $obj(['created' => $int(), 'updated' => $int(), 'errors' => $int()])]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden']]];

// ─── Admin ──────────────────────────────────────────────────────────────────
$p['/admin/users']['get'] = ['tags' => ['Admin'], 'summary' => 'Paginated user list with search and role filter', 'security' => [['BearerAuth' => []]], 'parameters' => [$pageParam, $perPageParam, ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']], ['name' => 'role', 'in' => 'query', 'schema' => ['type' => 'string']], ['name' => 'is_active', 'in' => 'query', 'schema' => ['type' => 'integer', 'enum' => [0, 1]]]], 'responses' => ['200' => ['description' => 'Paginated user list', 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/ApiResponse'], ['type' => 'object', 'properties' => ['data' => $arr($ref('User')), 'meta' => $ref('Pagination')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden']]];
$p['/admin/users/{id}']['put'] = ['tags' => ['Admin'], 'summary' => 'Update user (admin)', 'security' => [['BearerAuth' => []]], 'parameters' => $mid, 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['first_name' => $str(), 'last_name' => $str(), 'email' => ['type' => 'string', 'format' => 'email'], 'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'suspended']], 'is_active' => $bool()]]]]], 'responses' => ['200' => $ok($obj(['user' => $ref('User')])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

$p['/admin/roles']['get'] = ['tags' => ['Admin'], 'summary' => 'List all roles with permissions', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => $ok($obj(['roles' => $arr($obj(['id' => $int(), 'name' => $str(), 'slug' => $str(), 'permissions' => $arr($ref('Permission')), 'user_count' => $int()]))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden']]];
$p['/admin/roles']['post'] = ['tags' => ['Admin'], 'summary' => 'Assign role to user', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['user_id', 'role_id'], 'properties' => ['user_id' => $int(), 'role_id' => $int()]]]]], 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '404' => ['$ref' => '#/components/responses/NotFound'], '422' => ['$ref' => '#/components/responses/ValidationError']]];
$p['/admin/roles']['delete'] = ['tags' => ['Admin'], 'summary' => 'Remove role from user', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['user_id', 'role_id'], 'properties' => ['user_id' => $int(), 'role_id' => $int()]]]]], 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

$p['/admin/settings']['get'] = ['tags' => ['Admin'], 'summary' => 'Get all system settings grouped', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => $ok($obj(['settings' => ['type' => 'object', 'additionalProperties' => ['type' => 'object', 'additionalProperties' => true]]])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden']]];
$p['/admin/settings']['put'] = ['tags' => ['Admin'], 'summary' => 'Update system settings', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['settings'], 'properties' => ['settings' => $arr(['type' => 'object', 'required' => ['key', 'value'], 'properties' => ['key' => $str(), 'value' => $str()]])]]]]], 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

$p['/admin/features']['get'] = ['tags' => ['Admin'], 'summary' => 'List feature flags', 'security' => [['BearerAuth' => []]], 'responses' => ['200' => $ok($obj(['features' => $arr($ref('FeatureFlag'))])), '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden']]];
$p['/admin/features']['post'] = ['tags' => ['Admin'], 'summary' => 'Toggle a feature flag', 'security' => [['BearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['feature', 'enabled'], 'properties' => ['feature' => $str(), 'enabled' => $bool()]]]]], 'responses' => ['200' => ['$ref' => '#/components/responses/SuccessMessage'], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden'], '422' => ['$ref' => '#/components/responses/ValidationError']]];

$p['/admin/activity']['get'] = ['tags' => ['Admin'], 'summary' => 'Paginated activity log with filters', 'security' => [['BearerAuth' => []]], 'parameters' => [$pageParam, $perPageParam, ['name' => 'user_id', 'in' => 'query', 'schema' => ['type' => 'integer']], ['name' => 'action', 'in' => 'query', 'schema' => ['type' => 'string']], ['name' => 'date_from', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']], ['name' => 'date_to', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']]], 'responses' => ['200' => ['description' => 'Paginated activity log', 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/ApiResponse'], ['type' => 'object', 'properties' => ['data' => $arr($ref('ActivityLog')), 'meta' => $ref('Pagination')]]]]]]], '401' => ['$ref' => '#/components/responses/Unauthorized'], '403' => ['$ref' => '#/components/responses/Forbidden']]];

// ─── Build the complete document ─────────────────────────────────────────────
$spec = [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'Next Gen LMS API',
        'description' => 'Integrated Learning Management System - REST API for course management, enrollments, quizzes, assignments, gradebook, discussions, messaging, exam bridge, and administrative functions.',
        'version' => '1.0.0',
        'contact' => ['name' => 'LMS Team', 'url' => 'https://exam-portal.example.com'],
    ],
    'servers' => [
        ['url' => '/exam_portal/lms/public', 'description' => 'LMS API server'],
    ],
    'paths' => $p,
    'components' => [
        'securitySchemes' => [
            'BearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'JWT token obtained from /auth/login',
            ],
        ],
        'parameters' => [
            'page' => $pageParam,
            'perPage' => $perPageParam,
        ],
        'responses' => [
            'SuccessMessage' => $successMessage,
            'Unauthorized' => $unauthorized,
            'Forbidden' => $forbidden,
            'NotFound' => $notFound,
            'ValidationError' => $validationError,
            'Conflict' => $conflict,
            'AuthSuccess' => $authSuccess,
        ],
        'schemas' => $S,
    ],
    'tags' => $tags,
];

$json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/openapi.json', $json);
echo "Generated: " . strlen($json) . " bytes\n";
echo "Paths: " . count($p) . " endpoints\n";
