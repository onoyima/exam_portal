<?php
/**
 * Performance Configuration for High-Concurrency Exams (1000+ students)
 * Include this in config.php or require once in db.php
 */

// ============================================================
// SESSION HANDLING - Use Redis for 1000+ concurrent users
// ============================================================
if (extension_loaded('redis')) {
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', 'tcp://127.0.0.1:6379?timeout=2.5&read_timeout=2.5');
    // Alternative with auth: 'tcp://user:pass@127.0.0.1:6379'
} elseif (extension_loaded('memcached')) {
    ini_set('session.save_handler', 'memcached');
    ini_set('session.save_path', '127.0.0.1:11211');
}

// Session settings for high concurrency
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 1000);  // Less frequent GC
ini_set('session.gc_maxlifetime', 7200);  // 2 hours
ini_set('session.cookie_lifetime', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // Requires HTTPS
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);

// ============================================================
// OPcache - Critical for PHP performance
// ============================================================
if (function_exists('opcache_get_status')) {
    // These should be set in php.ini, but verify:
    // opcache.enable=1
    // opcache.memory_consumption=256
    // opcache.interned_strings_buffer=16
    // opcache.max_accelerated_files=20000
    // opcache.revalidate_freq=60
    // opcache.fast_shutdown=1
    // opcache.enable_cli=1
}

// ============================================================
// DATABASE CONNECTION POOLING
// ============================================================
// Use persistent connections for high concurrency
// In db.php, use: PDO::ATTR_PERSISTENT => true
// But be careful with max_connections on DB server

// ============================================================
// HEARTBEAT/PING OPTIMIZATION
// ============================================================
// Current: 15s interval → 67 req/s for 1000 students
// Recommended for 1000+: increase to 30s → 33 req/s
define('HEARTBEAT_INTERVAL', 30000);  // 30 seconds (ms)
define('HEARTBEAT_GRACE', 45000);     // 45s grace before violation

// ============================================================
// AUTO-SAVE ANSWER BATCHING
// ============================================================
// Current: 600ms batch → reduce frequency for 1000+
define('ANSWER_FLUSH_DELAY', 2000);  // 2 seconds (was 600ms)
define('ANSWER_MAX_BATCH', 50);       // Max answers per flush

// ============================================================
// WEBCAM - OPTIONAL FOR LARGE EXAMS
// ============================================================
// Disable webcam by default for exams > 500 students
// Override per-exam in exam settings if needed
define('WEBCAM_MAX_STUDENTS', 500);  // Auto-disable above this

// ============================================================
// PHP-FPM RECOMMENDED SETTINGS (php-fpm.d/www.conf)
// ============================================================
/*
pm = dynamic
pm.max_children = 1000        # Handle 1000 concurrent
pm.start_servers = 100
pm.min_spare_servers = 50
pm.max_spare_servers = 200
pm.max_requests = 500         # Recycle to prevent memory leaks
request_terminate_timeout = 60
request_slowlog_timeout = 10
slowlog = /var/log/php-fpm/www-slow.log
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 60
php_admin_value[max_input_vars] = 5000
*/

// ============================================================
// MYSQL/MARIADB RECOMMENDED SETTINGS (my.cnf)
// ============================================================
/*
[mysqld]
max_connections = 1500
innodb_buffer_pool_size = 4G          # 70-80% of RAM
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2    # 1 for ACID, 2 for performance
innodb_flush_method = O_DIRECT
innodb_file_per_table = 1
innodb_read_io_threads = 8
innodb_write_io_threads = 8
query_cache_type = 0                  # Disable for high concurrency
query_cache_size = 0
max_connect_errors = 100000
table_open_cache = 4000
thread_cache_size = 100
*/

// ============================================================
// NGINX RECOMMENDED SETTINGS
// ============================================================
/*
worker_processes auto;
worker_rlimit_nofile 65535;
events {
    worker_connections 4096;
    multi_accept on;
    use epoll;
}
http {
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    keepalive_requests 1000;
    client_max_body_size 50M;
    proxy_read_timeout 120s;
    proxy_send_timeout 120s;
    proxy_buffering off;
}
*/

// ============================================================
// MONITORING ENDPOINTS (add to admin_dashboard)
// ============================================================
function get_system_health(): array {
    $health = [];
    
    // PHP-FPM
    $fpm_status = @file_get_contents('http://127.0.0.1/fpm-status?json');
    if ($fpm_status) {
        $health['fpm'] = json_decode($fpm_status, true);
    }
    
    // Database
    try {
        $pdo = new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME, DB_USER, DB_PASS);
        $stmt = $pdo->query("SHOW STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Max_used_connections')");
        $health['db'] = [];
        foreach ($stmt as $row) {
            $health['db'][$row['Variable_name']] = $row['Value'];
        }
    } catch (Exception $e) {
        $health['db_error'] = $e->getMessage();
    }
    
    // Redis
    if (extension_loaded('redis')) {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $info = $redis->info();
            $health['redis'] = [
                'connected_clients' => $info['connected_clients'] ?? 'N/A',
                'used_memory_human' => $info['used_memory_human'] ?? 'N/A',
                'keyspace_hits' => $info['keyspace_hits'] ?? 'N/A',
                'keyspace_misses' => $info['keyspace_misses'] ?? 'N/A',
            ];
        } catch (Exception $e) {
            $health['redis_error'] = $e->getMessage();
        }
    }
    
    // System
    $health['load'] = sys_getloadavg();
    $health['memory'] = [
        'used' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit'),
    ];
    
    return $health;
}

// Quick health check endpoint
if (isset($_GET['health_check'])) {
    header('Content-Type: application/json');
    echo json_encode(get_system_health());
    exit;
}