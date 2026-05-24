<?php
declare(strict_types=1);

namespace App\Services\Rag;

use App\Core\Config;
use App\Models\Setting;
use App\Services\GlossaryValidationService;
use App\Services\Logger;

/**
 * RAG v2 — Librarian AI: term + bağlam → Wikipedia makale önerileri.
 *
 * Bkz: docs/GLOSSARY_AI_REDESIGN.md (Decision D4)
 *
 * Görev: "Döşeme + yapı_elemani" → { tr: ["Döşeme (yapı)"], en: ["Slab"] }
 *
 * Librarian Haiku modeline iletilir (ucuz, hızlı). Yanlış makale önerse
 * bile WikipediaFetcher disambiguation veya 404 ile geri döner —
 * sistem fallback'e yönlenir (manuel kaynak veya reddetme).
 *
 * Output JSON:
 *   {
 *     "tr_titles": ["Article 1", "Article 2"],
 *     "en_titles": ["Article 1", "Article 2"],
 *     "reasoning": "kısa açıklama (debug)"
 *   }
 */
final class LibrarianService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MODEL = 'claude-haiku-4-5';
    private const MAX_TOKENS = 600;
    private const TIMEOUT_SEC = 25;

    /**
     * Librarian'a sor: bu terim + bağlam için hangi makaleler doğru?
     *
     * @param string $term
     * @param array<int,string> $contextTypes  ['yapi_elemani', 'tarihsel'] gibi
     * @return array{tr_titles:array<int,string>,en_titles:array<int,string>,reasoning:string}
     */
    public static function suggest(string $term, array $contextTypes): array
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new \RuntimeException('Claude API anahtarı tanımlı değil.');
        }
        $term = trim($term);
        if ($term === '') {
            throw new \InvalidArgumentException('Term boş olamaz.');
        }

        // Bağlam listesini etiketleriyle birlikte sun
        $types = GlossaryValidationService::CONTEXT_TYPES;
        $ctxLines = [];
        foreach ($contextTypes as $ct) {
            if (isset($types[$ct])) {
                $ctxLines[] = '  - ' . $ct . ' (' . $types[$ct] . ')';
            }
        }
        $ctxBlock = $ctxLines === [] ? '  - diger (belirlenmemiş)' : implode("\n", $ctxLines);

        $sysPrompt = self::systemPrompt();
        $userMsg = self::userMessage($term, $ctxBlock);

        $body = [
            'model'       => self::MODEL,
            'max_tokens'  => self::MAX_TOKENS,
            'temperature' => 0.2, // Deterministik tarafı tercih
            'system'      => $sysPrompt,
            'messages'    => [
                ['role' => 'user', 'content' => $userMsg],
                ['role' => 'assistant', 'content' => '{'], // JSON prefill
            ],
        ];

        $resp = self::http($key, $body);
        $text = '';
        foreach ((array) ($resp['content'] ?? []) as $blk) {
            if (($blk['type'] ?? '') === 'text') {
                $text .= (string) ($blk['text'] ?? '');
            }
        }
        $json = self::extractJson('{' . $text);
        if (!is_array($json)) {
            if (class_exists(Logger::class)) {
                Logger::warning('rag.librarian.parse_fail', [
                    'term' => $term, 'raw' => mb_substr($text, 0, 200),
                ], 'editorial');
            }
            // Boş cevap → en azından terim adının kendisi denenir
            return [
                'tr_titles' => [$term],
                'en_titles' => [],
                'reasoning' => 'Librarian parse hatası — terim adı doğrudan denenir',
            ];
        }

        return [
            'tr_titles' => self::normalizeStringArray($json['tr_titles'] ?? [], 3),
            'en_titles' => self::normalizeStringArray($json['en_titles'] ?? [], 3),
            'reasoning' => mb_substr(trim((string) ($json['reasoning'] ?? '')), 0, 500),
        ];
    }

    private static function systemPrompt(): string
    {
        return <<<TXT
Sen Türkçe mimarlık/yapı terminolojisi için Wikipedia kütüphanecisisin.
Sana bir TERİM ve BAĞLAM TÜRÜ(leri) verilir. Görevin: o terim için Türkçe
ve İngilizce Wikipedia'da hangi makalelerin doğru kaynak olacağını bulmak.

KURALLAR:
- SADECE geçerli JSON döndür. Markdown/açıklama YOK.
- Her dil için 1-3 makale başlığı öner (en olası ilk).
- BAĞLAM kritik: aynı kelime farklı bağlamlarda farklı makaleye gider.
- Disambiguation sayfası ÖNERME (örn. "Döşeme" çıplak değil "Döşeme (yapı)").
- Eğer dile karşılık yoksa boş array bırak (zorla makale uydurma).
- İngilizce karşılık seçerken bağlama dikkat:
  yapi_elemani + döşeme → "Slab" değil "Concrete slab" veya "Floor slab"
  yapi_elemani + kemer  → "Arch" (yapı), "Roman arch" değil
  yapi_teknigi + betonarme → "Reinforced concrete"
  malzeme + ahşap → "Wood" veya "Lumber"

ÖRNEK GİRDİ-ÇIKTI:

GİRDİ:
  TERİM: Döşeme
  BAĞLAM: yapi_elemani (Yapı Elemanı)
ÇIKTI:
  {
    "tr_titles": ["Döşeme (yapı)", "Döşeme"],
    "en_titles": ["Concrete slab", "Floor slab", "Slab"],
    "reasoning": "Yapı elemanı bağlamında 'döşeme' = slab (yatay taşıyıcı plak); flooring değil."
  }

GİRDİ:
  TERİM: Kemer
  BAĞLAM: yapi_elemani, tarihsel
ÇIKTI:
  {
    "tr_titles": ["Kemer (mimarlık)"],
    "en_titles": ["Arch", "Roman arch"],
    "reasoning": "Hem mimari öğe (Arch) hem tarihsel Roma kemeri kapsanır."
  }

JSON ŞEMASI:
{
  "tr_titles": ["Article 1", "Article 2"],
  "en_titles": ["Article 1", "Article 2"],
  "reasoning": "Kısa açıklama (1 cümle)"
}
TXT;
    }

    private static function userMessage(string $term, string $ctxBlock): string
    {
        return "TERİM: " . $term . "\n"
            . "BAĞLAM TÜRÜ(LERİ):\n" . $ctxBlock . "\n\n"
            . "Bu terimi yukarıdaki bağlamda en doğru şekilde tarif eden "
            . "Wikipedia makale başlıklarını JSON ile öner.";
    }

    private static function apiKey(): string
    {
        $k = trim((string) Config::get('ANTHROPIC_API_KEY', ''));
        if ($k === '') {
            $k = trim((string) Setting::get('anthropic_api_key', '', 'ai'));
        }
        return $k;
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
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEC,
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
            throw new \RuntimeException('Librarian API bağlantı hatası: ' . $err);
        }
        $data = json_decode((string) $out, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Librarian API geçersiz yanıt (HTTP ' . $code . ').');
        }
        if ($code >= 400 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $code);
            throw new \RuntimeException('Librarian API hatası: ' . $msg);
        }
        return $data;
    }

    /**
     * @param mixed $input
     * @return array<int,string>
     */
    private static function normalizeStringArray(mixed $input, int $maxItems): array
    {
        if (!is_array($input)) return [];
        $out = [];
        foreach ($input as $v) {
            $s = trim((string) $v);
            if ($s === '' || mb_strlen($s) > 200) continue;
            if (in_array($s, $out, true)) continue;
            $out[] = $s;
            if (count($out) >= $maxItems) break;
        }
        return $out;
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
