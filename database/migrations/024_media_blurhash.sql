-- ════════════════════════════════════════════════════════════════════
--  024_media_blurhash.sql — BlurHash placeholder (Tier 5)
--  IDEMPOTENT
-- ════════════════════════════════════════════════════════════════════

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media' AND COLUMN_NAME = 'blurhash') = 0,
    'ALTER TABLE `media` ADD COLUMN `blurhash` VARCHAR(40) NULL AFTER `variants_json`', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
