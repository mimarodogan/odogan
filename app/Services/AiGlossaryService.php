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
    // PARÇALI üretim devreye girdi: her chunk ~3K token çıktı üretir,
    // Haiku 4.5'in 8192 tavanına rahat sığar. Sonnet'e gerek kalmadı
    // (cost ~4x daha pahalıydı). Admin Settings'ten override edebilir.
    private const DEFAULT_MODEL = 'claude-haiku-4-5';

    /**
     * Parçalı (chunked) üretim planı — H2 (2026-05): SADECE 3 ANA BÖLÜM.
     *
     * Sözlük girdileri artık şu üç başlığı içerir:
     *   1) [TERİM] Nedir?
     *   2) [TERİM] Kelime Anlamı ve Kökeni
     *   3) Sıkça Sorulan Sorular  (min 10 soru, FAQ JSON olarak)
     *
     * Diğer tüm alt başlıklar (Tarihsel Gelişim, Türler, Karıştırılanlar,
     * Örnekler, vs.) KALDIRILDI. Sebep: AI'nın spesifik mimar/yapı/tarih
     * uydurma riski + okunabilirlik (kısa-net referans formatı tercih).
     *
     * 2 ardışık API çağrısı; sistem promptu paylaşılır → prompt caching
     * ile ikinci çağrı ucuzdur.
     *   chunk_1 → HTML gövde (Nedir + Köken) + meta (term/cat/aliases)
     *   chunk_2 → JSON meta (FAQ min 10 + references) — HTML üretmez
     */
    public const CHUNK_PLAN = [
        'chunk_1' => [
            'label'      => 'TL;DR + Nedir + Kelime Anlamı ve Kökeni',
            'word_budget'=> 600, // ~600 kelime — iki sade H2 bölümü
            'max_tokens' => 4000,
            'voice'      => '3. tekil, nötr akademik.',
            'sections'   => [
                '<div class="tldr">…2-3 cümle SEO snippet-optimize özet (40-50 kelime)…</div>',
                '<h2>[TERİM] Nedir?</h2>',
                '  (İlk paragraf 40-50 kelime: TERİM + tanım + bağlam — featured snippet hedefli)',
                '  (İkinci paragraf: mimari/tasarım uygulamadaki yeri, ne işe yarar)',
                '  (Opsiyonel üçüncü paragraf: temel özellikleri ya da neden önemli olduğu)',
                '<h2>[TERİM] Kelime Anlamı ve Kökeni</h2>',
                '  <h3>Kelimenin Kökü ve İlk Anlamı</h3>',
                '    (Etimoloji: hangi dilden gelir, kök kelime, ilk anlamı.)',
                '  <h3>Türkçede Ne Anlama Gelir?</h3>',
                '    (Türkçe yüzeysel anlam ve mimari teknik anlam ayrımı.)',
            ],
            'json_extra' => 'Bu chunk\'ta meta bilgi de döndür: "term", "slug_hint", "category", "aliases". '
                . 'BAŞKA H2 BAŞLIĞI EKLEME — yalnızca "Nedir?" ve "Kelime Anlamı ve Kökeni" iki H2 olsun.',
        ],
        'chunk_2' => [
            'label'      => 'Sıkça Sorulan Sorular (min 10) + Kaynaklar — JSON meta',
            'word_budget'=> 900, // 10+ SSS × ~60-80 kelime cevap = ~700-900
            'max_tokens' => 5000, // FAQ artık ana bölüm → token bütçesi büyütüldü
            'sections'   => [
                '(Bu chunk HTML gövdesi ÜRETMEZ. "html" alanı boş string olsun.)',
                '(Sadece JSON meta alanları: "faq" + "references")',
            ],
            'json_extra' => 'Bu chunk için JSON şeması:'
                . "\n"
                . '{ "html": "", '
                . '"faq": [{"q":"Soru?","a":"Cevap (2-3 cümle, net)"},...], '
                . '"references": [{"text":"...","url":"..."}, ...] }'
                . "\n\n"
                . 'FAQ KURALLARI (KRİTİK):'
                . "\n"
                . '- EN AZ 10 SSS üret. Tercihen 12-15 arası.'
                . "\n"
                . '- "People Also Ask" tipi gerçek kullanıcı soruları — konsept-genel sorular.'
                . "\n"
                . '- Çeşitlilik: tanım / nasıl / neden / hangi / kim / ne fark / örnek / ölçü / uygulama / vs.'
                . "\n"
                . '- Her cevap 2-3 cümle, NET ve UYDURMASIZ.'
                . "\n"
                . '- Yerel/öznel iddia yok ("Bursa\'da…", "deneyimimde…" YASAK).'
                . "\n\n"
                . 'REFERENCES KURALLARI: 3-6 GERÇEK doğrulanabilir kaynak (uydurma yok). '
                . 'URL ALANI: sadece SPESİFİK içerik URL\'i (ör. /wiki/Konsol_kirisi). '
                . 'Anasayfa URL\'i (https://tdk.gov.tr gibi sadece kök) YASAK — '
                . 'bilmiyorsan url\'i BOŞ STRING bırak.',
            'voice' => '3. tekil, nötr akademik.',
        ],
    ];

    /**
     * Bir sözlük terimi için ilişkili 5-10 yeni terim önerisi.
     * Admin "sözlüğü büyütme" aracı — bir girdiyi kaydettikten sonra
     * "AI'a 5 ilgili terim öner" butonuyla çağrılır. Çıktıdaki her terim
     * bir link kartı olarak görünür → tıklanınca o terim için yeni AI
     * üretimi başlatılır.
     *
     * @return array<int,array{term:string,category:string,short:string}>
     */
    public static function suggestRelated(string $term, string $category = '', int $count = 6): array
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new \RuntimeException('Claude API anahtarı tanımlı değil.');
        }
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            throw new \InvalidArgumentException('Terim en az 2 karakter olmalı.');
        }
        $count = max(3, min($count, 10));

        $sysPrompt = <<<TXT
Sen Türkçe mimarlık sözlüğü editörüsün. Sana bir TERIM ve (opsiyonel)
KATEGORI verilecek. Bu terimle YAKIN ilişkili, mimari sözlükte
"genişletme" için faydalı olacak 3-10 YENI terim öner.

KURALLAR:
- SADECE geçerli JSON döndür. Markdown yok.
- Tüm metin Türkçe.
- Sadece BİLDİĞİN gerçek mimari/yapı terimleri öner — uydurma yok.
- Verilen terimin kendisini önerme; gerçek farklı kavramlar olsun.
- Çok yakın eşanlamlı yerine, "aynı bağlamda ama farklı kavram" öner
  (örn. "konsol kiriş" verilirse "askı kiriş", "ankraj", "moment
  dağılımı" gibi — "kanopi" değil).

JSON ŞEMASI:
{
  "suggestions": [
    { "term": "Yeni Terim Adı", "category": "Strüktür", "short": "1 cümlelik öz tanım" },
    ...
  ]
}

Kategori için listesi:
Strüktür, Yapı Elemanı, Cephe, Malzeme, Yapı Teknolojisi, Mimari Akım,
Tasarım Yaklaşımı, Sürdürülebilirlik, BIM, Kentleşme, Planlama,
İç Mimarlık, Peyzaj, Restorasyon, Yapı Fiziği, Pasif Tasarım, Detay,
Tipoloji, Bezeme, Taşıyıcı Sistem, Diğer
TXT;

        $userText = 'TERİM: ' . $term;
        if ($category !== '') {
            $userText .= "\nKATEGORI: " . $category;
        }
        $userText .= "\n\nLütfen " . $count . ' yakın ilişkili terim öner.';

        $body = [
            'model'       => self::model(),
            'max_tokens'  => 1200,
            'temperature' => 0.5,
            'system'      => [[
                'type' => 'text',
                'text' => $sysPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => [
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
        $json = self::extractJson('{' . $text);
        if (!is_array($json) || !isset($json['suggestions']) || !is_array($json['suggestions'])) {
            throw new \RuntimeException('AI yanıtı çözümlenemedi (suggestions yok).');
        }

        $out = [];
        foreach ($json['suggestions'] as $s) {
            if (!is_array($s)) continue;
            $t = mb_substr(trim((string) ($s['term']  ?? '')), 0, 180);
            $c = mb_substr(trim((string) ($s['category'] ?? '')), 0, 80);
            $d = mb_substr(trim((string) ($s['short'] ?? '')), 0, 300);
            if ($t === '') continue;
            $out[] = ['term' => $t, 'category' => $c, 'short' => $d];
            if (count($out) >= $count) break;
        }
        return $out;
    }

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
        // Glossary'ye özel model önceliği: glossary_ai_model > ai_model > sonnet default.
        $m = trim((string) Setting::get('glossary_ai_model', '', 'ai'));
        if ($m === '') {
            $m = trim((string) Setting::get('ai_model', '', 'ai'));
        }
        return $m !== '' ? $m : self::DEFAULT_MODEL;
    }

    /**
     * @param string $term      İstenen terim adı (örn: "Konsol Kiriş")
     * @param string $context   Opsiyonel kısa açıklama / hangi açıdan ele alınsın
     * @param string $depth     'kisa' | 'orta' | 'derin' (varsayılan: orta)
     * @param array  $current   Mevcut girdiye geçince doldurulur. Anahtarlar:
     *                          'definition'(html), 'category', 'aliases'(csv),
     *                          'references'([{text,url},...] veya json string).
     *                          Boş array → yeni-üretim modu (default).
     * @return array{
     *   term:string, slug_hint:string, category:string,
     *   aliases:array<int,string>, definition_html:string,
     *   references:array<int,array{text:string,url:string,dead:bool}>
     * }
     */
    public static function draft(string $term, string $context = '', string $depth = 'orta', array $current = []): array
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

        $isEnhance = self::hasCurrentContent($current);
        $userText = $isEnhance
            ? self::enhancePayload($term, $context, $depth, $current)
            : self::userPayload($term, $context, $depth);

        // max_tokens — modele göre. Haiku 4.5'in 8192 output tavanı 12-bölümlü
        // şablon için dar; Sonnet 4.5 ise 64K'ya kadar verir.
        //   Haiku:  kısa=4000  orta=7000  derin=8000  (tavan)
        //   Sonnet: kısa=7000  orta=14000 derin=20000 (cömert)
        $usingHaiku = str_contains(strtolower(self::model()), 'haiku');
        $maxTokens = match ($depth) {
            'derin' => $usingHaiku ? 8000  : 20000,
            'kisa'  => $usingHaiku ? 4000  : 7000,
            default => $usingHaiku ? 7000  : 14000,
        };

        $body = [
            'model'       => self::model(),
            'max_tokens'  => $maxTokens,
            // Enhance modunda biraz daha muhafazakar (mevcut metni koru); yeni
            // üretimde biraz daha yaratıcı.
            'temperature' => $isEnhance ? 0.25 : 0.4,
            'system'      => [[
                'type' => 'text',
                'text' => $isEnhance ? self::rubricEnhance() : self::rubric(),
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
            $hint = '';
            if ($reason === 'max_tokens') {
                $currentModel = self::model();
                if (str_contains(strtolower($currentModel), 'haiku')) {
                    $hint = ' → Çıktı Haiku tavanına takıldı. Settings\'ten '
                          . '"glossary_ai_model = claude-sonnet-4-5" yap (64K output kapasitesi) '
                          . 'veya derinliği "Kısa"ya çek.';
                } else {
                    $hint = ' → Bu derinlik için bile çıktı kesildi; "Orta" veya "Kısa" derinlikle dene.';
                }
            }
            throw new \RuntimeException(
                'AI yanıtı çözümlenemedi (stop=' . $reason . ').'
                . $hint
                . ' Başı: ' . mb_substr(trim($text), 0, 160)
            );
        }

        return self::normalize($json);
    }

    /**
     * OUTLINE PRE-PASS — 5 chunk'tan ÖNCE çağrılır.
     *
     * Tüm yazı için anahtar-cümle planı üretir; sonraki chunk'lar bu
     * plana uyarak çakışmadan yazılır. Tekrar ve üslup tutarsızlığını
     * büyük ölçüde önler.
     *
     * @return array{
     *   tldr:string, focus_keyword:string, secondary_keywords:array,
     *   outline:array, key_architects:array, key_buildings:array,
     *   ts_standards:array
     * }
     */
    public static function draftOutline(string $term, string $context = '', string $depth = 'orta', array $current = []): array
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new \RuntimeException('Claude API anahtarı tanımlı değil.');
        }

        $term = trim($term);
        if (mb_strlen($term) < 2) {
            throw new \InvalidArgumentException('Terim en az 2 karakter olmalı.');
        }
        $context = mb_substr(trim($context), 0, 800);
        $isEnhance = self::hasCurrentContent($current);

        $lines = [
            'TERİM: ' . $term,
            'DERİNLİK: ' . $depth . ' (toplam hedef: ' . self::depthWordTarget($depth) . ' kelime)',
        ];
        if ($context !== '') {
            $lines[] = 'KULLANICI NOTU: ' . $context;
        }
        if ($isEnhance) {
            $lines[] = '';
            $lines[] = 'GELİŞTİRME MODU: aşağıdaki mevcut girdiye bakarak outline üret.';
            $lines[] = 'Mevcut yapıyı koruyacak şekilde plan yap:';
            $lines[] = self::enhanceContextBlock($current);
        }

        $body = [
            'model'       => self::model(),
            'max_tokens'  => 1500,    // Outline küçük (~600-1000 token yeterli)
            'temperature' => 0.3,     // Plan deterministik olsun
            'system'      => [[
                'type' => 'text',
                'text' => self::outlineRubric(),
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => [
                ['role' => 'user', 'content' => implode("\n", $lines)],
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
            throw new \RuntimeException(
                'Outline çözümlenemedi (stop=' . $reason . '). '
                . 'Başı: ' . mb_substr(trim($text), 0, 160)
            );
        }

        // Normalize
        return [
            'tldr'               => mb_substr(trim((string) ($json['tldr'] ?? '')), 0, 600),
            'focus_keyword'      => mb_substr(trim((string) ($json['focus_keyword'] ?? '')), 0, 180),
            'secondary_keywords' => array_slice(array_filter(array_map('trim', (array) ($json['secondary_keywords'] ?? []))), 0, 6),
            'outline'            => is_array($json['outline'] ?? null) ? $json['outline'] : [],
            'key_architects'     => array_slice(array_filter(array_map('trim', (array) ($json['key_architects'] ?? []))), 0, 6),
            'key_buildings'      => array_slice(array_filter(array_map('trim', (array) ($json['key_buildings'] ?? []))), 0, 6),
            'ts_standards'       => array_slice(array_filter(array_map('trim', (array) ($json['ts_standards'] ?? []))), 0, 6),
        ];
    }

    /**
     * Toplam kelime hedefi (derinliğe göre).
     */
    private static function depthWordTarget(string $depth): int
    {
        return match ($depth) {
            'derin' => 5000,
            'kisa'  => 1500,
            default => 3000,
        };
    }

    /**
     * PARÇALI üretim: 12 H2 yapılı ansiklopedik şablonun TEK bir parçasını üret.
     *
     * Avantajları:
     *   - Her chunk ~3K token output → Haiku 4.5'in 8192 tavanına rahat sığar.
     *   - Sistem promptu paylaşılır (cache hit ikinci chunk'tan itibaren).
     *   - Bir chunk başarısız olsa diğerleri etkilenmez (client retry).
     *   - Kullanıcı bölümlerin canlı dolduğunu görür.
     *
     * @param string $chunkId  CHUNK_PLAN anahtarlarından biri
     * @param array  $current  Enhance modu için mevcut girdi (boş = yeni-üretim)
     * @param array  $outline  draftOutline() çıktısı (boş = legacy çağrı)
     * @return array{
     *   chunk_id:string,
     *   html:string,
     *   term?:string, slug_hint?:string, category?:string, aliases?:array<int,string>,
     *   references?:array<int,array{text:string,url:string,dead:bool}>,
     *   faq?:array<int,array{q:string,a:string}>
     * }
     */
    public static function draftChunk(string $chunkId, string $term, string $context = '', string $depth = 'orta', array $current = [], array $outline = []): array
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new \RuntimeException('Claude API anahtarı tanımlı değil.');
        }
        if (!isset(self::CHUNK_PLAN[$chunkId])) {
            throw new \InvalidArgumentException('Geçersiz chunk_id: ' . $chunkId);
        }

        $term = trim($term);
        if (mb_strlen($term) < 2) {
            throw new \InvalidArgumentException('Terim en az 2 karakter olmalı.');
        }
        $context = mb_substr(trim($context), 0, 800);
        $depth = in_array($depth, ['kisa', 'orta', 'derin'], true) ? $depth : 'orta';

        $isEnhance = self::hasCurrentContent($current);

        // Chunk başına max_tokens: CHUNK_PLAN'da her chunk için "max_tokens"
        // base değeri tanımlı (orta derinlik içindir). Derin/kısa için
        // multiplier uygulanır. Haiku 4.5 tavanı 8192 — asla aşılmaz.
        $plan = self::CHUNK_PLAN[$chunkId];
        $base = (int) $plan['max_tokens'];
        $multiplier = match ($depth) {
            'derin' => 1.3,
            'kisa'  => 0.6,
            default => 1.0,
        };
        $maxTokens = (int) ($base * $multiplier);
        if ($isEnhance) $maxTokens = (int) ($maxTokens * 1.15);
        // Haiku üst sınırı: 8000 (8192 tavanından 192 buffer)
        $maxTokens = min($maxTokens, 8000);

        $userText = self::chunkUserPayload($chunkId, $term, $context, $depth, $current, $isEnhance, $outline);

        $body = [
            'model'       => self::model(),
            'max_tokens'  => $maxTokens,
            'temperature' => $isEnhance ? 0.25 : 0.4,
            'system'      => [[
                'type' => 'text',
                'text' => self::chunkSystemRubric(),
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => [
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
        $json = self::extractJson('{' . $text);
        if (!is_array($json)) {
            $reason = (string) ($resp['stop_reason'] ?? '?');
            throw new \RuntimeException(
                'Chunk ' . $chunkId . ' çözümlenemedi (stop=' . $reason . '). '
                . 'Başı: ' . mb_substr(trim($text), 0, 160)
            );
        }

        return self::normalizeChunk($chunkId, $json);
    }

    /**
     * Chunk yanıtını projeye uygun hale getirir. chunk_1'de term/cat/aliases,
     * chunk_2'de references doğrulanır. HTML her chunk için sanitize edilir.
     *
     * @param array<string,mixed> $raw
     * @return array{
     *   chunk_id:string, html:string,
     *   term?:string, slug_hint?:string, category?:string, aliases?:array<int,string>,
     *   references?:array<int,array{text:string,url:string,dead:bool}>
     * }
     */
    private static function normalizeChunk(string $chunkId, array $raw): array
    {
        $out = ['chunk_id' => $chunkId];

        // HTML: defansif H1 → H2 dönüşümü + sanitize
        $html = (string) ($raw['html'] ?? '');
        if ($html !== '') {
            $html = (string) preg_replace('#<h1(\s[^>]*)?>#i', '<h2$1>', $html);
            $html = (string) preg_replace('#</h1>#i', '</h2>', $html);
            if (class_exists(Sanitizer::class)) {
                $html = Sanitizer::clean($html);
            }
        }
        $out['html'] = $html;

        // Chunk 1: meta alanları
        if ($chunkId === 'chunk_1') {
            $out['term']      = mb_substr(trim((string) ($raw['term']      ?? '')), 0, 180);
            $out['slug_hint'] = mb_substr(trim((string) ($raw['slug_hint'] ?? '')), 0, 120);
            $out['category']  = mb_substr(trim((string) ($raw['category']  ?? '')), 0, 80);

            $aliases = [];
            foreach ((array) ($raw['aliases'] ?? []) as $a) {
                $a = trim((string) $a);
                if ($a !== '' && mb_strlen($a) <= 120) {
                    $aliases[] = mb_substr($a, 0, 120);
                }
            }
            $out['aliases'] = array_slice(array_unique($aliases), 0, 15);
        }

        // Chunk 2 (H2): references — URL HEAD doğrulama + FAQ min 10
        if ($chunkId === 'chunk_2') {
            $refs = [];
            foreach ((array) ($raw['references'] ?? []) as $r) {
                if (!is_array($r)) continue;
                $rt = mb_substr(trim((string) ($r['text'] ?? '')), 0, 2000);
                $ru = mb_substr(trim((string) ($r['url']  ?? '')), 0, 500);
                if ($rt === '' && $ru === '') continue;
                if ($ru !== '' && !preg_match('#^https?://#i', $ru)) {
                    $ru = '';
                }
                // Anasayfa URL'leri "kaynak vermiş gibi" yapar ama bilgi
                // taşımaz. Tespit edip url'i boşalt — text alanı kalır,
                // okuyucu akademik atıfı görür ama yararsız link tıklayamaz.
                if ($ru !== '' && \App\Services\Glossary\UrlVerifier::isHomepageUrl($ru)) {
                    $ru = '';
                }
                if ($rt === '' && $ru !== '') $rt = $ru;
                $dead = $ru !== '' ? !\App\Services\Glossary\UrlVerifier::isAlive($ru) : false;
                $refs[] = ['text' => $rt, 'url' => $ru, 'dead' => $dead];
                if (count($refs) >= 8) break;
            }
            $out['references'] = $refs;

            // FAQ — H2 (2026-05): min 10, max 20 SSS. FAQPage schema markup için.
            // Eski limit 6'dan büyütüldü çünkü FAQ artık ana sözlük bölümü.
            $faqs = [];
            foreach ((array) ($raw['faq'] ?? []) as $f) {
                if (!is_array($f)) continue;
                $q = mb_substr(trim((string) ($f['q'] ?? '')), 0, 500);
                $a = mb_substr(trim((string) ($f['a'] ?? '')), 0, 1500);
                if ($q === '' || $a === '') continue;
                $faqs[] = ['q' => $q, 'a' => $a];
                if (count($faqs) >= 20) break;
            }
            $out['faq'] = $faqs;
        }

        return $out;
    }

    /**
     * Tüm chunk'lar için ortak sistem rubrik'i. Tek prompt cache slot'unda
     * kalır → ikinci chunk'tan itibaren cache-hit (ucuz).
     */
    private static function chunkSystemRubric(): string
    {
        return <<<TXT
Sen Türkçe mimarlık sözlüğü için SEO odaklı uzman editörsün. Konular:
MİMARLIK, İÇ MİMARLIK, YAPI TEKNOLOJİSİ, KENT, TASARIM, YAPI KÜLTÜRÜ.

GÖREV: Kullanıcı sana bir TERİM + outline + hangi CHUNK'ı üreteceğini
söyler. Sen yalnızca o chunk'ın bölümlerini üretirsin.

═══════════════════════════════════════════════════════════════════
KALİTE ANAYASASI (SIRASIYLA UYULACAK)
═══════════════════════════════════════════════════════════════════

1) UYDURMA YASAĞI (EN ÖNEMLİ):
   - Sayı/tarih/yapı/mimar adı emin değilsen ASLA yazma.
   - Bir bilgi vermek için en az 2 bağımsız hatırladığın kaynak olmalı.
   - Belirsizken "20. yy başları", "modernizm döneminde" gibi GENEL
     ifadeler kullan; spesifik yıl/yapı/kişi UYDURMA.
   - Şüphelenirsen bölümü kısa tut, içerik az kalsın — yanlış olmasından iyidir.
   - YEREL/ÖZNEL İDDİA YOK: "Türkiye'de", "Bursa'da", "uyguladığım"
     gibi yerel/kişisel ifadeler kullanma — bu yapı çıkarıldı.

   ─── KAYNAK (references) ÖZEL KURALLARI (chunk_2) ───

   1.a) UYDURMA YOK: sadece BİLDİĞİN gerçek yayın/kitap/makale.
        Tercih edilen domain'ler: tdk.gov.tr, archnet.org, jstor.org,
        dergipark.org.tr, mimarist.org, arkitera.com, yapi.com.tr,
        üniversite yayınları, akademik DOI'ler.

   1.b) URL ALANI İÇİN: SADECE SPESİFİK İÇERİK URL'i. Anasayfa URL'i
        VERMEZSİN — kullanıcıya yararı yok, "kaynak vermiş gibi"
        yapar ama bilgi taşımaz.

        ✅ KABUL (deep URL):
        - https://sozluk.gov.tr/?ara=konsol         (spesifik sözlük girdisi)
        - https://tr.wikipedia.org/wiki/Konsol_kirisi  (spesifik makale)
        - https://dergipark.org.tr/tr/pub/.../article/12345
        - https://archnet.org/sites/12345           (spesifik yapı)
        - https://doi.org/10.1234/xyz               (DOI)

        ❌ RED (anasayfa / yararsız):
        - https://tdk.gov.tr                        (sadece kök)
        - https://wikipedia.org                     (sadece kök)
        - https://www.archnet.org                   (sadece kök)
        - https://www.tmmob.org.tr                  (sadece kök)
        - https://www.yapi.com.tr                   (sadece kök)

   1.c) Spesifik URL'i BİLMİYORSAN, "url" alanını BOŞ STRING bırak ("").
        Tahmin etme, çıkarsama yapma. Boş URL kabul edilir; uydurma
        kabul edilmez. Text alanında tam akademik atıf yeterli:
        "Tanyeli, Uğur. Modern Türkiye Mimarlığı, İletişim, 2007, s.142"
        — URL olmadan da değerli kaynaktır.

2) TEKRAR YASAĞI:
   - chunk_1 → Nedir + Köken HTML; chunk_2 → SADECE FAQ + references (JSON).
   - chunk_2'de HTML üretme; FAQ cevaplarında chunk_1'deki tanımı KOPYALAMA,
     soruya doğrudan cevap ver.

3) FEATURED SNIPPET (chunk_1 ilk paragraf):
   - 40-50 kelime arası
   - "[TERİM], …" ile başlayan kısa-net tanım
   - Tek paragraf, alt cümle yok

4) FOCUS KEYWORD DENSITY:
   - Terim metinde doğal olarak %1-2 oranında geçsin.
   - Şişirme/stuffing yasak; doğal akışta serpiştir.

5) BAŞLIK HİYERARŞİSİ:
   - SADECE <h2>, <h3>, <h4>. <h1> KESİNLİKLE YASAK.
   - İzinli etiketler: <p>, <ul>, <ol>, <li>, <strong>, <em>, <code>,
     <blockquote>, <a>, <div class="tldr"> (sadece chunk_1).
   - YASAK: <script>, <iframe>, <style>, <h1>.

6) LENGTH BUDGET (sıkı uy):
   - Her chunk'ın "word_budget" hedefi user message'da verilir.
   - %20 üstüne çıkma; %20 altına da inme.

7) SES:
   - Tüm chunk'lar 3. tekil, nötr akademik ses.
   - 1. tekil ("gördüğüm", "uyguladığım") veya yerel iddia ("Bursa'da") YASAK.
   - Sadece evrensel/akademik bilgi.

8) GEÇİŞLER:
   - Bölüm başlarken bir önceki chunk'ın bittiği konuya kısa köprü kur
     (1 cümle yeter). Outline bilgisini kullan.

═══════════════════════════════════════════════════════════════════
JSON ŞEMASI
═══════════════════════════════════════════════════════════════════

SADECE geçerli JSON. Markdown, kod bloğu, açıklama YOK. Tüm metin Türkçe.

CHUNK 1 (HTML gövde — Nedir + Köken):
{
  "term": "Resmi terim (Türkçe yazım kuralları)",
  "slug_hint": "url-uyumlu-slug",
  "category": "TEK kategori (listeden)",
  "aliases": ["TR + yabancı dil + kısaltma"],
  "html": "<div class=\"tldr\">…</div><h2>[TERİM] Nedir?</h2>…<h2>[TERİM] Kelime Anlamı ve Kökeni</h2>… (yalnızca bu iki H2)"
}

CHUNK 2 (JSON meta — FAQ min 10 + References, HTML YOK):
{
  "html": "",
  "faq": [
    { "q": "Soru 1?",  "a": "Cevap (2-3 cümle)" },
    { "q": "Soru 2?",  "a": "Cevap" },
    …
    { "q": "Soru 10?", "a": "Cevap" }
  ],
  "references": [
    { "text": "Kaynak Başlığı — Yazar, Yayın (Yıl)", "url": "https://veya-bos" }
  ]
}

KATEGORİ LİSTESİ (chunk_1'de TEK seç):
Strüktür, Yapı Elemanı, Cephe, Malzeme, Yapı Teknolojisi, Mimari Akım,
Tasarım Yaklaşımı, Sürdürülebilirlik, BIM, Kentleşme, Planlama,
İç Mimarlık, Peyzaj, Restorasyon, Yapı Fiziği, Pasif Tasarım, Detay,
Tipoloji, Bezeme, Taşıyıcı Sistem, Diğer
TXT;
    }

    /**
     * Outline pre-pass rubrik'i — H2 (2026-05) sonrası SADE 2-bölümlü plan.
     * Çıktısı 2 chunk'a bağlam olarak verilir; FAQ konularının "Nedir/Köken"
     * bölümünde tekrar edilmemesini sağlar.
     */
    private static function outlineRubric(): string
    {
        return <<<TXT
Sen Türkçe mimarlık sözlüğü için kıdemli editörsün. Sana bir TERİM verilir.
Görevin sözlük girdisini planlamaktır — sonradan 2 chunk bu plana uyarak
içeriği üretecek.

YAPI (H2 — 2026-05 sade format):
  chunk_1 → <h2>Nedir?</h2> + <h2>Kelime Anlamı ve Kökeni</h2>  (~600 kelime HTML)
  chunk_2 → 10+ SSS (JSON) + References (JSON) — HTML üretmez

GÖREV: Bu iki chunk için anahtar-cümle planı üret. TL;DR yaz, hangi
SSS kategorileri olacağını listele. Etimolojinin ana hattını çiz.

YANIT KURALLARI:
- SADECE geçerli JSON döndür. Markdown, açıklama YOK.
- Tüm metin Türkçe.
- TL;DR 40-50 kelime — featured snippet hedefli.
- Belirsiz/uydurma bilgi yerine boş bırakmayı seç.
- Yerel/öznel iddialardan kaçın — yazı evrensel/akademik kalmalı.

JSON ŞEMASI (TAM):
{
  "tldr": "40-50 kelime, terim + tanım + bağlam (featured snippet hedefli)",
  "focus_keyword": "ana terim (genelde TERİM'in normal hali)",
  "secondary_keywords": ["yakın anahtar 1", "yakın 2", "yakın 3"],
  "outline": {
    "nedir_koken":   "Bölüm 1: Nedir + Kelime Anlamı ve Kökeni — hangi tanım, hangi etimoloji ipucu, hangi dilden gelir.",
    "faq_konulari":  "Bölüm 2: 10+ SSS hangi alanları kapsayacak (tanım/nasıl/neden/hangi/fark/örnek/ölçü/uygulama/malzeme/türler/karıştırılan vs)."
  },
  "key_architects": [],
  "key_buildings":  []
}

ÖNEMLİ:
- Bu sadece PLAN — gerçek yazıyı chunk_1 ve chunk_2 yazacak.
- key_architects ve key_buildings genellikle BOŞ kalır (kısa-net format).
TXT;
    }

    /**
     * Chunk'a özel kullanıcı mesajı — hangi bölümlerin üretileceği listelenir.
     * @param array<string,mixed> $current
     */
    private static function chunkUserPayload(string $chunkId, string $term, string $context, string $depth, array $current, bool $isEnhance, array $outline = []): string
    {
        $plan = self::CHUNK_PLAN[$chunkId];
        $sections = implode("\n", (array) $plan['sections']);
        // CHUNK_PLAN'da her chunk için voice/word_budget tanımlı.
        $voice = (string) $plan['voice'];
        $wordBudget = (int) $plan['word_budget'];

        $lines = [
            'TERİM: ' . $term,
            'CHUNK: ' . $chunkId . ' — ' . $plan['label'],
            'HEDEF UZUNLUK: ' . $wordBudget . ' kelime (±%20). Sıkı uy.',
            'YAZAR SESİ: ' . $voice,
        ];
        if ($context !== '') {
            $lines[] = 'KULLANICI NOTU: ' . $context;
        }

        // OUTLINE bağlamı (draftOutline'dan gelir) — tekrar/çakışma önleme
        if ($outline !== []) {
            $lines[] = '';
            $lines[] = '═══ KÜRESEL OUTLINE (diğer chunk\'lar bunu uyguluyor — sen sadece KENDİ bölümünü yaz) ═══';
            if (!empty($outline['focus_keyword'])) {
                $lines[] = 'Focus keyword: ' . $outline['focus_keyword'] . ' (doğal olarak metinde %1-2 geç)';
            }
            if (!empty($outline['outline']) && is_array($outline['outline'])) {
                $lines[] = 'Bölüm planları (her birinin neye değineceği):';
                foreach ($outline['outline'] as $secKey => $secNote) {
                    $lines[] = '  • ' . $secKey . ': ' . (string) $secNote;
                }
            }
            if (!empty($outline['key_architects'])) {
                $lines[] = 'Anahtar mimarlar: ' . implode(', ', (array) $outline['key_architects']);
            }
            if (!empty($outline['key_buildings'])) {
                $lines[] = 'Anahtar yapılar: ' . implode(', ', (array) $outline['key_buildings']);
            }
            if (!empty($outline['ts_standards'])) {
                $lines[] = 'Türk Standartları: ' . implode(', ', (array) $outline['ts_standards']);
            }
            // TLDR sadece chunk_1'de kullanılır
            if ($chunkId === 'chunk_1' && !empty($outline['tldr'])) {
                $lines[] = 'TLDR (chunk_1 başında <div class="tldr"> içine koy): ' . $outline['tldr'];
            }
            $lines[] = '═══════════════════════════════════════════════════════════════';
        }

        $lines[] = '';
        $lines[] = 'BU CHUNK\'TA ÜRETECEKLERİN (YALNIZCA aşağıdakileri yaz; başka bölüm üretme):';
        $lines[] = $sections;

        if (!empty($plan['json_extra'])) {
            $lines[] = '';
            $lines[] = (string) $plan['json_extra'];
        }

        // Enhance modu: mevcut içeriği bağlam olarak ekle
        if ($isEnhance) {
            $lines[] = '';
            $lines[] = 'GELİŞTİRME MODU AKTİF — mevcut girdi:';
            $lines[] = self::enhanceContextBlock($current);
            $lines[] = 'Mevcut metni TAMAMEN YENİDEN YAZMA; eksikleri tamamla, hataları düzelt.';
        }

        return implode("\n", $lines);
    }

    /**
     * Enhance bağlam bloğu (chunk'lara dağıtılır).
     * @param array<string,mixed> $current
     */
    private static function enhanceContextBlock(array $current): string
    {
        $curDef  = trim((string) ($current['definition'] ?? ''));
        $curCat  = trim((string) ($current['category']   ?? ''));
        $curAli  = trim((string) ($current['aliases']    ?? ''));
        $curRefs = $current['references'] ?? '';

        $refsBlock = '';
        if (is_string($curRefs) && $curRefs !== '') {
            $dec = json_decode($curRefs, true);
            if (is_array($dec)) {
                foreach ($dec as $i => $r) {
                    if (!is_array($r)) continue;
                    $refsBlock .= '  ' . ($i + 1) . ') '
                        . (string) ($r['text'] ?? '')
                        . ((!empty($r['url'])) ? '  (' . $r['url'] . ')' : '')
                        . "\n";
                }
            } else {
                $refsBlock = $curRefs;
            }
        }
        if ($refsBlock === '') $refsBlock = '  (yok)';

        return "  Kategori: " . ($curCat !== '' ? $curCat : '(yok)') . "\n"
             . "  Alias'lar: " . ($curAli !== '' ? $curAli : '(yok)') . "\n"
             . "  Mevcut Kaynaklar:\n" . $refsBlock
             . "  Mevcut Tanım (HTML, kısaltılmış):\n  "
             . mb_substr($curDef, 0, 4000)
             . (mb_strlen($curDef) > 4000 ? '...(kısaltıldı)' : '');
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
            if ($a !== '' && mb_strlen($a) <= 120) {
                $aliases[] = mb_substr($a, 0, 120);
            }
        }
        // 4-12 hedef; üst sınır 15 (yabancı dil varyantları için).
        $aliases = array_slice(array_unique($aliases), 0, 15);

        // Tanımı sanitize et (script/iframe vb. dışarı). AI bazen prompt'a
        // rağmen <h1> üretebilir — sayfa zaten H1 atadığından, defansif
        // olarak <h1>/</h1> → <h2>/</h2> dönüştür.
        $defHtml = (string) ($raw['definition_html'] ?? '');
        if ($defHtml !== '') {
            $defHtml = (string) preg_replace('#<h1(\s[^>]*)?>#i', '<h2$1>', $defHtml);
            $defHtml = (string) preg_replace('#</h1>#i', '</h2>', $defHtml);
            if (class_exists(Sanitizer::class)) {
                $defHtml = Sanitizer::clean($defHtml);
            }
        }

        // Referansları normalize et + URL doğrula. Ansiklopedik şablonda
        // 3-6 referans hedefli; üst sınır 10 (uzun girdiler için).
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
            $dead = $ru !== '' ? !\App\Services\Glossary\UrlVerifier::isAlive($ru) : false;
            $refs[] = ['text' => $rt, 'url' => $ru, 'dead' => $dead];
            if (count($refs) >= 8) break;
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

    // URL yardımcıları App\Services\Glossary\UrlVerifier sınıfına taşındı
    // (single-responsibility refactor). isHomepageUrl + isAlive sıkı işleri
    // burada yapılır; bu sınıf sadece Claude API orkestrasyonu üzerine odaklı.


    private static function rubric(): string
    {
        return <<<TXT
Sen Türkçe mimarlık sözlüğü için uzman bir editörsün. Konuları MİMARLIK,
İÇ MİMARLIK, YAPI TEKNOLOJİSİ, KENT, TASARIM ve YAPI KÜLTÜRÜ kapsamında
ele alırsın. Yazar Osman Doğan — mimar ve inşaat mühendisi, Bursa
merkezli — kişisel sitesi için yazıyorsun.

YANIT KURALLARI (KESİN):
- SADECE geçerli JSON döndür. Markdown, kod bloğu, açıklama YOK.
- Tüm metin Türkçe; akademik ama okunabilir, gereksiz tekrar yok.
- JSON şeması TAM olarak şu:

{
  "term": "Resmi terim adı (Türkçe yazım kuralları)",
  "slug_hint": "url-uyumlu-kısa-slug",
  "category": "TEK kategori — aşağıdaki listeden seç",
  "aliases": ["TR karşılık", "yabancı dilde karşılık", "kısaltma", ...],
  "definition_html": "<h2>...</h2>... AŞAĞIDAKI YAPIYA UYGUN UZUN HTML",
  "references": [
    { "text": "Kaynak Başlığı — Yazar/Kurum, Yayın", "url": "https://veya-bos" }
  ]
}

KATEGORİ LİSTESİ (TEK seç, başka yazma):
Strüktür, Yapı Elemanı, Cephe, Malzeme, Yapı Teknolojisi, Mimari Akım,
Tasarım Yaklaşımı, Sürdürülebilirlik, BIM, Kentleşme, Planlama,
İç Mimarlık, Peyzaj, Restorasyon, Yapı Fiziği, Pasif Tasarım, Detay,
Tipoloji, Bezeme, Taşıyıcı Sistem, Diğer

═══════════════════════════════════════════════════════════════════
definition_html YAPISI — KESİN UYULACAK
═══════════════════════════════════════════════════════════════════

Başlık hiyerarşisi: SADECE <h2>, <h3>, <h4>. H1 YASAK (sayfa zaten H1
üretiyor). Allowed: <p>, <ul>, <ol>, <li>, <strong>, <em>, <code>,
<blockquote>, <a>. YASAK: <script>, <iframe>, <style>, <h1>.

Aşağıdaki bölümleri TAM SIRADA üret. [TERİM] yerine gerçek terim adı.
Bölümler kısa-öz tutulsun (her bölüm 2-3 paragraf); fazla uzatma.

<h2>[TERİM] Nedir?</h2>
  Terimin genel tanımı + mimari/tasarım bağlamındaki anlamı.

<h2>[TERİM] Kelime Anlamı ve Kökeni</h2>
  Etimoloji girişi.
  <h3>Kelimenin Birinci Kökü veya İlk Anlamı</h3>
    İlk kök/anlam.
  <h3>Kelimenin İkinci Kökü veya İkinci Anlamı</h3>
    İkinci kök/anlam (uygun değilse "Türkçe Karşılığı" başlığıyla genişlet).
  <h3>[TERİM] Türkçede Ne Anlama Gelir?</h3>
    Türkçedeki karşılığı + mimari anlam farkları.
    <h4>Yüzeysel Anlam ve Mimari Anlam Farkı</h4>
      Halk arasındaki anlam vs mimari teknik anlam.

<h2>Mimari Sözlük Tanımı</h2>
  Akademik, net sözlük tanımı (1 paragraf).

<h2>[TERİM] Kavramının Tarihsel Gelişimi</h2>
  Tarihsel ortaya çıkış + ihtiyaç + düşünsel ortam.
  <h3>Erken Dönem Kullanımı</h3>
    Tarihsel öncüller.
    <h4>Geleneksel Mimarlıkta Karşılığı</h4>
      Varsa Anadolu/Osmanlı/antik karşılıkları.
  <h3>Modern Mimarlıkta Gelişimi</h3>
    20. yy ve sonrası gelişimi.
    <h4>Öne Çıkan Mimarlar, Yapılar veya Akımlar</h4>
      Spesifik isimler/yapılar; UYDURMA — emin olduklarını yaz.

<h2>[TERİM] Mimarlıkta Nasıl Kullanılır?</h2>
  Tasarım sürecindeki kullanım.
  <h3>Tasarım Ölçeğinde Kullanımı</h3>
    Form, mekân, plan, cephe, kütle, işlev.
  <h3>Teknik Ölçekte Kullanımı</h3>
    Strüktür, malzeme, detay, fizik, sistem.
  <h3>Kullanıcı Deneyimi Açısından Önemi</h3>
    İnsan algısı, konfor, psikoloji, gündelik kullanım.

<h2>[TERİM] Türleri veya Yaklaşımları</h2>
  Türleri/yaklaşımları açıkla. EN AZ 2, EN FAZLA 4 alt başlık.
  <h3>Birinci Tür veya Yaklaşım</h3>
    Açıkla.
    <h4>Özellikleri</h4>
      <ul><li>Madde 1</li>...</ul>
  <h3>İkinci Tür veya Yaklaşım</h3>
    Açıkla.
    <h4>Özellikleri</h4>
      <ul><li>Madde 1</li>...</ul>
  (3. ve 4. tür opsiyonel — gerçekten varsa ekle.)

<h2>[TERİM] Tasarımında Dikkat Edilmesi Gerekenler</h2>
  Doğru kullanım için kritik noktalar.
  <h3>Bağlam ve Yer Seçimi</h3>
    Çevre, iklim, kent, kullanıcı, işlev.
  <h3>Malzeme ve Detay</h3>
    Malzeme/detay/bakım/dayanıklılık.
  <h3>İklim ve Enerji Performansı</h3>
    Güneş, rüzgâr, ısı, enerji, sürdürülebilirlik.
  <h3>Estetik ve İşlev Dengesi</h3>
    Görsel tercih değil tasarım kararı olduğunu vurgula.

<h2>[TERİM] Ne Değildir?</h2>
  Yanlış kullanımlar.
  <h3>Yaygın Yanlış Anlama</h3>
    Eksik/hatalı/genelleştirilmiş kullanım.
    <h4>Neden Yanlıştır?</h4>
      Mimari açıdan neden yetersiz.
  <h3>Dekoratif Kullanım ile Gerçek Kullanım Farkı</h3>
    Görsel vs gerçek mimari/teknik kullanım. (Uygun değilse atla.)

<h2>[TERİM] ile Karıştırılan Kavramlar</h2>
  EN AZ 2, EN FAZLA 3 benzer kavram.
  <h3>Birinci Benzer Kavram</h3>
    Tanımla.
    <h4>[TERİM] ile Farkı</h4>
      Farkı açıkla.
  <h3>İkinci Benzer Kavram</h3>
    Tanımla.
    <h4>[TERİM] ile Farkı</h4>
      Farkı açıkla.
  (3. opsiyonel.)

<h2>Mimarlıkta [TERİM] Örnekleri</h2>
  EN AZ 2, EN FAZLA 3 gerçek örnek. UYDURMA — emin olduğun yapı/mimar.
  <h3>Birinci Örnek</h3>
    Yapı/mimar/dönem açıkla.
    <h4>Mimari Önemi</h4>
      Neden önemli.
  <h3>İkinci Örnek</h3>
    ...
    <h4>Mimari Önemi</h4>
      ...

<h2>Türkiye ve Yerel Mimarlık Bağlamında [TERİM]</h2>
  Türkiye'deki pratik, iklim, yönetmelik, malzeme kültürü.
  <h3>Türkiye İklimi Açısından Değerlendirme</h3>
    Farklı iklim bölgeleri.
  <h3>Bursa veya Marmara Bölgesi Açısından Değerlendirme</h3>
    Bursa/Marmara özelinde KISA ama anlamlı bir değerlendirme (uygunsa).

<h2>[TERİM] Kavramına Eleştirel Bakış</h2>
  Güçlü/zayıf yönler, doğru kullanım önerileri.
  <h3>Güçlü Yönleri</h3>
    Katkılar.
  <h3>Riskli veya Zayıf Yönleri</h3>
    Yanlış uygulama riskleri.
  <h3>Doğru Kullanım İçin Öneriler</h3>
    Bilinçli/doğru/bağlama uygun kullanım.

<h2>Mimari Sözlük İçin Kısa Tanım</h2>
  2-3 cümlelik özet sözlük tanımı (kullanıcı bunu rich snippet için kullanır).

<h2>Daha Akademik Sözlük Tanımı</h2>
  Daha teknik, kapsamlı paragraf (1-2 paragraf).

═══════════════════════════════════════════════════════════════════
DİĞER ALANLAR
═══════════════════════════════════════════════════════════════════

"aliases" → 4-12 öğe; TR karşılık + yabancı dil (EN, FR, DE, IT, LA) +
  kısaltma + eski adlandırma. Uydurma yok.

"references" → 3-6 kaynak. Her biri:
  - text: "Kaynak Başlığı — Yazar/Kurum, Yayın (Yıl)"
  - url:  "https://..." (varsa, yoksa boş string)
  Yalnızca BİLDİĞİN gerçek kaynaklar. Wikipedia varsa son sıraya,
  yardımcı kaynak olarak. Tercih: tdk.gov.tr, tmmob.org.tr,
  jstor.org, dergipark.org.tr, archnet.org, mimarist.org,
  yapi.com.tr, arkitera.com, kulturportali.gov.tr, üniversite
  yayınları, akademik dergi DOI'leri.

GENEL KURALLAR:
- Yanlış bilgi vermektense az bilgi ver. Emin değilsen bölümü kısa tut.
- Mimari Bursa-tonlu olabilir (yazar oradan) ama tüm Türkiye için yaz.
- Aşırı şişirme; her bölüm 2-3 paragraf yeterli. Sayı/tarih emin değilsen
  belirsiz bırak (örn. "20. yy başları" gibi).
- "term" alanını her zaman doldur — formdaki başlık olur.
TXT;
    }

    /**
     * Enhance-modu sistem rubrik'i — mevcut bir girdiyi GÜÇLENDİRMEK için.
     * Mevcut girdi ansiklopedik yapıda değilse, ona DÖNÜŞTÜRÜR; ama mevcut
     * doğru bilgileri korur ve kullanıcı sesini ezmez.
     */
    private static function rubricEnhance(): string
    {
        return <<<TXT
Sen Türkçe mimarlık sözlüğü editörüsün. Sana MEVCUT bir sözlük girdisinin
geçerli içeriği verilecek ve onu GELİŞTİRMEKLE görevlisin. Konular:
MİMARLIK, İÇ MİMARLIK, YAPI TEKNOLOJİSİ, KENT, TASARIM, YAPI KÜLTÜRÜ.
Yazar Osman Doğan — mimar ve inşaat mühendisi, Bursa.

═══════════════════════════════════════════════════════════════════
GENEL STRATEJİ
═══════════════════════════════════════════════════════════════════

İki senaryo olabilir:

A) Mevcut metin KISA / DÜZ paragraf ise:
   → Bu metni ANSIKLOPEDIK yapıya (aşağıdaki şablon) GENİŞLET.
   → Mevcut doğru bilgileri yapı içinde uygun yerlere yerleştir.
   → Boş kalan bölümleri emin olduğun şekilde doldur.

B) Mevcut metin zaten YAPISAL (H2/H3/H4 başlıklı) ise:
   → Yapıyı KORU.
   → Eksik bölümleri tamamla.
   → Yazım/dilbilgisi hatalarını düzelt.
   → Mevcut paragrafları SIFIRDAN yeniden yazma.

HER İKİ SENARYO İÇİN ORTAK KURALLAR:
- Mevcut referansları SİL ME — sadece sona EK yapabilirsin.
- Mevcut alias'ları SİL ME — ek yapabilirsin (TR + yabancı dil).
- "term" alanını dokunma (yazım hatası varsa düzelt sadece).
- Üslubu drastik değiştirme.
- Belirsizken UYDURMA — bölümü kısa tut veya atla.

═══════════════════════════════════════════════════════════════════
ANSIKLOPEDIK YAPI (uyman gereken hedef)
═══════════════════════════════════════════════════════════════════

Başlık hiyerarşisi: SADECE <h2>, <h3>, <h4>. H1 YASAK.

definition_html bu sırada bölümler içerir:

  <h2>[TERİM] Nedir?</h2>
  <h2>[TERİM] Kelime Anlamı ve Kökeni</h2>
    <h3>Kelimenin Birinci Kökü veya İlk Anlamı</h3>
    <h3>Kelimenin İkinci Kökü veya İkinci Anlamı</h3>
    <h3>[TERİM] Türkçede Ne Anlama Gelir?</h3>
      <h4>Yüzeysel Anlam ve Mimari Anlam Farkı</h4>
  <h2>Mimari Sözlük Tanımı</h2>
  <h2>[TERİM] Kavramının Tarihsel Gelişimi</h2>
    <h3>Erken Dönem Kullanımı</h3>
      <h4>Geleneksel Mimarlıkta Karşılığı</h4>
    <h3>Modern Mimarlıkta Gelişimi</h3>
      <h4>Öne Çıkan Mimarlar, Yapılar veya Akımlar</h4>
  <h2>[TERİM] Mimarlıkta Nasıl Kullanılır?</h2>
    <h3>Tasarım Ölçeğinde Kullanımı</h3>
    <h3>Teknik Ölçekte Kullanımı</h3>
    <h3>Kullanıcı Deneyimi Açısından Önemi</h3>
  <h2>[TERİM] Türleri veya Yaklaşımları</h2>
    <h3>Birinci Tür / İkinci Tür / Üçüncü Tür</h3>
      <h4>Özellikleri</h4> (ul/li)
  <h2>[TERİM] Tasarımında Dikkat Edilmesi Gerekenler</h2>
    <h3>Bağlam ve Yer Seçimi</h3>
    <h3>Malzeme ve Detay</h3>
    <h3>İklim ve Enerji Performansı</h3>
    <h3>Estetik ve İşlev Dengesi</h3>
  <h2>[TERİM] Ne Değildir?</h2>
    <h3>Yaygın Yanlış Anlama</h3>
      <h4>Neden Yanlıştır?</h4>
    <h3>Dekoratif Kullanım ile Gerçek Kullanım Farkı</h3>
  <h2>[TERİM] ile Karıştırılan Kavramlar</h2>
    <h3>Birinci Benzer / İkinci Benzer Kavram</h3>
      <h4>[TERİM] ile Farkı</h4>
  <h2>Mimarlıkta [TERİM] Örnekleri</h2>
    <h3>Birinci Örnek / İkinci Örnek</h3>
      <h4>Mimari Önemi</h4>
  <h2>Türkiye ve Yerel Mimarlık Bağlamında [TERİM]</h2>
    <h3>Türkiye İklimi Açısından Değerlendirme</h3>
    <h3>Bursa veya Marmara Bölgesi Açısından Değerlendirme</h3>
  <h2>[TERİM] Kavramına Eleştirel Bakış</h2>
    <h3>Güçlü Yönleri / Riskli Yönleri / Doğru Kullanım Önerileri</h3>
  <h2>Mimari Sözlük İçin Kısa Tanım</h2> (2-3 cümle)
  <h2>Daha Akademik Sözlük Tanımı</h2> (1-2 paragraf)

═══════════════════════════════════════════════════════════════════
JSON ŞEMASI
═══════════════════════════════════════════════════════════════════

SADECE geçerli JSON. Markdown, açıklama, kod bloğu YOK.

{
  "term": "Mevcut terim (değiştirme)",
  "slug_hint": "url-uyumlu-slug",
  "category": "Listeden TEK kategori (Strüktür, Yapı Elemanı, Cephe, Malzeme, Yapı Teknolojisi, Mimari Akım, Tasarım Yaklaşımı, Sürdürülebilirlik, BIM, Kentleşme, Planlama, İç Mimarlık, Peyzaj, Restorasyon, Yapı Fiziği, Pasif Tasarım, Detay, Tipoloji, Bezeme, Taşıyıcı Sistem, Diğer)",
  "aliases": ["mevcut + ek (silme yapma)"],
  "definition_html": "Ansiklopedik HTML — yukarıdaki yapı",
  "references": [
    { "text": "Mevcut + yeni", "url": "https://..." }
  ]
}

Mevcut referansları DİZİ BAŞINA KOY, yenileri sona ekle.
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
     * Enhance modunda kullanıcı payload'u — mevcut içerik AI'a serileştirilir.
     * @param array<string,mixed> $current
     */
    private static function enhancePayload(string $term, string $context, string $depth, array $current): string
    {
        $depthLabel = match ($depth) {
            'derin' => 'gerekirse uzat (4-5 paragrafa kadar)',
            'kisa'  => 'mevcut uzunluğu koru (gereksiz şişirme)',
            default => 'mevcut uzunluğa yakın, %20-30 büyüyebilir',
        };

        $curDef  = trim((string) ($current['definition'] ?? ''));
        $curCat  = trim((string) ($current['category']   ?? ''));
        $curAli  = trim((string) ($current['aliases']    ?? ''));
        $curRefs = $current['references'] ?? '';
        // references string olarak gelirse (JSON), pretty hale getir
        $refsBlock = '';
        if (is_string($curRefs) && $curRefs !== '') {
            $dec = json_decode($curRefs, true);
            if (is_array($dec)) {
                foreach ($dec as $i => $r) {
                    if (!is_array($r)) continue;
                    $refsBlock .= '  ' . ($i + 1) . ') '
                        . (string) ($r['text'] ?? '')
                        . ((!empty($r['url'])) ? '  (' . $r['url'] . ')' : '')
                        . "\n";
                }
            } else {
                $refsBlock = $curRefs;
            }
        } elseif (is_array($curRefs)) {
            foreach ($curRefs as $i => $r) {
                if (!is_array($r)) continue;
                $refsBlock .= '  ' . ($i + 1) . ') '
                    . (string) ($r['text'] ?? '')
                    . ((!empty($r['url'])) ? '  (' . $r['url'] . ')' : '')
                    . "\n";
            }
        }
        if ($refsBlock === '') $refsBlock = '  (yok)';

        $lines = [
            'GÖREV: GELİŞTİRME MODU — aşağıdaki mevcut sözlük girdisini güçlendir.',
            '       Sıfırdan yeniden yazma. Yapıyı ve yazar sesini koru.',
            '',
            'TERİM: ' . $term,
            'UZUNLUK STRATEJİSİ: ' . $depthLabel,
        ];
        if ($context !== '') {
            $lines[] = 'KULLANICI NOTU: ' . $context;
        }
        $lines[] = '';
        $lines[] = 'MEVCUT İÇERİK ─────────────────────────────';
        $lines[] = 'Kategori: ' . ($curCat !== '' ? $curCat : '(yok)');
        $lines[] = 'Alias\'lar: ' . ($curAli !== '' ? $curAli : '(yok)');
        $lines[] = 'Mevcut Kaynaklar:';
        $lines[] = $refsBlock;
        $lines[] = '';
        $lines[] = 'Mevcut Tanım (HTML):';
        $lines[] = $curDef !== '' ? $curDef : '(yok)';
        $lines[] = '────────────────────────────────────────';
        $lines[] = '';
        $lines[] = 'YAPACAĞIN: Yukarıyı GELİŞTİR. Mevcut metnin %70-80\'ini koru;';
        $lines[] = 'eksikleri tamamla, hataları düzelt. Mevcut referansları SİL ME;';
        $lines[] = 'sadece sonuna ekle (varsa, emin olduğun).';

        return implode("\n", $lines);
    }

    /**
     * Verilen current girdisinde gerçekten mevcut içerik var mı?
     * Sadece boş alanlardan oluşan dizi → yeni-üretim modu.
     * @param array<string,mixed> $current
     */
    private static function hasCurrentContent(array $current): bool
    {
        if ($current === []) return false;
        $def = trim((string) ($current['definition'] ?? ''));
        $cat = trim((string) ($current['category']   ?? ''));
        $ali = trim((string) ($current['aliases']    ?? ''));
        $ref = $current['references'] ?? '';
        if (is_array($ref)) $ref = json_encode($ref);
        $ref = trim((string) $ref);
        return $def !== '' || $cat !== '' || $ali !== '' || ($ref !== '' && $ref !== '[]');
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
            // Ansiklopedik yapı 6-8K token üretir → 30-90 sn AI cevap süresi.
            CURLOPT_TIMEOUT        => 180,
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
