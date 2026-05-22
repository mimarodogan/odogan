<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Setting;

/**
 * Faz 5 — AI Derin Analiz (opsiyonel, talep-üzerine).
 *
 * Kural tabanlı analizin yapamadığı ÖZNEL katmanı ekler: arama niyeti,
 * içerik boşluğu, başlık/meta/TL;DR/SSS önerisi. Claude Messages API'sini
 * çağırır. Varsayılan KAPALI; API anahtarı yoksa hiç çalışmaz (güvenli).
 *
 * Anahtar önceliği: ANTHROPIC_API_KEY (env) → settings.anthropic_api_key.
 * Rubrik system prompt'u prompt-caching ile işaretlenir (tekrar maliyeti düşer).
 */
final class AiAnalysisService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const DEFAULT_MODEL = 'claude-haiku-4-5';

    public static function isEnabled(): bool
    {
        return function_exists('feature') && feature('ai_analysis_enabled') && self::apiKey() !== '';
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
     * @param array<string,mixed> $post
     * @param array<string,mixed> $context
     * @return array<string,mixed>  {verdict, intent:{match,note}, gaps:[], suggestions:{title:[],meta,tldr,faq:[{q,a}]}}
     */
    public static function analyze(array $post, array $context = []): array
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new \RuntimeException('Claude API anahtarı tanımlı değil (ANTHROPIC_API_KEY veya ayar).');
        }

        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($post['body'] ?? ''))));
        // Uzun yazılar tam değerlendirilsin diye geniş sınır (~10k token); yine de
        // aşırı uzun içerikte maliyet/limit koruması için üst sınır.
        $plain = mb_substr($plain, 0, 28000);

        $userText = self::userPayload($post, $context, $plain);

        $body = [
            'model'       => self::model(),
            'max_tokens'  => 2048,
            'temperature' => 0.3,
            'system'      => [[
                'type' => 'text',
                'text' => self::rubric(),
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            // Assistant turn'ünü '{' ile ön-doldurmak Claude'da JSON çıktısını
            // garantiler: model yanıta doğrudan JSON gövdesiyle devam eder
            // (önüne açıklama/markdown ekleyemez).
            'messages'    => [
                ['role' => 'user', 'content' => $userText],
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
        // Ön-doldurulan '{' yanıta dahil değildir; başa ekleyip ayrıştırırız.
        $json = self::extractJson('{' . $text);
        if (!is_array($json)) {
            $reason = (string) ($resp['stop_reason'] ?? '?');
            throw new \RuntimeException('AI yanıtı çözümlenemedi (stop=' . $reason . '). Başı: ' . mb_substr(trim($text), 0, 160));
        }
        return $json;
    }

    private static function rubric(): string
    {
        return <<<TXT
Sen Türkçe içerik için uzman bir SEO/E-E-A-T/AEO editörüsün. Sana bir mimarlık/yapı
sektörü blog yazısının başlığı, odak anahtar kelimesi ve gövdesi verilecek. Görevin
yazıyı değerlendirip kurallarla ölçülemeyen ÖZNEL katmanı üretmek.

YANIT KURALLARI:
- SADECE geçerli JSON döndür. Markdown, açıklama, kod bloğu YOK.
- Tüm metinler Türkçe olsun.
- JSON şeması tam olarak şu olsun:
{
  "verdict": "1-2 cümle genel değerlendirme",
  "intent": { "match": "iyi|orta|zayıf", "note": "arama niyetini neden karşılıyor/karşılamıyor" },
  "gaps": ["kapsanması gereken ama eksik olan alt-konu", "..."],
  "suggestions": {
    "title": ["alternatif başlık 1", "alternatif başlık 2", "alternatif başlık 3"],
    "meta": "150-160 karakter önerilen meta açıklama",
    "tldr": "2-3 cümlelik üst-özet (TL;DR)",
    "faq": [ { "q": "olası soru", "a": "kısa net cevap" } ]
  }
}
- gaps en fazla 5 madde; faq en fazla 4 madde.
- Önerilerini gerçekten yazının konusuna ve odak kelimeye göre özelleştir.
TXT;
    }

    /** @param array<string,mixed> $post @param array<string,mixed> $context */
    private static function userPayload(array $post, array $context, string $plain): string
    {
        $lines = [
            'ODAK ANAHTAR KELİME: ' . (string) ($post['focus_keyword'] ?? '(yok)'),
            'İKİNCİL KELİMELER: ' . (string) ($post['secondary_keywords'] ?? '(yok)'),
            'ETİKETLER: ' . (string) ($post['tags'] ?? '(yok)'),
            'KATEGORİ: ' . (string) ($context['category_name'] ?? '(yok)'),
            'BAŞLIK: ' . (string) ($post['title'] ?? ''),
            'META AÇIKLAMA: ' . (string) ($post['meta_description'] ?? '(yok)'),
            '',
            'YAZI GÖVDESİ:',
            $plain !== '' ? $plain : '(boş)',
        ];
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
            CURLOPT_TIMEOUT        => 30,
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

    /** Yanıt metninden ilk geçerli JSON nesnesini çıkarır (kod bloğu/temizlik toleranslı). */
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
