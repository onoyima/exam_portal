-- Phase 4: System Health Monitoring
ALTER TABLE `objective_attempts` ADD COLUMN IF NOT EXISTS `battery_level` INT DEFAULT NULL AFTER `is_locked`;
ALTER TABLE `objective_attempts` ADD COLUMN IF NOT EXISTS `network_type` VARCHAR(50) DEFAULT NULL AFTER `battery_level`;
