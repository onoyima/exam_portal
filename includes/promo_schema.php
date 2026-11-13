<?php
/**
 * includes/promo_schema.php
 *
 * Auto-creates the Staff Promotional Examination tables on first request so the
 * feature works without a manual migration. Tables are isolated from the core
 * exams/students/examiners system; nothing existing is touched.
 *
 * Safe to include on every request: a flag file prevents re-running.
 */

if (!defined('DB_NAME')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../db.php';
}

function promo_ensure_schema(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $flag = dirname(__DIR__) . '/logs/.promo_schema_v1';
    if (is_file($flag)) {
        return;
    }

    try {
        $db->query("SELECT GET_LOCK('promo_schema_upgrade', 20)");

        $db->exec("CREATE TABLE IF NOT EXISTS `promo_staff` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `promo_exams` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `promo_exam_staff` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `exam_id` INT NOT NULL,
            `staff_id` INT NOT NULL,
            `enrolled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_promo_exam_staff` (`exam_id`, `staff_id`),
            KEY `idx_promo_exam_staff_staff` (`staff_id`),
            CONSTRAINT `fk_pes_exam` FOREIGN KEY (`exam_id`) REFERENCES `promo_exams` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pes_staff` FOREIGN KEY (`staff_id`) REFERENCES `promo_staff` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `promo_exam_questions` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `promo_exam_attempts` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `promo_exam_answers` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!is_dir(dirname($flag))) {
            @mkdir(dirname($flag), 0755, true);
        }
        @file_put_contents($flag, date('c'));
        $db->query("SELECT RELEASE_LOCK('promo_schema_upgrade')");
    } catch (Throwable $e) {
        try { $db->query("SELECT RELEASE_LOCK('promo_schema_upgrade')"); } catch (Throwable $e2) {}
        error_log('Promo schema auto-create failed: ' . $e->getMessage());
    }
}
