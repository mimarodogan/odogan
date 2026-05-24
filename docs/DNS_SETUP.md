# DNS Kurulumu — cPanel + Brevo

Bu doküman `odogan.com.tr` benzeri bir kök alan adı için **A/AAAA, MX, SPF, DKIM, DMARC** kayıtlarını cPanel veya cPanel + Brevo (transactional + newsletter) senaryosu için anlatır.

> Domain adını (`example.com`) ve sunucu IP'sini (`203.0.113.10`) kendi değerlerinle değiştir.

---

## 1. A / AAAA — Web sunucusu

Site trafiğini cPanel barındırmasına yönlendir.

| Type | Host          | Value                          | TTL   |
| ---- | ------------- | ------------------------------ | ----- |
| A    | `@`           | `203.0.113.10`                 | 14400 |
| A    | `www`         | `203.0.113.10`                 | 14400 |
| AAAA | `@`           | `2001:db8::1` (IPv6 varsa)     | 14400 |
| AAAA | `www`         | `2001:db8::1`                  | 14400 |
| CNAME | `*` (opsiyonel) | `example.com.`                | 14400 |

cPanel "Zone Editor" bölümünde **+ A Record** veya **+ AAAA Record** ile eklenir.

### Doğrulama

```bash
dig +short A example.com
dig +short A www.example.com
dig +short AAAA example.com   # IPv6 verdiysen
nslookup example.com
```

`203.0.113.10` döndüğünde A kaydı yayılmıştır (DNS propagation 0–48 saat).

---

## 2. MX — Posta sunucusu

### Senaryo A: cPanel mail (varsayılan)

| Type | Host | Priority | Value             | TTL   |
| ---- | ---- | -------- | ----------------- | ----- |
| MX   | `@`  | `0`      | `mail.example.com.` | 14400 |
| A    | `mail` | —      | `203.0.113.10`    | 14400 |

### Senaryo B: Brevo / harici SMTP

Brevo `mail` host'unu kendi sunucularına yönlendirir; cPanel mail'i kapatmayı unutma (yoksa **autodiscover** loop).

Brevo dashboard → **Senders, Domains & Dedicated IPs → Domains → Authenticate this domain** akışında verilen kayıtları cPanel Zone Editor'a kopyala.

Brevo MX kullanmıyor — sadece **gönderim** için SPF + DKIM ekler. MX'leri kendi posta sağlayıcına bırakabilirsin.

### Doğrulama

```bash
dig +short MX example.com
```

---

## 3. SPF — Gönderen IP yetkilendirmesi

SPF, `example.com` adına e-posta göndermeye hangi sunucuların izinli olduğunu belirtir. **Bir domain başına tek bir SPF TXT** olmalı.

### cPanel + Brevo birlikte

| Type | Host | Value                                                                                        | TTL   |
| ---- | ---- | -------------------------------------------------------------------------------------------- | ----- |
| TXT  | `@`  | `v=spf1 mx ip4:203.0.113.10 include:spf.brevo.com ~all`                                       | 3600  |

- `mx` → MX kayıtlarındaki sunucular yetkili
- `ip4:` → cPanel sunucusunun outbound IP'si (genellikle A kaydındaki IP)
- `include:spf.brevo.com` → Brevo ile e-posta atıyorsan
- `~all` → soft-fail; `-all` (hard-fail) emin olduktan sonra önerilir

### Doğrulama

```bash
dig +short TXT example.com | grep spf1
```

`v=spf1 …` dönmeli. SPF testçi: https://www.kitterman.com/spf/validate.html

---

## 4. DKIM — İmzalı e-posta

DKIM, gönderilen her e-postaya kriptografik imza ekler. Brevo / cPanel selector'ünü kendisi verir.

### cPanel DKIM (genellikle hazır gelir)

cPanel → **Email → Email Deliverability → Manage** sayfasında selector `default` ve TXT değerini otomatik gösterir. "INSTALL THE SUGGESTED RECORD" butonuna bas.

| Type | Host                       | Value                                              | TTL   |
| ---- | -------------------------- | -------------------------------------------------- | ----- |
| TXT  | `default._domainkey`       | `v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC...` | 3600  |

### Brevo DKIM

Brevo dashboard → Domain auth → "Add these to your DNS":

| Type | Host                  | Value                                                | TTL  |
| ---- | --------------------- | ---------------------------------------------------- | ---- |
| TXT  | `mail._domainkey`     | `k=rsa; p=MIGfMA0GCSqGSIb3D... (Brevo verir)`        | 3600 |
| CNAME | `1._domainkey`        | `1.domainkey.brevo.com.` (Brevo verir)              | 3600 |
| CNAME | `2._domainkey`        | `2.domainkey.brevo.com.`                            | 3600 |

### Doğrulama

```bash
dig +short TXT default._domainkey.example.com
dig +short CNAME 1._domainkey.example.com
```

Online: https://mxtoolbox.com/dkim.aspx

---

## 5. DMARC — Policy + raporlama

DMARC SPF + DKIM hizalanmadığında alıcının ne yapacağını söyler. **Aşamalı policy** önerilir:

```
p=none      → ilk 2 hafta gözlem
            ↓
p=quarantine → 2 hafta deneme
            ↓
p=reject    → sıkı moda geç
```

### 1. Hafta — gözlem (none)

| Type | Host       | Value                                                                                        | TTL  |
| ---- | ---------- | -------------------------------------------------------------------------------------------- | ---- |
| TXT  | `_dmarc`   | `v=DMARC1; p=none; rua=mailto:dmarc@example.com; ruf=mailto:dmarc@example.com; fo=1; pct=100; adkim=r; aspf=r` | 3600 |

`rua=` adresinde günlük JSON/XML raporlar görünür. Brevo veya `dmarcian.com` ücretsiz dashboard verir.

### 2. Hafta — yumuşak (quarantine)

Sadece `p=` değerini değiştir:

```
v=DMARC1; p=quarantine; pct=25; rua=mailto:dmarc@example.com; ruf=mailto:dmarc@example.com; fo=1; adkim=r; aspf=r
```

`pct=25` ile yalnız %25 trafik karantinaya alınır. Sorun yoksa `pct=100`.

### 3. Hafta — sıkı (reject)

```
v=DMARC1; p=reject; rua=mailto:dmarc@example.com; ruf=mailto:dmarc@example.com; fo=1; adkim=s; aspf=s
```

`adkim=s aspf=s` strict alignment — alt domain'lerden de tam eşleşme.

### Doğrulama

```bash
dig +short TXT _dmarc.example.com
```

Online: https://dmarcian.com/dmarc-tools/

---

## 6. SSL / AutoSSL

cPanel **AutoSSL** açıkken Let's Encrypt sertifikası A/CNAME kayıtları yayılır yayılmaz otomatik üretilir.

### Kontrol

cPanel → **Security → SSL/TLS Status**

- "AutoSSL Domain Validated" → yeşil
- "Currently Excluded from AutoSSL" → kayıt yayılınca tekrar dene

### Manuel tetikleme

cPanel terminal:

```bash
/usr/local/cpanel/bin/uapi --user=USER SSL start_autossl_check
```

### Site doğrulama

```bash
openssl s_client -connect example.com:443 -servername example.com < /dev/null | openssl x509 -noout -dates -issuer
```

`notAfter=...` 90 gün ileride olmalı, issuer **Let's Encrypt** veya AutoSSL sağlayıcısı görünmeli.

Browser'da `https://example.com` aç → kilit ikonu yeşil.

---

## 7. CAA (opsiyonel — sıkı güvenlik)

Sadece belirli CA'ların sertifika kesmesine izin ver:

| Type | Host | Value                          | TTL  |
| ---- | ---- | ------------------------------ | ---- |
| CAA  | `@`  | `0 issue "letsencrypt.org"`    | 3600 |
| CAA  | `@`  | `0 issuewild "letsencrypt.org"` | 3600 |
| CAA  | `@`  | `0 iodef "mailto:security@example.com"` | 3600 |

```bash
dig +short CAA example.com
```

---

## 8. DNS yayılma kontrolü

Tüm kayıtlar girildikten sonra:

```bash
# Tüm temel kayıtları tek seferde
dig +noall +answer example.com A AAAA MX TXT
dig +short TXT _dmarc.example.com
dig +short TXT default._domainkey.example.com
```

Online:

- https://dnschecker.org/#A/example.com → global propagation
- https://www.mail-tester.com → 10/10 skor hedefi (SPF + DKIM + DMARC + içerik)

---

## Sık karşılaşılan sorunlar

| Belirti                          | Sebep                                  | Çözüm                                                                |
| -------------------------------- | -------------------------------------- | -------------------------------------------------------------------- |
| `@` host'una iki SPF kaydı        | "include" eklerken duplicate          | Tek TXT içinde tüm include'lar — RFC 7208 izin vermiyor                |
| DKIM TXT 255 char limiti aşıyor  | Brevo uzun anahtar veriyor             | Sağlayıcı UI'sinde "split string" otomatik olur; manuel ekleme yapma |
| DMARC `p=reject` sonrası bounce  | SPF veya DKIM hizalanmamış            | `p=none`'a dönüp `rua` raporlarını incele                            |
| AutoSSL "domain not resolving"   | A kaydı henüz yayılmadı                | DNS propagation bekle, dig ile doğrula, AutoSSL'i manuel tetikle      |
| Brevo "domain not authenticated" | CNAME/TXT eksik veya yanlış host       | Brevo'nun gösterdiği değeri kopyala-yapıştır; host'a `example.com.` ekleme |
