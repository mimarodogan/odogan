CREATE TABLE IF NOT EXISTS `logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `level` ENUM('debug','info','notice','warning','error','critical','alert','emergency') NOT NULL DEFAULT 'info',
    `channel` VARCHAR(60) NOT NULL DEFAULT 'app',
    `message` VARCHAR(500) NOT NULL,
    `context_json` JSON NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `request_uri` VARCHAR(500) NULL,
    `log_date` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `logs_level_idx` (`level`),
    KEY `logs_channel_idx` (`channel`),
    KEY `logs_date_idx` (`log_date`),
    KEY `logs_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
