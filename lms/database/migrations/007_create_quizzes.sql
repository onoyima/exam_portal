-- UP
CREATE TABLE IF NOT EXISTS lms_quizzes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    instructions TEXT NULL,
    quiz_type ENUM('graded','practice','survey') NOT NULL DEFAULT 'graded',
    max_attempts INT NOT NULL DEFAULT 1,
    time_limit_minutes INT NULL,
    passing_score DECIMAL(5,2) NULL,
    shuffle_questions TINYINT(1) DEFAULT 0,
    shuffle_options TINYINT(1) DEFAULT 0,
    show_results_after ENUM('never','immediately','after_due','after_attempt') DEFAULT 'immediately',
    show_correct_answers TINYINT(1) DEFAULT 1,
    show_points TINYINT(1) DEFAULT 1,
    random_question_count INT NULL,
    due_at DATETIME NULL,
    available_from DATETIME NULL,
    available_until DATETIME NULL,
    is_visible TINYINT(1) DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_quizzes_course (course_id),
    INDEX idx_quizzes_module (module_id),
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES lms_course_modules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_quiz_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id BIGINT UNSIGNED NOT NULL,
    question_type ENUM('mcq','multiple_answer','true_false','short_answer','essay','matching','ordering','fill_blank') NOT NULL,
    question_text TEXT NOT NULL,
    question_image VARCHAR(500) NULL,
    options JSON NULL,
    correct_answer TEXT NULL,
    points DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    feedback_correct TEXT NULL,
    feedback_incorrect TEXT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_questions_quiz (quiz_id),
    INDEX idx_questions_order (quiz_id, order_index),
    FOREIGN KEY (quiz_id) REFERENCES lms_quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_quiz_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    time_taken_seconds INT NULL,
    score DECIMAL(8,2) NULL,
    max_score DECIMAL(8,2) NULL,
    percentage DECIMAL(5,2) NULL,
    passed TINYINT(1) NULL,
    status ENUM('in_progress','completed','reviewed') NOT NULL DEFAULT 'in_progress',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_attempt (quiz_id, user_id, attempt_number),
    INDEX idx_attempts_quiz (quiz_id),
    INDEX idx_attempts_user (user_id),
    FOREIGN KEY (quiz_id) REFERENCES lms_quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES lms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_quiz_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    given_answer TEXT NOT NULL,
    is_correct TINYINT(1) NULL,
    points_earned DECIMAL(8,2) NULL,
    points_possible DECIMAL(8,2) NULL,
    feedback TEXT NULL,
    time_spent_seconds INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_answers_attempt (attempt_id),
    INDEX idx_answers_question (question_id),
    FOREIGN KEY (attempt_id) REFERENCES lms_quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES lms_quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_quiz_answers;
DROP TABLE IF EXISTS lms_quiz_attempts;
DROP TABLE IF EXISTS lms_quiz_questions;
DROP TABLE IF EXISTS lms_quizzes;
