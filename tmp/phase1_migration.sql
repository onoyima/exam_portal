-- Phase 1: Database Setup for Enhanced Exam System

-- 1. Device Authorization Engine
CREATE TABLE IF NOT EXISTS `approved_devices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `mac_address` VARCHAR(100) DEFAULT NULL,
  `device_info` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_approved_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Reporting Sessions
CREATE TABLE IF NOT EXISTS `academic_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_name` VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add session linking to exams
ALTER TABLE `exams` ADD COLUMN IF NOT EXISTS `session_id` INT DEFAULT NULL AFTER `id`;
-- Add foreign key if we want strict relation, but usually soft link is fine for now
-- ALTER TABLE `exams` ADD CONSTRAINT `fk_exam_session` FOREIGN KEY (`session_id`) REFERENCES `academic_sessions`(`id`) ON DELETE SET NULL;

-- 3. Anti-Cheat Locking
ALTER TABLE `objective_attempts` ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) DEFAULT 0 AFTER `violation_count`;

-- 4. Unified Session System default entry (To prevent broken UI)
INSERT INTO `academic_sessions` (`session_name`, `is_active`) 
SELECT 'Session 1 (Default)', 1 
WHERE NOT EXISTS (SELECT 1 FROM `academic_sessions`);

-- Assign existing exams to the default session (assuming ID 1 is the newly created one)
UPDATE `exams` SET `session_id` = (SELECT `id` FROM `academic_sessions` LIMIT 1) WHERE `session_id` IS NULL;
