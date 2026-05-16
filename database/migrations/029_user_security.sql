-- ════════════════════════════════════════════════════════════════════
--  029_user_security.sql — Hesap güvenliği + soft delete (Tier 7)
--  • Failed login lockout (5 yanlış → 15 dk kilit)
--  • Soft delete: deleted_at, deleted_reason
--  • last_login_at tracking
--
--  IDEMPOTENT — INFORMATION_SCHEMA ile her kolon önce kontrol edilir,
--  yoksa eklenir. phpMyAdmin'de de güvenli çalışır.
-- ════════════════════════════════════════════════════════════════════

-- failed_login_count
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'failed_login_count') = 0,
        'ALTER TABLE `users` ADD COLUMN `failed_login_count` INT UNSIGNED NOT NULL DEFAULT 0',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- locked_until
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'locked_until') = 0,
        'ALTER TABLE `users` ADD COLUMN `locked_until` DATETIME NULL',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- last_login_at
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_login_at') = 0,
        'ALTER TABLE `users` ADD COLUMN `last_login_at` DATETIME NULL',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deleted_at
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deleted_at') = 0,
        'ALTER TABLE `users` ADD COLUMN `deleted_at` DATETIME NULL',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deleted_reason
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deleted_reason') = 0,
        'ALTER TABLE `users` ADD COLUMN `deleted_reason` VARCHAR(255) NULL',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index: users_deleted_idx
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_deleted_idx') = 0,
        'ALTER TABLE `users` ADD KEY `users_deleted_idx` (`deleted_at`)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index: users_locked_idx
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_locked_idx') = 0,
        'ALTER TABLE `users` ADD KEY `users_locked_idx` (`locked_until`)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
