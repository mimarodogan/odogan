-- ════════════════════════════════════════════════════════════════════
--  050_password_reset.sql — Şifremi unuttum akışı (Y1)
--
--  /sifremi-unuttum + /sifre-sifirla/{token} için tek seferlik token
--  kayıtları. used_at NULL → henüz kullanılmadı.
--
--  Rate limit: IP + email başına 3/saat (controller'da uygulanır).
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `token`      VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at`    DATETIME NULL,
    `ip`         VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `pwd_reset_token_uniq` (`token`),
    KEY `pwd_reset_user_idx` (`user_id`),
    KEY `pwd_reset_used_idx` (`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK user_id → users (idempotent)
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets' AND CONSTRAINT_NAME = 'pwd_reset_user_fk') = 0,
    'ALTER TABLE `password_resets` ADD CONSTRAINT `pwd_reset_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE',
    'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Mail şablonu — şifre sıfırlama linki
INSERT IGNORE INTO `mail_templates` (`key_name`, `label`, `description`, `subject`, `body_html`, `variables`) VALUES
('password_reset', 'Şifre Sıfırlama Bağlantısı', 'Kullanıcı /sifremi-unuttum üzerinden talep ettiğinde gönderilen tek seferlik link.',
 '{site_name} — şifre sıfırlama bağlantınız',
 '<p>Merhaba {user_name},</p><p>{site_name} hesabınız için şifre sıfırlama talebi alındı.</p><p>Aşağıdaki bağlantıya 60 dakika içinde tıklayarak yeni bir şifre belirleyebilirsiniz:</p><p><a href="{reset_link}" style="background:#1F3A8A;color:#fff;padding:.65rem 1.1rem;text-decoration:none;display:inline-block">Şifremi Sıfırla</a></p><p class="muted" style="color:#666;font-size:.85rem">Bu talebi siz yapmadıysanız bu e-postayı yok sayabilirsiniz; hesabınız güvende.</p><p class="muted" style="color:#666;font-size:.85rem">Tarih: {date_time}<br>IP: {ip_address}</p>',
 'user_name, site_name, reset_link, date_time, ip_address'
);
