-- ════════════════════════════════════════════════════════════════════
--  046_mail_tpl_account_delete.sql — Hesap silme doğrulama kodu mail
-- ════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `mail_templates` (`key_name`, `label`, `description`, `subject`, `body_html`, `variables`, `is_active`) VALUES
('account_delete_code',
 'Hesap Silme Doğrulama Kodu',
 'Kullanıcı hesabını silmek istediğinde 6 haneli doğrulama kodu gönderir.',
 'Hesap silme doğrulama kodunuz — {site_name}',
 '<p>Merhaba {user_name},</p><p>Hesabınızı silmek için aşağıdaki <strong>6 haneli doğrulama kodunu</strong> kullanın:</p><p style="font-family:monospace;font-size:1.9rem;letter-spacing:.3em;padding:1rem;background:#f5f1ea;border-radius:4px;text-align:center;color:#1F3A8A;font-weight:700">{code}</p><p>Bu kod <strong>10 dakika</strong> içinde geçerlidir ({expires_at}).</p><p><strong>Eğer hesap silme talebini siz oluşturmadıysanız</strong>, bu e-postayı yok sayın ve şifrenizi değiştirin.</p><p class="muted" style="color:#666;font-size:.85rem">Hesap silme süreci iki adımlıdır — bu kodu girdikten ve "SİL" yazısını onayladıktan sonra hesabınız kapatılır. Yazılarınız korunur, sadece oturumunuz devre dışı kalır.</p>',
 'user_name,email,code,expires_at',
 1);
