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

        // max_tokens: yapı 12 H2 bölümlü ansiklopedik şablon. Hedef:
        //   kısa  ≈ 4000 token (kısa-öz bölümler)
        //   orta  ≈ 6500 token (varsayılan)
        //   derin ≈ 8000 token (haiku-4-5 tavanına yakın)
        // Geliştirme modunda mevcut metin prompt'a girer; yine de output
        // limitini koruruz (zaten haiku üst sınırı 8192).
        $maxTokens = match ($depth) {
            'derin' => 8000,
            'kisa'  => 4000,
            default => 6500,
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
            $dead = $ru !== '' ? !self::urlAlive($ru) : false;
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
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
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
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
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
