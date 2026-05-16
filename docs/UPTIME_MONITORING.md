# Uptime Monitoring (Tier 4.2)

Site sağlığı (`/health` endpoint) UptimeRobot, StatusCake, BetterUptime gibi
ücretsiz servislerle 5 dakika aralıkla izlenir. Downtime'da admin email/SMS alarmı gelir.

## UptimeRobot Kurulumu (Önerilen — ücretsiz tier 50 monitor / 5dk)

1. https://uptimerobot.com → "Free Sign Up"
2. Dashboard'da **+ Add New Monitor** tıkla
3. Aşağıdaki bilgileri gir:

| Alan | Değer |
|---|---|
| **Monitor Type** | HTTP(s) |
| **Friendly Name** | Odogan — Health |
| **URL** | `https://odogan.com.tr/health` |
| **Monitoring Interval** | 5 minutes (free tier minimum) |
| **Keyword Monitoring** | Aç |
| **Keyword Type** | "exists" |
| **Keyword** | `"status":"ok"` |

4. **Alert Contacts** sekmesinde email'ini ekle (Brevo SMS de var, opsiyonel)
5. Monitor'a alert contacts'tan email seç → Kaydet

## Doğrulama

24 saat sonra dashboard'da uptime % grafik görünmeli. Test için:
- Sunucu kapatma simülasyonu: `/health` endpoint'i geçici bozulursa
  (örn. DB credentials yanlış) → keyword `"status":"ok"` bulunamaz → alarm
- Test mail alındığında email kutusunu kontrol

## Public Status Page (Opsiyonel)

UptimeRobot dashboard → Status Pages → New Public Status Page
- URL: `https://stats.uptimerobot.com/...`
- Custom domain ekleyebilirsin (örn. `status.odogan.com.tr`)
- Visitor'lar uptime geçmişini görür — kullanıcı güvenini artırır

## Alternatif Servisler

| Servis | Free Tier | Avantaj |
|---|---|---|
| **UptimeRobot** | 50 monitor / 5dk | Yaygın, basit |
| **BetterUptime** | 10 monitor / 3dk | SMS dahil, modern UI |
| **StatusCake** | 100 monitor / 5dk | Kapsamlı test çeşitleri |
| **Cloudflare Health Checks** | 50 monitor / 1dk | Cloudflare arkasındaysan bedava |

## /health Endpoint Davranışı

```bash
curl -s https://odogan.com.tr/health | jq
```

Beklenen çıktı:
```json
{
  "status": "ok",
  "time": "2026-05-14T14:32:11+03:00",
  "uptime_ms": 12,
  "cache_driver": "App\\Core\\Cache\\FileCache",
  "checks": {
    "db": "ok",
    "storage_writable": true,
    "cache": "ok",
    "mail_configured": "ok"
  }
}
```

HTTP 200 = sağlıklı, HTTP 503 = degraded (DB veya storage problemi).
