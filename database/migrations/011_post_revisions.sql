CREATE TABLE IF NOT EXISTS `post_revisions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `title` VARCHAR(220) NOT NULL,
    `excerpt` VARCHAR(500) NULL,
    `body` MEDIUMTEXT NOT NULL,
    `body_format` ENUM('markdown','html') NOT NULL DEFAULT 'markdown',
    `faq_json` JSON NULL,
    `note` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `pr_post_idx` (`post_id`),
    KEY `pr_user_idx` (`user_id`),
    CONSTRAINT `pr_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `pr_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
