-- UP
-- This table mirrors exam portal users (students + examiners) for LMS compatibility.
-- portal_user_id + portal_user_type link back to the original portal records.
CREATE TABLE IF NOT EXISTS lms_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NULL,
    avatar_url VARCHAR(500) NULL,
    timezone VARCHAR(50) DEFAULT 'Africa/Lagos',
    locale VARCHAR(10) DEFAULT 'en',
    is_active TINYINT(1) DEFAULT 1,
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    portal_user_id INT(11) NULL COMMENT 'FK to students.id or examiners.id from exam portal',
    portal_user_type VARCHAR(20) NULL COMMENT 'student or examiner',
    matric_no VARCHAR(50) NULL COMMENT 'Student matriculation number from exeat2',
    department VARCHAR(255) NULL COMMENT 'Department name from exeat2',
    faculty VARCHAR(255) NULL COMMENT 'Faculty name from exeat2',
    course_study VARCHAR(255) NULL COMMENT 'Program of study from exeat2',
    current_level VARCHAR(20) NULL COMMENT 'Current academic level',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_users_email (email),
    INDEX idx_users_uuid (uuid),
    INDEX idx_users_portal (portal_user_id, portal_user_type),
    INDEX idx_users_matric (matric_no),
    INDEX idx_users_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_users;
