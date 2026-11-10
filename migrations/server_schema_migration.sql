-- Server schema migration for CBT portal (idempotent, MySQL 8.0.19+)
-- Run anytime on your production database (the one in .env)

SET FOREIGN_KEY_CHECKS = 0;
SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- 1. examiners table
ALTER TABLE `examiners` 
  ADD COLUMN IF NOT EXISTS `firstname` VARCHAR(190) NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `lastname` VARCHAR(190) NULL AFTER `firstname`,
  ADD COLUMN IF NOT EXISTS `group_id` INT NULL AFTER `lastname`,
  ADD COLUMN IF NOT EXISTS `session_token` VARCHAR(128) NULL AFTER `group_id`,
  ADD COLUMN IF NOT EXISTS `last_seen_at` DATETIME NULL AFTER `session_token`,
  ADD INDEX IF NOT EXISTS `idx_examiners_group` (`group_id`);

-- 2. exams table
ALTER TABLE `exams`
  ADD COLUMN IF NOT EXISTS `partial_scoring` TINYINT(1) NOT NULL DEFAULT 1 AFTER `shuffle_options`,
  ADD COLUMN IF NOT EXISTS `math_notation` TINYINT(1) NOT NULL DEFAULT 0 AFTER `partial_scoring`,
  ADD COLUMN IF NOT EXISTS `late_start_minutes` INT NULL AFTER `end_time`,
  ADD COLUMN IF NOT EXISTS `group_id` INT NULL AFTER `late_start_minutes`,
  ADD COLUMN IF NOT EXISTS `remote_id` INT NULL AFTER `group_id`,
  ADD INDEX IF NOT EXISTS `idx_exams_group` (`group_id`);

-- Fix exams.type enum (add 'research' if missing)
ALTER TABLE `exams` MODIFY COLUMN `type` ENUM('practical','objective','viva','research') DEFAULT 'practical';

-- 3. objective_questions table
ALTER TABLE `objective_questions`
  ADD COLUMN IF NOT EXISTS `question_type` VARCHAR(20) NOT NULL DEFAULT 'mcq_single' AFTER `exam_id`,
  ADD COLUMN IF NOT EXISTS `question_data` TEXT NULL AFTER `question_image`,
  MODIFY COLUMN `correct_option` VARCHAR(64) NOT NULL DEFAULT '',
  MODIFY COLUMN `option_a` VARCHAR(1000) NOT NULL DEFAULT '',
  MODIFY COLUMN `option_b` VARCHAR(1000) NOT NULL DEFAULT '',
  MODIFY COLUMN `option_c` VARCHAR(1000) NOT NULL DEFAULT '',
  MODIFY COLUMN `option_d` VARCHAR(1000) NOT NULL DEFAULT '',
  ADD INDEX IF NOT EXISTS `idx_oq_exam_live` (`exam_id`, `deleted_at`);

-- 4. objective_answers table
ALTER TABLE `objective_answers`
  MODIFY COLUMN `selected_option` VARCHAR(255) NOT NULL DEFAULT '';

-- 5. objective_attempts table
ALTER TABLE `objective_attempts`
  ADD COLUMN IF NOT EXISTS `time_used` INT NOT NULL DEFAULT 0 AFTER `time_taken`,
  ADD COLUMN IF NOT EXISTS `last_tick_at` DATETIME NULL AFTER `time_used`,
  ADD COLUMN IF NOT EXISTS `remote_command` VARCHAR(50) NULL AFTER `last_tick_at`,
  ADD INDEX IF NOT EXISTS `idx_oa_exam_open` (`exam_id`, `finished_at`),
  ADD INDEX IF NOT EXISTS `idx_oa_student_exam` (`student_id`, `exam_id`);

-- 6. exam_violations table
ALTER TABLE `exam_violations` ADD INDEX IF NOT EXISTS `idx_ev_attempt` (`attempt_id`);

-- 7. New tables
CREATE TABLE IF NOT EXISTS `device_approval_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reset_at` DATETIME NOT NULL,
  `reset_by` VARCHAR(100) NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `device_info` TEXT NULL,
  `status` VARCHAR(20) NULL,
  `first_seen` DATETIME NULL,
  `last_seen` DATETIME NULL,
  KEY `idx_dal_reset` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `exam_groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(190) NOT NULL,
  `code` VARCHAR(40) NOT NULL,
  `description` TEXT NULL,
  `activation_opens` DATETIME NULL,
  `activation_closes` DATETIME NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `remote_id` INT NULL,
  `package_version` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  KEY `idx_eg_remote` (`remote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `exam_centers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(190) NOT NULL,
  `code` VARCHAR(40) NOT NULL,
  `location` VARCHAR(190) NULL,
  `manager_name` VARCHAR(190) NULL,
  `manager_contact` VARCHAR(190) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  UNIQUE KEY `uq_center_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `center_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT NOT NULL,
  `center_id` INT NOT NULL,
  `prefix` VARCHAR(12) NOT NULL,
  `pkg_key` CHAR(64) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'issued',
  `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `issued_by` VARCHAR(100) NULL,
  `activated_at` DATETIME NULL,
  `activated_ip` VARCHAR(64) NULL,
  `last_download_at` DATETIME NULL,
  `last_sync_at` DATETIME NULL,
  `sync_count` INT NOT NULL DEFAULT 0,
  `revoked_at` DATETIME NULL,
  UNIQUE KEY `uq_token_prefix` (`prefix`),
  KEY `idx_ct_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `center_students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `token_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  UNIQUE KEY `uq_cs` (`token_id`, `student_id`),
  KEY `idx_cs_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `center_links` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT NOT NULL,
  `local_center_id` INT NOT NULL,
  `remote_center_id` INT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  UNIQUE KEY `uq_cl` (`group_id`, `local_center_id`, `remote_center_id`),
  KEY `idx_cl_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- LMS tables (core ones)
CREATE TABLE IF NOT EXISTS `lms_users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(190) NOT NULL,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `password_hash` VARCHAR(190) NOT NULL,
  `middle_name` VARCHAR(190) NULL,
  `phone` VARCHAR(50) NULL,
  `avatar_url` VARCHAR(500) NULL,
  `timezone` VARCHAR(60) NOT NULL DEFAULT 'Africa/Lagos',
  `locale` VARCHAR(10) NOT NULL DEFAULT 'en',
  `email_verified_at` DATETIME NULL,
  `last_login_at` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `portal_user_id` INT NULL,
  `portal_user_type` VARCHAR(50) NULL,
  `matric_no` VARCHAR(60) NULL,
  `department` VARCHAR(190) NULL,
  `faculty` VARCHAR(190) NULL,
  `course_study` VARCHAR(190) NULL,
  `current_level` VARCHAR(20) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- (other LMS tables created by auto-migration on first request)

SET FOREIGN_KEY_CHECKS = 1;