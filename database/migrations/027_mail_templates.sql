-- ════════════════════════════════════════════════════════════════════
--  027_mail_templates.sql — Mail şablonları (Tier 6)
--
--  • Tüm sistem mailleri admin panelinden düzenlenebilir hâle gelir.
--  • Değişken yer tutucu: {user_name}, {site_name}, {verification_link} vb.
--  • Subject + body_html ayrı tutulur.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `mail_templates` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key_name`    VARCHAR(80)  NOT NULL,
    `label`       VARCHAR(200) NOT NULL,
    `description` VARCHAR(500) NULL,
    `subject`     VARCHAR(255) NOT NULL,
    `body_html`   LONGTEXT     NOT NULL,
    `variables`   VARCHAR(500) NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `mt_key_uniq` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default şablonlar — admin sonradan kişiselleştirebilir
INSERT IGNORE INTO `mail_templates` (`key_name`, `label`, `description`, `subject`, `body_html`, `variables`) VALUES
('verify_email', 'E-posta Doğrulama', 'Yeni kayıt sonrası gönderilen doğrulama e-postası.',
 'E-posta adresinizi doğrulayın',
 '<p>Merhaba {user_name},</p><p>{site_name} hesabınızı doğrulamak için aşağıdaki bağlantıya tıklayın:</p><p><a href="{verification_link}" style="background:#1F3A8A;color:#fff;padding:.65rem 1.1rem;text-decoration:none;display:inline-block">Hesabımı Doğrula</a></p><p class="muted" style="color:#666;font-size:.85rem">Eğer kayıt olmadıysanız bu e-postayı yok sayın.</p>',
 'user_name, site_name, verification_link'
),
('password_changed', 'Şifre Değişti Bildirimi', 'Kullanıcı şifresini değiştirince bilgilendirme.',
 'Şifreniz değiştirildi',
 '<p>Merhaba {user_name},</p><p>Hesabınızın şifresi az önce değiştirildi.</p><p><strong>Bu değişikliği siz yapmadıysanız derhal iletişime geçin.</strong></p><p class="muted" style="color:#666;font-size:.85rem">Tarih: {date_time}<br>IP: {ip_address}</p>',
 'user_name, date_time, ip_address'
),
('email_changed_old', 'E-posta Değişimi (Eski Adres)', 'E-posta değiştirildiğinde eski adrese bilgi.',
 'E-posta adresiniz değiştirildi',
 '<p>Merhaba {user_name},</p><p>Hesabınızın e-posta adresi <strong>{new_email}</strong> olarak değiştirildi.</p><p>Bu işlemi siz yapmadıysanız derhal destek ekibine ulaşın.</p><p class="muted" style="color:#666;font-size:.85rem">Tarih: {date_time}<br>IP: {ip_address}</p>',
 'user_name, new_email, date_time, ip_address'
),
('post_submitted', 'Yazı Onay İçin Gönderildi', 'Yazar yazı gönderdiğinde editörlere bildirim.',
 'Yeni içerik onayı bekliyor: {post_title}',
 '<p>Merhaba,</p><p><strong>{post_title}</strong> başlıklı yeni bir içerik <em>{author_name}</em> tarafından onaya sunuldu.</p><p><a href="{review_link}" style="background:#1F3A8A;color:#fff;padding:.6rem 1rem;text-decoration:none;display:inline-block">İncele</a></p>',
 'post_title, author_name, review_link'
),
('post_approved', 'Yazı Onaylandı', 'Editör yazıyı onayladığında yazara bildirim.',
 'İçeriğiniz yayında: {post_title}',
 '<p>Merhaba {user_name},</p><p><strong>{post_title}</strong> başlıklı içeriğiniz onaylandı ve yayında.</p><p><a href="{public_link}">Yayındaki sayfayı görüntüleyin</a></p>',
 'user_name, post_title, public_link'
),
('post_rejected', 'Yazı Revizyon İstendi', 'Editör revizyon istediğinde yazara bildirim.',
 'İçeriğiniz revizyon istiyor: {post_title}',
 '<p>Merhaba {user_name},</p><p><strong>{post_title}</strong> başlıklı içeriğiniz revizyon talebi ile geri gönderildi.</p><p><strong>Editör notu:</strong></p><p>{reason}</p><p>Düzeltmenin ardından panelden tekrar gönderebilirsiniz.</p>',
 'user_name, post_title, reason'
),
('comment_admin_notify', 'Yorum Geldi (Admin)', 'Yeni yorum geldiğinde admin/editöre bildirim.',
 'Yeni yorum onayı bekliyor: {post_title}',
 '<p>Merhaba,</p><p><strong>{post_title}</strong> yazısına yeni bir yorum geldi ve onay bekliyor.</p><p><strong>Gönderen:</strong> {commenter_name} &lt;{commenter_email}&gt;</p><blockquote style="border-left:3px solid #1F3A8A;padding:.5rem 1rem;margin:.5rem 0;background:#FAF7F2;color:#1a1a1a">{comment_excerpt}</blockquote><p><a href="{moderation_link}" style="background:#1F3A8A;color:#fff;padding:.6rem 1rem;text-decoration:none;display:inline-block">Moderasyon Kuyruğunu Aç</a></p>',
 'post_title, commenter_name, commenter_email, comment_excerpt, moderation_link'
),
('author_app_submitted', 'Yazar Başvurusu Geldi (Admin)', 'Yeni yazar başvurusunda admine bildirim.',
 'Yeni yazar başvurusu: {applicant_name}',
 '<p>Merhaba,</p><p><strong>{applicant_name}</strong> ({applicant_email}) yazar olmak için başvurdu.</p><p><em>"{headline}"</em></p><p><strong>Uzmanlık:</strong> {expertise}</p><p><a href="{review_link}" style="background:#1F3A8A;color:#fff;padding:.6rem 1rem;text-decoration:none;display:inline-block">Başvuruyu İncele</a></p>',
 'applicant_name, applicant_email, headline, expertise, review_link'
),
('author_app_approved', 'Yazar Başvurusu Onaylandı', 'Başvurucu yazar olunca tebrik.',
 '🎉 Yazar başvurunuz onaylandı',
 '<p>Merhaba {user_name},</p><p>🎉 Tebrikler! Yazar başvurunuz onaylandı.</p><p>Artık panelden yazı oluşturup onaya gönderebilirsiniz.</p><p><a href="{panel_link}" style="background:#1F3A8A;color:#fff;padding:.6rem 1rem;text-decoration:none;display:inline-block">İlk Yazınızı Yazın</a></p>',
 'user_name, panel_link'
),
('author_app_rejected', 'Yazar Başvurusu Reddedildi', 'Başvuru reddedilince bildirim + sebep.',
 'Yazar başvurunuz hakkında',
 '<p>Merhaba {user_name},</p><p>Yazar başvurunuzu değerlendirdik. Bu kez devam edemediğimizi üzülerek bildirmek isteriz.</p><p><strong>Editör notu:</strong></p><p>{reason}</p><p>Üye olarak siteyi gezmeye, yorum yapmaya devam edebilirsiniz.</p>',
 'user_name, reason'
);
