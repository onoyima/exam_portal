<?php
/**
 * Next Gen LMS — Exam Portal Integration Configuration
 */

return [
    'exam_portal' => [
        // Base URL of the Exam Portal installation
        'base_url' => getenv('EXAM_PORTAL_URL') ?: 'http://localhost/exam_portal',

        // API authentication
        'api_key'    => getenv('EXAM_PORTAL_API_KEY') ?: '',
        'api_secret' => getenv('EXAM_PORTAL_API_SECRET') ?: '',

        // Webhook verification
        'webhook_key' => getenv('EXAM_PORTAL_WEBHOOK_KEY') ?: '',

        // Feature toggles
        'sync_enabled' => filter_var(getenv('LMS_EXAM_SYNC_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'sso_enabled'  => filter_var(getenv('LMS_SSO_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),

        // Sync settings
        'sync' => [
            'retry_attempts'     => 5,
            'retry_delay_seconds' => 30,
            'batch_size'          => 100,
            'timeout_seconds'     => 30,
        ],

        // SSO settings
        'sso' => [
            'token_ttl_seconds' => 300,   // 5 minutes
            'allowed_ips'       => ['127.0.0.1', '::1', '192.168.0.0/16'],
        ],

        // Exam Portal API endpoints (relative to base_url)
        'endpoints' => [
            'sync_exam'         => '/api/exams/sync',
            'enroll_students'   => '/api/exams/enroll',
            'sso_login'         => '/api/sso/login',
            'exam_results'      => '/api/exams/{exam_id}/results',
            'student_lookup'    => '/api/students/lookup',
            'health'            => '/api/health',
            'webhook_receive'   => '/api/exams/webhook',
        ],
    ],

    // Mapping: LMS exam types to Exam Portal exam types
    'exam_type_mapping' => [
        'graded_quiz'    => 'objective',
        'practice_quiz'  => 'objective',
        'final_exam'     => 'objective',
        'practical'      => 'practical',
        'oral'           => 'viva',
    ],

    // Events that trigger sync
    'sync_triggers' => [
        'course.created'     => true,
        'course.updated'     => true,
        'course.deleted'     => true,
        'enrollment.created' => true,
        'enrollment.dropped' => true,
        'enrollment.completed' => false,
    ],
];
