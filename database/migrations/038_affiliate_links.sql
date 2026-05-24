-- ════════════════════════════════════════════════════════════════════
--  038_affiliate_links.sql — Affiliate / Sponsor Link Tracking (Tier 8)
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `affiliate_links` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code`       VARCHAR(40) NOT NULL,
    `label`      VARCHAR(160) NOT NULL,
    `to_url`     VARCHAR(500) NOT NULL,
    `partner`    VARCHAR(120) NULL,
    `commission` DECIMAL(5,2) NULL,
    `click_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `note`       VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `al_code_uniq` (`code`),
    KEY `al_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
