-- ════════════════════════════════════════════════════════════════════
--  030_engagement.sql — Clap + Bookmark + Author Follow (Tier 7)
-- ════════════════════════════════════════════════════════════════════

-- Clap (Medium-vari beğeni) — bir kullanıcı bir yazıya 1-50 arası clap atabilir
CREATE TABLE IF NOT EXISTS `post_claps` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `post_id`    BIGINT UNSIGNED NOT NULL,
    `user_id`    BIGINT UNSIGNED NULL,
    `ip_hash`    VARCHAR(64) NULL,
    `count`      INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `pc_user_post` (`post_id`, `user_id`),
    UNIQUE KEY `pc_ip_post` (`post_id`, `ip_hash`),
    KEY `pc_post_idx` (`post_id`),
    CONSTRAINT `pc_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sunucu-tarafı bookmark — üye girişliyse DB'ye, değilse LocalStorage
CREATE TABLE IF NOT EXISTS `post_bookmarks` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `post_id`    BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `pb_user_post` (`user_id`, `post_id`),
    KEY `pb_user_idx` (`user_id`),
    CONSTRAINT `pb_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pb_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Yazara abone — yeni yazı yayında bildirilir
CREATE TABLE IF NOT EXISTS `author_follows` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `follower_id`   BIGINT UNSIGNED NOT NULL,
    `author_id`     BIGINT UNSIGNED NOT NULL,
    `notify_email`  TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `af_pair` (`follower_id`, `author_id`),
    KEY `af_author_idx` (`author_id`),
    CONSTRAINT `af_follower_fk` FOREIGN KEY (`follower_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `af_author_fk` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
