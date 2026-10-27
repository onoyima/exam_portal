-- UP
CREATE TABLE IF NOT EXISTS lms_grade_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    item_type ENUM('assignment','quiz','participation','manual','final_exam') NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    max_points DECIMAL(8,2) NOT NULL DEFAULT 100.00,
    weight DECIMAL(5,2) NULL,
    category VARCHAR(100) NULL,
    due_date DATETIME NULL,
    is_extra_credit TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_grade_items_course (course_id),
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_gradebook (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    grade_item_id BIGINT UNSIGNED NOT NULL,
    points_earned DECIMAL(8,2) NULL,
    percentage DECIMAL(5,2) NULL,
    letter_grade VARCHAR(2) NULL,
    comments TEXT NULL,
    graded_by BIGINT UNSIGNED NULL,
    graded_at DATETIME NULL,
    is_override TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_grade (user_id, grade_item_id),
    INDEX idx_gradebook_user (user_id),
    INDEX idx_gradebook_course (course_id),
    INDEX idx_gradebook_item (grade_item_id),
    FOREIGN KEY (user_id) REFERENCES lms_users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (grade_item_id) REFERENCES lms_grade_items(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES lms_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_gradebook;
DROP TABLE IF EXISTS lms_grade_items;
