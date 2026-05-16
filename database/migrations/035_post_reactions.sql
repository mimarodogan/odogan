-- ════════════════════════════════════════════════════════════════════
--  035_post_reactions.sql — Emoji Reactions (Tier 8)
--
--  • 6 emoji set: 👍 ❤ 🔥 💡 😮 🙏
--  • Anonim ziyaretçi IP-hash, üye user_id
--
--  IDEMPOTENT. FK adı `post_reactions_post_fk` — eskiden `pr_post_fk`
--  idi ama 011_post_revisions.sql ile çakışıyordu (InnoDB FK adları
--  veritabanı genelinde unique olmalı, errno 121).
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `post_reactions` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `post_id`    BIGINT UNSIGNED NOT NULL,
    `user_id`    BIGINT UNSIGNED NULL,
    `ip_hash`    VARCHAR(64) NULL,
    `reaction`   VARCHAR(20) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `pr_user_post_emoji` (`post_id`, `user_id`, `reaction`),
    UNIQUE KEY `pr_ip_post_emoji` (`post_id`, `ip_hash`, `reaction`),
    KEY `pr_post_idx` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK constraint idempotent — sadece henüz yoksa ekle
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'post_reactions' AND CONSTRAINT_NAME = 'post_reactions_post_fk') = 0,
    'ALTER TABLE `post_reactions` ADD CONSTRAINT `post_reactions_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
