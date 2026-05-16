# R10.net Tanıtım Yazısı — İki Sürüm

İki versiyon hazırladım: **BBCode** (forum'a doğrudan yapıştır) ve **düz metin**
(önceden gözden geçirip düzenlemek istersen).

Önerilen kategori: **Web Master Genel** veya **Açık Kaynak Yazılımlar**

---

## 🎯 BAŞLIK ÖNERİLERİ

Birini seç (forum kuralı genelde köşeli parantez ile prefix ister):

1. `[TANITIM] Odogan CMS — Mimari Bloglar İçin Açık Kaynak PHP CMS`
2. `[AÇIK KAYNAK] Framework'süz PHP CMS — Mimari/İçerik Odaklı`
3. `Odogan CMS — Magazine Estetiğinde, Tam Türkçe, MIT Lisanslı CMS Paylaşımı`

**Tavsiyem:** İlk başlık — net, açıklayıcı, tıklatıcı.

---

## 📝 BBCode SÜRÜMÜ (Forum'a Doğrudan Yapıştır)

```bbcode
Merhaba r10 ailesi,

Son ~6 ayda kendi mimari blogum için sıfırdan yazdığım PHP tabanlı CMS'i
açık kaynak olarak GitHub'a yükledim. Belki birinin işine yarar diye
paylaşıyorum, eleştirilerinize/önerilerinize açığım.

[B]Kısaca ne?[/B] Hazır CMS'lerden (WordPress, Ghost, vb.) sıkıldım. Mimari ve
restorasyon yazıları için "magazine atelier" estetiğinde, hızlı, sade ve
tamamen elimde olan bir altyapı istedim. Sonuç: framework kullanmayan,
PHP 8.2+ üzerine kurulu, MySQL ile çalışan, paylaşımlı hosting'de bile
sorunsuz çalışan bir CMS.

[B]Canlı görmek için:[/B] [URL=https://odogan.com.tr]odogan.com.tr[/URL]
[B]Kaynak kodu:[/B] [URL=https://github.com/mimarodogan/odogan]github.com/mimarodogan/odogan[/URL]

[CENTER][IMG]https://raw.githubusercontent.com/mimarodogan/odogan/main/docs/screenshots/home-desktop.webp[/IMG][/CENTER]

[B][SIZE=4]Neden Yine Bir CMS Daha?[/SIZE][/B]

WordPress'in eklenti karmaşası, Ghost'un Node.js bağımlılığı, statik
generator'ların dinamik özellik eksikliği... Kendi ihtiyaçlarıma göre
yazmak daha temiz oldu. Özellikle:

[LIST]
[*] Magazine grid + featured + editörün seçimi gibi yayıncılık odaklı düzen
[*] Türkçe içeriğe özel: Ateşman okunabilirlik puanı, doğru karakter encoding
[*] cPanel/paylaşımlı hosting friendly — Node.js/Docker gerekmiyor
[*] Atelier estetiği — serif tipografi, § paragraf işareti, kobalt aksent
[/LIST]

[B][SIZE=4]Öne Çıkan Özellikler[/SIZE][/B]

[B]İçerik Editörü:[/B]
[LIST]
[*] WYSIWYG zengin metin editörü
[*] [B]Slash komutları[/B] (/alıntı, /tablo, /görsel — Notion tarzı)
[*] [B]Footnote sistemi[/B] (akademik [^1] syntax)
[*] [B]Outline panel[/B] (yazarken yan tarafta H2/H3 live)
[*] Co-author / çoklu yazar
[*] Series (dizi yazılar)
[*] Auto-save + revision history
[*] Quick edit modal
[*] Markdown + sanitized HTML dual format
[/LIST]

[B]SEO:[/B]
[LIST]
[*] Otomatik XML sitemap (posts, projects, authors, [B]images[/B])
[*] [B]IndexNow protokolü[/B] (Bing/Yandex anında bildirim)
[*] [B]llms.txt[/B] (AI search engine optimizasyonu)
[*] JSON-LD @graph (Article, Person, Organization, FAQ, ImageGallery)
[*] OG image otomatik üreteç
[*] SEO skoru + Ateşman okunabilirlik (canlı, debounced)
[*] Internal link önerisi (FULLTEXT search tabanlı)
[*] Reading progress + table of contents
[/LIST]

[CENTER][IMG]https://raw.githubusercontent.com/mimarodogan/odogan/main/docs/screenshots/post-desktop.webp[/IMG][/CENTER]

[B]Güvenlik:[/B]
[LIST]
[*] [B]Argon2id[/B] password hashing
[*] [B]TOTP 2FA[/B] (RFC 6238)
[*] CSRF middleware (global)
[*] Rate limiting (login, register, password reset)
[*] [B]HSTS + CSP[/B] + X-Frame-Options + Permissions-Policy
[*] File upload polyglot sanitization
[*] [B]Forgot password flow[/B] + email change pending pattern
[*] [B]KVKK Cookie Consent[/B] + Consent Mode V2 (GDPR uyumlu)
[/LIST]

[B]Performans:[/B]
[LIST]
[*] Build pipeline: PurgeCSS + cssnano + Stylelint
[*] JS pipeline: ESLint + Prettier + Terser + source maps
[*] AVIF + WebP + JPEG fallback ([B]<picture>[/B])
[*] [B]BlurHash[/B] placeholder (lazy image loading)
[*] Brotli + gzip + tag-based cache invalidation
[*] Critical CSS desteği
[/LIST]

[B]Erişilebilirlik (WCAG 2.2 AA):[/B]
[LIST]
[*] prefers-reduced-motion global
[*] Modal focus traps
[*] aria-pressed, aria-current, aria-live
[*] Tablo <th scope> + caption
[*] :focus-visible outline tutarlılığı
[/LIST]

[CENTER][IMG]https://raw.githubusercontent.com/mimarodogan/odogan/main/docs/screenshots/projects-desktop.webp[/IMG][/CENTER]

[B][SIZE=4]Teknoloji Yığını[/SIZE][/B]

[LIST]
[*] [B]Backend:[/B] PHP 8.2+, MySQL 8.0+ (framework YOK)
[*] [B]Frontend:[/B] Vanilla JS (33 modül), CSS partials → Terser/cssnano
[*] [B]Cache:[/B] File-based (Redis opsiyonel)
[*] [B]Mail:[/B] Brevo (transactional + newsletter)
[*] [B]Error tracking:[/B] Sentry
[*] [B]Hosting:[/B] cPanel / paylaşımlı (LiteSpeed / Apache)
[/LIST]

Composer için sadece [B]4 paket[/B]: PHPMailer, Parsedown, Brevo SDK, Sentry SDK.
"Framework yok" derken gerçekten yok — Router, DB, Cache, Schema renderer
hepsi sıfırdan yazıldı. 481 dosya, ~22 MB.

[B][SIZE=4]Bonus: İlginç Olabilecek Özellikler[/SIZE][/B]

[LIST]
[*] [B]Proje portfolyo[/B] + Leaflet interaktif harita (yapı tipi filter chip)
[*] [B]Sözlük modülü[/B] — alfabetik akordeon + FULLTEXT arama
[*] [B]Yazar başvuru[/B] sayfası (multi-step wizard)
[*] [B]Yorum sistemi[/B] (threaded, moderasyon, admin email)
[*] [B]Etkileşim[/B] — clap, bookmark, follow author, emoji reactions, quote-to-tweet
[*] [B]Newsletter[/B] (Brevo, double opt-in, unsubscribe token)
[*] [B]Backup CLI[/B] — DB + uploads otomatik yedek (cron destekli)
[*] [B]Migration runner[/B] (admin panelden veya CLI'dan)
[/LIST]

[CENTER][IMG]https://raw.githubusercontent.com/mimarodogan/odogan/main/docs/screenshots/map-desktop.webp[/IMG][/CENTER]

[B][SIZE=4]Kullanım — Hızlı Başlangıç[/SIZE][/B]

[CODE]
git clone https://github.com/mimarodogan/odogan
cd odogan
composer install
npm install && npm run build
cp .env.example .env  # DB credentials doldur
php database/migrate.php
php -S localhost:8000 router.php
[/CODE]

[B][SIZE=4]Lisans[/SIZE][/B]

MIT — istediğin gibi kullan, fork'la, modifiye et, satışa koy. Sadece
LICENSE dosyasını koruman yeterli. Atıf zorunlu değil ama yapılırsa
sevinirim.

[B][SIZE=4]Geri Bildirim[/SIZE][/B]

Henüz yeni public oldu. Eleştirilerinizi, bug raporlarınızı, PR'larınızı
bekliyorum. Özellikle:

[LIST]
[*] Kod kalitesi (PHP 8.2 idiomatik mi?)
[*] Güvenlik açıkları (responsible disclosure için profile'da mail var)
[*] Performans (TTFB, LCP iyileştirme önerileri)
[*] Eksik özellik fikirleri
[/LIST]

Sorularınız varsa altta yazın, elimden geldiğince hızlı cevap veririm.

Teşekkürler 🌿

[B]Linkler:[/B]
🌐 [URL=https://odogan.com.tr]odogan.com.tr[/URL]
🐙 [URL=https://github.com/mimarodogan/odogan]github.com/mimarodogan/odogan[/URL]
👤 [URL=https://github.com/mimarodogan]github.com/mimarodogan[/URL]
```

---

## 📄 DÜZ METİN SÜRÜMÜ (Önizleme + Düzenleme İçin)

(Yukarıdaki BBCode'un BBCode tag'leri olmadan düz hali — gözden geçirmek istersen)

```
Merhaba r10 ailesi,

Son ~6 ayda kendi mimari blogum için sıfırdan yazdığım PHP tabanlı CMS'i
açık kaynak olarak GitHub'a yükledim. Belki birinin işine yarar diye
paylaşıyorum, eleştirilerinize/önerilerinize açığım.

Kısaca ne?
Hazır CMS'lerden (WordPress, Ghost, vb.) sıkıldım. Mimari ve restorasyon
yazıları için "magazine atelier" estetiğinde, hızlı, sade ve tamamen
elimde olan bir altyapı istedim. Sonuç: framework kullanmayan, PHP 8.2+
üzerine kurulu, MySQL ile çalışan, paylaşımlı hosting'de bile sorunsuz
çalışan bir CMS.

Canlı görmek için: https://odogan.com.tr
Kaynak kodu: https://github.com/mimarodogan/odogan

[geri kalan içerik aynı, sadece tag'siz]
```

---

## 🖼️ GÖRSEL KULLANIM

BBCode `[IMG]` tag'leri **GitHub raw** URL'leri kullanıyor — direkt çalışır,
ayrıca upload gerekmez:

| Görsel | URL |
|---|---|
| Anasayfa | `raw.githubusercontent.com/mimarodogan/odogan/main/docs/screenshots/home-desktop.webp` |
| Yazı sayfası | `.../post-desktop.webp` |
| Projeler | `.../projects-desktop.webp` |
| Harita | `.../map-desktop.webp` |
| Sözlük | `.../glossary-desktop.webp` |

Eğer r10.net `.webp` görüntülemekte zorluk yaşarsa, alternatif olarak:
- imgur.com'a yükle → BBCode'da `[IMG]https://i.imgur.com/...[/IMG]`
- Forum'un kendi "Resim Yükle" butonu (varsa)

---

## ⚠️ FORUM KURALLARINA DİKKAT

R10.net genelde şunlara hassas:

1. **Self-promo abartısı** — Yazıda 3-4 link yeterli, herkes biliyor
2. **Aynı yazıyı birden fazla kategoriye spamlamak** — Tek bir uygun kategori seç
3. **Cevapları kasıtlı geciktirmek** — İlk 24 saatte aktif ol, sorulara cevap ver
4. **"AI ile yazdırılmış" havası** — Bu yazı zaten samimi, ama yine de göz at, kendi cümleni ekle

## 📅 ZAMANLAMA

En yüksek trafik:
- **Hafta içi: 10:00 — 13:00** (öğle arası okunma)
- **Hafta sonu: 14:00 — 18:00**
- **Pazar akşamı 20:00 — 22:00** (sakin scroll saati)

İlk gün post + 2-3 yorum cevabı + 1 hafta boyunca güncellemeler (yeni özellik,
PR, vs.) — forum görünürlüğü için iyi.

## 💬 OLASI SORULAR + CEVAP TASLAKLARI

**Q: WordPress'ten neden vazgeçtin?**
A: Eklenti güvenlik açıkları + tema editörü ile hızımı kısıtlaması + magazine
estetiğine uygun tema bulamamam. Kendi yazınca tam istediğim gibi oluyor.

**Q: Performans nasıl?**
A: Sayfa başına ~12-15 query, gzip sonrası ~80 KB HTML+CSS+JS toplam,
LCP ~1.2 saniye (mobile, 3G simülasyonu). Görsel ağırsa BlurHash placeholder
ile algılanan hız iyileşiyor.

**Q: Multi-site / multi-tenant destekliyor mu?**
A: Hayır, tek site için tasarlandı. Multi-tenant istersen Database katmanını
genişletmen gerek — zor değil ama hazır değil.

**Q: PHP framework yerine kullanmak güvenli mi?**
A: Composer paketleri minimum (sadece 4: PHPMailer, Parsedown, Brevo, Sentry).
PDO prepared statement her yerde, CSRF global middleware, password Argon2id.
Production'da 6 aydır çalışıyor.

**Q: Tema/template sistemi var mı?**
A: Şu an yok — view'lar fixed. Tek bir site için yazıldı. İhtiyaç olursa
View base directory'i swap'lanabilir.

**Q: Demo admin paneli görmek mümkün mü?**
A: Public demo yok ama screenshot çekip eklerim isteyen olursa.

---

## ✅ KOPYALAYIP YAPIŞTIR ÖNCESİ KONTROL LİSTESİ

- [ ] Forum'a uygun kategori seçildi
- [ ] Başlık seçildi (köşeli parantez prefix dahil)
- [ ] BBCode formatı forum'la uyumlu (preview'da kontrol et)
- [ ] Görseller yükleniyor mu? (GitHub raw URL'leri çalışıyor)
- [ ] Kişisel imza/avatarın güncel
- [ ] İlk 24 saat aktif olmaya hazırsın

İyi paylaşımlar 🌿
