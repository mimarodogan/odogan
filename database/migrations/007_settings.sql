CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_name` VARCHAR(60) NOT NULL DEFAULT 'general',
    `key_name` VARCHAR(120) NOT NULL,
    `value` LONGTEXT NULL,
    `value_type` ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `settings_group_key_unique` (`group_name`, `key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
