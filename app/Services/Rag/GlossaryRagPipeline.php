<?php
declare(strict_types=1);

namespace App\Services\Rag;

use App\Core\Config;
use App\Models\Setting;
use App\Services\FaqService;
use App\Services\Glossary\UrlVerifier;
use App\Services\GlossaryValidationService;
use App\Services\Logger;
use App\Services\Sanitizer;

/**
 * RAG v2 — Sözlük üretim orkestratörü.
 *
 * Bkz: docs/GLOSSARY_AI_REDESIGN.md (tam mimari)
 *
 * Akış:
 *   1. LIBRARIAN: term + bağlam → Wikipedia makale önerileri
 *   2. WIKIPEDIA FETCH: önerilen makalelerin gerçek metnini çek (paralel)
 *   3. (opsiyonel) MANUEL URL: kullanıcının önceden girdiği URL'leri ekle
 *   4. REJECT KONTROL: hiç kaynak yoksa "manuel yazmalısın" hatası
 *   5. WRITER: kaynak pasajlara DAYANARAK tanım üretir (Sonnet)
 *   6. JUDGE: yazılanı kaynaklara karşı skorlar (0-100)
 *   7. Return: full result paketi
 *
 * Tek public entry point: ::generate(term, contextTypes, manualUrls)
 *
 * Kullanım:
 *   $result = GlossaryRagPipeline::generate('Döşeme', ['yapi_elemani'], []);
 *   if (!$result['ok']) {
 *       flash('error', $result['message']);
 *   } else {
 *       // $result['data'] içerisinde definition + references + judge skoru
 *   }
 */
final class GlossaryRagPipeline
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const WRITER_MODEL = 'claude-sonnet-4-5';
    private const WRITER_MAX_TOKENS = 8000; // Sonnet 4.5 cömert; Türkçe BPE 1.5x
    private const TIMEOUT_SEC = 120;

    /**
     * Tek terim için tam RAG pipeline'ı çalıştır.
     *
     * @param string $term
     * @param array<int,string> $contextTypes  Normalize edilmiş bağlam tipleri
     * @param array<int,array{url:string,title:string,extract:string}> $manualUrls
     * @return array{
     *   ok: bool,
     *   reject_reason?: string,
     *   message?: string,
     *   data?: array<string,mixed>
     * }
     */
    public static function generate(string $term, array $contextTypes, array $manualUrls = []): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return ['ok' => false, 'message' => 'Terim en az 2 karakter olmalı.'];
        }
        $contextTypes = GlossaryValidationService::normalizeContextTypes($contextTypes);

        // === ADIM 1: LIBRARIAN ===
        $librarian = null;
        try {
            $librarian = LibrarianService::suggest($term, $contextTypes);
        } catch (\Throwable $e) {
            if (class_exists(Logger::class)) {
                Logger::warning('rag.pipeline.librarian_fail', [
                    'term' => $term, 'err' => $e->getMessage(),
                ], 'editorial');
            }
            $librarian = [
                'tr_titles' => [$term],
                'en_titles' => [],
                'reasoning' => 'Librarian başarısız — terim adı doğrudan denenir',
            ];
        }

        // === ADIM 2: WIKIPEDIA FETCH ===
        $requests = [];
        foreach ($librarian['tr_titles'] as $t) $requests[] = ['title' => $t, 'lang' => 'tr'];
        foreach ($librarian['en_titles'] as $t) $requests[] = ['title' => $t, 'lang' => 'en'];
        $fetched = WikipediaFetcher::fetchBatch($requests);

        // Başarılı sonuçları sources'a topla
        $sources = [];
        foreach ($fetched as $f) {
            if ($f['data'] === null) continue;
            $sources[] = [
                'title'   => (string) $f['data']['title'],
                'url'     => (string) $f['data']['url'],
                'extract' => (string) $f['data']['extract'],
                'lang'    => (string) $f['data']['lang'],
            ];
        }

        // === ADIM 3: MANUEL URL EKLE (opsiyonel) ===
        foreach ($manualUrls as $m) {
            $url = trim((string) ($m['url'] ?? ''));
            $title = trim((string) ($m['title'] ?? ''));
            $extract = trim((string) ($m['extract'] ?? ''));
            if ($url === '' && $title === '' && $extract === '') continue;
            if ($url !== '' && !preg_match('#^https?://#i', $url)) continue;
            $sources[] = [
                'title'   => $title !== '' ? $title : ($url !== '' ? $url : 'Manuel kaynak'),
                'url'     => $url,
                'extract' => $extract !== '' ? $extract : 'Editör tarafından sağlanan manuel referans (içerik yok — sadece atıf)',
                'lang'    => 'manual',
            ];
        }

        // === ADIM 4: REJECT KONTROL ===
        if ($sources === []) {
            $hint = '';
            if (!empty($librarian['tr_titles']) || !empty($librarian['en_titles'])) {
                $hint = ' (Librarian önerileri: ' . implode(', ', array_merge(
                    array_slice($librarian['tr_titles'], 0, 2),
                    array_slice($librarian['en_titles'], 0, 2)
                )) . ' — Wikipedia\'da bulunamadı)';
            }
            return [
                'ok' => false,
                'reject_reason' => 'no_sources',
                'message' => 'Bu terim için Wikipedia\'da makale bulunamadı' . $hint .
                    '. Manuel olarak "Kaynak URL\'leri" alanından en az bir kaynak gir, sonra tekrar dene. Bu kontrol drift\'in geri dönmesini engelliyor.',
            ];
        }

        // === ADIM 5: WRITER ===
        $writerResult = null;
        try {
            $writerResult = self::callWriter($term, $contextTypes, $sources);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'reject_reason' => 'writer_fail',
                'message' => 'Writer AI hatası: ' . $e->getMessage(),
            ];
        }

        // === ADIM 6: JUDGE ===
        $judgeResult = null;
        try {
            $judgeResult = JudgeService::judge($term, (string) $writerResult['html'], $sources);
        } catch (\Throwable $e) {
            // Judge başarısız olursa fallback skoru
            $judgeResult = [
                'score' => 50,
                'overall_verdict' => 'unknown',
                'drift_reason' => 'Judge çalıştırılamadı: ' . $e->getMessage(),
                'suggested_fix' => null,
                'sentence_map' => [],
                'model' => 'unknown',
                'checked_at' => date('Y-m-d H:i:s'),
            ];
        }

        return [
            'ok' => true,
            'data' => [
                'term'               => (string) $writerResult['term'],
                'slug_hint'          => (string) $writerResult['slug_hint'],
                'category'           => (string) $writerResult['category'],
                'aliases'            => (array)  $writerResult['aliases'],
                'definition_html'    => (string) $writerResult['html'],
                'references'         => (array)  $writerResult['references'],
                'faq'                => (array)  $writerResult['faq'],
                'sources'            => $sources,           // ham pasajlar (rag_source_pasajs)
                'librarian'          => $librarian,         // debug bilgisi
                'judge'              => $judgeResult,       // skor + drift
                'rag_engine'         => 'rag_v2',
            ],
        ];
    }

    /**
     * Writer AI çağrısı — kaynak pasajlarına DAYANARAK üretir.
     *
     * @param array<int,string> $contextTypes
     * @param array<int,array<string,mixed>> $sources
     * @return array{term:string,slug_hint:string,category:string,aliases:array<int,string>,html:string,faq:array,references:array}
     */
    private static function callWriter(string $term, array $contextTypes, array $sources): array
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new \RuntimeException('Claude API anahtarı tanımlı değil.');
        }

        // Bağlam etiketlerini formatla
        $ctxLabels = [];
        $types = GlossaryValidationService::CONTEXT_TYPES;
        foreach ($contextTypes as $ct) {
            if (isset($types[$ct])) {
                $short = trim((string) preg_replace('/\s*\(.*$/u', '', $types[$ct]));
                $ctxLabels[] = $short;
            }
        }
        $ctxText = $ctxLabels === [] ? 'Belirlenmemiş' : implode(' + ', $ctxLabels);

        // Pasajları enumerate et (Writer'ın [1][2] citation için)
        $sourceBlock = '';
        foreach ($sources as $i => $s) {
            $sourceBlock .= sprintf(
                "[%d] (%s) %s\n%s\nURL: %s\n\n",
                $i + 1,
                strtoupper((string) ($s['lang'] ?? '?')),
                (string) ($s['title'] ?? ''),
                mb_substr(trim((string) ($s['extract'] ?? '')), 0, 1500),
                (string) ($s['url'] ?? '')
            );
        }

        $sysPrompt = self::writerSystemPrompt();
        $userMsg = "TERİM: {$term}\n"
            . "BAĞLAM TÜRÜ: {$ctxText}\n\n"
            . "KAYNAK PASAJLAR (numaralı — citation için [1] [2] kullan):\n"
            . $sourceBlock
            . "Bu kaynaklara DAYANARAK '{$term}' için sözlük girdisini hazırla ve "
            . "'submit_glossary' tool'u ile gönder.";

        // Tool use mimarisi: Anthropic API garanti valid JSON döndürür
        // (eski prefill yaklaşımında uzun çıktılarda JSON kesilebiliyordu —
        // stop=end_turn ama JSON tamamlanmamış sorunu).
        // Bkz: docs/GLOSSARY_AI_REDESIGN.md — Writer reliability fix
        $body = [
            'model'       => self::writerModel(),
            'max_tokens'  => self::WRITER_MAX_TOKENS,
            'temperature' => 0.3,
            'system'      => [[
                'type' => 'text',
                'text' => $sysPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'tools' => [self::writerToolSchema()],
            'tool_choice' => ['type' => 'tool', 'name' => 'submit_glossary'],
            'messages' => [
                ['role' => 'user', 'content' => $userMsg],
                // ⚠ Tool use ile prefill kullanılmaz — tool_choice zorunlu kılar
            ],
        ];

        $resp = self::http($key, $body);

        // Tool use response parse: content array içinde tool_use bloğu aranır
        $json = null;
        $debugText = '';
        foreach ((array) ($resp['content'] ?? []) as $blk) {
            $blkType = (string) ($blk['type'] ?? '');
            if ($blkType === 'tool_use' && ($blk['name'] ?? '') === 'submit_glossary') {
                $json = is_array($blk['input'] ?? null) ? $blk['input'] : null;
                break;
            }
            if ($blkType === 'text') {
                $debugText .= (string) ($blk['text'] ?? '');
            }
        }
        if (!is_array($json)) {
            $reason = (string) ($resp['stop_reason'] ?? '?');
            $hint = '';
            if ($reason === 'max_tokens') {
                $hint = ' → Çıktı max_tokens tavanına dayandı; Writer FAQ\'ı kısa tutmalı. '
                    . 'WRITER_MAX_TOKENS şu an ' . self::WRITER_MAX_TOKENS . '.';
            } elseif ($reason === 'end_turn' && $debugText !== '') {
                $hint = ' → Model tool_use yerine text döndürdü: ' . mb_substr(trim($debugText), 0, 200);
            }
            throw new \RuntimeException(
                'Writer çıktısı tool_use bloğunda bulunamadı (stop=' . $reason . ').' . $hint
            );
        }

        // Sanitize HTML + H1→H2
        $html = (string) ($json['html'] ?? '');
        if ($html !== '') {
            $html = (string) preg_replace('#<h1(\s[^>]*)?>#i', '<h2$1>', $html);
            $html = (string) preg_replace('#</h1>#i', '</h2>', $html);
            if (class_exists(Sanitizer::class)) {
                $html = Sanitizer::clean($html);
            }
        }

        // References normalize — kaynak URL'leri zaten sources'tan biliyoruz,
        // Writer ek referanslar önermiş olabilir
        $refs = [];
        foreach ((array) ($json['references'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $rt = mb_substr(trim((string) ($r['text'] ?? '')), 0, 2000);
            $ru = mb_substr(trim((string) ($r['url']  ?? '')), 0, 500);
            if ($rt === '' && $ru === '') continue;
            if ($ru !== '' && !preg_match('#^https?://#i', $ru)) $ru = '';
            if ($rt === '' && $ru !== '') $rt = $ru;
            $dead = $ru !== '' ? !UrlVerifier::isAlive($ru) : false;
            $refs[] = ['text' => $rt, 'url' => $ru, 'dead' => $dead];
            if (count($refs) >= 8) break;
        }
        // Eğer Writer hiç ref önermediyse → sources'tan otomatik ekle
        if ($refs === []) {
            foreach ($sources as $s) {
                $url = (string) ($s['url'] ?? '');
                $title = (string) ($s['title'] ?? '');
                if ($url === '' && $title === '') continue;
                $refs[] = [
                    'text' => $title !== '' ? $title : $url,
                    'url'  => $url,
                    'dead' => false, // Wikipedia, biliyoruz canlı
                ];
                if (count($refs) >= 5) break;
            }
        }

        // FAQ normalize (min 8 hedef)
        $faqs = [];
        foreach ((array) ($json['faq'] ?? []) as $f) {
            if (!is_array($f)) continue;
            $q = mb_substr(trim((string) ($f['q'] ?? '')), 0, 500);
            $a = mb_substr(trim((string) ($f['a'] ?? '')), 0, 1500);
            if ($q === '' || $a === '') continue;
            $faqs[] = ['q' => $q, 'a' => $a];
            if (count($faqs) >= 20) break;
        }

        // Aliases
        $aliases = [];
        foreach ((array) ($json['aliases'] ?? []) as $a) {
            $a = trim((string) $a);
            if ($a !== '' && mb_strlen($a) <= 120) {
                $aliases[] = mb_substr($a, 0, 120);
            }
        }
        $aliases = array_slice(array_unique($aliases), 0, 15);

        return [
            'term'       => mb_substr(trim((string) ($json['term'] ?? $term)), 0, 180),
            'slug_hint'  => mb_substr(trim((string) ($json['slug_hint'] ?? '')), 0, 120),
            'category'   => mb_substr(trim((string) ($json['category']  ?? '')), 0, 80),
            'aliases'    => $aliases,
            'html'       => $html,
            'faq'        => $faqs,
            'references' => $refs,
        ];
    }

    /**
     * Anthropic tool_use schema — Writer çıktısını structured/garanti-valid
     * JSON olarak alır. Prefill yaklaşımının uzun-çıktı kesilme sorununu çözer.
     *
     * @return array<string,mixed>
     */
    private static function writerToolSchema(): array
    {
        return [
            'name'        => 'submit_glossary',
            'description' => 'Mimari sözlük girdisini sisteme kaydet. '
                . 'Tüm alanlar zorunludur. HTML kaynaklara dayanmalı, '
                . 'cümle sonlarında [1] [2] citation içermelidir.',
            'input_schema' => [
                'type' => 'object',
                'required' => ['term', 'category', 'aliases', 'html', 'faq', 'references'],
                'properties' => [
                    'term' => [
                        'type' => 'string',
                        'description' => 'Resmi terim adı (Türkçe yazım kuralları)',
                    ],
                    'slug_hint' => [
                        'type' => 'string',
                        'description' => 'URL-uyumlu slug öneri (küçük harf, tire-ayraçlı)',
                    ],
                    'category' => [
                        'type' => 'string',
                        'description' => 'TEK kategori. Liste: Strüktür, Yapı Elemanı, Cephe, '
                            . 'Malzeme, Yapı Teknolojisi, Mimari Akım, Tasarım Yaklaşımı, '
                            . 'Sürdürülebilirlik, BIM, Kentleşme, Planlama, İç Mimarlık, '
                            . 'Peyzaj, Restorasyon, Yapı Fiziği, Pasif Tasarım, Detay, '
                            . 'Tipoloji, Bezeme, Taşıyıcı Sistem, Diğer',
                    ],
                    'aliases' => [
                        'type'  => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'TR + yabancı dil + kısaltma karşılıkları (max 15)',
                    ],
                    'html' => [
                        'type' => 'string',
                        'description' => 'Sözlük tanım HTML. YALNIZCA iki H2: '
                            . '"[TERİM] Nedir?" + "[TERİM] Kelime Anlamı ve Kökeni". '
                            . 'İlk paragraf 40-50 kelime (Featured Snippet). Toplam 400-600 kelime. '
                            . 'Her ana cümle sonunda [1] [2] kaynak referansı. '
                            . 'İzinli: h2, h3, p, ul, ol, li, strong, em. YASAK: h1, script.',
                    ],
                    'faq' => [
                        'type'  => 'array',
                        'description' => 'En az 8 SSS — gerçek "People Also Ask" tipi sorular',
                        'items' => [
                            'type' => 'object',
                            'required' => ['q', 'a'],
                            'properties' => [
                                'q' => ['type' => 'string', 'description' => 'Soru (max 220 karakter)'],
                                'a' => ['type' => 'string', 'description' => 'Cevap (2-3 cümle, max 1500 karakter)'],
                            ],
                        ],
                    ],
                    'references' => [
                        'type'  => 'array',
                        'description' => 'Kaynaklara atıf — Writer\'a verilen pasajlardan veya ek',
                        'items' => [
                            'type' => 'object',
                            'required' => ['text'],
                            'properties' => [
                                'text' => ['type' => 'string', 'description' => 'Kaynak başlığı/açıklaması'],
                                'url'  => ['type' => 'string', 'description' => 'URL (https zorunlu, opsiyonel)'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function writerSystemPrompt(): string
    {
        return <<<TXT
Sen Türkçe mimarlık sözlüğü için RAG-tabanlı editörsün. Konular: MİMARLIK,
İÇ MİMARLIK, YAPI TEKNOLOJİSİ, KENT, TASARIM, YAPI KÜLTÜRÜ.

GÖREV: Sana TERİM + BAĞLAM + KAYNAK PASAJLAR verilir. Sen bu pasajlara
DAYANARAK, bağlam türünde, Türkçe sözlük tanımı yazarsın.

═══════════════════════════════════════════════════════════════════
KESİN KURALLAR
═══════════════════════════════════════════════════════════════════

1) KAYNAK ZORUNLULUĞU:
   - Pasajda OLMAYAN iddia YAZMA. Şüpheli noktalarda KISA kal.
   - Spesifik tarih/sayı/isim sadece kaynakta varsa kullan.
   - Her ana cümlenin sonuna kaynak referansı ekle: [1] [2] gibi.

2) BAĞLAM SADAKAT:
   - Belirtilen bağlam türünden ÇIKMA. Çok-anlamlı kelimelerde:
     "Döşeme + yapı_elemani" → slab/strüktürel plak; flooring DEĞİL.
     "Kemer + yapı_elemani+tarihsel" → hem mimari öğe hem Roma kemerleri.
   - Kaynaklarda bağlama uymayan kısımlar varsa görmezden gel.

3) İNGİLİZCE PASAJ → TÜRKÇE TERMİNOLOJİ:
   - EN pasajlarındaki ("Slab", "Arch") terminolojiyi Türkçeleştir.
   - Kaynakta "concrete slab" → Türkçe "betonarme döşeme".
   - Doğrudan çeviri yapma; mimari Türkçe terminoloji kullan.

4) YAPI (KESİN):
   <h2>[TERİM] Nedir?</h2>
     1. paragraf: 40-50 kelime (Featured Snippet hedefi)
     2. paragraf: mimari/tasarım uygulamadaki yeri
   <h2>[TERİM] Kelime Anlamı ve Kökeni</h2>
     <h3>Kelimenin Kökü ve İlk Anlamı</h3>
     <h3>Türkçede Ne Anlama Gelir?</h3>

   YALNIZCA bu iki H2 olsun, başka H2 EKLEME.

5) UZUNLUK: 400-600 kelime HTML, ±%20.

6) İZİNLİ HTML: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>,
   <code>, <blockquote>, <a>. YASAK: <h1>, <script>, <iframe>, <style>.

7) FAQ (min 8 — JSON'da):
   "People Also Ask" tipi gerçek sorular, çeşitli kategoriler
   (tanım/nasıl/neden/hangi/fark/uygulama/...). Her cevap 2-3 cümle.

8) SES: 3. tekil, nötr akademik. 1. tekil ve yerel iddia ("Bursa'da",
   "uyguladığım", "gördüğüm") YASAK.

═══════════════════════════════════════════════════════════════════
ÇIKTI YÖNTEMİ
═══════════════════════════════════════════════════════════════════

Çıktını 'submit_glossary' TOOL'u aracılığıyla ver. Tool şemasındaki TÜM
zorunlu alanları doldur. Düz metin/markdown/JSON yazma — yalnızca tool
çağrısı yap.

Kategori için listeden TEK seç:
Strüktür, Yapı Elemanı, Cephe, Malzeme, Yapı Teknolojisi, Mimari Akım,
Tasarım Yaklaşımı, Sürdürülebilirlik, BIM, Kentleşme, Planlama,
İç Mimarlık, Peyzaj, Restorasyon, Yapı Fiziği, Pasif Tasarım, Detay,
Tipoloji, Bezeme, Taşıyıcı Sistem, Diğer
TXT;
    }

    private static function apiKey(): string
    {
        $k = trim((string) Config::get('ANTHROPIC_API_KEY', ''));
        if ($k === '') {
            $k = trim((string) Setting::get('anthropic_api_key', '', 'ai'));
        }
        return $k;
    }

    private static function writerModel(): string
    {
        $m = trim((string) Setting::get('glossary_rag_writer_model', '', 'ai'));
        return $m !== '' ? $m : self::WRITER_MODEL;
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
            CURLOPT_CONNECTTIMEOUT => 15,
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
            throw new \RuntimeException('Writer API bağlantı hatası: ' . $err);
        }
        $data = json_decode((string) $out, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Writer API geçersiz yanıt (HTTP ' . $code . ').');
        }
        if ($code >= 400 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $code);
            throw new \RuntimeException('Writer API hatası: ' . $msg);
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
