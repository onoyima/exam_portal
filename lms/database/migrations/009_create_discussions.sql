-- UP
CREATE TABLE IF NOT EXISTS lms_discussion_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_disc_cat_course (course_id),
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_discussion_threads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    module_id BIGINT UNSIGNED NULL,
    lesson_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    is_announcement TINYINT(1) DEFAULT 0,
    is_closed TINYINT(1) DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_threads_course (course_id),
    INDEX idx_threads_category (category_id),
    INDEX idx_threads_pinned (is_pinned, created_at),
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES lms_discussion_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (module_id) REFERENCES lms_course_modules(id) ON DELETE SET NULL,
    FOREIGN KEY (lesson_id) REFERENCES lms_lessons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_discussion_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    content MEDIUMTEXT NOT NULL,
    is_solution TINYINT(1) DEFAULT 0,
    is_edited TINYINT(1) DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_replies_thread (thread_id),
    INDEX idx_replies_parent (parent_id),
    FOREIGN KEY (thread_id) REFERENCES lms_discussion_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES lms_discussion_replies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_discussion_replies;
DROP TABLE IF EXISTS lms_discussion_threads;
DROP TABLE IF EXISTS lms_discussion_categories;
