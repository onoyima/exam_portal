-- UP
CREATE TABLE IF NOT EXISTS lms_exam_sync_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    direction ENUM('lms_to_portal','portal_to_lms') NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    request_payload JSON NULL,
    response_payload JSON NULL,
    status_code INT NULL,
    status ENUM('success','failed','pending') NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    retry_count INT DEFAULT 0,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sync_status (status),
    INDEX idx_sync_entity (entity_type, entity_id),
    INDEX idx_sync_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_exam_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lms_quiz_id BIGINT UNSIGNED NULL,
    portal_exam_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NULL,
    sync_status ENUM('linked','unlinked','pending_sync') DEFAULT 'linked',
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_exam_link (portal_exam_id, course_id),
    INDEX idx_exam_links_quiz (lms_quiz_id),
    INDEX idx_exam_links_course (course_id),
    FOREIGN KEY (lms_quiz_id) REFERENCES lms_quizzes(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_exam_links;
DROP TABLE IF EXISTS lms_exam_sync_log;
