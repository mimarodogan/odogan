-- ════════════════════════════════════════════════════════════════════
--  066_cerez_politikasi_tablosu.sql — F3.2: Çerez Politikası belgesini
--  tablo formuyla (KVKK rehberi formatı) güncelle.
--
--  Mevcut "cerez-politikasi" belgesi 052_cookie_consent_seed.sql ile
--  oluşturulmuştu (genel açıklama). Bu migration, KVK Kurumu rehberinin
--  önerdiği "çerez adı / amaç / süre / tip" tablosu formuna geçirir.
--
--  KVK Kurumu rehberi:
--    https://www.kvkk.gov.tr/Icerik/7256/Cerez-Uygulamalari-Rehberi
-- ════════════════════════════════════════════════════════════════════

UPDATE `legal_documents`
SET `body_html` =
'<p class="lead">Bu Çerez Politikası, <strong>odogan.com.tr</strong> sitesini ziyaret ettiğinizde tarayıcınıza yerleştirdiğimiz çerezlerin türleri, amaçları ve süreleri hakkında sizi bilgilendirmek üzere hazırlanmıştır. KVK Kurumu Çerez Uygulamaları Rehberi\'ne uygun olarak düzenlenmiştir.</p>

<h2>1. Çerez Nedir?</h2>
<p>Çerez (cookie), web siteyi ziyaret ettiğinizde tarayıcınıza kaydedilen küçük metin dosyalarıdır. Çerezler, sitenin sizi tekrar tanımasını, tercihlerinizi hatırlamasını ve ziyaret istatistiklerinizi anonim olarak ölçmesini sağlar.</p>

<h2>2. Kullandığımız Çerezler</h2>
<p>Sitemiz aşağıdaki çerezleri kullanır. Çerez onay panelinden zorunlu olmayan çerezleri istediğiniz zaman kapatabilirsiniz.</p>

<table style="width:100%;border-collapse:collapse;margin:1.5rem 0;font-size:.95rem">
    <thead>
        <tr style="background:#f1ece2;border-bottom:2px solid #111;text-align:left">
            <th style="padding:.75rem;border-right:1px solid #d5cfc1">Çerez Adı</th>
            <th style="padding:.75rem;border-right:1px solid #d5cfc1">Tipi</th>
            <th style="padding:.75rem;border-right:1px solid #d5cfc1">Amacı</th>
            <th style="padding:.75rem;border-right:1px solid #d5cfc1">Süresi</th>
            <th style="padding:.75rem">Sahibi</th>
        </tr>
    </thead>
    <tbody>
        <tr style="border-bottom:1px solid #e5dece">
            <td style="padding:.75rem;font-family:monospace;font-size:.85rem">odogan_sid</td>
            <td style="padding:.75rem"><strong>Zorunlu</strong></td>
            <td style="padding:.75rem">Oturum yönetimi (üye giriş durumu, sepet/yorum formu süresi)</td>
            <td style="padding:.75rem">Oturum sonunda silinir</td>
            <td style="padding:.75rem">odogan.com.tr</td>
        </tr>
        <tr style="border-bottom:1px solid #e5dece">
            <td style="padding:.75rem;font-family:monospace;font-size:.85rem">odogan_csrf</td>
            <td style="padding:.75rem"><strong>Zorunlu</strong></td>
            <td style="padding:.75rem">Cross-Site Request Forgery (CSRF) koruması — form gönderimi güvenliği</td>
            <td style="padding:.75rem">Oturum sonunda silinir</td>
            <td style="padding:.75rem">odogan.com.tr</td>
        </tr>
        <tr style="border-bottom:1px solid #e5dece">
            <td style="padding:.75rem;font-family:monospace;font-size:.85rem">odogan:cookie-consent</td>
            <td style="padding:.75rem"><strong>Zorunlu</strong></td>
            <td style="padding:.75rem">Çerez tercihlerinizi hatırlamak (localStorage)</td>
            <td style="padding:.75rem">1 yıl</td>
            <td style="padding:.75rem">odogan.com.tr</td>
        </tr>
        <tr style="border-bottom:1px solid #e5dece">
            <td style="padding:.75rem;font-family:monospace;font-size:.85rem">odogan_vt</td>
            <td style="padding:.75rem"><strong>Zorunlu</strong></td>
            <td style="padding:.75rem">Açık rıza denetim takibi (KVKK m.5 ispat yükümlülüğü için anonim ziyaretçi token)</td>
            <td style="padding:.75rem">2 yıl</td>
            <td style="padding:.75rem">odogan.com.tr</td>
        </tr>
        <tr style="border-bottom:1px solid #e5dece">
            <td style="padding:.75rem;font-family:monospace;font-size:.85rem">_ga, _ga_*</td>
            <td style="padding:.75rem"><strong>Analitik</strong> (onaya bağlı)</td>
            <td style="padding:.75rem">Google Analytics 4 — anonim ziyaret istatistikleri, sayfa görüntüleme, yönlendirme kaynağı</td>
            <td style="padding:.75rem">2 yıl</td>
            <td style="padding:.75rem">Google LLC (ABD)</td>
        </tr>
        <tr>
            <td style="padding:.75rem;font-family:monospace;font-size:.85rem">_gid</td>
            <td style="padding:.75rem"><strong>Analitik</strong> (onaya bağlı)</td>
            <td style="padding:.75rem">Google Analytics 4 — günlük tekil ziyaretçi sayımı</td>
            <td style="padding:.75rem">24 saat</td>
            <td style="padding:.75rem">Google LLC (ABD)</td>
        </tr>
    </tbody>
</table>

<h2>3. Çerez Kategorileri</h2>

<h3>3.1. Zorunlu Çerezler</h3>
<p>Sitenin temel işlevleri için gerekli olan çerezlerdir; kapatılması durumunda site düzgün çalışmaz (giriş yapma, yorum gönderme, CSRF koruması). KVKK m.5/2-f kapsamında "meşru menfaat" hukuki sebebine dayanır, açık rıza gerekmez.</p>

<h3>3.2. Analitik Çerezler</h3>
<p>Sitemizin nasıl kullanıldığını anonim olarak anlamak için Google Analytics 4 kullanırız. <strong>Bu çerezler sadece açık rızanız varsa yüklenir.</strong> Onay vermezseniz GA4 "cookieless ping" modunda çalışır — kişisel veri toplanmaz, yalnızca anonim sayfa görüntüleme istatistiği tutulur.</p>
<p>Analytics çerezleri ABD\'ye (Google LLC sunucularına) aktarılır. Bu yurt dışı aktarım KVKK m.9 kapsamında <strong>açık rızanızla</strong> gerçekleştirilir.</p>

<h3>3.3. Pazarlama Çerezleri</h3>
<p>Şu anda sitemizde reklam veya pazarlama çerezi kullanılmamaktadır. Çerez onay panelinde bu kategori opsiyonel olarak listelenmektedir; gelecekte sponsor içerik veya kampanya başlatıldığında devreye girebilir.</p>

<h2>4. Çerez Tercihlerinizi Yönetme</h2>

<h3>4.1. Sitemizde</h3>
<p>İlk ziyaretinizde alttan beliren çerez onay panelinden tercihlerinizi belirleyebilirsiniz. Üç seçenek sunulur:</p>
<ul>
    <li><strong>Sadece Gerekli:</strong> Yalnızca zorunlu çerezler aktif olur; analitik/pazarlama reddedilir.</li>
    <li><strong>Tercihler:</strong> Modal açılır; analytics ve marketing kategorilerini ayrı ayrı seçebilirsiniz.</li>
    <li><strong>Hepsini Kabul Et:</strong> Tüm çerezler aktif olur.</li>
</ul>
<p>Tercihinizi değiştirmek için sayfanın altındaki çerez ayarları bağlantısına tıklayın veya localStorage\'ı temizleyin (Geliştirici Araçları → Application → Local Storage).</p>

<h3>4.2. Tarayıcı Üzerinden</h3>
<p>Tüm tarayıcılar çerezleri yönetme imkânı sunar. Tarayıcı ayarlarından çerezleri silebilir, belirli sitelerden çerez kabulünü engelleyebilirsiniz:</p>
<ul>
    <li><a href="https://support.google.com/chrome/answer/95647" rel="noopener" target="_blank">Google Chrome</a></li>
    <li><a href="https://support.mozilla.org/tr/kb/cerezleri-silme-web-sitelerinin-bilgilerini-kaldirma" rel="noopener" target="_blank">Mozilla Firefox</a></li>
    <li><a href="https://support.apple.com/tr-tr/guide/safari/sfri11471/mac" rel="noopener" target="_blank">Apple Safari</a></li>
    <li><a href="https://support.microsoft.com/tr-tr/microsoft-edge" rel="noopener" target="_blank">Microsoft Edge</a></li>
</ul>

<h2>5. Açık Rıza Kaydı</h2>
<p>Çerez tercihleriniz KVKK m.5 ispat yükümlülüğü gereği <code>consent_logs</code> tablosuna anonim olarak kaydedilir (anonim ziyaretçi token, tercih kategorisi, IP, user-agent, zaman damgası). Bu kayıt 5 yıl saklanır, sonra anonimleştirilir.</p>

<h2>6. Üçüncü Taraf Hizmetler</h2>
<p>Sitemizde yalnızca <strong>Google Analytics 4</strong> üçüncü taraf hizmeti aktiftir ve sadece onayınıza bağlı çalışır. Google\'ın çerez politikasını incelemek için <a href="https://policies.google.com/technologies/cookies?hl=tr" rel="noopener" target="_blank">Google Çerez Politikası</a> sayfasına bakabilirsiniz.</p>

<h2>7. Politikanın Güncellenmesi</h2>
<p>Bu politika gerektiğinde güncellenir. Önemli değişiklikler çerez onay panelinde "yeniden onay" tetikleyerek size sorulur. Mevcut versiyon: <strong>1.0</strong> (2026-05-24).</p>

<h2>8. İletişim</h2>
<p>Çerezlerle ilgili sorularınız için bizimle iletişime geçebilirsiniz:</p>
<ul>
    <li><strong>E-posta:</strong> {{org_email}}</li>
    <li><strong>İletişim Formu:</strong> <a href="/iletisim">odogan.com.tr/iletisim</a></li>
</ul>

<p style="margin-top:2rem;color:#5A544D;font-size:.9rem"><em>Son güncelleme: 2026-05-24 · Versiyon 1.0</em></p>',
`version` = `version` + 1,
`updated_at` = NOW()
WHERE `slug` = 'cerez-politikasi';
