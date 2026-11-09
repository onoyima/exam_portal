-- Remote schema backup of dbnhedghziuzs9 before applying missing objects
-- 2026-11-09T15:17:38+01:00
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `academic_sessions`;
CREATE TABLE `academic_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `answers`;
CREATE TABLE `answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int DEFAULT NULL,
  `question_id` int DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  `examiner_id` int DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  KEY `student_id` (`student_id`),
  KEY `examiner_id` (`examiner_id`),
  KEY `idx_exam_student_question` (`exam_id`,`student_id`,`question_id`),
  KEY `fk_answers_updated_by` (`updated_by`),
  CONSTRAINT `answers_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  CONSTRAINT `answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `answers_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `answers_ibfk_4` FOREIGN KEY (`examiner_id`) REFERENCES `examiners` (`id`),
  CONSTRAINT `fk_answers_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `examiners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17529 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `approved_devices`;
CREATE TABLE `approved_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `mac_address` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `device_info` text COLLATE utf8mb4_general_ci,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_approved_ip` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=703 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `audit_trail`;
CREATE TABLE `audit_trail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `user_type` enum('admin','examiner','student') COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `table_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `old_values` text COLLATE utf8mb4_general_ci,
  `new_values` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_action` (`user_id`,`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=40736 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `receiver_id` int DEFAULT NULL,
  `group_id` int DEFAULT NULL,
  `message` text COLLATE utf8mb4_general_ci,
  `media_type` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `media_blob` longblob,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `examiners` (`id`),
  CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `examiners` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `exam_examiners`;
CREATE TABLE `exam_examiners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int DEFAULT NULL,
  `examiner_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `examiner_id` (`examiner_id`),
  KEY `idx_exam_examiner` (`exam_id`,`examiner_id`),
  CONSTRAINT `exam_examiners_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  CONSTRAINT `exam_examiners_ibfk_2` FOREIGN KEY (`examiner_id`) REFERENCES `examiners` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `exam_questions`;
CREATE TABLE `exam_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int DEFAULT NULL,
  `question_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  KEY `idx_exam_question` (`exam_id`,`question_id`),
  CONSTRAINT `exam_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  CONSTRAINT `exam_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1091 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `exam_students`;
CREATE TABLE `exam_students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`exam_id`,`student_id`),
  KEY `student_id` (`student_id`),
  KEY `idx_exam_student` (`exam_id`,`student_id`),
  CONSTRAINT `exam_students_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  CONSTRAINT `exam_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34015 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `exam_violations`;
CREATE TABLE `exam_violations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attempt_id` int DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  `exam_id` int DEFAULT NULL,
  `violation_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `examiners`;
CREATE TABLE `examiners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'examiner',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `source` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'manual' COMMENT 'Origin: manual, exeat2, upload',
  `external_id` bigint unsigned DEFAULT NULL COMMENT 'FK to exeat2.staff.id if source=exeat2',
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_source` (`source`),
  KEY `idx_external_id` (`external_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `exams`;
CREATE TABLE `exams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` int DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `type` enum('practical','objective','viva') COLLATE utf8mb4_general_ci DEFAULT 'practical',
  `student_can_view_score` tinyint(1) DEFAULT '0',
  `description` text COLLATE utf8mb4_general_ci,
  `total_mark` decimal(6,2) DEFAULT NULL,
  `open_at` datetime DEFAULT NULL,
  `close_at` datetime DEFAULT NULL,
  `duration_minutes` int DEFAULT '0',
  `max_attempts` int DEFAULT '1',
  `score_policy` enum('highest','first','last') COLLATE utf8mb4_general_ci DEFAULT 'highest',
  `shuffle_objective` tinyint(1) DEFAULT '0',
  `questions_to_show` int DEFAULT '0',
  `grading_method` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'highest',
  `is_strict` tinyint(1) DEFAULT '1',
  `shuffle_options` tinyint(1) DEFAULT '1',
  `proctor_fullscreen` tinyint(1) DEFAULT '0',
  `proctor_tab_lock` tinyint(1) DEFAULT '0',
  `proctor_kb_lock` tinyint(1) DEFAULT '0',
  `proctor_auto_submit` tinyint(1) DEFAULT '0',
  `proctor_browser_check` tinyint(1) DEFAULT '0',
  `proctor_webcam` tinyint(1) DEFAULT '0',
  `proctor_live_video` tinyint(1) DEFAULT '0',
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `export_history`;
CREATE TABLE `export_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `export_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `record_count` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_export` (`user_id`,`created_at`),
  KEY `idx_export_type` (`export_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `login_history`;
CREATE TABLE `login_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `user_type` enum('admin','examiner','student') COLLATE utf8mb4_general_ci NOT NULL,
  `login_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `logout_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `status` enum('success','failed') COLLATE utf8mb4_general_ci DEFAULT 'success',
  PRIMARY KEY (`id`),
  KEY `idx_user_login` (`user_id`,`login_time`)
) ENGINE=InnoDB AUTO_INCREMENT=771 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `objective_answers`;
CREATE TABLE `objective_answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `student_id` int NOT NULL,
  `question_id` int NOT NULL,
  `selected_option` char(1) COLLATE utf8mb4_general_ci NOT NULL,
  `submitted_at` datetime NOT NULL,
  `attempt_no` int DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_exam_attempt_question` (`exam_id`,`student_id`,`attempt_no`,`question_id`),
  KEY `exam_id` (`exam_id`),
  KEY `student_id` (`student_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `objective_answers_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `objective_answers_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `objective_answers_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `objective_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11246 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `objective_attempts`;
CREATE TABLE `objective_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `student_id` int NOT NULL,
  `login_id` int DEFAULT NULL,
  `attempt_no` int NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `time_taken` int DEFAULT NULL,
  `question_order` text COLLATE utf8mb4_general_ci,
  `score` decimal(10,2) DEFAULT NULL,
  `violation_count` int DEFAULT '0',
  `is_locked` tinyint(1) DEFAULT '0',
  `last_active` datetime DEFAULT CURRENT_TIMESTAMP,
  `proctor_status` enum('active','blur','disconnected','submitted') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `webcam_snapshot` longtext COLLATE utf8mb4_general_ci,
  `snapshot_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_live_requested` tinyint(1) DEFAULT '0',
  `last_warning_at` datetime DEFAULT NULL,
  `warning_message` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exam_id` (`exam_id`,`student_id`,`attempt_no`)
) ENGINE=InnoDB AUTO_INCREMENT=596 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `objective_questions`;
CREATE TABLE `objective_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `question_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `question_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_a` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `option_a_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_b` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `option_b_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_c` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `option_c_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_d` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `option_d_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correct_option` char(1) COLLATE utf8mb4_general_ci NOT NULL,
  `mark` float DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `objective_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int DEFAULT NULL,
  `text` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `score_values` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1081 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `firstname` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `middlename` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lastname` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `matric_no` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci DEFAULT '12345',
  `current_session_id` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `source` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'upload' COMMENT 'Origin: upload, exeat2, manual',
  `external_id` bigint unsigned DEFAULT NULL COMMENT 'FK to exeat2.students.id if source=exeat2',
  PRIMARY KEY (`id`),
  UNIQUE KEY `matric_no` (`matric_no`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_source` (`source`),
  KEY `idx_external_id` (`external_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3561 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `system_chat_log`;
CREATE TABLE `system_chat_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `user_role` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `user_message` text COLLATE utf8mb4_general_ci NOT NULL,
  `bot_response` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `system_events`;
CREATE TABLE `system_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `event_description` text COLLATE utf8mb4_general_ci,
  `severity` enum('low','medium','high','critical') COLLATE utf8mb4_general_ci DEFAULT 'low',
  `user_id` int DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=734 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `webrtc_signals`;
CREATE TABLE `webrtc_signals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attempt_id` int NOT NULL,
  `from_role` enum('student','admin') COLLATE utf8mb4_general_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS=1;
