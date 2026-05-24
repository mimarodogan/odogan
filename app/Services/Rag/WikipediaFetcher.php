<?php
declare(strict_types=1);

namespace App\Services\Rag;

use App\Services\Logger;

/**
 * RAG v2 — Wikipedia REST API'den makale özetlerini çeker.
 *
 * Bkz: docs/GLOSSARY_AI_REDESIGN.md (Decision D3, D4)
 *
 * Endpoint: https://{lang}.wikipedia.org/api/rest_v1/page/summary/{title}
 *   - Ücretsiz, anonim, rate limit ~200 req/sn (anonymous)
 *   - Yanıt: { type, title, extract, content_urls, description, ... }
 *
 * Disambiguation handling: type=='disambiguation' geldiğinde null döner —
 * Librarian'ın yanlış makale önerdiğini gösterir.
 *
 * Cache: file-based, 24h TTL (storage/cache/wiki/). Negatif cache (NULL)
 * de tutulur — aynı 404'ü tekrar etmemek için. /storage altı .gitignore
 * tarafından zaten hariç tutulur — repo'ya bulaşmaz.
 *
 * Kullanım:
 *   $data = WikipediaFetcher::fetch('Slab', 'en');
 *   // null veya ['title','extract','url','description']
 */
final class WikipediaFetcher
{
    private const REST_BASE = 'https://%s.wikipedia.org/api/rest_v1/page/summary/%s';
    private const CACHE_DIR_REL = '/storage/cache/wiki';
    private const CACHE_TTL_SEC = 86400; // 24 saat
    private const TIMEOUT_SEC = 8;
    private const CONNECT_TIMEOUT_SEC = 5;
    private const MAX_ATTEMPTS = 2;
    private const USER_AGENT = 'OdoganCMS/1.0 (https://odogan.com.tr; contact via site form)';

    /**
     * Bir Wikipedia makalesinin özet bilgilerini döndürür.
     *
     * @param string $title  Makale başlığı (boşluk veya '_' kabul edilir)
     * @param string $lang   'tr' veya 'en'
     * @return array{title:string,extract:string,url:string,description:?string,lang:string}|null
     */
    public static function fetch(string $title, string $lang = 'tr'): ?array
    {
        $title = trim($title);
        $lang = strtolower(trim($lang));
        if ($title === '' || !in_array($lang, ['tr', 'en'], true)) {
            return null;
        }

        // Cache lookup (positive ve negative)
        $cacheKey = $lang . '_' . md5(mb_strtolower($title, 'UTF-8'));
        $cached = self::cacheRead($cacheKey);
        if ($cached === 'NEG_NULL') return null;
        if (is_array($cached)) return $cached;

        // Title normalize: "Concrete slab" → "Concrete_slab", URL encode
        $normalizedTitle = str_replace(' ', '_', $title);
        $url = sprintf(self::REST_BASE, $lang, rawurlencode($normalizedTitle));

        $attempt = 0;
        $delaySec = 1;
        while ($attempt < self::MAX_ATTEMPTS) {
            $attempt++;
            [$body, $httpCode, $err] = self::httpGet($url);

            if ($body === null) {
                // Transport error → retry
                if ($attempt < self::MAX_ATTEMPTS) {
                    sleep($delaySec);
                    $delaySec *= 3;
                    continue;
                }
                if (class_exists(Logger::class)) {
                    Logger::warning('rag.wikipedia.transport_fail', [
                        'title' => $title, 'lang' => $lang, 'err' => $err,
                    ], 'editorial');
                }
                self::cacheWriteNegative($cacheKey);
                return null;
            }

            // 404 → makale yok (kalıcı), negative cache
            if ($httpCode === 404) {
                self::cacheWriteNegative($cacheKey);
                return null;
            }

            // 200 dışındaki HTTP cevapları → bir kez daha dene
            if ($httpCode !== 200) {
                if ($attempt < self::MAX_ATTEMPTS) {
                    sleep($delaySec);
                    $delaySec *= 3;
                    continue;
                }
                if (class_exists(Logger::class)) {
                    Logger::warning('rag.wikipedia.http_error', [
                        'title' => $title, 'lang' => $lang, 'code' => $httpCode,
                    ], 'editorial');
                }
                self::cacheWriteNegative($cacheKey);
                return null;
            }

            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                self::cacheWriteNegative($cacheKey);
                return null;
            }

            // Disambiguation page — Librarian yanlış makale önerdi
            if (($decoded['type'] ?? '') === 'disambiguation') {
                if (class_exists(Logger::class)) {
                    Logger::info('rag.wikipedia.disambig', [
                        'title' => $title, 'lang' => $lang,
                    ], 'editorial');
                }
                self::cacheWriteNegative($cacheKey);
                return null;
            }

            $extract = trim((string) ($decoded['extract'] ?? ''));
            if (mb_strlen($extract) < 30) {
                // Faydasız kısa extract
                self::cacheWriteNegative($cacheKey);
                return null;
            }

            $normalized = [
                'title'       => (string) ($decoded['title'] ?? $title),
                'extract'     => $extract,
                'url'         => (string) ($decoded['content_urls']['desktop']['page'] ?? ''),
                'description' => isset($decoded['description']) ? (string) $decoded['description'] : null,
                'lang'        => $lang,
            ];
            self::cacheWritePositive($cacheKey, $normalized);
            return $normalized;
        }

        return null;
    }

    /**
     * Birden fazla makaleyi sıralı çek. (İleride curl_multi ile paralel
     * yapılabilir — şimdilik basit ve idempotent.)
     *
     * @param array<int,array{title:string,lang:string}> $requests
     * @return array<int,array{title:string,lang:string,data:array|null}>
     */
    public static function fetchBatch(array $requests): array
    {
        $out = [];
        foreach ($requests as $req) {
            $title = (string) ($req['title'] ?? '');
            $lang = (string) ($req['lang'] ?? 'tr');
            $out[] = [
                'title' => $title,
                'lang'  => $lang,
                'data'  => self::fetch($title, $lang),
            ];
        }
        return $out;
    }

    /**
     * @return array{0:?string,1:int,2:string}  [body, httpCode, err]
     */
    private static function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEC,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SEC,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'accept-language: tr,en;q=0.8',
            ],
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $body = ($raw === false || $raw === '') ? null : (string) $raw;
        return [$body, $code, (string) $err];
    }

    private static function cacheDir(): string
    {
        $base = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3);
        $dir = $base . self::CACHE_DIR_REL;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function cachePath(string $key): string
    {
        return self::cacheDir() . '/' . preg_replace('/[^a-z0-9_]/i', '', $key) . '.json';
    }

    /**
     * @return array<string,mixed>|string|null
     *   array → positive cache hit
     *   'NEG_NULL' → negative cache hit (eski 404/disambig)
     *   null → cache miss
     */
    private static function cacheRead(string $key)
    {
        $path = self::cachePath($key);
        if (!is_file($path)) return null;
        $mtime = filemtime($path);
        if ($mtime === false || (time() - $mtime) > self::CACHE_TTL_SEC) {
            @unlink($path);
            return null;
        }
        $content = @file_get_contents($path);
        if ($content === false) return null;
        $decoded = json_decode($content, true);
        if ($decoded === null && trim($content) === '"__NEG__"') return 'NEG_NULL';
        if (is_array($decoded)) {
            if (isset($decoded['__neg']) && $decoded['__neg'] === true) return 'NEG_NULL';
            return $decoded;
        }
        return null;
    }

    /** @param array<string,mixed> $data */
    private static function cacheWritePositive(string $key, array $data): void
    {
        $path = self::cachePath($key);
        $encoded = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($path, $encoded, LOCK_EX);
    }

    private static function cacheWriteNegative(string $key): void
    {
        $path = self::cachePath($key);
        @file_put_contents($path, '{"__neg":true}', LOCK_EX);
    }
}
