-- ════════════════════════════════════════════════════════════════════
--  032_redirects_404.sql — 301 redirect manager + 404 logger (Tier 7)
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `redirects` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `from_path`  VARCHAR(500) NOT NULL,
    `to_url`     VARCHAR(500) NOT NULL,
    `code`       SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    `hit_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `note`       VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `r_from_uniq` (`from_path`),
    KEY `r_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `not_found_log` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `path`       VARCHAR(500) NOT NULL,
    `referer`    VARCHAR(500) NULL,
    `user_agent` VARCHAR(255) NULL,
    `hit_count`  INT UNSIGNED NOT NULL DEFAULT 1,
    `first_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resolved`   TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY `nf_path_uniq` (`path`),
    KEY `nf_last_seen_idx` (`last_seen`),
    KEY `nf_resolved_idx` (`resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
