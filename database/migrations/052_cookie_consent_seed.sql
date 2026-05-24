-- ════════════════════════════════════════════════════════════════════
--  052_cookie_consent_seed.sql — KVKK çerez politikası seed
--
--  • legal_documents tablosuna 'cerez-politikasi' satırı ekler.
--    Slug UNIQUE olduğu için INSERT IGNORE re-run güvenli.
--
--  • body_html alanı tek tırnaklı tek SQL string'i — PHP concat (.)
--    SQL'de geçersiz. Tüm HTML tek satırda birleştirildi.
-- ════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `legal_documents` (`slug`, `title`, `body_html`, `version`, `is_active`)
VALUES (
    'cerez-politikasi',
    'Çerez Politikası',
    '<h2>Çerez Politikamız</h2><p>Bu site, kullanıcı deneyimini iyileştirmek, tercihleri hatırlamak ve trafik analizi yapabilmek için çerezler ve benzeri teknolojiler kullanır. Bu politika, hangi çerezleri kullandığımızı ve onayınızı nasıl yönetebileceğinizi açıklar.</p><h2>Çerez Türleri</h2><h3>Zorunlu Çerezler</h3><p>Oturum yönetimi, CSRF koruması ve tema tercihleri için gereklidir. Onay olmadan çalışır; devre dışı bırakılamaz.</p><h3>Analitik Çerezler</h3><p>Google Analytics 4 (Consent Mode V2). Yalnızca "Kabul Et" seçtiğinizde etkinleşir. Onay vermezseniz cookieless ping ile yalnızca toplu, anonim sayfa görüntüleme verisi toplanır.</p><h2>Onayınızı Yönetme</h2><p>Site giriş ekranındaki banner üzerinden "Kabul Et" veya "Sadece Gerekli" seçeneklerinden birini işaretleyebilirsiniz. Onayınız <code>localStorage</code> üzerinde saklanır; tarayıcı verisini temizlediğinizde tekrar sorulur.</p><h2>Üçüncü Taraflar</h2><p>Yalnızca Google Analytics (analytics_storage). Reklam ve kişiselleştirme çerezleri kullanılmaz.</p><h2>Detaylı Bilgi</h2><p>Kişisel verilerinizin işlenmesi hakkında ayrıntılı bilgi için <a href="/sozlesmeler/gizlilik-politikasi">Gizlilik Politikası</a> belgemizi inceleyebilirsiniz.</p>',
    1,
    1
);
