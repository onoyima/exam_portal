<?php
return [
    'database' => [
        'exam_portal' => [
            'driver'   => 'mysql',
            'host'     => defined('DB_HOST') ? DB_HOST : 'localhost',
            'port'     => defined('DB_PORT') ? DB_PORT : '3306',
            'database' => defined('DB_NAME') ? DB_NAME : 'backup_db_pre_implementation',
            'username' => defined('DB_USER') ? DB_USER : 'root',
            'password' => defined('DB_PASS') ? DB_PASS : '',
            'charset'  => 'utf8mb4',
            'prefix'   => '',
        ],
        'exeat2' => [
            'driver'   => 'mysql',
            'host'     => defined('EXEAT2_DB_HOST') && EXEAT2_DB_HOST ? EXEAT2_DB_HOST : 'localhost',
            'port'     => defined('EXEAT2_DB_PORT') ? EXEAT2_DB_PORT : '3306',
            'database' => defined('EXEAT2_DB_NAME') && EXEAT2_DB_NAME ? EXEAT2_DB_NAME : 'exeat2',
            'username' => defined('EXEAT2_DB_USER') && EXEAT2_DB_USER ? EXEAT2_DB_USER : 'root',
            'password' => defined('EXEAT2_DB_PASS') ? EXEAT2_DB_PASS : '',
            'charset'  => 'utf8mb4',
            'prefix'   => '',
        ],
    ],
];
