-- ════════════════════════════════════════════════════════════════════
--  043_sponsor_slots.sql — Newsletter Sponsor Slot (Tier 9)
--
--  Bültenin altında (veya yazı sayfasında) görünen sponsor banner'ı.
--  Tıklama sayacı + dönem tarihleri ile yönetilir.
--
--  Feature flag: features.sponsor_slot_enabled (default false)
--  IDEMPOTENT
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `sponsor_slots` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(180) NOT NULL,
    `tagline`       VARCHAR(255) NULL,
    `image_url`     VARCHAR(500) NULL,
    `target_url`    VARCHAR(500) NOT NULL,
    `placement`     ENUM('newsletter','sidebar','below_post','header') NOT NULL DEFAULT 'newsletter',
    `weight`        INT UNSIGNED NOT NULL DEFAULT 1,
    `view_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `click_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `starts_at`     DATETIME NULL,
    `ends_at`       DATETIME NULL,
    `active`        TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `ss_active_idx` (`active`, `placement`, `starts_at`, `ends_at`),
    KEY `ss_weight_idx` (`weight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
