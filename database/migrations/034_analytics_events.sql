-- ════════════════════════════════════════════════════════════════════
--  034_analytics_events.sql — First-party analytics (Tier 8)
--  • read_depth: yazıyı %25/%50/%75/%100 kim okudu
--  • time_on_page: gerçek aktif süre
--  • outbound_click: dış link tıklamaları
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `analytics_events` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_type`  VARCHAR(40) NOT NULL,
    `post_id`     BIGINT UNSIGNED NULL,
    `user_id`     BIGINT UNSIGNED NULL,
    `session_hash` VARCHAR(64) NULL,
    `value_int`   INT NULL,
    `value_str`   VARCHAR(500) NULL,
    `meta_json`   LONGTEXT NULL,
    `referer`     VARCHAR(500) NULL,
    `ua_kind`     VARCHAR(40) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `ae_type_idx` (`event_type`),
    KEY `ae_post_idx` (`post_id`),
    KEY `ae_session_idx` (`session_hash`),
    KEY `ae_created_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
