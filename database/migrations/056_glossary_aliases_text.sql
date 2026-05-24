-- ════════════════════════════════════════════════════════════════════
--  056_glossary_aliases_text.sql — glossary.aliases → TEXT
--
--  AI taslak üreteci artık ansiklopedik yapıda girdi üretebiliyor;
--  buna bağlı olarak eş anlamlı listesi 8'den 15'e çıkarıldı. CSV string
--  uzunluğu 500 karakter sınırını aşabiliyor (özellikle iki-dilli
--  varyantlarda: TR + EN + LA). VARCHAR(500) → TEXT.
--
--  IDEMPOTENT — sadece hâlâ VARCHAR ise değiştirir.
-- ════════════════════════════════════════════════════════════════════

SET @t := (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'glossary'
      AND COLUMN_NAME = 'aliases');

SET @sql := IF(@t = 'varchar',
    'ALTER TABLE `glossary` MODIFY COLUMN `aliases` TEXT NULL',
    'SELECT 1');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
