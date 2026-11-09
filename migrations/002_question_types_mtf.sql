-- Question types (single, multiple answer, true/false, multiple true/false),
-- preserved exam time and supporting indexes.
--
-- The portal applies these changes by itself on the first request after the
-- update (see qe_ensure_schema in includes/question_engine.php). This file is
-- for DBAs who prefer to run the change by hand; run it once, then the
-- automatic check will find nothing to do.

ALTER TABLE objective_questions
    ADD COLUMN question_type VARCHAR(20) NOT NULL DEFAULT 'mcq_single' AFTER exam_id,
    ADD COLUMN question_data TEXT NULL AFTER question_image,
    MODIFY correct_option VARCHAR(64) NOT NULL DEFAULT '',
    MODIFY option_a VARCHAR(1000) NOT NULL DEFAULT '',
    MODIFY option_b VARCHAR(1000) NOT NULL DEFAULT '',
    MODIFY option_c VARCHAR(1000) NOT NULL DEFAULT '',
    MODIFY option_d VARCHAR(1000) NOT NULL DEFAULT '',
    ADD INDEX idx_oq_exam_live (exam_id, deleted_at);

ALTER TABLE objective_answers
    MODIFY selected_option VARCHAR(255) NOT NULL DEFAULT '';

ALTER TABLE objective_attempts
    ADD COLUMN time_used INT NOT NULL DEFAULT 0 AFTER time_taken,
    ADD COLUMN last_tick_at DATETIME NULL AFTER time_used,
    ADD COLUMN remote_command VARCHAR(50) NULL,
    ADD INDEX idx_oa_exam_open (exam_id, finished_at),
    ADD INDEX idx_oa_student_exam (student_id, exam_id);

UPDATE objective_attempts SET time_used = COALESCE(time_taken, 0) WHERE finished_at IS NOT NULL;
UPDATE objective_attempts
   SET time_used = LEAST(GREATEST(TIMESTAMPDIFF(SECOND, started_at, NOW()), 0), 86400), last_tick_at = NOW()
 WHERE finished_at IS NULL AND started_at IS NOT NULL;

ALTER TABLE exams
    ADD COLUMN partial_scoring TINYINT(1) NOT NULL DEFAULT 1 AFTER shuffle_options;

ALTER TABLE exam_violations
    ADD INDEX idx_ev_attempt (attempt_id);

-- Late-entry cutoff, Research exams, device approval history
ALTER TABLE exams ADD COLUMN late_start_minutes INT NULL DEFAULT NULL AFTER end_time;
ALTER TABLE exams MODIFY type ENUM('practical','objective','viva','research') DEFAULT 'practical';
CREATE TABLE IF NOT EXISTS device_approval_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reset_at DATETIME NOT NULL,
    reset_by VARCHAR(100) NULL,
    ip_address VARCHAR(45) NOT NULL,
    device_info TEXT NULL,
    status VARCHAR(20) NULL,
    first_seen DATETIME NULL,
    last_seen DATETIME NULL,
    KEY idx_dal_reset (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Maths & science notation per objective exam
ALTER TABLE exams ADD COLUMN math_notation TINYINT(1) NOT NULL DEFAULT 0 AFTER partial_scoring;

-- Grouped examinations and exam centres (main server and centre servers).
-- The application creates these itself on first load; this is for manual installs.
CREATE TABLE IF NOT EXISTS exam_groups (
    id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, code VARCHAR(40) NOT NULL, description TEXT NULL,
    activation_opens DATETIME NULL, activation_closes DATETIME NULL, exam_ends DATETIME NULL, ended_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft', remote_id INT NULL, package_version INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, deleted_at DATETIME NULL, KEY idx_eg_remote (remote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE exams ADD COLUMN group_id INT NULL DEFAULT NULL, ADD INDEX idx_exams_group (group_id), ADD COLUMN remote_id INT NULL DEFAULT NULL;
CREATE TABLE IF NOT EXISTS exam_centers (
    id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, code VARCHAR(40) NOT NULL, location VARCHAR(190) NULL,
    manager_name VARCHAR(190) NULL, manager_contact VARCHAR(190) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL, UNIQUE KEY uq_center_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS center_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY, group_id INT NOT NULL, center_id INT NOT NULL, prefix VARCHAR(12) NOT NULL, pkg_key CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'issued', issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, issued_by VARCHAR(100) NULL,
    activated_at DATETIME NULL, activated_ip VARCHAR(64) NULL, last_download_at DATETIME NULL, last_sync_at DATETIME NULL,
    sync_count INT NOT NULL DEFAULT 0, revoked_at DATETIME NULL, UNIQUE KEY uq_token_prefix (prefix), KEY idx_ct_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS center_students (
    id INT AUTO_INCREMENT PRIMARY KEY, token_id INT NOT NULL, student_id INT NOT NULL, UNIQUE KEY uq_cs (token_id, student_id), KEY idx_cs_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS center_links (
    id INT AUTO_INCREMENT PRIMARY KEY, group_id INT NOT NULL, main_url VARCHAR(255) NULL, prefix VARCHAR(12) NOT NULL, pkg_key CHAR(64) NOT NULL,
    center_name VARCHAR(190) NULL, center_code VARCHAR(40) NULL, package_version INT NULL, activated_at DATETIME NULL,
    last_sync_at DATETIME NULL, last_sync_status TEXT NULL, UNIQUE KEY uq_link_prefix (prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sync_map (
    id INT AUTO_INCREMENT PRIMARY KEY, link_id INT NOT NULL, entity VARCHAR(20) NOT NULL, remote_id INT NOT NULL, local_id INT NOT NULL,
    UNIQUE KEY uq_sync_map (link_id, entity, remote_id), KEY idx_sync_local (entity, local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY, token_id INT NULL, link_id INT NULL, action VARCHAR(30) NOT NULL, ok TINYINT(1) NOT NULL DEFAULT 1,
    summary TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_sl_token (token_id), KEY idx_sl_link (link_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE examiners ADD COLUMN firstname VARCHAR(100) NULL, ADD COLUMN lastname VARCHAR(100) NULL,
    ADD COLUMN group_id INT NULL DEFAULT NULL, ADD INDEX idx_examiners_group (group_id),
    ADD COLUMN session_token VARCHAR(128) NULL, ADD COLUMN last_seen_at DATETIME NULL;
