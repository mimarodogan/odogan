# Backup & Restore Drill

Yedek **olduğu kadar geri yükleme prosedürü de test edilmiş** olmalı. Bu doküman yedek formatlarını, geri yükleme adımlarını, test sıklığını ve felaket kurtarma (DR) senaryosunu kapsar.

> Cron job kurulumu için bkz. `docs/CRON_SETUP.md`.

---

## 1. Yedek formatları

| Tür      | Konum                                            | Dosya                                  | Sıkıştırma                       | Cron        |
| -------- | ------------------------------------------------ | -------------------------------------- | -------------------------------- | ----------- |
| Database | `storage/backups/db-YYYY-MM-DD-HHMM.sql.gz`      | gzip'lenmiş `mysqldump` (SQL text)     | `gzip -9` (`-9` max sıkıştırma)   | Günlük 03:00 |
| Uploads  | `storage/backups/uploads-YYYY-MM-DD.tar.gz`      | tar arşivi                              | `tar -czf` (gzip)                | Haftalık Pazar 04:00 |

### DB yedek detayları (`bin/backup-db.php`)

- `mysqldump --single-transaction --quick --skip-lock-tables --default-character-set=utf8mb4`
- Parola **MYSQL_PWD env var** ile geçer — `ps aux` çıktısında görünmez.
- Retention: 30 gün; otomatik temizlenir.
- Logger'a `backup.db.ok` kanalı yazılır.

### Uploads yedek detayları (`bin/backup-uploads.php`)

- `tar -czf` — tüm `public/uploads` (veya cPanel flat layout için `uploads/`) klasörü.
- Retention: 30 gün.
- Sadece **haftalık** — büyük dosya boyutu, günlük yedek diski doldurur.

---

## 2. Yedeği localhost'a indirme

cPanel **File Manager** veya SSH ile:

### SSH (önerilen)

```bash
# En son DB yedeği
scp USER@example.com:/home/USER/public_html/storage/backups/db-$(date +%Y-%m-%d)-*.sql.gz ./

# Tüm yedek klasörü
rsync -avz USER@example.com:/home/USER/public_html/storage/backups/ ./backups-prod/

# Tek dosya
sftp USER@example.com
> cd public_html/storage/backups
> get db-2026-05-14-0300.sql.gz
```

### cPanel File Manager

1. **File Manager → public_html/storage/backups**
2. Dosyaya sağ tık → **Download**

> Yedekleri **off-site** (lokal disk, S3, Backblaze B2, Google Drive) bir kopya tut. Sunucu komple çökerse `storage/backups/` de kaybolur.

---

## 3. Staging'de geri yükleme

### 3.1 — DB restore

```bash
# Yedeği aç (.sql olarak)
gunzip < db-2026-05-14-0300.sql.gz > db-restore.sql

# Veya tek satır pipe ile direkt MySQL'e
gunzip < db-2026-05-14-0300.sql.gz | mysql -h localhost -u USER -p DBNAME

# Staging DB'sini önce sıfırla (DESTRUKTIF — staging only!)
mysql -h localhost -u USER -p -e "DROP DATABASE IF EXISTS odogan_staging; CREATE DATABASE odogan_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip < db-2026-05-14-0300.sql.gz | mysql -h localhost -u USER -p odogan_staging
```

#### Doğrulama

```bash
# Satır sayıları
mysql -u USER -p odogan_staging -e "
SELECT 'posts' AS tbl, COUNT(*) AS n FROM posts
UNION ALL SELECT 'projects', COUNT(*) FROM projects
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'settings', COUNT(*) FROM settings;
"

# Son yayın
mysql -u USER -p odogan_staging -e "
SELECT id, title, status, published_at FROM posts ORDER BY id DESC LIMIT 5;
"
```

### 3.2 — Uploads restore

```bash
# Tarball içindekileri listele (önce kuru çalıştır)
tar -tzf uploads-2026-05-12.tar.gz | head -20

# Hedef klasöre aç (mevcut içeriği üzerine yazar)
tar -xzf uploads-2026-05-12.tar.gz -C /var/www/staging/public/

# İzinleri düzelt (Apache user'ı = www-data örnek)
chown -R www-data:www-data /var/www/staging/public/uploads
find /var/www/staging/public/uploads -type d -exec chmod 755 {} \;
find /var/www/staging/public/uploads -type f -exec chmod 644 {} \;
```

### 3.3 — Uygulama tarafı

```bash
# Cache + session temizle (eski user_id'ler artık geçersiz olabilir)
rm -rf /var/www/staging/storage/cache/*
rm -rf /var/www/staging/storage/sessions/*

# Composer dependencies (production parity için --no-dev)
cd /var/www/staging && composer install --no-dev --optimize-autoloader

# Migration'lar otomatik çalışır (bootstrap.php sırasında değil; admin paneli ilk ziyarette)
# Manuel tetikleme:
php -r "require './bootstrap.php'; \App\Services\MigrationRunner::up();"
```

### 3.4 — Smoke test

| Test                                  | Beklenen                                            |
| ------------------------------------- | --------------------------------------------------- |
| `https://staging.example.com/`        | Anasayfa açılıyor, hero görseli görünüyor          |
| `https://staging.example.com/yazilar` | Yazı listesi, kategori filtreleri                   |
| `https://staging.example.com/health`  | JSON `status: ok`, `checks.db: ok`                 |
| Admin login                           | Mevcut prod hesabıyla giriş yapılabiliyor           |
| Bir yazıya tıkla                      | Cover image (uploads'tan) görünüyor                 |
| Admin → Settings                      | SMTP / Brevo / Sentry değerleri prod ile eşleşiyor  |

---

## 4. Test sıklığı

> **Yedeklenmiş ama test edilmemiş yedek, yedek değildir.**

| Sıklık       | Test                                                                                  |
| ------------ | ------------------------------------------------------------------------------------- |
| **3 ayda bir** | Staging'e tam DB + uploads restore. Smoke test checklist'i çalıştır.                  |
| Aylık        | `storage/backups/` dosyalarına `ls -lh` ve dosya bütünlüğü: `gunzip -t db-*.sql.gz`.  |
| Haftalık     | UptimeRobot benzeri monitorle `/health` doğrulama (zaten otomatik).                  |
| **Yıllık**   | Tam DR senaryosu: yeni cPanel hesabında sıfırdan kuruluma git (bkz. §5).             |

Test loguna `docs/BACKUP_RESTORE.md` altına ek not bırak (tarih + sonuç + süre).

---

## 5. Disaster Recovery (DR) senaryosu

Senaryo: production sunucusu erişilemez, kurtarılamıyor. Yeni bir hosting hesabına sıfırdan kurulum.

### 5.1 — Pre-flight

- [ ] Off-site backup erişimi var (lokal/cloud kopya)
- [ ] DNS panel erişimi var (registrar veya ayrı DNS sağlayıcısı)
- [ ] `.env` production değerleri (parola, API key) güvenli vault'ta saklı
- [ ] Repository erişimi (git remote)

### 5.2 — Yeni sunucu kurulum

```bash
# 1) Repo clone
cd /home/USER/public_html
git clone git@github.com:odogan/cms.git .

# 2) .env oluştur
cp .env.production.example .env
# APP_KEY, DB_*, MAIL_*, SENTRY_DSN, BREVO_* değerlerini doldur
nano .env

# Önemli: APP_KEY orijinal production değeri olmalı,
# yoksa Crypto::encrypt edilmiş SMTP password çözülemez.

# 3) Composer
composer install --no-dev --optimize-autoloader

# 4) DB oluştur ve restore
mysql -u USER -p -e "CREATE DATABASE odogan_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip < /path/to/latest-db-backup.sql.gz | mysql -u USER -p odogan_prod

# 5) Uploads restore
mkdir -p public/uploads  # veya uploads/ flat layout için
tar -xzf /path/to/latest-uploads.tar.gz -C public/

# 6) Storage izinleri
mkdir -p storage/cache storage/logs storage/backups
chmod 775 storage storage/* public/uploads

# 7) DNS güncelle (yeni IP)
# Bkz. docs/DNS_SETUP.md → A/AAAA kayıtlarını yeni IP'ye çevir.

# 8) SSL
# cPanel AutoSSL otomatik tetikler; DNS yayılır yayılmaz sertifika gelir.

# 9) Smoke test (yukarıdaki §3.4 checklist'i)

# 10) Cron joblarını ekle (bkz. docs/CRON_SETUP.md)
```

### 5.3 — Recovery Time Objective (RTO) hedefi

| Adım                       | Süre tahmini |
| -------------------------- | ------------ |
| Repo clone + composer       | 5 dk         |
| DB restore (50MB sıkışık)   | 5–15 dk      |
| Uploads restore (5GB)       | 30–60 dk     |
| DNS propagation             | 1–4 saat     |
| AutoSSL                     | 5–30 dk      |
| Smoke test + cron           | 15 dk        |
| **Toplam (DNS hariç)**      | **~90 dk**   |
| **Toplam (DNS dahil)**      | **2–5 saat** |

DNS propagation ana darboğaz. Kritikse Cloudflare gibi proxied DNS kullan — TTL'i düşür (60–300s), failover IP'sini önceden hazırla.

---

## 6. Geri yükleme öncesi kontrol

Restore ettikten **hemen sonra**, prod'a yönelmeden önce:

```bash
# 1) APP_DEBUG=false mi?
grep APP_DEBUG .env

# 2) Sentry DSN aktif mi (production'da)?
grep SENTRY_DSN .env

# 3) Cron job'lar tanımlı mı?
crontab -l | grep odogan

# 4) /health endpoint sağlıklı mı?
curl -s https://example.com/health | jq

# 5) cron.log yazılabiliyor mu?
ls -la /home/USER/cron.log

# 6) Backup retention çalışıyor mu? (eski yedeği kasten oluşturup test et)
touch -d "40 days ago" storage/backups/db-test.sql.gz
/usr/local/bin/php bin/backup-db.php
ls storage/backups/db-test.sql.gz 2>&1   # silinmiş olmalı: "No such file"
```

---

## 7. Bilinen kısıtlar

| Konu                                            | Durum                                                                                                                |
| ----------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Kullanıcı session'ları                          | DB tabanlı değil; restore sonrası tüm aktif user'lar yeniden login olmak zorunda. PHP session dosyaları farklı.    |
| Tam tablo locks                                  | `--single-transaction` InnoDB için snapshot alır; MyISAM tablolar varsa restore tutarsız olabilir.                  |
| Ciphertext (`enc:v1:…`)                         | APP_KEY aynıysa decrypt sorunsuz. Key değiştiyse SMTP password / 2FA recovery codes okunamaz — sıfırlaman gerekir. |
| OG image cache                                  | `storage/cache/og/` regenerate edilir; restore sonrası ilk istekler yavaş olabilir.                                 |
| Migration version'ı                              | DB'deki `migrations` tablosu restore edilirse runner skip eder. Codebase'in versiyonu DB'den ileridiyse migrate.    |
