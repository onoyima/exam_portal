-- UP
CREATE TABLE IF NOT EXISTS lms_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    enrollment_type ENUM('student','ta','audit') NOT NULL DEFAULT 'student',
    status ENUM('active','completed','dropped','pending') NOT NULL DEFAULT 'active',
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    dropped_at DATETIME NULL,
    grade DECIMAL(5,2) NULL,
    letter_grade VARCHAR(2) NULL,
    gpa_value DECIMAL(3,2) NULL,
    progress_percent DECIMAL(5,2) DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_enrollment (user_id, course_id, enrollment_type),
    INDEX idx_enrollments_user (user_id),
    INDEX idx_enrollments_course (course_id),
    INDEX idx_enrollments_status (status),
    FOREIGN KEY (user_id) REFERENCES lms_users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_enrollments;
