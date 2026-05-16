# Odogan CMS

Modern, framework'süz PHP 8.2+ tabanlı **mimari blog ve portfolio CMS'i**.
[odogan.com.tr](https://odogan.com.tr) için özel geliştirildi — mimari, yapı,
restorasyon ve mühendislik içerikleri için "atelier" estetiğinde.

## Özellikler

### İçerik Yönetimi
- WYSIWYG zengin metin editör (slash komutları, footnote, image gallery)
- Markdown + sanitized HTML dual format
- Co-author / çoklu yazar desteği
- Series (dizi yazılar) + bölüm navigasyonu
- Tag ve kategori sistemi
- Glossary (sözlük) modülü
- Project portfolio + interaktif Leaflet harita

### SEO & Discoverability
- Otomatik XML sitemap (posts/projects/authors/images)
- IndexNow protokolü (Bing/Yandex anında bildirim)
- llms.txt (AI search engine optimizasyonu)
- JSON-LD Schema.org (@graph: Article, Person, Organization, WebSite, FAQPage)
- OG image otomatik generator
- Reading progress + table of contents

### Editör Pro Araçları
- Outline panel (live H2/H3 sidebar)
- SEO skoru (canlı, debounced)
- Okunabilirlik puanı (Türkçe Ateşman formülü)
- Internal link önerisi (FULLTEXT search tabanlı)
- Auto-save + revision history
- Quick edit modal
- Bulk actions

### Etkileşim
- Yorum sistemi (threaded, moderation)
- Clap, bookmark, follow author
- Emoji reactions
- Quote-to-tweet
- Save post (localStorage)
- Newsletter (Brevo entegrasyonu)
- Author follow + email digest

### Güvenlik
- Argon2id password hashing
- TOTP 2FA (RFC 6238)
- CSRF middleware (global)
- Rate limiting (login, register, password reset, analytics)
- HSTS + CSP + X-Frame-Options
- File upload polyglot sanitization
- IndexNow + SMTP encrypted credentials
- Cookie consent (KVKK / Consent Mode V2)
- Forgot password flow + email change pending pattern

### Performans
- Build pipeline: PurgeCSS + cssnano + Stylelint
- JS pipeline: ESLint + Prettier + Terser + source maps
- AVIF + WebP + JPEG fallback (`<picture>`)
- BlurHash placeholder
- LCP image preload
- Brotli + gzip
- Tag-based cache invalidation
- Critical CSS support

### Erişilebilirlik (WCAG 2.2 AA)
- `prefers-reduced-motion` global
- Modal focus traps (lightbox, gallery, modal'lar)
- `aria-pressed`, `aria-current`, `aria-live`
- Tablo `<th scope>` + caption
- `:focus-visible` outline tutarlılığı
- Skip-link, keyboard navigation

## Teknoloji Yığını

| Katman | Stack |
|---|---|
| Backend | PHP 8.2+, MySQL 8.0+, no framework |
| Build | Node 20+, npm, PostCSS, Terser, ESLint, Prettier |
| Cache | File-based (Redis opsiyonel) |
| Mail | Brevo (transactional + newsletter) |
| Error tracking | Sentry |
| Hosting | cPanel / shared (LiteSpeed / Apache) |

## Kurulum

```bash
# Composer & npm bağımlılıkları
composer install
npm install

# .env hazırla
cp .env.example .env
# DB credentials, APP_URL, SMTP ayarları doldur

# DB migrasyonları
php database/migrate.php

# Build CSS/JS bundle'ları
npm run build

# Local dev (port 8000)
php -S localhost:8000 router.php
```

## Build Komutları

```bash
npm run css:lint     # Stylelint kontrol
npm run css:build    # PurgeCSS + cssnano
npm run js:lint      # ESLint kontrol
npm run js:format    # Prettier
npm run js:build     # Terser minify + source map
npm run build        # Tüm pipeline (CSS + JS)
```

## Klasör Yapısı

```
app/                # PHP uygulaması
  ├── Controllers/  # HTTP request handler'lar
  ├── Models/       # DB entity'ler
  ├── Services/     # İş mantığı (Auth, Mail, Cache, Schema, ...)
  ├── Middleware/   # CSRF, Auth, Guest, RateLimit
  ├── Core/         # Router, Request, Response, DB, Cache
  └── Views/        # PHP template'ler
assets/             # Frontend kaynak (CSS partials, JS)
bin/                # CLI script'leri
database/migrations/ # Sıralı SQL migration'lar (001-052)
docs/               # DNS, cron, backup rehberleri
scripts/            # npm build script'leri
storage/            # cache, logs, backups (runtime)
uploads/            # User-uploaded media
```

## Production Deploy

Detaylı rehber için: [`docs/DNS_SETUP.md`](docs/DNS_SETUP.md), [`docs/CRON_SETUP.md`](docs/CRON_SETUP.md), [`docs/BACKUP_RESTORE.md`](docs/BACKUP_RESTORE.md).

## Lisans

MIT — bkz. [`LICENSE`](LICENSE) dosyası.

## İletişim

[odogan.com.tr](https://odogan.com.tr) · [@mimarodogan](https://github.com/mimarodogan)
