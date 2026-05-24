-- ════════════════════════════════════════════════════════════════════
--  059_mail_tpl_contact_form.sql — İletişim formu mail şablonu
--
--  Yeni /iletisim formu admin'e mail göndermek için bu şablonu kullanır.
--  Değişkenler: visitor_name, visitor_email, visitor_phone, subject_line,
--  message, site_name, ip_address.
-- ════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `mail_templates` (`key_name`, `label`, `description`, `subject`, `body_html`, `variables`, `is_active`) VALUES
('contact_form_received',
 'İletişim Formu Alındı',
 'Public /iletisim sayfasından bir mesaj geldiğinde admin/yazar adresine bildirim gönderir.',
 'Yeni iletişim mesajı: {subject_line} — {site_name}',
 '<p>Yeni bir iletişim mesajı geldi.</p><table style="border-collapse:collapse;margin-top:1rem"><tr><td style="padding:.4rem .8rem .4rem 0;color:#666"><strong>Gönderen:</strong></td><td style="padding:.4rem 0">{visitor_name}</td></tr><tr><td style="padding:.4rem .8rem .4rem 0;color:#666"><strong>E-posta:</strong></td><td style="padding:.4rem 0"><a href="mailto:{visitor_email}">{visitor_email}</a></td></tr><tr><td style="padding:.4rem .8rem .4rem 0;color:#666"><strong>Telefon:</strong></td><td style="padding:.4rem 0">{visitor_phone}</td></tr><tr><td style="padding:.4rem .8rem .4rem 0;color:#666"><strong>Konu:</strong></td><td style="padding:.4rem 0">{subject_line}</td></tr><tr><td style="padding:.4rem .8rem .4rem 0;color:#666"><strong>IP:</strong></td><td style="padding:.4rem 0;font-family:monospace;font-size:.85rem">{ip_address}</td></tr></table><div style="margin-top:1.5rem;padding:1rem;background:#f5f1ea;border-left:3px solid #1F3A8A"><strong>Mesaj:</strong><br><br>{message}</div><p class="muted" style="color:#666;font-size:.85rem;margin-top:1.5rem">Yanıtlamak için doğrudan "Yanıtla" tuşunu kullanabilirsin — Reply-To başlığı gönderenin adresine ayarlı.</p>',
 'visitor_name,visitor_email,visitor_phone,subject_line,message,site_name,ip_address',
 1);
