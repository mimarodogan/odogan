<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Services\AuthService;

final class SettingsController
{
    /** Fields shown on the form, grouped + typed. */
    private const SCHEMA = [
        'general' => [
            'site_name'        => ['type' => 'string', 'label' => 'Site Adı'],
            'site_tagline'     => ['type' => 'string', 'label' => 'Kısa Slogan'],
            'site_description' => ['type' => 'string', 'label' => 'Varsayılan Meta Açıklaması'],
            'site_keywords'    => ['type' => 'string', 'label' => 'Anahtar Kelimeler (virgülle)'],
            'site_locale'      => ['type' => 'string', 'label' => 'Yerel (örn. tr_TR)'],
            'site_favicon'     => ['type' => 'string', 'label' => 'Favicon URL (örn. /favicon.ico veya uploads/.../icon.png)'],
            'footer_text'      => ['type' => 'string', 'label' => 'Footer Metni'],
            'donation_url'     => ['type' => 'string', 'label' => 'Bağış / Destek URL (Patreon, BuyMeACoffee, vb.)'],
        ],
        'seo' => [
            'canonical_base'   => ['type' => 'string', 'label' => 'Canonical Temel URL (https://...)'],
            'default_og_image' => ['type' => 'string', 'label' => 'Varsayılan Paylaşım Görseli (URL)'],
            'meta_title_sep'   => ['type' => 'string', 'label' => 'Başlık Ayracı (örn.  —  )'],
            'twitter_handle'   => ['type' => 'string', 'label' => 'Twitter Kullanıcı Adı (@otorite)'],
            'robots_default'   => ['type' => 'string', 'label' => 'Varsayılan Robots Direktifi'],
            'indexnow_enabled' => ['type' => 'bool',   'label' => 'IndexNow Otomatik Bildirim (Bing/Yandex)', 'default' => true],
            'indexnow_key'     => ['type' => 'string', 'label' => 'IndexNow API Anahtarı (32 hex char — boşsa otomatik üretilir)'],
        ],
        'analytics' => [
            'google_analytics_id' => ['type' => 'string', 'label' => 'Google Analytics ID (G-XXXXXX)'],
            'google_site_verify'  => ['type' => 'string', 'label' => 'Google Search Console Doğrulama Token'],
            'bing_site_verify'    => ['type' => 'string', 'label' => 'Bing Webmaster Doğrulama Token'],
        ],
        // KVKK / GDPR — çerez onay banner'ı ve Consent Mode V2 default state.
        // cookie_banner_enabled=true ise public sayfalarda banner render edilir.
        // gdpr_consent_required=true ise GA4 yüklenmeden önce gtag('consent',
        // 'default', {…'denied'}) basılır → kullanıcı kabul edene kadar ad &
        // analytics çerezleri yazılmaz (cookieless ping yine atılır).
        'privacy' => [
            'cookie_banner_enabled' => ['type' => 'bool',   'label' => 'Çerez Onay Banner\'ı (KVKK)', 'default' => true],
            'cookie_banner_text'    => ['type' => 'string', 'label' => 'Çerez Banner Metni (kısa HTML: <strong>, <a>)'],
            'gdpr_consent_required' => ['type' => 'bool',   'label' => 'AB Ziyaretçi için Consent Mode V2 (önerilir)', 'default' => true],
        ],
        'social' => [
            'social_twitter'   => ['type' => 'string', 'label' => 'Twitter / X URL'],
            'social_linkedin'  => ['type' => 'string', 'label' => 'LinkedIn URL'],
            'social_instagram' => ['type' => 'string', 'label' => 'Instagram URL'],
            'social_facebook'  => ['type' => 'string', 'label' => 'Facebook URL'],
            'social_youtube'   => ['type' => 'string', 'label' => 'YouTube URL'],
        ],
        'content' => [
            'posts_per_page'           => ['type' => 'int',  'label' => 'Anasayfada Yazı Sayısı', 'default' => 12],
            'comments_enabled'         => ['type' => 'bool', 'label' => 'Yorumlar Açık'],
            'comments_require_approval'=> ['type' => 'bool', 'label' => 'Yorumlar Önce Onaya Düşer'],
        ],
        // T2 (2026-05): features grubu burada toplanmıyor — tek otorite
        // FeaturesController. /admin/ozellikler tüm toggle'ları yönetir;
        // /admin/ayarlar bu grup için referans tutmaz, çakışma yaratmaz.
        // critical_css_content için /admin/critical-css ayrı sayfa var.

        // Kuruluş bilgileri — Schema.org Organization node'unda kullanılır.
        // Knowledge Panel adaylığı + sosyal kimlik verifikasyonu için kritik.
        // F2.5 (KVKK): Veri Sorumlusu / VERBİS alanları — Aydınlatma Metni'nde
        // ve footer'da kullanılır. KVKK m.10 kapsamında doldurulması zorunlu.
        'organization' => [
            'org_legal_name'    => ['type' => 'string', 'label' => 'Resmî / Tüzel Ad (Site Adı\'ndan farklıysa)'],
            'org_founding_date' => ['type' => 'string', 'label' => 'Kuruluş Tarihi (YYYY-MM-DD)'],
            'org_founder'       => ['type' => 'string', 'label' => 'Kurucu / Sahip (kişi adı)'],
            'principal_author_slug' => ['type' => 'string', 'label' => 'Ana Yazar Profili (slug) — kişi şeması (Person) ana sayfaya ve Organization.founder\'a @id ile bağlanır. Örn: osman-dogan'],
            'org_street_address'=> ['type' => 'string', 'label' => 'Sokak / Cadde Adresi'],
            'org_city'          => ['type' => 'string', 'label' => 'Şehir (örn. İstanbul)'],
            'org_postal_code'   => ['type' => 'string', 'label' => 'Posta Kodu'],
            'org_country'       => ['type' => 'string', 'label' => 'Ülke (ISO kodu, örn. TR)'],
            'org_email'         => ['type' => 'string', 'label' => 'Editöryal İletişim E-posta'],
            'org_phone'         => ['type' => 'string', 'label' => 'İletişim Telefon (+90...)'],
            'org_license_url'   => ['type' => 'string', 'label' => 'Telif Lisansı URL (örn. CC BY-NC 4.0)'],
            'org_copyright_holder' => ['type' => 'string', 'label' => 'Telif Sahibi (varsayılan: Site Adı)'],
            // KVKK uyum alanları — Aydınlatma Metni şablonu bu değerleri otomatik kullanır
            'kvkk_data_controller'   => ['type' => 'string', 'label' => 'KVKK · Veri Sorumlusu (gerçek kişi veya tüzel kişi adı — örn. "Osman Doğan")'],
            'kvkk_verbis_no'         => ['type' => 'string', 'label' => 'KVKK · VERBİS Sicil No (kayıtlıysa; değilse boş bırakın → "kayıt zorunluluğu kapsamında değildir" ibaresi otomatik çıkar)'],
            'kvkk_data_officer_email'=> ['type' => 'string', 'label' => 'KVKK · İrtibat Kişisi E-posta (başvuru/talepler için — boşsa org_email kullanılır)'],
            'kvkk_kep_address'       => ['type' => 'string', 'label' => 'KVKK · KEP Adresi (Kayıtlı Elektronik Posta — varsa)'],
        ],
        // AI Derin Analiz — Faz 5. Anahtar boşsa .env ANTHROPIC_API_KEY kullanılır.
        // ai_analysis_enabled (features) açık + anahtar varsa editörde buton görünür.
        'ai' => [
            'ai_model'           => ['type' => 'string', 'label' => 'AI Model — Yazı analizi (boş = claude-haiku-4-5)'],
            'glossary_ai_model'  => ['type' => 'string', 'label' => 'AI Model — Sözlük taslakları (boş = claude-sonnet-4-5; ansiklopedik yapı için Sonnet önerilir, Haiku output tavanına takılır)'],
            'anthropic_api_key'  => ['type' => 'string', 'label' => 'Claude API Anahtarı (boşsa .env ANTHROPIC_API_KEY kullanılır)'],
        ],
        // G2 (2026-05): Hakkımda + İletişim sayfa içerikleri admin-yönetilebilir.
        // View'lar bu key'leri Setting::get(..., 'pages') ile okur; boş bırakılırsa
        // her view kendi varsayılan kopyasını gösterir.
        'pages' => [
            'about_manifesto_html'    => ['type' => 'string', 'label' => 'Hakkımda · Manifesto Paragrafı (kısa HTML — <strong>, <em> serbest)'],
            'contact_page_title'      => ['type' => 'string', 'label' => 'İletişim · Sayfa Başlığı (örn. "Bana yazın")'],
            'contact_page_lead'       => ['type' => 'string', 'label' => 'İletişim · Giriş Paragrafı (sayfanın üstünde 1-2 cümle)'],
            'contact_response_time'   => ['type' => 'string', 'label' => 'İletişim · Cevap Süresi Açıklaması'],
            'contact_collaboration'   => ['type' => 'string', 'label' => 'İletişim · İşbirliği / Kapsam Açıklaması'],
        ],
    ];

    public function index(Request $req): Response
    {
        $values = [];
        foreach (self::SCHEMA as $group => $fields) {
            $stored = Setting::group($group);
            foreach ($fields as $key => $def) {
                $default = $def['default'] ?? '';
                $values[$group][$key] = $stored[$key] ?? $default;
            }
        }
        return view('admin.settings', [
            'title'  => 'Site Ayarları',
            'user'   => AuthService::user(),
            'schema' => self::SCHEMA,
            'values' => $values,
        ]);
    }

    public function update(Request $req): Response
    {
        foreach (self::SCHEMA as $group => $fields) {
            $submitted = (array) $req->input($group, []);
            $payload = [];
            $types = [];
            foreach ($fields as $key => $def) {
                $type = (string) $def['type'];
                $types[$key] = $type;
                $payload[$key] = self::coerce($submitted[$key] ?? null, $type, $key);
            }
            Setting::saveGroup($group, $payload, $types);
        }
        Setting::flushCache();
        flash('success', 'Ayarlar kaydedildi.');
        return Response::redirect(url('/admin/ayarlar'));
    }

    /**
     * Raw input'u tipine göre dönüştür. HTML kabul eden anahtarları
     * (`*_html` suffix'i olan) Sanitizer::clean() ile geçirir — admin oturumu
     * çalınsa bile stored XSS payload'unu temiz HTML allowlist'ine düşürür
     * (defense-in-depth: zaten esc() ile view'da escape edilmemiş alanlar
     * için bu temel güvenlik kontrolüdür).
     */
    private static function coerce(mixed $raw, string $type, string $key = ''): mixed
    {
        if ($type === 'bool') {
            return $raw === '1' || $raw === 'on' || $raw === 'true' || $raw === true;
        }
        if ($type === 'int') {
            return (int) ($raw ?? 0);
        }
        $value = trim((string) ($raw ?? ''));
        // F1.3 (KRİTİK): HTML kabul eden setting key'leri Sanitizer'dan geçir.
        // Konvansiyon: anahtar `_html` ile bitiyorsa raw HTML içerik içerir.
        if ($value !== '' && str_ends_with($key, '_html')) {
            $value = \App\Services\Sanitizer::clean($value);
        }
        return $value;
    }
}
