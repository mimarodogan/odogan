<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Setting;

/**
 * AI Sözlük Taslak Üretici — talep-üzerine (admin panelden).
 *
 * Editör bir terim adı + (opsiyonel) bağlam verir; servis Claude Messages
 * API'sini çağırıp şu yapıda yapılandırılmış JSON döndürür:
 *
 *   {
 *     "term": string,
 *     "slug_hint": string,
 *     "category": string,
 *     "aliases": string[],
 *     "definition_html": string,      // gövde, mimari konvansiyona uygun HTML
 *     "references": [ {"text", "url"} ]
 *   }
 *
 * Yanıttaki tüm URL'ler HEAD ile doğrulanır; 200 dönmeyenler `dead=true`
 * etiketiyle döner (UI'da sarı "doğrula" rozetiyle gösterilir).
 *
 * Anahtar önceliği:  ANTHROPIC_API_KEY (env)  →  settings.anthropic_api_key
 * Sistem promptu prompt-caching ile işaretlenir.
 *
 * GATING:
 *   - feature('glossary_ai_enabled') === true olmalı
 *   - apiKey() boş değilse aktif
 */
final class AiGlossaryService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const DEFAULT_MODEL = 'claude-haiku-4-5';

    public static function isEnabled(): bool
    {
        return function_exists('feature')
            && feature('glossary_ai_enabled')
            && self::apiKey() !== '';
    }

    private static function apiKey(): string
    {
        $k = trim((string) Config::get('ANTHROPIC_API_KEY', ''));
        if ($k === '') {
            $k = trim((string) Setting::get('anthropic_api_key', '', 'ai'));
        }
        return $k;
    }

    private static function model(): string
    {
        $m = trim((string) Setting::get('ai_model', '', 'ai'));
        return $m !== '' ? $m : self::DEFAULT_MODEL;
    }

    /**
     * @param string $term      İstenen terim adı (örn: "Konsol Kiriş")
     * @param string $context   Opsiyonel kısa açıklama / hangi açıdan ele alınsın
     * @param string $depth     'kisa' | 'orta' | 'derin' (varsayılan: orta)
     * @return array{
     *   term:string, slug_hint:string, category:string,
     *   aliases:array<int,string>, definition_html:string,
     *   references:array<int,array{text:string,url:string,dead:bool}>
     * }
     */
    public static function draft(string $term, string $context = '', string $depth = 'orta'): array
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new \RuntimeException('Claude API anahtarı tanımlı değil (ANTHROPIC_API_KEY veya ayar).');
        }

        $term = trim($term);
        if (mb_strlen($term) < 2) {
            throw new \InvalidArgumentException('Terim en az 2 karakter olmalı.');
        }
        $context = mb_substr(trim($context), 0, 800);
        $depth = in_array($depth, ['kisa', 'orta', 'derin'], true) ? $depth : 'orta';

        $userText = self::userPayload($term, $context, $depth);

        // max_tokens: derin için 3500, orta 2200, kısa 1200
        $maxTokens = match ($depth) {
            'derin' => 3500,
            'kisa'  => 1200,
            default => 2200,
        };

        $body = [
            'model'       => self::model(),
            'max_tokens'  => $maxTokens,
            'temperature' => 0.4,
            'system'      => [[
                'type' => 'text',
                'text' => self::rubric(),
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => [
                ['role' => 'user', 'content' => $userText],
                // JSON çıktısını garantilemek için '{' ile prefill
                ['role' => 'assistant', 'content' => '{'],
            ],
        ];

        $resp = self::http($key, $body);

        $text = '';
        foreach (($resp['content'] ?? []) as $blk) {
            if (($blk['type'] ?? '') === 'text') {
                $text .= (string) ($blk['text'] ?? '');
            }
        }
        $json = self::extractJson('{' . $text);
        if (!is_array($json)) {
            $reason = (string) ($resp['stop_reason'] ?? '?');
            throw new \RuntimeException('AI yanıtı çözümlenemedi (stop=' . $reason . '). Başı: ' . mb_substr(trim($text), 0, 160));
        }

        return self::normalize($json);
    }

    /**
     * Yanıtı projeye uygun hale getirir; URL doğrulaması ve sanitizasyon
     * burada yapılır.
     *
     * @param array<string,mixed> $raw
     * @return array{
     *   term:string, slug_hint:string, category:string,
     *   aliases:array<int,string>, definition_html:string,
     *   references:array<int,array{text:string,url:string,dead:bool}>
     * }
     */
    private static function normalize(array $raw): array
    {
        $term = mb_substr(trim((string) ($raw['term'] ?? '')), 0, 180);
        $slug = mb_substr(trim((string) ($raw['slug_hint'] ?? '')), 0, 120);
        $cat  = mb_substr(trim((string) ($raw['category']  ?? '')), 0, 80);

        $aliases = [];
        foreach ((array) ($raw['aliases'] ?? []) as $a) {
            $a = trim((string) $a);
            if ($a !== '' && mb_strlen($a) <= 80) {
                $aliases[] = mb_substr($a, 0, 80);
            }
        }
        $aliases = array_slice(array_unique($aliases), 0, 8);

        // Tanımı sanitize et (script/iframe vb. dışarı)
        $defHtml = (string) ($raw['definition_html'] ?? '');
        if ($defHtml !== '' && class_exists(Sanitizer::class)) {
            $defHtml = Sanitizer::clean($defHtml);
        }

        // Referansları normalize et + URL doğrula
        $refs = [];
        foreach ((array) ($raw['references'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $rt = mb_substr(trim((string) ($r['text'] ?? '')), 0, 2000);
            $ru = mb_substr(trim((string) ($r['url']  ?? '')), 0, 500);
            if ($rt === '' && $ru === '') continue;
            if ($ru !== '' && !preg_match('#^https?://#i', $ru)) {
                $ru = '';
            }
            if ($rt === '' && $ru !== '') $rt = $ru;
            $dead = $ru !== '' ? !self::urlAlive($ru) : false;
            $refs[] = ['text' => $rt, 'url' => $ru, 'dead' => $dead];
        }

        return [
            'term'            => $term,
            'slug_hint'       => $slug,
            'category'        => $cat,
            'aliases'         => $aliases,
            'definition_html' => $defHtml,
            'references'      => $refs,
        ];
    }

    /**
     * URL canlı mı? HEAD ile dener, 405/501 ise GET fallback. 8 sn timeout.
     * Performans için aynı host'ta seri çağrı kabul; 5 URL × 8 sn maksimum
     * 40 sn ekstra latency demek — taslak üretiminde tolere edilebilir.
     */
    private static function urlAlive(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT      => 'OdoganBot/1.0 (+https://odogan.com.tr) link-check',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 400) return true;

        // HEAD reddedildi (405 / 501) — GET ile son şans
        if ($code === 405 || $code === 501 || $code === 0) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_USERAGENT      => 'OdoganBot/1.0 (+https://odogan.com.tr) link-check',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_RANGE          => '0-1024', // sadece ilk 1KB
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code >= 200 && $code < 400;
        }
        return false;
    }

    private static function rubric(): string
    {
        return <<<TXT
Sen Türkçe mimarlık/inşaat mühendisliği terimleri için uzman bir sözlük editörüsün.
Sana bir terim adı (ve opsiyonel bağlam) verilecek; tek bir sözlük girdisi üreteceksin.
Yazar Osman Doğan — mimar ve inşaat mühendisi — kişisel sitesi için.

YANIT KURALLARI:
- SADECE geçerli JSON döndür. Markdown, kod bloğu, açıklama YOK.
- Tüm metin Türkçe.
- JSON şeması TAM olarak şu:

{
  "term": "Tek-cümlelik resmi terim adı (Title Case değil; Türkçe yazım kuralları)",
  "slug_hint": "url-uyumlu-kısa-slug",
  "category": "Strüktür | Yapı Elemanı | Malzeme | Tasarım | Yönetmelik | BIM | Tarih | Sürdürülebilirlik | Mühendislik | Şehircilik | Diğer",
  "aliases": ["eş anlamlı 1", "kısaltma", "eski adlandırma"],
  "definition_html": "<p>...</p> şeklinde 2-5 paragraf HTML. <h3>, <ul>, <ol>, <strong>, <em>, <code>, <a> kullanabilirsin. <script>, <iframe>, <style> KULLANMA.",
  "references": [
    { "text": "Kaynak metni (kitap/yazar/yıl/sayfa veya kurum)", "url": "https://opsiyonel-link" }
  ]
}

İÇERİK KURALLARI:
- "definition_html" gövdesi MİMAR/MÜHENDİS okuyucusuna yazılır. Akademik ama erişilebilir.
- Açık tanımla başla: terimin ne olduğu, nerede kullanıldığı.
- Pratik bir örnekle bitir (mümkünse Türk yapı ortamından).
- Yanlış bilgi vermektense az bilgi ver. Emin değilsen yazma.
- Referans verirken UYDURMA. Emin olduğun kaynak yoksa "references"u boş döndür.
- Referansta URL veriyorsan sadece bilinen, kalıcı domain'leri kullan (ör. tdk.gov.tr, tmmob.org.tr, jstor.org, dergipark.org.tr, archnet.org, kayalitepe.gov.tr, tubitak.gov.tr).
- "aliases" en fazla 8 öğe; gerçekten eş anlamlı olanları yaz.
- "category" listede yoksa "Diğer" yaz; uydurma.
TXT;
    }

    private static function userPayload(string $term, string $context, string $depth): string
    {
        $depthLabel = match ($depth) {
            'derin' => 'derinlemesine (4-5 paragraf, alt başlık ekleyebilirsin)',
            'kisa'  => 'kısa (2 paragraf, en temel tanım)',
            default => 'orta (3 paragraf, pratik örnek dahil)',
        };
        $lines = [
            'TERİM: ' . $term,
            'DERİNLİK: ' . $depthLabel,
        ];
        if ($context !== '') {
            $lines[] = 'BAĞLAM/AÇIKLAMA: ' . $context;
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private static function http(string $key, array $body): array
    {
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . $key,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_POSTFIELDS     => (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $out = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($out === false) {
            throw new \RuntimeException('AI servisine bağlanılamadı: ' . $err);
        }
        $data = json_decode((string) $out, true);
        if (!is_array($data)) {
            throw new \RuntimeException('AI servisi geçersiz yanıt döndü (HTTP ' . $code . ').');
        }
        if ($code >= 400 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $code);
            throw new \RuntimeException('AI servisi hatası: ' . $msg);
        }
        return $data;
    }

    /** Yanıt metninden ilk geçerli JSON nesnesini çıkarır. */
    private static function extractJson(string $text): ?array
    {
        $t = trim($text);
        $t = (string) preg_replace('/^```(?:json)?|```$/m', '', $t);
        $start = strpos($t, '{');
        $end = strrpos($t, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $candidate = substr($t, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);
        return is_array($decoded) ? $decoded : null;
    }
}
