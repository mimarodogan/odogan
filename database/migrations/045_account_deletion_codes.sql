-- ════════════════════════════════════════════════════════════════════
--  045_account_deletion_codes.sql — Hesap silme 2-step doğrulama kodu
--
--  Kullanıcı /panel/hesap/sil'e formla giriş yapınca:
--    1) Bir 6-haneli kod üretilip mail'e gönderilir
--    2) Kullanıcı 10 dakika içinde kodu girip son onayı verir → soft delete
--
--  IDEMPOTENT.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `account_deletion_codes` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `code_hash`  VARCHAR(128) NOT NULL,
    `reason`     VARCHAR(255) NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at`    DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `adc_user_idx` (`user_id`, `expires_at`),
    KEY `adc_used_idx` (`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK user_id → users (idempotent)
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'account_deletion_codes' AND CONSTRAINT_NAME = 'adc_user_fk') = 0,
    'ALTER TABLE `account_deletion_codes` ADD CONSTRAINT `adc_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE', 'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
