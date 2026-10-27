-- UP
-- LMS Courses extended with portal course references and academic context.
-- portal_course_id links to exeat2.courses.id
-- exam_id links to backup_db_pre_implementation.exams.id
CREATE TABLE IF NOT EXISTS lms_course_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    parent_id INT UNSIGNED NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES lms_course_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_courses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    code VARCHAR(20) NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    short_description VARCHAR(500) NULL,
    category_id INT UNSIGNED NULL,
    thumbnail_url VARCHAR(500) NULL,
    banner_url VARCHAR(500) NULL,
    level VARCHAR(50) NULL,
    credits INT DEFAULT 3,
    duration_weeks INT NULL,
    prerequisites JSON NULL,
    learning_outcomes JSON NULL,
    syllabus TEXT NULL,
    status ENUM('draft','published','archived') DEFAULT 'draft',
    is_required TINYINT(1) DEFAULT 0,
    max_students INT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    enrollment_start DATETIME NULL,
    enrollment_end DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    portal_course_id INT(11) NULL COMMENT 'FK to exeat2.courses.id for academic course sync',
    exam_id INT(11) NULL COMMENT 'FK to backup_db_pre_implementation.exams.id for exam linking',
    vu_session_id INT(11) NULL COMMENT 'FK to exeat2.vu_sessions.id',
    academic_session_id INT(11) NULL COMMENT 'FK to exeat2.academic_sessions.id',
    department_id INT(11) NULL COMMENT 'FK to exeat2.departments.id',
    course_study_id INT(11) NULL COMMENT 'FK to exeat2.course_studies.id',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_courses_status (status),
    INDEX idx_courses_category (category_id),
    INDEX idx_courses_slug (slug),
    INDEX idx_courses_portal (portal_course_id),
    INDEX idx_courses_exam (exam_id),
    INDEX idx_courses_department (department_id),
    INDEX idx_courses_created_by (created_by),
    INDEX idx_courses_deleted_at (deleted_at),
    FOREIGN KEY (category_id) REFERENCES lms_course_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_course_instructors (
    course_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('primary','assistant','guest') DEFAULT 'primary',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, user_id),
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES lms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_course_instructors;
DROP TABLE IF EXISTS lms_courses;
DROP TABLE IF EXISTS lms_course_categories;
