<?php
declare(strict_types=1);

namespace App\Services\Rag;

use App\Core\Config;
use App\Models\Setting;
use App\Services\Logger;

/**
 * RAG v2 — Judge AI: yazılan tanım kaynak pasajlarla tutarlı mı?
 *
 * Bkz: docs/GLOSSARY_AI_REDESIGN.md (Decision D5)
 *
 * Görev: Writer'ın ürettiği tanımdaki TÜM iddialar kaynak pasajlardan
 * çıkarsanabilir mi? Skor (0-100) + cümle bazlı sınıflandırma + drift_reason.
 *
 * Sonnet modeli kullanılır (semantic anlama gerek; Haiku yetersiz kaldı —
 * eski self-review sistemindeki kör nokta tekrarlanmasın).
 *
 * Output JSON:
 *   {
 *     "score": 0-100,
 *     "overall_verdict": "supported" | "partial" | "drift",
 *     "drift_reason": "Şu cümleler kaynakta yok: ...",
 *     "suggested_fix": "Şu pasaja göre şöyle yeniden yaz: ...",
 *     "sentence_map": [
 *       { "sentence": "...", "support": "full"|"partial"|"none", "source_idx": 0 }
 *     ]
 *   }
 */
final class JudgeService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MODEL_DEFAULT = 'claude-sonnet-4-5';
    private const MAX_TOKENS = 2000;
    private const TIMEOUT_SEC = 60;

    /**
     * Tanımı pasajlara karşı judge eder.
     *
     * @param string $term
     * @param string $definitionHtml  Writer'ın ürettiği HTML tanım
     * @param array<int,array{title:string,url:string,extract:string,lang:string}> $sources
     * @return array{
     *   score:int,
     *   overall_verdict:string,
     *   drift_reason:?string,
     *   suggested_fix:?string,
     *   sentence_map:array<int,array{sentence:string,support:string,source_idx:?int}>,
     *   model:string,
     *   checked_at:string
     * }
     */
    public static function judge(string $term, string $definitionHtml, array $sources): array
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new \RuntimeException('Claude API anahtarı tanımlı değil.');
        }
        $term = trim($term);
        if ($term === '') {
            throw new \InvalidArgumentException('Term boş olamaz.');
        }
        if ($sources === []) {
            return self::fallbackResult('no_sources');
        }

        // Tanımı düz metne çevir + kısalt
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($definitionHtml)));
        if (mb_strlen($plain) < 30) {
            return self::fallbackResult('too_short');
        }
        if (mb_strlen($plain) > 4000) {
            $plain = mb_substr($plain, 0, 4000) . '…';
        }

        // Pasajları enumerate et (sentence_map'te source_idx için)
        $sourceBlock = '';
        foreach ($sources as $i => $s) {
            $title = (string) ($s['title'] ?? '?');
            $lang = (string) ($s['lang'] ?? '?');
            $extract = trim((string) ($s['extract'] ?? ''));
            if ($extract === '') continue;
            if (mb_strlen($extract) > 1500) {
                $extract = mb_substr($extract, 0, 1500) . '…';
            }
            $sourceBlock .= sprintf(
                "[%d] (%s) %s:\n%s\n\n",
                $i,
                strtoupper($lang),
                $title,
                $extract
            );
        }

        $sysPrompt = self::systemPrompt();
        $userMsg = self::userMessage($term, $plain, $sourceBlock);

        $body = [
            'model'       => self::model(),
            'max_tokens'  => self::MAX_TOKENS,
            'temperature' => 0.1, // Judge deterministik olmalı
            'system'      => $sysPrompt,
            'messages'    => [
                ['role' => 'user', 'content' => $userMsg],
                ['role' => 'assistant', 'content' => '{'],
            ],
        ];

        try {
            $resp = self::http($key, $body);
        } catch (\Throwable $e) {
            if (class_exists(Logger::class)) {
                Logger::warning('rag.judge.api_error', [
                    'term' => $term, 'err' => $e->getMessage(),
                ], 'editorial');
            }
            return self::fallbackResult('api_error: ' . $e->getMessage());
        }

        $text = '';
        foreach ((array) ($resp['content'] ?? []) as $blk) {
            if (($blk['type'] ?? '') === 'text') {
                $text .= (string) ($blk['text'] ?? '');
            }
        }
        $json = self::extractJson('{' . $text);
        if (!is_array($json)) {
            return self::fallbackResult('parse_error');
        }

        $score = (int) max(0, min(100, (int) ($json['score'] ?? 0)));
        $verdict = (string) ($json['overall_verdict'] ?? 'unknown');
        if (!in_array($verdict, ['supported', 'partial', 'drift'], true)) {
            $verdict = $score >= 80 ? 'supported' : ($score >= 50 ? 'partial' : 'drift');
        }
        $reason = trim((string) ($json['drift_reason'] ?? ''));
        $fix = trim((string) ($json['suggested_fix'] ?? ''));

        $sentMap = [];
        foreach ((array) ($json['sentence_map'] ?? []) as $sm) {
            if (!is_array($sm)) continue;
            $sentence = trim((string) ($sm['sentence'] ?? ''));
            $support = (string) ($sm['support'] ?? 'unknown');
            if (!in_array($support, ['full', 'partial', 'none'], true)) {
                $support = 'unknown';
            }
            $srcIdx = isset($sm['source_idx']) && is_numeric($sm['source_idx'])
                ? (int) $sm['source_idx'] : null;
            if ($sentence === '') continue;
            $sentMap[] = [
                'sentence'   => mb_substr($sentence, 0, 600),
                'support'    => $support,
                'source_idx' => $srcIdx,
            ];
            if (count($sentMap) >= 30) break;
        }

        return [
            'score'           => $score,
            'overall_verdict' => $verdict,
            'drift_reason'    => $reason !== '' ? $reason : null,
            'suggested_fix'   => $fix !== '' ? $fix : null,
            'sentence_map'    => $sentMap,
            'model'           => self::model(),
            'checked_at'      => date('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string,mixed> */
    private static function fallbackResult(string $reason): array
    {
        return [
            'score'           => 0,
            'overall_verdict' => 'unknown',
            'drift_reason'    => 'Judge çalıştırılamadı: ' . $reason,
            'suggested_fix'   => null,
            'sentence_map'    => [],
            'model'           => self::model(),
            'checked_at'      => date('Y-m-d H:i:s'),
        ];
    }

    private static function systemPrompt(): string
    {
        return <<<TXT
Sen Türkçe mimarlık/yapı terminolojisi alanında titiz bir editör/hakem
(judge)'sin. Sana bir TERİM, üretilmiş bir TANIM ve KAYNAK PASAJLAR verilir.
Görevin: tanımdaki iddiaların kaynaklardan destekli olup olmadığını
değerlendirmek ve sayısal bir SKOR (0-100) vermek.

PUANLAMA REHBERİ:
- 90-100: Her cümle kaynaklardan birinden doğrudan çıkarsanabilir
- 75-89:  Çoğu cümle destekli, 1-2 cümle 'partial' veya genel-bilgi
- 50-74:  Yarısı destekli, yarısı uydurma/eksik kaynak
- 25-49:  Çoğunluk uydurma, kaynak sadece yüzeysel kullanılmış
- 0-24:   Kaynaklarla taban tabana çelişen drift vakası

DRIFT TANIMI:
- Tanım, kaynaklarda belirtilen anlamdan FARKLI bir anlamı tarif ediyorsa
- Örn: kaynak "döşeme = yatay taşıyıcı plak", tanım "döşeme = yüzey kaplaması"
  diyorsa → DRIFT, score 0-30, drift_reason açıklar.

YANIT KURALI:
- SADECE geçerli JSON, başka hiçbir şey yok.
- Türkçe açıklamalar.

JSON ŞEMASI:
{
  "score": 0-100,
  "overall_verdict": "supported" | "partial" | "drift",
  "drift_reason": "Eğer drift veya partial: hangi cümle/iddia kaynakta yok (1-2 cümle). Yoksa boş.",
  "suggested_fix": "Eğer drift veya partial: tanım nasıl düzeltilmeli (1-2 cümle). Yoksa boş.",
  "sentence_map": [
    { "sentence": "tanımdaki cümle (ilk 100 char)", "support": "full"|"partial"|"none", "source_idx": 0 }
  ]
}
TXT;
    }

    private static function userMessage(string $term, string $plain, string $sourceBlock): string
    {
        return "TERİM: " . $term . "\n\n"
            . "ÜRETİLEN TANIM (HTML temizlenmiş):\n"
            . "---\n" . $plain . "\n---\n\n"
            . "KAYNAK PASAJLAR (numaralanmış):\n"
            . $sourceBlock
            . "Bu tanımdaki iddiaları yukarıdaki kaynaklara karşı değerlendir. "
            . "JSON ile cevap ver.";
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
        $m = trim((string) Setting::get('glossary_judge_model', '', 'ai'));
        return $m !== '' ? $m : self::MODEL_DEFAULT;
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
            throw new \RuntimeException('Judge API bağlantı hatası: ' . $err);
        }
        $data = json_decode((string) $out, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Judge API geçersiz yanıt (HTTP ' . $code . ').');
        }
        if ($code >= 400 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $code);
            throw new \RuntimeException('Judge API hatası: ' . $msg);
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
