<?php
/**
 * Next Gen LMS — Configuration
 */

return [
    'app' => [
        'name'    => 'Veritas Integrated LMS',
        'version' => '1.0.0',
        'env'     => getenv('APP_ENV') ?: 'development',
        'debug'   => (getenv('APP_DEBUG') ?: 'true') === 'true',
        'url'     => getenv('LMS_URL') ?: 'http://localhost/exam_portal/lms/public',
        'timezone' => 'Africa/Lagos',
        'locale'  => 'en',
    ],

    'database' => [
        'lms' => [
            'driver'   => getenv('LMS_DB_DRIVER') ?: 'mysql',
            'host'     => getenv('LMS_DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost'),
            'port'     => getenv('LMS_DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : '3306'),
            'database' => getenv('LMS_DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'backup_db_pre_implementation'),
            'username' => getenv('LMS_DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root'),
            'password' => getenv('LMS_DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : ''),
            'charset'  => 'utf8mb4',
            'prefix'   => 'lms_',
        ],
    ],

    'auth' => [
        'jwt_secret'        => getenv('LMS_JWT_SECRET') ?: 'lms-secret-key-change-in-production',
        'jwt_issuer'        => 'veritas-lms',
        'access_token_ttl'  => 3600,       // 1 hour
        'refresh_token_ttl' => 2592000,    // 30 days
        'password_cost'     => 12,         // bcrypt cost factor
        'login_throttle'    => 5,           // max attempts before lockout
        'lockout_duration'  => 900,         // 15 minutes
    ],

    'cache' => [
        'driver'  => getenv('LMS_CACHE_DRIVER') ?: 'file',
        'prefix'  => 'lms_',
        'ttl'     => 3600,
        'redis'   => [
            'host' => getenv('LMS_REDIS_HOST') ?: '127.0.0.1',
            'port' => getenv('LMS_REDIS_PORT') ?: 6379,
            'password' => getenv('LMS_REDIS_PASS') ?: null,
        ],
    ],

    'storage' => [
        'driver' => getenv('LMS_STORAGE_DRIVER') ?: 'local',
        'local'  => [
            'path' => LMS_STORAGE . '/media',
        ],
        's3'     => [
            'key'    => getenv('LMS_S3_KEY') ?: '',
            'secret' => getenv('LMS_S3_SECRET') ?: '',
            'region' => getenv('LMS_S3_REGION') ?: 'us-east-1',
            'bucket' => getenv('LMS_S3_BUCKET') ?: 'veritas-lms-media',
            'endpoint' => getenv('LMS_S3_ENDPOINT') ?: '',
        ],
    ],

    'queue' => [
        'driver' => getenv('LMS_QUEUE_DRIVER') ?: 'sync',
        'connection' => [
            'host' => getenv('LMS_RABBITMQ_HOST') ?: '127.0.0.1',
            'port' => getenv('LMS_RABBITMQ_PORT') ?: 5672,
            'user' => getenv('LMS_RABBITMQ_USER') ?: 'guest',
            'pass' => getenv('LMS_RABBITMQ_PASS') ?: 'guest',
        ],
    ],

    'exam_portal' => [
        'base_url'    => getenv('EXAM_PORTAL_URL') ?: 'http://localhost/exam_portal',
        'api_key'     => getenv('EXAM_PORTAL_API_KEY') ?: '',
        'api_secret'  => getenv('EXAM_PORTAL_API_SECRET') ?: '',
        'webhook_key' => getenv('EXAM_PORTAL_WEBHOOK_KEY') ?: bin2hex(random_bytes(16)),
        'sync_enabled' => getenv('LMS_EXAM_SYNC_ENABLED') ?: 'true',
        'sso_enabled'  => getenv('LMS_SSO_ENABLED') ?: 'true',
    ],

    'features' => [
        'notifications' => [
            'in_app' => true,
            'email'  => true,
            'push'   => false,
        ],
        'analytics' => [
            'enabled'          => true,
            'retention_days'   => 365,
            'predictive'       => false,   // Enable when ML pipeline is ready
        ],
        'discussions' => true,
        'messaging'   => true,
    ],

    'pagination' => [
        'per_page' => 20,
        'max_per_page' => 100,
    ],

    'upload' => [
        'max_file_size' => 52428800,     // 50MB
        'allowed_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'video/mp4', 'video/webm',
            'audio/mpeg', 'audio/ogg', 'audio/wav',
            'application/zip', 'text/plain',
        ],
        'scan_uploads' => false,  // Enable ClamAV scanning in production
    ],
];
