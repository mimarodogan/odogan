-- ════════════════════════════════════════════════════════════════════
--  049_email_pending.sql — E-posta değiştirme pending pattern (K6)
--
--  Kullanıcı /panel/profil/eposta'dan yeni adres girince eski adres
--  ANINDA değiştirilmez. Yeni adres `email_pending` sütununa yazılır,
--  token + 48h expiry üretilir. Onay linkine tıklandığında swap olur.
--
--  IDEMPOTENT — kolonları yalnızca yoksa ekler.
-- ════════════════════════════════════════════════════════════════════

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_pending');
SET @sql := IF(@col = 0,
    'ALTER TABLE `users` ADD COLUMN `email_pending` VARCHAR(255) NULL AFTER `email`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_pending_token');
SET @sql := IF(@col = 0,
    'ALTER TABLE `users` ADD COLUMN `email_pending_token` VARCHAR(64) NULL AFTER `email_pending`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_pending_expires_at');
SET @sql := IF(@col = 0,
    'ALTER TABLE `users` ADD COLUMN `email_pending_expires_at` DATETIME NULL AFTER `email_pending_token`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_email_pending_token');
SET @sql := IF(@idx = 0,
    'ALTER TABLE `users` ADD INDEX `idx_email_pending_token` (`email_pending_token`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Mail şablonları — email change confirm / notify (yeni adresin onayı + eskiye revoke linki)
INSERT IGNORE INTO `mail_templates` (`key_name`, `label`, `description`, `subject`, `body_html`, `variables`) VALUES
('email_change_confirm', 'E-posta Değişimi Onayı (Yeni Adres)', 'E-posta değişimi yapıldığında YENİ adrese gönderilen onay linki.',
 '{site_name} — yeni e-posta adresinizi onaylayın',
 '<p>Merhaba {user_name},</p><p>{site_name} hesabınızın e-posta adresini <strong>{new_email}</strong> olarak değiştirme talebi aldık.</p><p>Onaylamak için aşağıdaki bağlantıya tıklayın (link 48 saat geçerli):</p><p><a href="{confirm_link}" style="background:#1F3A8A;color:#fff;padding:.65rem 1.1rem;text-decoration:none;display:inline-block">E-postamı Onayla</a></p><p class="muted" style="color:#666;font-size:.85rem">Bu talebi siz yapmadıysanız hesabınıza giriş yapın ve mevcut şifrenizi değiştirin.</p>',
 'user_name, site_name, new_email, confirm_link'
),
('email_change_request_old', 'E-posta Değişimi Bildirimi (Eski Adres)', 'Değişim talebi geldiğinde eski adrese gönderilen uyarı.',
 'E-posta değişikliği talep edildi',
 '<p>Merhaba {user_name},</p><p>{site_name} hesabınızdan e-posta adresinizi <strong>{new_email}</strong> olarak değiştirme talebi alındı.</p><p>Eğer bu talebi siz yapmadıysanız hesabınıza giriş yapıp şifrenizi <strong>derhal</strong> değiştirin — talep yeni adres tarafından onaylanana kadar mevcut e-postanız aktif kalır.</p><p class="muted" style="color:#666;font-size:.85rem">Tarih: {date_time}<br>IP: {ip_address}</p>',
 'user_name, site_name, new_email, date_time, ip_address'
);
