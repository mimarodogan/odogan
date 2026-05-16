-- ════════════════════════════════════════════════════════════════════
--  036_active_sessions.sql — Active Session Tracker (Tier 8)
--  • Üye hangi cihazdan login olduğunu görür, uzakta çıkış yapabilir.
--
--  IDEMPOTENT — FK ayrı idempotent ALTER ile eklenir.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `session_id`   VARCHAR(128) NOT NULL,
    `ip_address`   VARCHAR(45) NULL,
    `user_agent`   VARCHAR(500) NULL,
    `device_kind`  VARCHAR(40) NULL,
    `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `us_session_uniq` (`session_id`),
    KEY `us_user_idx` (`user_id`),
    KEY `us_last_seen_idx` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_sessions' AND CONSTRAINT_NAME = 'user_sessions_user_fk') = 0,
    'ALTER TABLE `user_sessions` ADD CONSTRAINT `user_sessions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
