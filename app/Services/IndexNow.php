<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Setting;

/**
 * IndexNow — Bing / Yandex / Cloudflare / Naver gibi search engine'lere
 * URL'lerin değiştiğini ANINDA bildirir (no manual sitemap submit).
 *
 * Spec: https://www.indexnow.org/documentation
 *
 * Akış:
 *   1. Random key üret (örn: openssl rand -hex 16) → Setting'e kaydet
 *   2. Key dosyasını site root'a koy: /{key}.txt (içeriği aynı key)
 *      ← /Users/osmandogan/Desktop/odogan/{key}.txt manuel veya cron
 *   3. POST https://api.indexnow.org/IndexNow ile URL bildir
 *
 * Production deploy sonrası tek seferlik kurulum gerekir (key dosyası).
 * Bu service NON-BLOCKING — başarısız olursa sessizce loglar, akışı durdurmaz.
 */
final class IndexNow
{
    private const ENDPOINT = 'https://api.indexnow.org/IndexNow';
    private const TIMEOUT  = 3; // saniye (non-blocking için kısa)

    /**
     * Tek URL veya URL array bildir.
     *
     * @param string|string[] $urls
     */
    public static function ping(string|array $urls): void
    {
        // Feature flag — admin Settings'ten kapatılabilir
        if (!self::isEnabled()) {
            return;
        }
        $key = self::getKey();
        if ($key === '') {
            return; // Key kurulmamış — sessizce skip
        }

        $urls = is_array($urls) ? $urls : [$urls];
        $urls = array_filter(array_map('trim', $urls));
        if (!$urls) {
            return;
        }

        // Sadece production'da gerçekten ping at (local/staging'de log'la)
        if (Config::get('APP_ENV') !== 'production') {
            Logger::debug('indexnow.skip', [
                'reason' => 'non-production',
                'urls'   => $urls,
                'env'    => Config::get('APP_ENV'),
            ], 'seo');
            return;
        }

        $host = self::getHost();
        if ($host === '') {
            return;
        }

        $payload = [
            'host'        => $host,
            'key'         => $key,
            'keyLocation' => 'https://' . $host . '/' . $key . '.txt',
            'urlList'     => array_values($urls),
        ];

        try {
            $ch = curl_init(self::ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json; charset=utf-8',
                    'User-Agent: Odogan-CMS/1.0',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            // IndexNow response codes:
            // 200 = OK, 202 = accepted, 400 = bad request, 403 = key mismatch,
            // 422 = unprocessable, 429 = rate limited
            if ($code >= 200 && $code < 300) {
                Logger::info('indexnow.ok', [
                    'count'    => count($urls),
                    'code'     => $code,
                    'first'    => $urls[0] ?? null,
                ], 'seo');
            } else {
                Logger::warning('indexnow.fail', [
                    'code'     => $code,
                    'error'    => $err,
                    'response' => is_string($resp) ? mb_substr($resp, 0, 500) : null,
                    'urls'     => $urls,
                ], 'seo');
            }
        } catch (\Throwable $e) {
            Logger::error('indexnow.exception', [
                'msg'  => $e->getMessage(),
                'urls' => $urls,
            ], 'seo');
        }
    }

    /**
     * Bulk ping — birden fazla URL tek istekte (10000 limit).
     */
    public static function bulkPing(array $urls): void
    {
        // 10000 limit/request — chunk'la
        foreach (array_chunk(array_unique($urls), 10000) as $chunk) {
            self::ping($chunk);
        }
    }

    private static function isEnabled(): bool
    {
        return (bool) Setting::get('indexnow_enabled', true, 'seo');
    }

    private static function getKey(): string
    {
        return trim((string) Setting::get('indexnow_key', '', 'seo'));
    }

    private static function getHost(): string
    {
        $url = (string) Config::get('APP_URL', '');
        $host = (string) parse_url($url, PHP_URL_HOST);
        return $host !== '' ? $host : (string) ($_SERVER['HTTP_HOST'] ?? '');
    }

    /**
     * Yeni rastgele key üretir (kurulum kolaylığı için).
     * Admin /admin/ayarlar'dan "Yeni anahtar üret" butonuyla çağrılabilir.
     */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(16)); // 32 char hex
    }
}
