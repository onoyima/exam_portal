-- UP
CREATE TABLE IF NOT EXISTS lms_classes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NULL,
    course_id INT(11) NOT NULL COMMENT 'exeat2 courses.id',
    lms_course_id BIGINT UNSIGNED NULL COMMENT 'lms_courses.id',
    vu_session_id INT(11) NOT NULL,
    vu_semester_id INT(11) NOT NULL,
    academic_session_id INT(11) NULL,
    level_id INT(11) NULL,
    department_id INT(11) NULL,
    course_study_id INT(11) NULL,
    total_students INT DEFAULT 0,
    total_staff INT DEFAULT 0,
    status ENUM('active','archived','completed') DEFAULT 'active',
    sync_status ENUM('synced','partial','manual') DEFAULT 'synced',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uk_class_offering (course_id, vu_session_id, vu_semester_id),
    INDEX idx_classes_status (status),
    INDEX idx_classes_vu_session (vu_session_id),
    INDEX idx_classes_vu_semester (vu_semester_id),
    INDEX idx_classes_department (department_id),
    INDEX idx_classes_course_study (course_study_id),
    INDEX idx_classes_deleted_at (deleted_at),
    FOREIGN KEY (lms_course_id) REFERENCES lms_courses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_class_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id BIGINT UNSIGNED NOT NULL,
    student_id INT(11) NOT NULL COMMENT 'exeat2 students.id',
    lms_user_id BIGINT UNSIGNED NULL,
    matric_no VARCHAR(50) NULL,
    student_name VARCHAR(255) NULL,
    ca_one DECIMAL(5,2) NULL,
    ca_two DECIMAL(5,2) NULL,
    ca_three DECIMAL(5,2) NULL,
    examination DECIMAL(5,2) NULL,
    total DECIMAL(5,2) NULL,
    grade VARCHAR(5) NULL,
    status ENUM('active','dropped','completed') DEFAULT 'active',
    enrollment_source ENUM('auto_sync','manual') DEFAULT 'auto_sync',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_class_student (class_id, student_id),
    INDEX idx_enrollments_class (class_id),
    INDEX idx_enrollments_student (student_id),
    INDEX idx_enrollments_status (status),
    FOREIGN KEY (class_id) REFERENCES lms_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (lms_user_id) REFERENCES lms_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_class_staff (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id BIGINT UNSIGNED NOT NULL,
    staff_id INT(11) NOT NULL COMMENT 'exeat2 staff.id',
    lms_user_id BIGINT UNSIGNED NULL,
    staff_name VARCHAR(255) NULL,
    role ENUM('primary','assistant','guest') DEFAULT 'assistant',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_class_staff (class_id, staff_id),
    INDEX idx_staff_class (class_id),
    INDEX idx_staff_staff_id (staff_id),
    FOREIGN KEY (class_id) REFERENCES lms_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (lms_user_id) REFERENCES lms_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_class_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NULL,
    date DATE NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    venue VARCHAR(255) NULL,
    topic VARCHAR(255) NULL,
    status ENUM('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sessions_class (class_id),
    INDEX idx_sessions_date (date),
    FOREIGN KEY (class_id) REFERENCES lms_classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_class_sessions;
DROP TABLE IF EXISTS lms_class_staff;
DROP TABLE IF EXISTS lms_class_enrollments;
DROP TABLE IF EXISTS lms_classes;
