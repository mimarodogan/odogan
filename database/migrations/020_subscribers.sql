-- Newsletter abone tablosu.
-- Brevo entegrasyonu için brevo_contact_id alanı (senkronizasyon kontrolü).
-- Double opt-in: confirmed_at NULL ise abone henüz doğrulamadı.

CREATE TABLE IF NOT EXISTS `subscribers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NULL,
    `confirmed_at` DATETIME NULL,
    `confirm_token` VARCHAR(64) NULL,
    `unsub_token` VARCHAR(64) NOT NULL,
    `brevo_contact_id` VARCHAR(64) NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `subs_email_unique` (`email`),
    UNIQUE KEY `subs_unsub_token` (`unsub_token`),
    KEY `subs_confirm_token` (`confirm_token`),
    KEY `subs_confirmed_idx` (`confirmed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
