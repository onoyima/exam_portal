-- UP
-- LMS Mail Queue
CREATE TABLE IF NOT EXISTS lms_mail_queue (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `to_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `alt_body` TEXT NULL,
  `data` JSON NULL,
  `attachments` JSON NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` TEXT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  `max_attempts` INT NOT NULL DEFAULT 3,
  `scheduled_at` TIMESTAMP NULL,
  `sent_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `mail_queue_status` (`status`),
  INDEX `mail_queue_scheduled` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN
DROP TABLE IF EXISTS lms_mail_queue;
