-- ════════════════════════════════════════════════════════════════════
--  049_cookie_consent_seed.sql — KVKK çerez politikası seed + privacy defaults
--
--  • `legal_documents` tablosunda `cerez-politikasi` satırı yoksa oluşturur.
--    Diğer üç sözleşme (gizlilik, üyelik, yazar, kullanım) 026_*.sql
--    içinde halihazırda INSERT IGNORE ile seed edilmiş — burada YALNIZCA
--    çerez politikası eksiğini kapatıyoruz. Slug UNIQUE olduğu için
--    INSERT IGNORE re-run güvenli.
--
--  • Bu migration legal_documents şemasıyla uyumludur:
--      slug · title · body_html · version · is_active · created_at · updated_at
--    (body_md alanı yoktur; admin paneli HTML üzerinden düzenler.)
--
--  • Privacy varsayılan ayarları SettingsController::SCHEMA içinde
--    'default' => true olarak tanımlandığı için `settings` tablosuna seed
--    şart değildir — kullanıcı ilk kez admin/ayarlar formunu kaydedince
--    yazılır. Boş `settings` durumunda Setting::get(...) bool default'unu
--    döndürecektir. Yine de erken başlangıçta tutarlılık için bool
--    değerlerin de seed edilmesi tercih edilirse aşağıdaki ek INSERT
--    blokları açılabilir.
-- ════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `legal_documents` (`slug`, `title`, `body_html`, `version`, `is_active`)
VALUES (
    'cerez-politikasi',
    'Çerez Politikası',
    '<h2>Çerez Politikamız</h2>'
    . '<p>Bu site, kullanıcı deneyimini iyileştirmek, tercihleri hatırlamak ve trafik analizi yapabilmek için çerezler ve benzeri teknolojiler kullanır. Bu politika, hangi çerezleri kullandığımızı ve onayınızı nasıl yönetebileceğinizi açıklar.</p>'
    . '<h2>Çerez Türleri</h2>'
    . '<h3>Zorunlu Çerezler</h3>'
    . '<p>Oturum yönetimi, CSRF koruması ve tema tercihleri için gereklidir. Onay olmadan çalışır; devre dışı bırakılamaz.</p>'
    . '<h3>Analitik Çerezler</h3>'
    . '<p>Google Analytics 4 (Consent Mode V2). Yalnızca "Kabul Et" seçtiğinizde etkinleşir. Onay vermezseniz cookieless ping ile yalnızca toplu, anonim sayfa görüntüleme verisi toplanır.</p>'
    . '<h2>Onayınızı Yönetme</h2>'
    . '<p>Site giriş ekranındaki banner üzerinden "Kabul Et" veya "Sadece Gerekli" seçeneklerinden birini işaretleyebilirsiniz. Onayınız <code>localStorage</code> üzerinde saklanır; tarayıcı verisini temizlediğinizde tekrar sorulur.</p>'
    . '<h2>Üçüncü Taraflar</h2>'
    . '<p>Yalnızca Google Analytics (analytics_storage). Reklam ve kişiselleştirme çerezleri kullanılmaz.</p>'
    . '<h2>Detaylı Bilgi</h2>'
    . '<p>Kişisel verilerinizin işlenmesi hakkında ayrıntılı bilgi için <a href="/sozlesmeler/gizlilik-politikasi">Gizlilik Politikası</a> belgemizi inceleyebilirsiniz.</p>',
    1,
    1
);
