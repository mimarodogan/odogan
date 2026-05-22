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
        // Özellikler — her biri on/off toggle. View ve controller'lar
        // `feature('name')` helper'ı ile bu flag'i kontrol eder. Off ise
        // ilgili UI render edilmez, endpoint 404 döner. Default = false:
        // bir özellik açıkça aktive edilmediği sürece görünmez (güvenli).
        'features' => [
            'footnotes_enabled'       => ['type' => 'bool', 'label' => 'Dipnot Sistemi'],
            'outline_panel_enabled'   => ['type' => 'bool', 'label' => 'Editörde Outline Panel'],
            'slash_commands_enabled'  => ['type' => 'bool', 'label' => 'Slash Komutları (/alinti, /tablo)'],
            'co_author_enabled'       => ['type' => 'bool', 'label' => 'Co-author / Çoklu Yazar'],
            'editors_pick_enabled'    => ['type' => 'bool', 'label' => 'Editörün Seçimi (Featured)'],
            'author_bio_card_enabled' => ['type' => 'bool', 'label' => 'Yazı Altı Yazar Bio Kartı'],
            'prev_next_nav_enabled'   => ['type' => 'bool', 'label' => 'Önceki / Sonraki Yazı Linki'],
            'save_post_enabled'       => ['type' => 'bool', 'label' => 'Yazıyı Kaydet (LocalStorage)'],
            'series_enabled'          => ['type' => 'bool', 'label' => 'Series / Dizi Yazılar'],
            'image_gallery_enabled'   => ['type' => 'bool', 'label' => 'ImageGallery Schema'],
            'internal_link_suggest'   => ['type' => 'bool', 'label' => 'Internal Link Önerisi (editörde)'],
            'blurhash_enabled'        => ['type' => 'bool', 'label' => 'BlurHash Placeholder'],
            'lightbox_zoom_enabled'   => ['type' => 'bool', 'label' => 'Lightbox Zoom / Pan'],
            'dashboard_widgets'       => ['type' => 'bool', 'label' => 'Genişletilmiş Dashboard Widget\'ları'],
            'bulk_actions_enabled'    => ['type' => 'bool', 'label' => 'Toplu İşlemler (Bulk Actions)'],
            'quick_edit_enabled'      => ['type' => 'bool', 'label' => 'Quick Edit Modal'],
            'comment_admin_mail'      => ['type' => 'bool', 'label' => 'Yorum Geldiğinde Admin\'e Mail'],
            'seo_score_enabled'       => ['type' => 'bool', 'label' => 'SEO Skoru (yazarken)'],
            'readability_enabled'     => ['type' => 'bool', 'label' => 'Okunabilirlik Puanı (Türkçe)'],
            'ai_analysis_enabled'     => ['type' => 'bool', 'label' => 'AI Derin Analiz (yazarken, talep-üzerine — Claude API gerekir)'],
            'author_application'      => ['type' => 'bool', 'label' => 'Yazar Başvuru Sayfası (/yazar-ol)'],
            // Tier 7
            'clap_enabled'             => ['type' => 'bool', 'label' => 'Clap (Beğeni Sistemi)'],
            'bookmark_db_enabled'      => ['type' => 'bool', 'label' => 'Sunucu Tarafı Bookmark'],
            'author_follow_enabled'    => ['type' => 'bool', 'label' => 'Yazar Takip / Abone Ol'],
            'audit_log_enabled'        => ['type' => 'bool', 'label' => 'Audit Log (Admin İşlem Kaydı)'],
            'login_lockout_enabled'    => ['type' => 'bool', 'label' => 'Login Lockout'],
            'account_delete_enabled'   => ['type' => 'bool', 'label' => 'Hesap Silme (Soft Delete)'],
            'data_export_enabled'      => ['type' => 'bool', 'label' => 'Veri İndirme (KVKK)'],
            'redirect_manager_enabled' => ['type' => 'bool', 'label' => '301 Redirect Manager'],
            'not_found_logger_enabled' => ['type' => 'bool', 'label' => '404 Logger'],
            'draft_preview_enabled'    => ['type' => 'bool', 'label' => 'Taslak Önizleme Linki'],
            'post_templates_enabled'   => ['type' => 'bool', 'label' => 'Yazı Şablonları'],
            'glossary_enabled'         => ['type' => 'bool', 'label' => 'Mimari Sözlük'],
            'before_after_enabled'     => ['type' => 'bool', 'label' => 'Öncesi/Sonrası Slider'],
            'pwa_enabled'              => ['type' => 'bool', 'label' => 'PWA + Service Worker'],
            'sponsored_post_enabled'   => ['type' => 'bool', 'label' => 'Sponsorlu İçerik Tipi'],
            'donation_enabled'         => ['type' => 'bool', 'label' => 'Bağış Butonu'],
            // Tier 8
            'reactions_enabled'         => ['type' => 'bool', 'label' => 'Emoji Reaksiyon'],
            'quote_share_enabled'       => ['type' => 'bool', 'label' => 'Alıntı Paylaş'],
            'comment_threading_enabled' => ['type' => 'bool', 'label' => 'Yorum Threading'],
            'analytics_events_enabled'  => ['type' => 'bool', 'label' => 'Analytics Events'],
            'active_sessions_enabled'   => ['type' => 'bool', 'label' => 'Aktif Oturumlar'],
            'project_portfolio_enabled' => ['type' => 'bool', 'label' => 'Proje Portfolyo'],
            'affiliate_enabled'         => ['type' => 'bool', 'label' => 'Affiliate Linkler'],
            'prefetch_on_hover_enabled' => ['type' => 'bool', 'label' => 'Hover Prefetch'],
            // Tier 9
            'building_map_enabled'      => ['type' => 'bool', 'label' => 'Yapı Haritası'],
            'approval_workflow_enabled' => ['type' => 'bool', 'label' => 'Çok Aşamalı Onay'],
            'ab_test_enabled'           => ['type' => 'bool', 'label' => 'A/B Başlık Testi'],
            'paywall_enabled'           => ['type' => 'bool', 'label' => 'Üye-only Paywall'],
            'sponsor_slot_enabled'      => ['type' => 'bool', 'label' => 'Sponsor Slot'],
            // Critical CSS — varsayılan AÇIK (admin kapatabilir). İçerik boş olsa bile
            // head-meta.php Setting değerini okur; content boşsa normal CSS yüklenir.
            'critical_css_enabled'      => ['type' => 'bool', 'label' => 'Critical CSS', 'default' => true],
            'critical_css_content'      => ['type' => 'string', 'label' => 'Critical CSS İçerik (above-the-fold için inline edilir)'],
        ],
        // Kuruluş bilgileri — Schema.org Organization node'unda kullanılır.
        // Knowledge Panel adaylığı + sosyal kimlik verifikasyonu için kritik.
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
        ],
        // AI Derin Analiz — Faz 5. Anahtar boşsa .env ANTHROPIC_API_KEY kullanılır.
        // ai_analysis_enabled (features) açık + anahtar varsa editörde buton görünür.
        'ai' => [
            'ai_model'          => ['type' => 'string', 'label' => 'AI Model (boş = claude-haiku-4-5)'],
            'anthropic_api_key' => ['type' => 'string', 'label' => 'Claude API Anahtarı (boşsa .env ANTHROPIC_API_KEY kullanılır)'],
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
                $payload[$key] = self::coerce($submitted[$key] ?? null, $type);
            }
            Setting::saveGroup($group, $payload, $types);
        }
        Setting::flushCache();
        flash('success', 'Ayarlar kaydedildi.');
        return Response::redirect(url('/admin/ayarlar'));
    }

    private static function coerce(mixed $raw, string $type): mixed
    {
        if ($type === 'bool') {
            return $raw === '1' || $raw === 'on' || $raw === 'true' || $raw === true;
        }
        if ($type === 'int') {
            return (int) ($raw ?? 0);
        }
        return trim((string) ($raw ?? ''));
    }
}
