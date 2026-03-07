<?php
/**
 * Audit Trail System for Assessment Portal
 * BMXcode Initiator
 */

class AuditSystem
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->initializeTables();
    }

    /**
     * Initialize audit trail tables if they don't exist
     */
    private function initializeTables()
    {
        // Audit trail table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS audit_trail (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                user_type ENUM('admin', 'examiner', 'student') NOT NULL,
                username VARCHAR(100),
                action VARCHAR(255) NOT NULL,
                table_name VARCHAR(100),
                record_id INT,
                old_values TEXT,
                new_values TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_action (user_id, action),
                INDEX idx_created_at (created_at),
                INDEX idx_table_record (table_name, record_id)
            )
        ");

        // System events table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS system_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(100) NOT NULL,
                event_description TEXT,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
                user_id INT,
                username VARCHAR(100),
                ip_address VARCHAR(45),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_severity (severity),
                INDEX idx_created_at (created_at)
            )
        ");

        // Login history table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS login_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                username VARCHAR(100) NOT NULL,
                user_type ENUM('admin', 'examiner', 'student') NOT NULL,
                login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                logout_time DATETIME NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                status ENUM('success', 'failed') DEFAULT 'success',
                INDEX idx_user_login (user_id, login_time),
                INDEX idx_username (username),
                INDEX idx_status (status)
            )
        ");

        // Export history table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS export_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                username VARCHAR(100),
                export_type VARCHAR(100) NOT NULL,
                file_name VARCHAR(255),
                record_count INT,
                ip_address VARCHAR(45),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_export (user_id, created_at),
                INDEX idx_export_type (export_type)
            )
        ");
    }

    /**
     * Internal helper to log to file as a fallback
     */
    private function logToFile($type, $data)
    {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir))
            mkdir($logDir, 0777, true);

        $fileName = $logDir . '/audit_' . date('Y-m-d') . '.log';
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'data' => $data,
            'ip' => $this->getClientIP(),
            'ua' => $this->getUserAgent()
        ];

        file_put_contents($fileName, json_encode($entry) . PHP_EOL, FILE_APPEND);
    }

    /**
     * Log user action
     */
    public function logAction($userId, $userType, $username, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null)
    {
        $data = [
            $userId,
            $userType,
            $username,
            $action,
            $tableName,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $this->getClientIP(),
            $this->getUserAgent()
        ];

        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute($data);
        } catch (Exception $e) {
            $this->logToFile('ACTION', array_combine(['user_id', 'user_type', 'username', 'action', 'table', 'id', 'old', 'new', 'ip', 'ua'], $data));
            return false;
        }
    }

    /**
     * Log system event
     */
    public function logSystemEvent($eventType, $eventDescription, $severity = 'low', $userId = null, $username = null)
    {
        $data = [$eventType, $eventDescription, $severity, $userId, $username, $this->getClientIP()];
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_events (event_type, event_description, severity, user_id, username, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute($data);
        } catch (Exception $e) {
            $this->logToFile('SYSTEM_EVENT', array_combine(['type', 'desc', 'sev', 'user_id', 'username', 'ip'], $data));
            return false;
        }
    }

    /**
     * Log login attempt
     */
    public function logLogin($userId, $username, $userType, $status = 'success')
    {
        $data = [$userId, $username, $userType, $this->getClientIP(), $this->getUserAgent(), $status];
        try {
            $stmt = $this->db->prepare("
                INSERT INTO login_history (user_id, username, user_type, ip_address, user_agent, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute($data);
        } catch (Exception $e) {
            $this->logToFile('LOGIN', array_combine(['user_id', 'username', 'type', 'ip', 'ua', 'status'], $data));
            return false;
        }
    }

    /**
     * Log logout
     */
    public function logLogout($userId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE login_history 
                SET logout_time = NOW() 
                WHERE user_id = ? AND logout_time IS NULL 
                ORDER BY login_time DESC 
                LIMIT 1
            ");
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            $this->logToFile('LOGOUT', ['user_id' => $userId]);
            return false;
        }
    }

    /**
     * Log data export
     */
    public function logExport($userId, $username, $exportType, $fileName, $recordCount)
    {
        $stmt = $this->db->prepare("
            INSERT INTO export_history (user_id, username, export_type, file_name, record_count, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $userId,
            $username,
            $exportType,
            $fileName,
            $recordCount,
            $this->getClientIP()
        ]);
    }

    /**
     * Get audit trail entries
     */
    public function getAuditTrail($limit = 100, $offset = 0, $filters = [])
    {
        $where = [];
        $params = [];

        if (!empty($filters['user_type'])) {
            $where[] = "user_type = ?";
            $params[] = $filters['user_type'];
        }

        if (!empty($filters['action'])) {
            $where[] = "action LIKE ?";
            $params[] = "%{$filters['action']}%";
        }

        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT * FROM audit_trail 
            {$whereClause}
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get system events
     */
    public function getSystemEvents($limit = 100, $offset = 0)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM system_events 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get login history
     */
    public function getLoginHistory($limit = 100, $offset = 0)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM login_history 
            ORDER BY login_time DESC 
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get export history
     */
    public function getExportHistory($limit = 100, $offset = 0)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM export_history 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get client IP address
     */
    private function getClientIP()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get user agent
     */
    private function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    /**
     * Clean old audit records (older than specified days)
     */
    public function cleanOldRecords($days = 365)
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $this->db->exec("DELETE FROM audit_trail WHERE created_at < '{$date}'");
        $this->db->exec("DELETE FROM system_events WHERE created_at < '{$date}'");
        $this->db->exec("DELETE FROM login_history WHERE login_time < '{$date}'");
        $this->db->exec("DELETE FROM export_history WHERE created_at < '{$date}'");
    }

    /**
     * Get audit statistics
     */
    public function getAuditStats()
    {
        $stats = [];

        // Total actions by user type
        $stmt = $this->db->query("
            SELECT user_type, COUNT(*) as count 
            FROM audit_trail 
            GROUP BY user_type
        ");
        $stats['actions_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent activity (last 7 days)
        $stmt = $this->db->query("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM audit_trail 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stats['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Most active users
        $stmt = $this->db->query("
            SELECT username, user_type, COUNT(*) as action_count 
            FROM audit_trail 
            GROUP BY username, user_type 
            ORDER BY action_count DESC 
            LIMIT 10
        ");
        $stats['active_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }
}

// Initialize audit system
global $audit;
$audit = new AuditSystem($db);
?>