-- ════════════════════════════════════════════════════════════════════
--  026_legal_documents.sql — Sözleşmeler (Tier 6)
--
--  • Üyelik sözleşmesi, yazar sözleşmesi, gizlilik politikası,
--    kullanım koşulları — admin panelinden düzenlenebilir HTML metinler.
--  • Versiyon takibi (kullanıcı onayladığı versiyon değişirse yeniden kabul)
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `legal_documents` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug`       VARCHAR(80)  NOT NULL,
    `title`      VARCHAR(200) NOT NULL,
    `body_html`  LONGTEXT     NULL,
    `version`    INT UNSIGNED NOT NULL DEFAULT 1,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `legal_slug_uniq` (`slug`),
    KEY `legal_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default sözleşmeler — admin sonradan düzenler
INSERT IGNORE INTO `legal_documents` (`slug`, `title`, `body_html`) VALUES
('uyelik-sozlesmesi', 'Üyelik Sözleşmesi',
'<h2>1. Taraflar</h2><p>Bu sözleşme, <strong>Odogan</strong> ("Site") ile siteye üye olan kişi ("Üye") arasındadır.</p><h2>2. Üyelik Hakları</h2><p>Üye olduğunuzda yorum yapabilir, yazıları kaydedebilir ve yazar başvurusunda bulunabilirsiniz.</p><h2>3. Üyelik Yükümlülükleri</h2><ul><li>Hesap bilgilerinin gizliliğinden üye sorumludur.</li><li>Hakaret, spam, telif ihlali içeren yorumlar yasaktır.</li><li>Tek kişi bir hesap açabilir.</li></ul><h2>4. Hesap Silme</h2><p>Üyelik istediğiniz zaman hesabınızı silebilirsiniz. Yorumlarınız anonimleştirilir.</p><h2>5. Sorumluluk</h2><p>Site, üye tarafından paylaşılan içeriklerden sorumlu değildir.</p>'
),
('yazar-sozlesmesi', 'Yazar Sözleşmesi',
'<h2>1. Yayın Yönergeleri</h2><p>Yazar olarak gönderdiğiniz içerikler editöryal incelemeden geçer. Yayın yönergelerimize uymayan içerikler revizyon istenir veya yayınlanmaz.</p><h2>2. Telif Hakları</h2><p><strong>Gönderdiğiniz yazılarda yer alan görsel ve metnin telifi size aittir.</strong> Üçüncü kişilere ait içerik kullanıyorsanız kaynak belirtmeniz zorunludur.</p><h2>3. Kullanım İzni</h2><p>Site, yazılarınızı yayınlama, arşivleme ve sosyal medyada tanıtma hakkına sahiptir. Telif size ait kalır.</p><h2>4. Yazı Niteliği</h2><ul><li>Özgün içerik olmalıdır.</li><li>En az 300 kelime, dilbilgisi açısından düzgün.</li><li>Tıbbi/hukuki tavsiye içeriyorsa açıkça belirtilmelidir.</li></ul><h2>5. Yazar Statüsü</h2><p>Yazar yetkisi geri alınabilir (defalarca yayın yönergesi ihlali, telif hakkı sorunu vb. durumda).</p>'
),
('gizlilik-politikasi', 'Gizlilik Politikası',
'<h2>Topladığımız Veriler</h2><p>Ad, e-posta, IP adresi, oturum bilgisi.</p><h2>Kullanım Amacı</h2><p>Hesap yönetimi, yorum moderasyonu, yayın bildirimleri.</p><h2>Çerezler</h2><p>Site oturumu ve tema tercihleri için zorunlu çerezler kullanılır.</p><h2>Üçüncü Taraflar</h2><p>E-posta gönderimi için Brevo (SendGrid), hata izleme için Sentry kullanılır.</p><h2>Haklarınız</h2><p>KVKK kapsamında verilerinizi indirme, düzeltme, silme hakkınız vardır. <a href="mailto:iletisim@odogan.com.tr">iletisim@odogan.com.tr</a> üzerinden bize ulaşın.</p>'
),
('kullanim-kosullari', 'Kullanım Koşulları',
'<h2>Site Erişimi</h2><p>Site herkesin kullanımına açıktır. Üyelik isteğe bağlıdır.</p><h2>Yasak Davranışlar</h2><ul><li>Otomatik bot veya scraper kullanımı.</li><li>Sistemin güvenliğini ihlal etme girişimleri.</li><li>Reklam veya spam içerikli yorum.</li></ul><h2>Fikri Mülkiyet</h2><p>Site içeriği telif hakkıyla korunur. İzinsiz çoğaltılamaz.</p><h2>Sorumluluk Reddi</h2><p>Bilgiler genel niteliktedir, profesyonel tavsiye yerine geçmez.</p>'
);
