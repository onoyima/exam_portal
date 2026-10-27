-- UP
CREATE TABLE IF NOT EXISTS lms_analytics_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event VARCHAR(100) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    course_id BIGINT UNSIGNED NULL,
    entity_type VARCHAR(50) NULL,
    entity_id BIGINT UNSIGNED NULL,
    payload JSON NULL,
    session_id VARCHAR(100) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ae_event (event, created_at),
    INDEX idx_ae_user (user_id, created_at),
    INDEX idx_ae_course (course_id, created_at),
    INDEX idx_ae_entity (entity_type, entity_id),
    FOREIGN KEY (user_id) REFERENCES lms_users(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_analytics_events;
