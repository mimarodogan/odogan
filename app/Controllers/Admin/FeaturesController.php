<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Services\Logger;

/**
 * Admin "Özellikler" özet panel — tüm Tier 5 feature flag'lerini tek ekranda
 * toggle. /admin/ayarlar'ın shortcut'u — sadece features grubu render edilir.
 */
final class FeaturesController
{
    /**
     * Settings → features grubuyla aynı schema'yı kullanır (single source of truth).
     */
    public static function flags(): array
    {
        return [
            'footnotes_enabled'       => ['label' => 'Dipnot Sistemi', 'desc' => 'Yazıda [^1] markerları + yazı sonu kaynak listesi'],
            'outline_panel_enabled'   => ['label' => 'Outline Panel',  'desc' => 'Editör sağ kolonunda canlı H2/H3 başlık listesi'],
            'slash_commands_enabled'  => ['label' => 'Slash Komutları','desc' => '/alinti, /tablo, /gorsel kısayolları'],
            'co_author_enabled'       => ['label' => 'Co-author / Çoklu Yazar', 'desc' => 'Bir yazıya ortak yazar atama'],
            'editors_pick_enabled'    => ['label' => 'Editörün Seçimi','desc' => 'Featured işaretli yazılar anasayfada öne çıkar'],
            'author_bio_card_enabled' => ['label' => 'Yazar Bio Kartı','desc' => 'Yazı altında yazarın avatar + bio + diğer yazıları'],
            'prev_next_nav_enabled'   => ['label' => 'Önceki / Sonraki','desc' => 'Yazı altında kategori içi navigasyon'],
            'save_post_enabled'       => ['label' => 'Yazıyı Kaydet',  'desc' => 'LocalStorage tabanlı kayıt + /kaydedilenler'],
            'series_enabled'          => ['label' => 'Series / Dizi',  'desc' => 'Sıralı yazı dizileri (Sinan Külliyatı · Bölüm 3/7)'],
            'image_gallery_enabled'   => ['label' => 'ImageGallery Schema','desc' => 'JSON-LD ile galeri schema'],
            'internal_link_suggest'   => ['label' => 'Internal Link Önerisi','desc' => 'Yazarken benzer yazıları sidebar\'da öner'],
            'blurhash_enabled'        => ['label' => 'BlurHash Placeholder','desc' => 'Görsel yüklenmeden bulanık önizleme'],
            'lightbox_zoom_enabled'   => ['label' => 'Lightbox Zoom',  'desc' => 'Mouse wheel + drag pan + mobil pinch'],
            'dashboard_widgets'       => ['label' => 'Dashboard Widget','desc' => 'Son 7/30 gün metrikleri'],
            'bulk_actions_enabled'    => ['label' => 'Toplu İşlemler', 'desc' => 'Posts listesinde checkbox + bulk action'],
            'quick_edit_enabled'      => ['label' => 'Quick Edit',     'desc' => 'Modal\'da hızlı başlık/slug/durum güncelleme'],
            'comment_admin_mail'      => ['label' => 'Yorum Bildirimi','desc' => 'Yeni yorum geldiğinde admin\'e e-posta'],
            'seo_score_enabled'       => ['label' => 'SEO Skoru',      'desc' => 'Yazarken canlı SEO skoru + ipuçları'],
            'readability_enabled'     => ['label' => 'Okunabilirlik',  'desc' => 'Türkçe Ateşman puanı (canlı)'],
            'author_application'      => ['label' => 'Yazar Başvurusu','desc' => '/yazar-ol multi-step başvuru formu'],

            // Tier 7 (yeni)
            'clap_enabled'            => ['label' => 'Clap (Beğeni)',   'desc' => 'Medium-vari beğeni — 1-50 arası clap'],
            'bookmark_db_enabled'     => ['label' => 'Sunucu Bookmark', 'desc' => 'Üye girişliyse bookmark DB\'de tutulur (cross-device)'],
            'author_follow_enabled'   => ['label' => 'Yazar Takip',     'desc' => 'Üyeler yazara abone olur, yeni yazıda mail gelir'],
            'audit_log_enabled'       => ['label' => 'Audit Log',       'desc' => 'Admin işlemlerinin denetim kaydı (önerilen: aktif)'],
            'login_lockout_enabled'   => ['label' => 'Login Lockout',   'desc' => '5 yanlış parola → 15 dk hesap kilidi'],
            'account_delete_enabled'  => ['label' => 'Hesap Sil',       'desc' => 'Üye kendi hesabını silebilir (soft delete + 30 gün)'],
            'data_export_enabled'     => ['label' => 'Veri İndir',      'desc' => 'KVKK — üye kendi verilerini JSON olarak indirir'],
            'redirect_manager_enabled' => ['label' => '301 Redirect',  'desc' => 'Eski → yeni URL yönlendirme yöneticisi'],
            'not_found_logger_enabled' => ['label' => '404 Logger',     'desc' => 'Bulunamayan URL\'leri kaydet + yakın eşleşme öner'],
            'draft_preview_enabled'   => ['label' => 'Taslak Önizleme', 'desc' => 'Taslak yazıyı token URL ile dış kişilere göster'],
            'post_templates_enabled'  => ['label' => 'Yazı Şablonları', 'desc' => 'Haber, rehber, söyleşi, eleştiri preset\'leri'],
            'glossary_enabled'        => ['label' => 'Mimari Sözlük',   'desc' => '/sozluk — mimari/mühendislik terimleri'],
            'before_after_enabled'    => ['label' => 'Öncesi/Sonrası',  'desc' => 'Yazıya before-after slider ekleme (restorasyon, vb.)'],
            'pwa_enabled'             => ['label' => 'PWA / Offline',   'desc' => 'Service Worker + manifest — telefonda app gibi davranır'],
            'sponsored_post_enabled'  => ['label' => 'Sponsorlu İçerik','desc' => 'Yazıyı "sponsorlu" işaretle (etik etiket)'],
            'donation_enabled'        => ['label' => 'Bağış Butonu',    'desc' => 'Header\'da "Destek Ol" butonu'],

            // Tier 8
            'reactions_enabled'         => ['label' => 'Emoji Reaksiyon',  'desc' => '6 emoji ile yazıya tepki (👍❤🔥💡😮🙏)'],
            'quote_share_enabled'       => ['label' => 'Alıntı Paylaş',    'desc' => 'Yazıda metin seç → tweet popup'],
            'comment_threading_enabled' => ['label' => 'Yorum Threading',  'desc' => 'Yorumlara nested yanıt verme'],
            'analytics_events_enabled'  => ['label' => 'Analytics Events', 'desc' => 'Read-depth, time-on-page, outbound click first-party'],
            'active_sessions_enabled'   => ['label' => 'Aktif Oturumlar',  'desc' => 'Üye cihazlarını yönetir + uzakta çıkış'],
            'project_portfolio_enabled' => ['label' => 'Proje Portfolyo',  'desc' => 'Mimari proje portfolyosu (/projeler) — lokasyon, yıl, müellif'],
            'affiliate_enabled'         => ['label' => 'Affiliate Linkler','desc' => '/git/{code} → tıklama takibi + counter'],
            'prefetch_on_hover_enabled' => ['label' => 'Hover Prefetch',   'desc' => 'Link hover\'da hedef sayfa önyüklenir'],

            // Tier 9
            'building_map_enabled'      => ['label' => 'Yapı Haritası',    'desc' => '/harita — coğrafi etiketli projeleri Leaflet harita üzerinde göster'],
            'approval_workflow_enabled' => ['label' => 'Çok Aşamalı Onay', 'desc' => 'Yazı: yazar → editör → admin onay süreci'],
            'ab_test_enabled'           => ['label' => 'A/B Başlık Testi', 'desc' => 'Bir yazıya iki başlık tanımla, CTR ölç'],
            'paywall_enabled'           => ['label' => 'Üye-only Paywall', 'desc' => 'Bir yazıyı sadece kayıtlı üyelere açık yap'],
            'sponsor_slot_enabled'      => ['label' => 'Sponsor Slot',     'desc' => 'Bülten / sidebar / yazı altı sponsor banner'],
            'critical_css_enabled'      => ['label' => 'Critical CSS',     'desc' => 'İlk render için inline CSS — geri kalan stylesheet lazy yüklenir'],
        ];
    }

    public function index(Request $req): Response
    {
        $flags = self::flags();
        $values = Setting::group('features');
        $current = [];
        foreach ($flags as $key => $meta) {
            $current[$key] = (bool) ($values[$key] ?? false);
        }
        $activeCount = count(array_filter($current));
        $totalCount = count($flags);

        return view('admin.features', [
            'title' => 'Özellikler',
            'flags' => $flags,
            'current' => $current,
            'active_count' => $activeCount,
            'total_count' => $totalCount,
        ]);
    }

    public function update(Request $req): Response
    {
        $flags = self::flags();
        $payload = [];
        $types = [];
        foreach ($flags as $key => $_) {
            $raw = $req->input($key, '0');
            $payload[$key] = ($raw === '1' || $raw === 'on' || $raw === true);
            $types[$key] = 'bool';
        }
        Setting::saveGroup('features', $payload, $types);
        Setting::flushCache();

        Logger::warning('features.updated', [
            'active' => count(array_filter($payload)),
            'total' => count($payload),
        ], 'admin');

        flash('success', 'Özellik ayarları güncellendi (' . count(array_filter($payload)) . '/' . count($payload) . ' aktif).');
        return Response::redirect(url('/admin/ozellikler'));
    }
}
