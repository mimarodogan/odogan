-- ════════════════════════════════════════════════════════════════════
--  055_glossary_references_text.sql — glossary.references → TEXT
--
--  Sözlük girdisinin "Kaynaklar" alanı, yazı editöründeki dipnot
--  (footnote) pattern'ine uygun olarak çoklu satır {text, url} olarak
--  JSON saklanacak. VARCHAR(500) bunun için dar kalır; TEXT'e çıkarıyoruz.
--
--  Eski biçim ('A; B; https://x') geriye dönük uyumlu kalır:
--  controller okurken JSON parse dener, başarısızsa `;` ile ayırır.
--
--  IDEMPOTENT — tipi sadece bugün hâlâ VARCHAR ise değiştirir.
-- ════════════════════════════════════════════════════════════════════

SET @t := (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'glossary'
      AND COLUMN_NAME = 'references');

SET @sql := IF(@t = 'varchar',
    'ALTER TABLE `glossary` MODIFY COLUMN `references` TEXT NULL',
    'SELECT 1');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
