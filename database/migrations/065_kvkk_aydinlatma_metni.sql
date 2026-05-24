-- ════════════════════════════════════════════════════════════════════
--  065_kvkk_aydinlatma_metni.sql — F3.1: KVKK m.10 Aydınlatma Metni.
--
--  KVK Kanunu madde 10 gereği VERİ SORUMLUSU, kişisel veri toplarken
--  ilgilileri (ziyaretçi/üye) belirli konularda aydınlatmakla yükümlüdür:
--    • kimliği
--    • verilerin işlenme amacı + hukuki sebebi
--    • aktarıldığı kişiler ve amaç
--    • toplama yöntemi
--    • saklama süresi
--    • m.11 hakları (bilgi talep, düzeltme, silme, itiraz)
--
--  Bu şablon yer tutucu içerir: {{kvkk_data_controller}}, {{org_address}},
--  {{org_email}}, {{org_phone}}, {{verbis_status}}.
--
--  Admin Settings → Kuruluş Bilgileri'nde KVKK alanları doldurulduktan
--  sonra placeholder'lar otomatik değiştirilir (LegalController içinde
--  render aşamasında veya bu migration'dan sonra manuel UPDATE ile).
--
--  Boş bırakılan alan varsa policy non-compliant — admin uyarıcı görür.
-- ════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `legal_documents` (`slug`, `title`, `body_html`, `is_active`) VALUES
('aydinlatma-metni', 'KVKK Aydınlatma Metni',
'<p class="lead"><strong>6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK)</strong> 10. madde kapsamında veri sorumlusu sıfatıyla, kişisel verilerinizin nasıl işlendiğine dair sizi aydınlatıyoruz.</p>

<h2>1. Veri Sorumlusunun Kimliği</h2>
<p>Bu sitenin (odogan.com.tr) veri sorumlusu sıfatıyla işleyeni:</p>
<ul>
    <li><strong>Veri Sorumlusu:</strong> {{kvkk_data_controller}}</li>
    <li><strong>Adres:</strong> {{org_address}}</li>
    <li><strong>E-posta:</strong> {{org_email}}</li>
    <li><strong>Telefon:</strong> {{org_phone}}</li>
    <li><strong>VERBİS Durumu:</strong> {{verbis_status}}</li>
</ul>

<h2>2. İşlenen Kişisel Veriler</h2>
<p>Sitemizi ziyaret ettiğinizde veya üye olduğunuzda aşağıdaki kişisel verileriniz işlenebilir:</p>
<ul>
    <li><strong>Kimlik bilgisi:</strong> ad-soyad (üyelik, yorum)</li>
    <li><strong>İletişim bilgisi:</strong> e-posta adresi (üyelik, yorum, bülten), telefon (opsiyonel iletişim formu)</li>
    <li><strong>İşlem bilgisi:</strong> IP adresi, çerez kimlikleri, sayfa görüntülemeleri, tıklama yolları</li>
    <li><strong>Cihaz bilgisi:</strong> user-agent (tarayıcı, işletim sistemi)</li>
    <li><strong>İçerik:</strong> yorum metni, profil bilgisi (üye olunduysa)</li>
</ul>

<h2>3. Kişisel Verilerin İşlenme Amaçları</h2>
<p>Verileriniz şu amaçlarla işlenir:</p>
<ul>
    <li>Site hizmetlerinin sunulması (yorum yayını, üyelik, bülten)</li>
    <li>Bilgi güvenliğinin sağlanması (CSRF, oturum yönetimi, spam koruması)</li>
    <li>İstatistik ve performans analizi (Google Analytics — onayınıza bağlı)</li>
    <li>Yasal yükümlülüklerin yerine getirilmesi (5651 sayılı kanun kapsamında IP loglarının tutulması, denetim talep edildiğinde sunulması)</li>
    <li>İletişim formu / abonelik üzerinden gönderdiğiniz mesajların yanıtlanması</li>
</ul>

<h2>4. İşlemenin Hukuki Sebepleri</h2>
<p>KVKK m.5 kapsamında verilerinizin işlenmesinin hukuki sebepleri:</p>
<ul>
    <li><strong>Açık rıza</strong> (m.5/1): Bülten aboneliği, analitik çerezler, pazarlama çerezleri</li>
    <li><strong>Sözleşmenin kurulması/ifası</strong> (m.5/2-c): Üyelik kaydı, yorum yayını</li>
    <li><strong>Hukuki yükümlülük</strong> (m.5/2-ç): 5651 sayılı kanun gereği erişim/işlem kayıtları</li>
    <li><strong>Meşru menfaat</strong> (m.5/2-f): Spam koruması, güvenlik logları, hata izleme</li>
</ul>

<h2>5. Verilerin Aktarıldığı Üçüncü Kişiler</h2>
<p>Kişisel verileriniz aşağıdaki üçüncü taraflarla, yalnızca hizmetin gerektirdiği ölçüde paylaşılır:</p>
<ul>
    <li><strong>Hosting sağlayıcı:</strong> sunucu altyapısı — yurt içi</li>
    <li><strong>Google LLC (Google Analytics 4):</strong> sadece analitik çerez onayı verirseniz; veriler ABD\'ye aktarılır. Açık rıza yoksa cookieless ping kullanılır, kişisel veri aktarımı olmaz.</li>
    <li><strong>SMTP hizmet sağlayıcı:</strong> bülten ve onay mailleri için</li>
</ul>
<p>Yurt dışı aktarımlar (Google Analytics) KVKK m.9 kapsamında açık rızanızla yapılır. Çerez onay panelinden analitik çerezleri reddederek aktarımı durdurabilirsiniz.</p>

<h2>6. Verilerin Toplama Yöntemi</h2>
<p>Verileriniz şu yollarla toplanır:</p>
<ul>
    <li>Doğrudan sizin tarafınızdan girilenler (üyelik formu, iletişim formu, yorum formu)</li>
    <li>Otomatik olarak (çerezler, log dosyaları, IP — onayınız varsa)</li>
</ul>

<h2>7. Saklama Süreleri</h2>
<p>Verileriniz, işlenme amacının gerektirdiği süre kadar saklanır:</p>
<ul>
    <li><strong>Üyelik verileri:</strong> hesap açık olduğu sürece; silme talebinde 30 gün içinde anonimleştirilir</li>
    <li><strong>Yorum IP adresleri:</strong> 180 gün sonra otomatik anonimleştirilir</li>
    <li><strong>Sistem log IP\'leri:</strong> 90 gün</li>
    <li><strong>Bülten abonelik bilgisi:</strong> abonelikten çıkana kadar; çıkıştan sonra unsubscribe kaydı 1 yıl tutulur</li>
    <li><strong>Çerez onay kayıtları:</strong> denetim için 5 yıl (anonimleştirilir)</li>
</ul>

<h2>8. İlgili Kişinin Hakları (KVKK m.11)</h2>
<p>Veri sahibi olarak aşağıdaki haklara sahipsiniz:</p>
<ul>
    <li>Kişisel verilerinizin işlenip işlenmediğini öğrenme</li>
    <li>Hangi verilerin işlendiği hakkında bilgi talep etme</li>
    <li>İşleme amacını ve amaca uygun kullanılıp kullanılmadığını öğrenme</li>
    <li>Verilerin yurt içi/yurt dışı aktarıldığı kişileri bilme</li>
    <li>Eksik/yanlış verilerin düzeltilmesini isteme</li>
    <li>KVKK m.7 kapsamında verilerinizin silinmesini/yok edilmesini isteme</li>
    <li>İşleme nedeniyle aleyhinize çıkan sonuca itiraz etme</li>
    <li>Verilerin kanuna aykırı işlenmesi nedeniyle zarara uğramışsanız tazminat talep etme</li>
</ul>

<h2>9. Başvuru Yöntemi</h2>
<p>KVKK m.11 haklarınızı kullanmak için:</p>
<ul>
    <li><strong>E-posta:</strong> {{org_email}}</li>
    <li><strong>Posta:</strong> {{org_address}}</li>
</ul>
<p>Başvurunuz Veri Sorumlusuna Başvuru Usul ve Esasları Hakkında Tebliğ\'e uygun olarak en geç <strong>30 gün içinde</strong> ücretsiz cevaplanır. Başvuruda <em>kimlik bilgileriniz</em>, <em>talebin konusu</em>, <em>iletişim adresiniz</em> bulunmalıdır.</p>

<h2>10. Çerez Politikası</h2>
<p>Sitemizdeki çerezler hakkında ayrıntılı bilgi için <a href="/sozlesmeler/cerez-politikasi">Çerez Politikası</a> sayfamızı inceleyebilirsiniz. Çerez onay panelinden tercihlerinizi istediğiniz zaman değiştirebilirsiniz.</p>

<h2>11. Metnin Güncellenmesi</h2>
<p>Bu Aydınlatma Metni gerektiğinde güncellenir. Önemli değişiklikler bültende ve sitede duyurulur. Son güncelleme tarihi sayfa altında belirtilir.</p>

<p style="margin-top:2rem;color:#5A544D;font-size:.9rem"><em>Son güncelleme: 2026-05-24</em></p>',
1);
