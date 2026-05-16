-- ════════════════════════════════════════════════════════════════════
--  039_projects.sql — Mimari Proje Portfolyosu (Tier 9)
--
--  • Yeni `projects` tablosu: ad, slug, lokasyon (lat/lng), yıl,
--    rol (müellif/danışman/kontrol), yüzölçümü, müşteri, partner JSON,
--    galeri JSON, kapak görsel
--  • `posts.project_id` → bir proje hakkında yazılan makale (opsiyonel)
--  • İndeks: status + published_at, year_completed
--
--  Feature flag: features.project_portfolio_enabled (default false)
--  IDEMPOTENT — phpMyAdmin'de güvenli çalışır.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `projects` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`             VARCHAR(220) NOT NULL,
    `slug`             VARCHAR(240) NOT NULL,
    `subtitle`         VARCHAR(255) NULL,
    `description`      LONGTEXT NULL,
    `cover_image`      VARCHAR(255) NULL,
    `location`         VARCHAR(180) NULL,
    `lat`              DECIMAL(10, 7) NULL,
    `lng`              DECIMAL(10, 7) NULL,
    `year_started`     SMALLINT UNSIGNED NULL,
    `year_completed`   SMALLINT UNSIGNED NULL,
    `surface_m2`       INT UNSIGNED NULL,
    `role`             ENUM('arsitekt','musavir','kontrol','danisman','arastirma','diger') NOT NULL DEFAULT 'arsitekt',
    `client`           VARCHAR(180) NULL,
    `partners_json`    LONGTEXT NULL,
    `gallery_json`     LONGTEXT NULL,
    `tags_json`        LONGTEXT NULL,
    `links_json`       LONGTEXT NULL,
    `status`           ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `featured`         TINYINT(1) NOT NULL DEFAULT 0,
    `view_count`       INT UNSIGNED NOT NULL DEFAULT 0,
    `meta_title`       VARCHAR(180) NULL,
    `meta_description` VARCHAR(255) NULL,
    `published_at`     DATETIME NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `projects_slug_uniq` (`slug`),
    KEY `projects_status_pub_idx` (`status`, `published_at`),
    KEY `projects_year_idx` (`year_completed`),
    KEY `projects_geo_idx` (`lat`, `lng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- posts.project_id (bir yazı bir projeyle ilişkilendirilebilir)
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'project_id') = 0,
    'ALTER TABLE `posts` ADD COLUMN `project_id` INT UNSIGNED NULL AFTER `series_position`', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- posts.project_id index
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND INDEX_NAME = 'posts_project_idx') = 0,
    'ALTER TABLE `posts` ADD KEY `posts_project_idx` (`project_id`)', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- posts.project_id FK
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND CONSTRAINT_NAME = 'posts_project_fk') = 0,
    'ALTER TABLE `posts` ADD CONSTRAINT `posts_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
