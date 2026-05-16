-- ════════════════════════════════════════════════════════════════════
--  031_post_extensions.sql — Post taslak preview + sponsored + template
-- ════════════════════════════════════════════════════════════════════

-- IDEMPOTENT — her kolon önce INFORMATION_SCHEMA ile kontrol edilir
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'preview_token') = 0,
    'ALTER TABLE `posts` ADD COLUMN `preview_token` VARCHAR(64) NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'template_key') = 0,
    'ALTER TABLE `posts` ADD COLUMN `template_key` VARCHAR(60) NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'is_sponsored') = 0,
    'ALTER TABLE `posts` ADD COLUMN `is_sponsored` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'sponsor_name') = 0,
    'ALTER TABLE `posts` ADD COLUMN `sponsor_name` VARCHAR(160) NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'sponsor_url') = 0,
    'ALTER TABLE `posts` ADD COLUMN `sponsor_url` VARCHAR(300) NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'clap_count') = 0,
    'ALTER TABLE `posts` ADD COLUMN `clap_count` INT UNSIGNED NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND INDEX_NAME = 'posts_preview_idx') = 0,
    'ALTER TABLE `posts` ADD KEY `posts_preview_idx` (`preview_token`)', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND INDEX_NAME = 'posts_sponsored_idx') = 0,
    'ALTER TABLE `posts` ADD KEY `posts_sponsored_idx` (`is_sponsored`)', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Yazı şablonları — admin tanımlar, yazar "Yeni İçerik" oluştururken seçer
CREATE TABLE IF NOT EXISTS `post_templates` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key_name`   VARCHAR(60) NOT NULL,
    `label`      VARCHAR(160) NOT NULL,
    `description` VARCHAR(500) NULL,
    `body_html`  LONGTEXT NULL,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `position`   INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `pt_key_uniq` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `post_templates` (`key_name`, `label`, `description`, `body_html`, `position`) VALUES
('haber', 'Haber', 'Güncel haber/duyuru — kısa giriş + alıntı + bağlam.',
 '<p><strong>Lead (özet):</strong> Olayın özünü 2 cümleyle anlat.</p><h2>Olay</h2><p>Detaylar burada.</p><h2>Bağlam</h2><p>Neden önemli, neye yol açar.</p><h2>İleri Okuma</h2><ul><li>İlgili kaynak 1</li></ul>',
 1),
('rehber', 'Rehber', 'Adım-adım nasıl-yapılır rehberi.',
 '<p><em>Bu rehberin sonunda öğreneceksin:</em></p><ul><li>Hedef 1</li><li>Hedef 2</li></ul><h2>1. Adım — Ön Hazırlık</h2><p>Açıklama.</p><h2>2. Adım — Yapılış</h2><p>Açıklama.</p><h2>3. Adım — Doğrulama</h2><p>Açıklama.</p><h2>Sonuç</h2><p>Özet.</p>',
 2),
('soylesi', 'Söyleşi', 'Soru-cevap formatlı söyleşi.',
 '<p><strong>Konuşan:</strong> {Mimar adı}, {ünvan}.</p><blockquote>Açılış sözü.</blockquote><h3>Mimarlık size ne öğretti?</h3><p>Cevap…</p><h3>İlk projeniz hakkında?</h3><p>Cevap…</p><h3>Genç mimarlara önerin?</h3><p>Cevap…</p>',
 3),
('elestiri', 'Kitap/Eser Eleştirisi', 'Yapı/kitap/sergi eleştirisi.',
 '<p><strong>Eser:</strong> {Ad}, {Yazar/Mimar}, {Yıl}.</p><h2>Bağlam</h2><p>Eser hangi dönemde, hangi tartışmaya doğdu.</p><h2>Güçlü Yönler</h2><ul><li>…</li></ul><h2>Zayıf Yönler</h2><ul><li>…</li></ul><h2>Hüküm</h2><p>Tek paragraflık genel değerlendirme.</p>',
 4),
('makale', 'Akademik Makale', 'Bilimsel makale formatı.',
 '<h2>Özet (Abstract)</h2><p>Çalışmanın amacı, yöntemi, bulgusu, sonucu — tek paragraf.</p><h2>1. Giriş</h2><p>…</p><h2>2. Yöntem</h2><p>…</p><h2>3. Bulgular</h2><p>…</p><h2>4. Tartışma</h2><p>…</p><h2>5. Sonuç</h2><p>…</p><h2>Kaynaklar</h2><ol><li>[^1]</li></ol>',
 5);
