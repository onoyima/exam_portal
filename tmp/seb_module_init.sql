-- Standalone Secure Gate Module Migration
CREATE TABLE IF NOT EXISTS `seb_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `config_key` varchar(255) DEFAULT NULL,
    `quit_password` varchar(255) DEFAULT 'SECURE-EXIT-PA',
    `is_mandatory` tinyint(1) DEFAULT '0',
    `allowed_browsers` text,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default settings
INSERT INTO `seb_settings` (config_key, quit_password, is_mandatory) 
VALUES ('VERITAS-SECURE-KEY-HASH-V1', 'VERITAS_EXIT_2024', 0);
