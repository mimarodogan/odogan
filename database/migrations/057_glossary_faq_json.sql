-- ════════════════════════════════════════════════════════════════════
--  057_glossary_faq_json.sql — glossary.faq_json kolonu
--
--  AI chunk_5 üreteci "faq" array'i döndürüyordu; eskiden definition HTML
--  içine "Sıkça Sorulan Sorular" H2 bloğu olarak gömülüyordu. Artık ayrı
--  bir kolonda JSON formatında saklanır:
--
--    [{"q":"Soru?","a":"Cevap..."}, ...]
--
--  Yarar:
--   - Public sayfada <details>/<summary> accordion render etmek temiz
--   - Schema.org FAQPage markup HTML parse etmeden doğrudan üretilir
--   - Admin formda repeater olarak düzenlenebilir
--
--  IDEMPOTENT.
-- ════════════════════════════════════════════════════════════════════

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'glossary'
      AND COLUMN_NAME = 'faq_json');

SET @sql := IF(@col = 0,
    'ALTER TABLE `glossary` ADD COLUMN `faq_json` TEXT NULL AFTER `references`',
    'SELECT 1');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
