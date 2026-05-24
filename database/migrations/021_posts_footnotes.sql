-- Dipnot / kaynak listesi (akademik). FaqService gibi JSON formatta saklanır.
-- Format: [{"n": 1, "text": "Kaynak metni veya açıklama...", "url": "https://...opsiyonel"}]
-- Body içinde [^1] [^2] markerları MarkdownService::render() ile sup link'e dönüşür.
-- IDEMPOTENT — phpMyAdmin'de de güvenli çalışır.

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'footnotes_json') = 0,
    'ALTER TABLE `posts` ADD COLUMN `footnotes_json` LONGTEXT NULL AFTER `faq_json`', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
