-- ════════════════════════════════════════════════════════════════════
--  025_users_author_application.sql — Yazar başvuru sistemi (Tier 5)
--  IDEMPOTENT
-- ════════════════════════════════════════════════════════════════════

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'author_application_json') = 0,
    'ALTER TABLE `users` ADD COLUMN `author_application_json` LONGTEXT NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'author_application_at') = 0,
    'ALTER TABLE `users` ADD COLUMN `author_application_at` DATETIME NULL', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'author_application_status') = 0,
    'ALTER TABLE `users` ADD COLUMN `author_application_status` ENUM(\'none\',\'pending\',\'approved\',\'rejected\') NOT NULL DEFAULT \'none\'',
    'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_app_status_idx') = 0,
    'ALTER TABLE `users` ADD KEY `users_app_status_idx` (`author_application_status`)', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
