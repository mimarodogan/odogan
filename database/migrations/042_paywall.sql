-- ════════════════════════════════════════════════════════════════════
--  042_paywall.sql — Üye-only Paywall (Tier 9)
--
--  Bir yazıyı sadece kayıtlı üyelere açık yap. Misafire özet kısmı
--  (paywall_excerpt) gösterilir; "Üye olarak okumaya devam et"
--  butonu ile login/register CTA sunulur.
--
--  • posts.paywall TINYINT(1) — 0/1 toggle
--  • posts.paywall_excerpt TEXT — misafir için özet (NULL ise otomatik
--    body'nin ilk 300 karakteri kullanılır)
--
--  Feature flag: features.paywall_enabled (default false)
--  IDEMPOTENT
-- ════════════════════════════════════════════════════════════════════

-- posts.paywall
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'paywall') = 0,
    'ALTER TABLE `posts` ADD COLUMN `paywall` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- posts.paywall_excerpt
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'paywall_excerpt') = 0,
    'ALTER TABLE `posts` ADD COLUMN `paywall_excerpt` TEXT NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index for paywall queries
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND INDEX_NAME = 'posts_paywall_idx') = 0,
    'ALTER TABLE `posts` ADD KEY `posts_paywall_idx` (`paywall`, `status`)', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
