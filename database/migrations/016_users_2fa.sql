-- 2FA TOTP (RFC 6238) kolonları.
-- totp_secret: Base32 encoded 160-bit secret (raw döner ekranda).
-- totp_enabled: kullanıcı 2FA'yı tamamen aktive etti mi.
-- totp_enabled_at: aktivasyon zamanı.
-- totp_recovery_codes: JSON array — tek-kullanımlık geri-kazanım kodları (consume edilirse listeden çıkar).

ALTER TABLE `users`
    ADD COLUMN `totp_secret` VARCHAR(64) NULL AFTER `password_hash`,
    ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`,
    ADD COLUMN `totp_enabled_at` DATETIME NULL AFTER `totp_enabled`,
    ADD COLUMN `totp_recovery_codes` JSON NULL AFTER `totp_enabled_at`;
