# Cron Jobs — cPanel kurulumu

Odogan CMS production'da 5 zamanlanmış görev kullanır. Bu doküman cPanel **Cron Jobs** menüsünden veya `crontab -e` ile nasıl eklendiklerini anlatır.

> Yollar `/home/USER/public_html/` varsayar; kendi cPanel kullanıcı adınla değiştir.

---

## 1. PHP CLI yolunu tespit et

cPanel'in default `php` binary'si çoğunlukla web SAPI sürümünden farklıdır. Doğru sürümü bul:

```bash
which php
# /usr/local/bin/php

ea-php82 --version
# PHP 8.2.x ...

# cPanel multi-php senaryosunda explicit sürüm:
/opt/cpanel/ea-php82/root/usr/bin/php --version
```

Tüm cron komutlarında **bu tam yolu** kullan — `php` aliası login shell dışında bulunmayabilir.

---

## 2. cPanel Cron Jobs sayfası

cPanel → **Advanced → Cron Jobs**

1. "Add New Cron Job" bölümünde yukarıdaki tabloda verilen schedule + command'ı gir.
2. "Add New Cron Job" tıkla.
3. **E-mail address** alanına admin mail'ini gir — cron stderr çıktısı oraya gönderilir. Log dosyasına da redirect ekliyoruz, mail spam'i tetikleyebilir; istersen mail boş bırak.

---

## 3. Zamanlamalar

| Zaman          | Crontab                  | Görev                                       | Komut                                                                                       |
| -------------- | ------------------------ | ------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Her gece 03:00 | `0 3 * * *`              | DB yedek (mysqldump → gzip)                  | `/usr/local/bin/php /home/USER/public_html/bin/backup-db.php`                              |
| Pazar 04:00    | `0 4 * * 0`              | Uploads yedek (tar.gz)                       | `/usr/local/bin/php /home/USER/public_html/bin/backup-uploads.php`                         |
| 5 dakikada bir | `*/5 * * * *`            | Zamanlanmış yazıları yayınla                 | Aşağıdaki inline PHP komutu                                                                  |
| Pazartesi 05:00 | `0 5 * * 1`             | Kırık link taraması                          | Aşağıdaki inline PHP komutu                                                                  |
| Ay başı 06:00  | `0 6 1 * *`              | 30+ günlük log dosyaları sil                 | Aşağıdaki temizlik komutu                                                                    |

### 3.1 — DB yedek (gece 03:00)

```cron
0 3 * * * /usr/local/bin/php /home/USER/public_html/bin/backup-db.php >> /home/USER/cron.log 2>&1
```

Çıktı: `storage/backups/db-YYYY-MM-DD-HHMM.sql.gz`
Retention: 30 gün (script otomatik temizler).

### 3.2 — Uploads yedek (Pazar 04:00)

```cron
0 4 * * 0 /usr/local/bin/php /home/USER/public_html/bin/backup-uploads.php >> /home/USER/cron.log 2>&1
```

Çıktı: `storage/backups/uploads-YYYY-MM-DD.tar.gz`
Sadece haftada bir — dosya boyutu büyük, her gün çalıştırma.

### 3.3 — `PostScheduler::publishDue()` (her 5 dakika)

Inline PHP — ekstra bin script gerekmiyor, mevcut servisi çağırır:

```cron
*/5 * * * * /usr/local/bin/php -r "require '/home/USER/public_html/bootstrap.php'; \App\Services\PostScheduler::publishDue();" >> /home/USER/cron.log 2>&1
```

> **Not:** `bootstrap.php`'in opportunistic trigger'ı (her request başına 60s'lik throttle ile) sayfa trafiği varken yeterlidir. Cron yine de gece/sessiz saatlerde "scheduled_at geçti ama hâlâ draft" senaryosunu kapatır.

### 3.4 — Kırık link taraması (Pazartesi 05:00)

```cron
0 5 * * 1 /usr/local/bin/php -r "require '/home/USER/public_html/bootstrap.php'; \App\Services\LinkChecker::scanAll();" >> /home/USER/cron.log 2>&1
```

Çıktı: `link_checks` tablosuna kaydedilir, admin panelden raporlanır.

### 3.5 — Log retention (ay başı 06:00)

`storage/logs/` altında 30 günden eski daily file'ları sil:

```cron
0 6 1 * * find /home/USER/public_html/storage/logs -type f -name "*.log" -mtime +30 -delete >> /home/USER/cron.log 2>&1
```

DB tablosundaki `logs` kaydı için ek olarak admin panelden manuel cleanup yapabilirsin; veya inline:

```cron
0 7 1 * * /usr/local/bin/php -r "require '/home/USER/public_html/bootstrap.php'; \App\Core\Database::instance()->run('DELETE FROM logs WHERE created_at < (NOW() - INTERVAL 90 DAY)');" >> /home/USER/cron.log 2>&1
```

---

## 4. Doğrulama

Cron çalıştığını teyit etmek için:

```bash
# cron.log son 50 satır
tail -50 /home/USER/cron.log

# Yedek dosyaları
ls -lh /home/USER/public_html/storage/backups/

# logs tablosunda en yeni kayıt
mysql -u USER -p DBNAME -e "SELECT created_at, channel, level, message FROM logs ORDER BY id DESC LIMIT 10;"
```

### Manuel test

Her bin script doğrudan çalıştırılabilir:

```bash
cd /home/USER/public_html
/usr/local/bin/php bin/backup-db.php
/usr/local/bin/php bin/backup-uploads.php
```

Exit code `0` başarılı, ≠ 0 hata. STDERR çıktısına bak.

---

## 5. Crontab kopyala-yapıştır blok

cPanel UI yerine `crontab -e` kullanıyorsan tek seferde:

```cron
# Odogan CMS — production cron jobs
0 3 * * *  /usr/local/bin/php /home/USER/public_html/bin/backup-db.php >> /home/USER/cron.log 2>&1
0 4 * * 0  /usr/local/bin/php /home/USER/public_html/bin/backup-uploads.php >> /home/USER/cron.log 2>&1
*/5 * * * * /usr/local/bin/php -r "require '/home/USER/public_html/bootstrap.php'; \App\Services\PostScheduler::publishDue();" >> /home/USER/cron.log 2>&1
0 5 * * 1  /usr/local/bin/php -r "require '/home/USER/public_html/bootstrap.php'; \App\Services\LinkChecker::scanAll();" >> /home/USER/cron.log 2>&1
0 6 1 * *  find /home/USER/public_html/storage/logs -type f -name "*.log" -mtime +30 -delete >> /home/USER/cron.log 2>&1
0 7 1 * *  /usr/local/bin/php -r "require '/home/USER/public_html/bootstrap.php'; \App\Core\Database::instance()->run('DELETE FROM logs WHERE created_at < (NOW() - INTERVAL 90 DAY)');" >> /home/USER/cron.log 2>&1
# KVKK IP retention — günde 1 kez log/yorum/login_attempt IP'lerini anonimleştir
0 2 * * *  /usr/local/bin/php /home/USER/public_html/bin/purge-old-ips.php >> /home/USER/cron.log 2>&1
```

---

## 6. Sık karşılaşılan sorunlar

| Belirti                                | Sebep                                                | Çözüm                                                                              |
| -------------------------------------- | ---------------------------------------------------- | ---------------------------------------------------------------------------------- |
| `php: command not found`               | Cron PATH minimal; alias yok                          | Tam yol kullan (`/usr/local/bin/php`)                                              |
| `mysqldump: command not found`         | `backup-db.php` zaten which/yaygın yolları tarar     | cron e-mail içeriğinde `mysqldump` aranan yolları görürsün; symlink ekle           |
| Backup dosyası 256 byte'tan az          | mysqldump credentials hatalı / DB yok                 | `bin/backup-db.php` exit code 1 verir, hata STDERR'e basılır                       |
| Inline PHP `\App\...` namespace error  | tek tırnak/escape hatası                             | Komut çift tırnak içinde, namespace `\\` ile escape                                |
| Cron çalışıyor ama log boş             | stderr redirect yok                                   | `2>&1` ekle, mail trafiğini kapat                                                  |
| `publishDue` aynı yazıyı 2 kez yayınlıyor | Hem opportunistic hem cron tetikliyor; idempotent değil | `posts.status='scheduled'` filtresine güven — race condition yoksa zararsız     |
