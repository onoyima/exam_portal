-- UP
CREATE TABLE IF NOT EXISTS lms_course_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    order_index INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_modules_course (course_id),
    INDEX idx_modules_order (course_id, order_index),
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_lessons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content_type ENUM('text','video','h5p','scorm','pdf','quiz','assignment','discussion') NOT NULL DEFAULT 'text',
    content LONGTEXT NULL,
    video_url VARCHAR(500) NULL,
    video_duration INT NULL,
    h5p_id INT NULL,
    scorm_package_id INT NULL,
    file_url VARCHAR(500) NULL,
    duration_minutes INT DEFAULT 0,
    order_index INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    is_required TINYINT(1) DEFAULT 1,
    allow_download TINYINT(1) DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_lessons_module (module_id),
    INDEX idx_lessons_order (module_id, order_index),
    INDEX idx_lessons_slug (slug),
    FOREIGN KEY (module_id) REFERENCES lms_course_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_lessons;
DROP TABLE IF EXISTS lms_course_modules;
