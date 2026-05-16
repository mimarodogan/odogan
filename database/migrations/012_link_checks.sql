CREATE TABLE IF NOT EXISTS `link_checks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` BIGINT UNSIGNED NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `status_code` SMALLINT NOT NULL DEFAULT 0,
    `error` VARCHAR(255) NULL,
    `last_checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `lc_post_url_unique` (`post_id`, `url`),
    KEY `lc_status_idx` (`status_code`),
    KEY `lc_resolved_idx` (`resolved`),
    CONSTRAINT `lc_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
