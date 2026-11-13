-- Staff Promotional Examination feature
-- Completely isolated from the existing exams/students/examiners system.
-- No foreign keys reference the core exam tables; nothing existing is modified.
-- Run once on the promo DB (dbnhedghziuzs9) via run_migration.php, or the PHP
-- pages below will auto-create these tables on first request.

CREATE TABLE IF NOT EXISTS `promo_staff` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `source` ENUM('manual','upload','exeat2') NOT NULL DEFAULT 'manual',
    `external_id` VARCHAR(100) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_promo_staff_email` (`email`),
    KEY `idx_promo_staff_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promo_exams` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `type` ENUM('objective','subjective') NOT NULL DEFAULT 'objective',
    `instructions` TEXT NULL,
    `start_time` DATETIME NULL,
    `end_time` DATETIME NULL,
    `duration_minutes` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_promo_exam_active` (`is_active`, `start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promo_exam_staff` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `exam_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `enrolled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_promo_exam_staff` (`exam_id`, `staff_id`),
    KEY `idx_promo_exam_staff_staff` (`staff_id`),
    CONSTRAINT `fk_pes_exam` FOREIGN KEY (`exam_id`) REFERENCES `promo_exams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pes_staff` FOREIGN KEY (`staff_id`) REFERENCES `promo_staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promo_exam_questions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `exam_id` INT NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM('objective','subjective') NOT NULL DEFAULT 'objective',
    `options_json` TEXT NULL,
    `correct_answer` TEXT NULL,
    `marks` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_peq_exam` (`exam_id`, `sort_order`),
    CONSTRAINT `fk_peq_exam` FOREIGN KEY (`exam_id`) REFERENCES `promo_exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promo_exam_attempts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `exam_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `attempt_no` INT NOT NULL DEFAULT 1,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finished_at` DATETIME NULL,
    `is_submitted` TINYINT(1) NOT NULL DEFAULT 0,
    `score` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pea_staff_exam` (`staff_id`, `exam_id`),
    KEY `idx_pea_open` (`exam_id`, `finished_at`),
    CONSTRAINT `fk_pea_exam` FOREIGN KEY (`exam_id`) REFERENCES `promo_exams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pea_staff` FOREIGN KEY (`staff_id`) REFERENCES `promo_staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promo_exam_answers` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `attempt_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `selected_option` CHAR(1) NULL,
    `answer_text` TEXT NULL,
    `score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_promo_answer` (`attempt_id`, `question_id`),
    KEY `idx_pea_question` (`question_id`),
    CONSTRAINT `fk_pa_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `promo_exam_attempts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pa_question` FOREIGN KEY (`question_id`) REFERENCES `promo_exam_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
