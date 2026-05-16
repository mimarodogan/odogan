-- ════════════════════════════════════════════════════════════════════
--  051_email_verify_expiry.sql — E-posta doğrulama token süresi (Y2)
--
--  Kayıt sonrası gönderilen `email_verification_token` artık 72 saatte
--  expire eder. Resend sayfasından yeni link talep edilir (rate-limited).
--
--  IDEMPOTENT.
-- ════════════════════════════════════════════════════════════════════

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verification_expires_at');
SET @sql := IF(@col = 0,
    'ALTER TABLE `users` ADD COLUMN `email_verification_expires_at` DATETIME NULL AFTER `email_verification_token`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
