-- ════════════════════════════════════════════════════════════════════
--  064_mail_tpl_newsletter.sql — F2.6: Newsletter mail şablonları (KVKK).
--
--  Üç şablon:
--    1) newsletter_confirm  — çift opt-in onay maili
--    2) newsletter_welcome  — onay sonrası karşılama maili
--    3) newsletter_issue    — periyodik bülten gönderimi
--
--  KVKK uyum: her şablonda {unsub_url} placeholder zorunlu; admin yazılı
--  override etse bile abonelikten çıkış linki kaldırılamaz (NewsletterController
--  unsub_url'yi her gönderimde otomatik ekler — template'te ek olarak
--  belirgin link bulunmalı).
-- ════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `mail_templates` (`key_name`, `label`, `description`, `subject`, `body_html`, `variables`, `is_active`) VALUES
('newsletter_confirm',
 'Bülten · Onay Maili (çift opt-in)',
 'Abone form gönderdikten sonra atılan onay maili. Kullanıcı linke tıklayana kadar abone olarak kabul edilmez.',
 'E-postanı doğrula — {site_name} bülteni',
 '<div style="font-family:Georgia,serif;color:#111;line-height:1.6"><p>Merhaba,</p><p>{site_name} bültenine abone olmak için bir istek aldık. Aboneliğini etkinleştirmek için aşağıdaki bağlantıya tıkla:</p><p style="margin:1.5rem 0"><a href="{confirm_url}" style="display:inline-block;background:#1F3A8A;color:#FAF7F2;padding:.85rem 1.5rem;text-decoration:none;font-family:monospace;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase">E-postamı Doğrula</a></p><p style="color:#5A544D;font-size:.92rem">Eğer bu istek sana ait değilse bu maili görmezden gelebilirsin — onay bağlantısına tıklanmadığı sürece adresin sisteme kaydedilmez.</p><hr style="border:0;border-top:1px solid #d5cfc1;margin:2rem 0"><p style="color:#5A544D;font-size:.82rem;font-family:monospace">KVKK kapsamında bu mail, sadece senin onayını almak için gönderildi. Verilerin nasıl işlendiği hakkında bilgi için <a href="{site_url}/sozlesmeler/aydinlatma-metni" style="color:#1F3A8A">Aydınlatma Metni</a> ve <a href="{site_url}/sozlesmeler/gizlilik-politikasi" style="color:#1F3A8A">Gizlilik Politikası</a> sayfalarımızı inceleyebilirsin.</p></div>',
 'site_name,site_url,confirm_url',
 1),

('newsletter_welcome',
 'Bülten · Hoş Geldin Maili',
 'Abone onay link\'ine tıkladıktan sonra atılan karşılama maili.',
 'Hoş geldin — {site_name} bülteni etkinleştirildi',
 '<div style="font-family:Georgia,serif;color:#111;line-height:1.6"><p>Hoş geldin!</p><p>{site_name} bültenine abonelik onayın tamamlandı. Ayda en fazla bir kez, mimarlık ve yapı kültürü üzerine yeni içerikler e-posta kutuna düşecek.</p><p>Bu arada okumaya başlamak istersen:</p><ul style="padding-left:1.2em"><li><a href="{site_url}/" style="color:#1F3A8A">Anasayfa</a> — en son yazılar</li><li><a href="{site_url}/sozluk" style="color:#1F3A8A">Mimari Sözlük</a> — terminoloji referansı</li><li><a href="{site_url}/hakkimda" style="color:#1F3A8A">Hakkımda</a> — yazar hakkında</li></ul><hr style="border:0;border-top:1px solid #d5cfc1;margin:2rem 0"><p style="color:#5A544D;font-size:.82rem;font-family:monospace">Her bültende altta abonelikten çıkış (<em>unsubscribe</em>) bağlantısı bulunur. Bu maili istemiyorsan <a href="{unsub_url}" style="color:#1F3A8A">buradan çıkabilirsin</a> — tek tıkla, onay sormadan.</p></div>',
 'site_name,site_url,unsub_url',
 1),

('newsletter_issue',
 'Bülten · Periyodik Sayı (varsayılan şablon)',
 'Periyodik bülten gönderim şablonu. {issue_html} bültenin gövdesi, {issue_title} başlığı.',
 '{issue_title} — {site_name}',
 '<div style="font-family:Georgia,serif;color:#111;line-height:1.6"><h1 style="font-family:Georgia,serif;font-size:1.6rem;color:#111;margin:0 0 1rem">{issue_title}</h1>{issue_html}<hr style="border:0;border-top:1px solid #d5cfc1;margin:2.5rem 0"><p style="color:#5A544D;font-size:.82rem;font-family:monospace">Bu maili {site_name} bülten aboneliği nedeniyle alıyorsun. <a href="{unsub_url}" style="color:#1F3A8A">Abonelikten çık</a> · <a href="{site_url}/sozlesmeler/aydinlatma-metni" style="color:#1F3A8A">KVKK Aydınlatma</a></p></div>',
 'site_name,site_url,issue_title,issue_html,unsub_url',
 1);
