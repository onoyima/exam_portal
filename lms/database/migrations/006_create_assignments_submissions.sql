-- UP
CREATE TABLE IF NOT EXISTS lms_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    instructions TEXT NULL,
    max_points DECIMAL(8,2) NOT NULL DEFAULT 100.00,
    passing_points DECIMAL(8,2) NULL,
    allow_late_submission TINYINT(1) DEFAULT 0,
    late_penalty_percent DECIMAL(5,2) DEFAULT 0.00,
    max_attempts INT DEFAULT 1,
    due_at DATETIME NULL,
    available_from DATETIME NULL,
    available_until DATETIME NULL,
    submission_type ENUM('file','text','url','online_text') NOT NULL DEFAULT 'file',
    allowed_extensions JSON NULL,
    max_file_size INT DEFAULT 52428800,
    allow_group_submission TINYINT(1) DEFAULT 0,
    group_size INT NULL,
    is_visible TINYINT(1) DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_assignments_course (course_id),
    INDEX idx_assignments_module (module_id),
    INDEX idx_assignments_due (due_at),
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES lms_course_modules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT NOT NULL DEFAULT 1,
    content TEXT NULL,
    file_url VARCHAR(500) NULL,
    file_size INT NULL,
    file_type VARCHAR(100) NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_late TINYINT(1) DEFAULT 0,
    grade DECIMAL(8,2) NULL,
    graded_by BIGINT UNSIGNED NULL,
    graded_at DATETIME NULL,
    feedback TEXT NULL,
    plagiarism_score DECIMAL(5,2) NULL,
    status ENUM('submitted','graded','returned') NOT NULL DEFAULT 'submitted',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_submission (assignment_id, user_id, attempt_number),
    INDEX idx_submissions_assignment (assignment_id),
    INDEX idx_submissions_user (user_id),
    INDEX idx_submissions_status (status),
    FOREIGN KEY (assignment_id) REFERENCES lms_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES lms_users(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES lms_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_submissions;
DROP TABLE IF EXISTS lms_assignments;
