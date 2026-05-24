<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Setting;

/**
 * Q2 — Sözlük girdi kalite & bağlam denetimi servisi.
 *
 * AMAÇ: AiGlossaryService'in ürettiği tanımın doğru disambiguation
 * bağlamında olup olmadığını ikinci bir AI çağrısı ile kontrol etmek.
 *
 * Örnek senaryo:
 *   - Term: "Döşeme"
 *   - context_type: "yapi_elemani"  (kullanıcı seçti)
 *   - AI çıktısı: "Döşeme, fayans veya seramik karoların zemine
 *     yapıştırılarak monte edilmesi işlemidir..."
 *   → DRIFT: yapı elemanı bağlamı yerine eylem/işlem tanımlamış
 *   → drift_flag=1, quality_score=25, drift_reason açıklar.
 *
 * MİMARİ:
 *   - Anthropic Claude Haiku (ucuz, hızlı) — Sonnet gerekmez bu kontrol için
 *   - Tek API çağrısı, ~$0.001/terim maliyet
 *   - JSON-only yanıt: { is_correct_context, confidence, drift_*, score }
 *
 * KULLANIM:
 *   $result = GlossaryValidationService::validate(
 *       term: 'Döşeme',
 *       contextType: 'yapi_elemani',
 *       definitionHtml: '<h2>...</h2>...'
 *   );
 *   if ($result['drift_flag']) { ... admin'e bildir ... }
 */
final class GlossaryValidationService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const DEFAULT_MODEL = 'claude-haiku-4-5';
    private const MAX_TOKENS = 800; // JSON çıktı kısa, 800 yeterli

    /** Bağlam tipleri — form select ile eş zamanlı. */
    public const CONTEXT_TYPES = [
        'yapi_elemani'         => 'Yapı Elemanı (kiriş, kolon, döşeme — bir nesne/eleman)',
        'yapi_teknigi'         => 'Yapı Tekniği / Süreç (betonarme dökme, kaynak — eylem/yöntem)',
        'malzeme'              => 'Malzeme (beton, çelik — ham bileşen)',
        'mimari_akim'          => 'Mimari Akım / Üslup (brutalizm, art deco)',
        'tasarim_yaklasimi'    => 'Tasarım Yaklaşımı / Kavram (sürdürülebilirlik, biyofilik)',
        'tarihsel'             => 'Tarihsel Dönem / Kişi (Antik Roma, Mimar Sinan)',
        'standart_yonetmelik'  => 'Standart / Yönetmelik (İmar Yönetmeliği, ASCE 7)',
        'ic_mimarlik'          => 'İç Mimarlık / Donatı (mobilya, aydınlatma)',
        'diger'                => 'Diğer / Belirlenmemiş',
    ];

    /**
     * Bir sözlük girdisini bağlam denetiminden geçirir.
     *
     * @return array{
     *   ok: bool,
     *   is_correct_context: bool,
     *   confidence: float,
     *   quality_score: int,
     *   drift_flag: bool,
     *   drift_reason: ?string,
     *   suggested_fix: ?string,
     *   model: string,
     *   checked_at: string
     * }
     */
    public static function validate(string $term, string $contextType, string $definitionHtml): array
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new \RuntimeException('Claude API anahtarı tanımlı değil (ANTHROPIC_API_KEY veya ayar).');
        }

        $term = trim($term);
        if ($term === '') {
            throw new \InvalidArgumentException('Term boş olamaz.');
        }
        if (!isset(self::CONTEXT_TYPES[$contextType])) {
            $contextType = 'diger';
        }
        // Tanımdan HTML'i sıyır — AI sadece anlam için bakacak
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($definitionHtml)));
        if (mb_strlen($plain) < 30) {
            // Çok kısa tanım — anlamlı kontrol yapılamaz
            return self::fallbackResult($term, $contextType, 'too_short');
        }
        // Token bütçesi için kırp (3000 char ~= 750 token)
        if (mb_strlen($plain) > 3000) {
            $plain = mb_substr($plain, 0, 3000) . '…';
        }

        $sysPrompt = self::systemPrompt();
        $userPrompt = self::userPrompt($term, $contextType, $plain);

        $body = [
            'model'      => self::model(),
            'max_tokens' => self::MAX_TOKENS,
            'system'     => $sysPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        try {
            $resp = self::http($key, $body);
        } catch (\Throwable $e) {
            // API hatası — fail-open, admin manuel kontrol etsin
            return self::fallbackResult($term, $contextType, 'api_error: ' . $e->getMessage());
        }

        $text = '';
        foreach ((array) ($resp['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }
        $json = self::extractJson($text);
        if (!is_array($json)) {
            return self::fallbackResult($term, $contextType, 'parse_error');
        }

        $isCorrect = (bool) ($json['is_correct_context'] ?? true);
        $confidence = max(0.0, min(1.0, (float) ($json['confidence'] ?? 0.5)));
        $reason = trim((string) ($json['drift_reason'] ?? ''));
        $fix = trim((string) ($json['suggested_fix'] ?? ''));

        // Quality score formülü: doğru bağlam + yüksek confidence = 80-100,
        // yanlış bağlam + yüksek confidence = 10-30, belirsiz = 50-60.
        if ($isCorrect) {
            $score = (int) round(60 + $confidence * 40); // 60-100
        } else {
            $score = (int) round(40 - $confidence * 30); // 10-40
        }

        return [
            'ok'                  => true,
            'is_correct_context'  => $isCorrect,
            'confidence'          => $confidence,
            'quality_score'       => $score,
            'drift_flag'          => !$isCorrect && $confidence >= 0.55,
            'drift_reason'        => $reason !== '' ? $reason : null,
            'suggested_fix'       => $fix !== '' ? $fix : null,
            'model'               => self::model(),
            'checked_at'          => date('Y-m-d H:i:s'),
        ];
    }

    /** Insan-okunabilir context_type etiketi. */
    public static function contextLabel(?string $type): string
    {
        if ($type === null || $type === '') return 'Belirlenmemiş';
        return self::CONTEXT_TYPES[$type] ?? 'Diğer';
    }

    /** Validation atlandığında dönen sonuç. */
    private static function fallbackResult(string $term, string $contextType, string $reason): array
    {
        return [
            'ok'                  => false,
            'is_correct_context'  => true, // şüpheli durumda admin'e zarar verme
            'confidence'          => 0.0,
            'quality_score'       => null,
            'drift_flag'          => false,
            'drift_reason'        => 'Otomatik denetim yapılamadı: ' . $reason,
            'suggested_fix'       => null,
            'model'               => self::model(),
            'checked_at'          => date('Y-m-d H:i:s'),
        ];
    }

    private static function systemPrompt(): string
    {
        return <<<TXT
Sen Türkçe mimarlık ve yapı terminolojisinin kıdemli editörüsün.
GÖREVİN: Sana verilen bir sözlük tanımının, terim için BELİRTİLEN
BAĞLAM TÜRÜNDE doğru olup olmadığını tek bir JSON ile döndürmek.

Çok-anlamlı Türkçe kelimeler (örn. "döşeme", "perde", "çatı", "askı")
mimari sözlükte spesifik bir bağlamda kullanılır. Eğer tanım o
bağlamı tarif etmiyorsa drift'tir.

DRIFT ÖRNEKLERI:
- Term: "Döşeme", context: yapi_elemani, tanım fayans döşeme işleminden
  bahsediyor → DRIFT (yanlış bağlam, eylem değil eleman olmalı)
- Term: "Perde", context: yapi_elemani (perde duvar), tanım pencere
  perdesinden bahsediyor → DRIFT
- Term: "Konsol Kiriş", context: yapi_elemani, tanım statik mühendislik
  açısından konsol elemanı anlatıyor → DOĞRU

YANIT KURALI:
- SADECE geçerli JSON, başka hiçbir şey yok.
- Türkçe sebepler.

JSON ŞEMASI:
{
  "is_correct_context": true | false,
  "confidence": 0.0-1.0,
  "drift_reason": "Kayma varsa kısa açıklama (1 cümle). Yoksa boş string.",
  "suggested_fix": "Düzeltme önerisi: tanımın hangi yönde yenilenmesi gerek (1 cümle). Yoksa boş string."
}
TXT;
    }

    private static function userPrompt(string $term, string $contextType, string $plain): string
    {
        $label = self::CONTEXT_TYPES[$contextType] ?? 'Belirlenmemiş';
        return "TERİM: {$term}\n"
            . "BAĞLAM TÜRÜ: {$contextType} ({$label})\n\n"
            . "MEVCUT TANIM (HTML stripped, ilk ~3000 karakter):\n"
            . "---\n"
            . $plain
            . "\n---\n\n"
            . "Bu tanım, '{$term}' kelimesinin '{$label}' bağlamındaki anlamını "
            . "doğru tarif ediyor mu? JSON ile cevap ver.";
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
        // Validation için ucuz Haiku yeterli — Sonnet maliyetli ve gereksiz.
        return self::DEFAULT_MODEL;
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
            CURLOPT_TIMEOUT        => 30, // kısa kontrol, 30 sn yeterli
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
            throw new \RuntimeException('Validation API bağlantı hatası: ' . $err);
        }
        $data = json_decode((string) $out, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Validation API geçersiz yanıt (HTTP ' . $code . ').');
        }
        if ($code >= 400 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $code);
            throw new \RuntimeException('Validation API hatası: ' . $msg);
        }
        return $data;
    }

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
