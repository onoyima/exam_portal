-- Migration v2: exeat2 integration — source tracking columns
-- Adds source/external_id to students and examiners, plus email to examiners.

ALTER TABLE students
  ADD COLUMN `source` VARCHAR(20) NOT NULL DEFAULT 'upload'
    COMMENT 'Origin: upload, exeat2, manual',
  ADD COLUMN `external_id` BIGINT(20) UNSIGNED DEFAULT NULL
    COMMENT 'FK to exeat2.students.id if source=exeat2',
  ADD INDEX `idx_source` (`source`),
  ADD INDEX `idx_external_id` (`external_id`);

ALTER TABLE examiners
  ADD COLUMN `source` VARCHAR(20) NOT NULL DEFAULT 'manual'
    COMMENT 'Origin: manual, exeat2, upload',
  ADD COLUMN `external_id` BIGINT(20) UNSIGNED DEFAULT NULL
    COMMENT 'FK to exeat2.staff.id if source=exeat2',
  ADD COLUMN `email` VARCHAR(255) DEFAULT NULL,
  ADD INDEX `idx_source` (`source`),
  ADD INDEX `idx_external_id` (`external_id`);
