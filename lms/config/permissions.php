<?php
/**
 * Next Gen LMS — RBAC Permission Definitions
 */

return [
    'roles' => [
        'super_admin' => [
            'name'        => 'Super Administrator',
            'description' => 'Full system access with all permissions',
            'is_system'   => true,
            'parents'     => ['admin'],
        ],
        'admin' => [
            'name'        => 'Administrator',
            'description' => 'System administration and configuration',
            'is_system'   => true,
            'parents'     => ['instructor'],
        ],
        'instructor' => [
            'name'        => 'Instructor',
            'description' => 'Course creation, management, and grading',
            'is_system'   => true,
            'parents'     => ['ta'],
        ],
        'ta' => [
            'name'        => 'Teaching Assistant',
            'description' => 'Grading support and discussion moderation',
            'is_system'   => true,
            'parents'     => ['student'],
        ],
        'student' => [
            'name'        => 'Student',
            'description' => 'Course enrollment, content access, and assessments',
            'is_system'   => true,
            'parents'     => [],
        ],
    ],

    'permissions' => [
        // ─── Course Permissions ──────────────────────────────────────────────
        'courses.read'          => ['*'],
        'courses.create'        => ['instructor', 'admin', 'super_admin'],
        'courses.update'        => ['instructor', 'admin', 'super_admin'],
        'courses.delete'        => ['admin', 'super_admin'],
        'courses.publish'       => ['admin', 'super_admin'],
        'courses.clone'         => ['instructor', 'admin', 'super_admin'],

        // ─── Module Permissions ──────────────────────────────────────────────
        'modules.read'          => ['*'],
        'modules.manage'        => ['instructor', 'admin', 'super_admin'],
        'modules.reorder'       => ['instructor', 'admin', 'super_admin'],

        // ─── Lesson Permissions ──────────────────────────────────────────────
        'lessons.read'          => ['*'],
        'lessons.manage'        => ['instructor', 'admin', 'super_admin'],
        'lessons.complete'      => ['student', 'ta'],

        // ─── Enrollment Permissions ──────────────────────────────────────────
        'enrollments.read'      => ['*'],
        'enrollments.self'      => ['student'],
        'enrollments.manage'    => ['admin', 'super_admin', 'instructor'],
        'enrollments.drop'      => ['admin', 'super_admin'],

        // ─── Assignment Permissions ──────────────────────────────────────────
        'assignments.read'      => ['*'],
        'assignments.manage'    => ['instructor', 'admin', 'super_admin'],
        'assignments.submit'    => ['student'],
        'assignments.grade'     => ['ta', 'instructor', 'admin', 'super_admin'],

        // ─── Quiz Permissions ────────────────────────────────────────────────
        'quizzes.read'          => ['*'],
        'quizzes.manage'        => ['instructor', 'admin', 'super_admin'],
        'quizzes.attempt'       => ['student'],
        'quizzes.grade'         => ['ta', 'instructor', 'admin', 'super_admin'],
        'quizzes.view_results'  => ['student', 'ta', 'instructor', 'admin', 'super_admin'],

        // ─── Gradebook Permissions ───────────────────────────────────────────
        'gradebook.read_own'    => ['student'],
        'gradebook.read_course' => ['ta', 'instructor', 'admin', 'super_admin'],
        'gradebook.manage'      => ['ta', 'instructor', 'admin', 'super_admin'],
        'gradebook.override'    => ['instructor', 'admin', 'super_admin'],

        // ─── Discussion Permissions ──────────────────────────────────────────
        'discussions.read'      => ['*'],
        'discussions.create'    => ['*'],
        'discussions.reply'     => ['*'],
        'discussions.moderate'  => ['ta', 'instructor', 'admin', 'super_admin'],
        'discussions.announce'  => ['instructor', 'admin', 'super_admin'],

        // ─── Messaging Permissions ──────────────────────────────────────────
        'messages.send'         => ['*'],
        'messages.read'         => ['*'],

        // ─── Notification Permissions ───────────────────────────────────────
        'notifications.read'    => ['*'],
        'notifications.manage'  => ['admin', 'super_admin'],

        // ─── Analytics Permissions ──────────────────────────────────────────
        'analytics.read_own'    => ['student'],
        'analytics.read_course' => ['ta', 'instructor', 'admin', 'super_admin'],
        'analytics.read_institutional' => ['admin', 'super_admin'],
        'analytics.export'      => ['admin', 'super_admin'],

        // ─── User Management ────────────────────────────────────────────────
        'users.read'            => ['admin', 'super_admin'],
        'users.create'          => ['admin', 'super_admin'],
        'users.update'          => ['admin', 'super_admin'],
        'users.delete'          => ['super_admin'],
        'users.impersonate'     => ['super_admin'],

        // ─── Role Management ────────────────────────────────────────────────
        'roles.read'            => ['admin', 'super_admin'],
        'roles.manage'          => ['super_admin'],

        // ─── System Configuration ──────────────────────────────────────────
        'system.config'         => ['super_admin'],
        'system.monitor'        => ['admin', 'super_admin'],
        'system.logs'           => ['super_admin'],

        // ─── Exam Integration ────────────────────────────────────────────────
        'exams.sync'            => ['admin', 'super_admin'],
        'exams.view'            => ['*'],
        'exams.bridge'          => ['admin', 'super_admin'],

        // ─── Content Management ─────────────────────────────────────────────
        'content.upload'        => ['instructor', 'admin', 'super_admin'],
        'content.delete'        => ['admin', 'super_admin'],
    ],
];
