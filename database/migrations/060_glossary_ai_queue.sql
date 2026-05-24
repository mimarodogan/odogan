-- ════════════════════════════════════════════════════════════════════
--  060_glossary_ai_queue.sql — AI sözlük toplu üretim kuyruğu
--
--  Admin /admin/sozluk/toplu sayfasından terim listesi yapıştırılır,
--  her satır kuyruğa girer. Yine admin'den (veya cron'dan) tek tek
--  işlenir → her başarılı işlem yeni bir glossary satırı oluşturur.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `glossary_ai_queue` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `term`            VARCHAR(180) NOT NULL,
    `context`         VARCHAR(800) NULL,
    `depth`           ENUM('kisa', 'orta', 'derin') NOT NULL DEFAULT 'orta',
    `status`          ENUM('pending', 'processing', 'done', 'error', 'skipped') NOT NULL DEFAULT 'pending',
    `error_message`   TEXT NULL,
    `created_glossary_id` INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at`    DATETIME NULL,
    UNIQUE KEY `gaq_term_uniq` (`term`),
    KEY `gaq_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
