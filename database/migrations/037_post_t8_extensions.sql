-- ════════════════════════════════════════════════════════════════════
--  037_post_t8_extensions.sql — Project Portfolio + Before/After (Tier 8)
--  IDEMPOTENT — INFORMATION_SCHEMA ile her kolon kontrol edilir.
-- ════════════════════════════════════════════════════════════════════

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'project_data_json') = 0,
    'ALTER TABLE `posts` ADD COLUMN `project_data_json` LONGTEXT NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'gallery_json') = 0,
    'ALTER TABLE `posts` ADD COLUMN `gallery_json` LONGTEXT NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'before_after_json') = 0,
    'ALTER TABLE `posts` ADD COLUMN `before_after_json` LONGTEXT NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'reaction_count') = 0,
    'ALTER TABLE `posts` ADD COLUMN `reaction_count` INT UNSIGNED NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
