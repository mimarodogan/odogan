-- ════════════════════════════════════════════════════════════════════
--  023_series.sql — Series / Dizi yazılar (Tier 5)
--
--  • Yeni `series` tablosu: ad, slug, açıklama, kapak, post sayısı
--  • `posts` tablosuna `series_id` ve `series_position` kolonları
--  • Kompozit index series_id + series_position → "Bölüm N" sıralı sorgu
--
--  Feature flag: features.series_enabled (default false)
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `series` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`         VARCHAR(180) NOT NULL,
    `slug`         VARCHAR(220) NOT NULL,
    `description`  TEXT NULL,
    `cover_image`  VARCHAR(255) NULL,
    `post_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `series_slug_uniq` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IDEMPOTENT ALTER'lar
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'series_id') = 0,
    'ALTER TABLE `posts` ADD COLUMN `series_id` INT UNSIGNED NULL AFTER `category_id`', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'series_position') = 0,
    'ALTER TABLE `posts` ADD COLUMN `series_position` INT UNSIGNED NULL AFTER `series_id`', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND INDEX_NAME = 'posts_series_idx') = 0,
    'ALTER TABLE `posts` ADD KEY `posts_series_idx` (`series_id`, `series_position`)', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND CONSTRAINT_NAME = 'posts_series_fk') = 0,
    'ALTER TABLE `posts` ADD CONSTRAINT `posts_series_fk` FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE SET NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
