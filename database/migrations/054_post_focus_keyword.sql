-- ════════════════════════════════════════════════════════════════════
--  054_post_focus_keyword.sql — Odak anahtar kelime (Yazı Analizi Faz 1)
--
--  posts tablosuna `focus_keyword` (birincil hedef) + `secondary_keywords`
--  (virgülle ayrılmış ikincil) eklenir. Yazı Analizi bu alanları kullanarak
--  Türkçe kök-eşleşmeli yerleşim + yoğunluk puanı üretir. Panelden girilir.
--
--  IDEMPOTENT.
-- ════════════════════════════════════════════════════════════════════

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'focus_keyword');
SET @sql := IF(@col = 0,
    'ALTER TABLE `posts` ADD COLUMN `focus_keyword` VARCHAR(190) NULL AFTER `meta_description`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col2 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'secondary_keywords');
SET @sql2 := IF(@col2 = 0,
    'ALTER TABLE `posts` ADD COLUMN `secondary_keywords` VARCHAR(500) NULL AFTER `focus_keyword`',
    'SELECT 1');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
