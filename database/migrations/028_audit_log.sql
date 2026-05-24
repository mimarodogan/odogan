-- ════════════════════════════════════════════════════════════════════
--  028_audit_log.sql — Admin Audit Log (Tier 7)
--  • Hassas admin işlemlerini kayıt eder: rol değişimi, post silme, vb.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor_id`   BIGINT UNSIGNED NULL,
    `actor_name` VARCHAR(120) NULL,
    `action`     VARCHAR(80)  NOT NULL,
    `target_type` VARCHAR(60) NULL,
    `target_id`  BIGINT UNSIGNED NULL,
    `summary`    VARCHAR(500) NULL,
    `meta_json`  LONGTEXT NULL,
    `ip_address` VARCHAR(45)  NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `al_actor_idx` (`actor_id`),
    KEY `al_action_idx` (`action`),
    KEY `al_created_idx` (`created_at`),
    KEY `al_target_idx` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
