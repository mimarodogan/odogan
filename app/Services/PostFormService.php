<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\Category;
use App\Models\Post;

/**
 * Validation + cover-image resolver + default-payload helpers,
 * extracted from Panel\PostController so the controller stays under 10KB.
 */
final class PostFormService
{
    /**
     * @return array{0:array<string,mixed>, 1:array<string,string>}
     */
    public static function validate(Request $req, int $userId): array
    {
        $errors = [];
        $title = trim((string) $req->input('title', ''));
        $body = (string) $req->input('body', '');
        $cat = (int) $req->input('category_id', 0);

        if (mb_strlen($title) < 4) {
            $errors['title'] = 'Başlık en az 4 karakter olmalı.';
        }
        if (mb_strlen($body) < 30) {
            $errors['body'] = 'İçerik en az 30 karakter olmalı.';
        }
        if ($cat <= 0 || Category::findById($cat) === null) {
            $errors['category_id'] = 'Geçerli bir kategori seçin.';
        }

        $faq = FaqService::normalize($req->body['faq'] ?? []);
        $cover = self::resolveCover($req, $userId);
        $format = (string) $req->input('body_format', 'markdown');
        if (!in_array($format, ['markdown', 'html'], true)) {
            $format = 'markdown';
        }
        $cleanBody = $format === 'html' ? Sanitizer::clean($body) : $body;

        $slug = trim((string) $req->input('slug', ''));
        if ($slug === '') {
            // ?? operator only catches null — empty form input is still ''. Fallback to title here.
            $slug = $title;
        }
        // Article alt-tipi (BlogPosting default)
        $articleType = trim((string) $req->input('article_type', 'BlogPosting'));
        $allowedTypes = ['BlogPosting','NewsArticle','TechArticle','HowTo','Article'];
        if (!in_array($articleType, $allowedTypes, true)) {
            $articleType = 'BlogPosting';
        }

        // HowTo adımları — sadece article_type=HowTo ise işlenir
        $howtoJson = null;
        if ($articleType === 'HowTo') {
            $howtoRaw = $req->body['howto'] ?? [];
            $howto = self::normalizeHowto(is_array($howtoRaw) ? $howtoRaw : []);
            if ($howto['steps']) {
                $howtoJson = (string) json_encode($howto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        // Dipnotlar (footnotes) — sadece feature aktifse işle
        $footnotesJson = null;
        if (function_exists('feature') && feature('footnotes_enabled')) {
            $footnotesRaw = $req->body['footnotes'] ?? [];
            $footnotes = FootnoteService::normalize(is_array($footnotesRaw) ? $footnotesRaw : []);
            if ($footnotes) {
                $footnotesJson = FootnoteService::encode($footnotes);
            }
        }

        $data = [
            'title' => mb_substr($title, 0, 220),
            'slug' => $slug,
            'excerpt' => mb_substr(trim((string) $req->input('excerpt', '')), 0, 500),
            'body' => $cleanBody,
            'body_format' => $format,
            'article_type' => $articleType,
            'category_id' => $cat,
            'cover_image' => $cover,
            'og_image' => mb_substr(trim((string) $req->input('og_image', '')), 0, 255) ?: null,
            'meta_title' => mb_substr((string) $req->input('meta_title', ''), 0, 220),
            'meta_description' => mb_substr((string) $req->input('meta_description', ''), 0, 255),
            'faq_json' => $faq ? FaqService::encode($faq) : null,
            'howto_steps_json' => $howtoJson,
            'footnotes_json' => $footnotesJson,
        ];

        // Editörün Seçimi — sadece feature aktif + admin/editor yetkili kullanıcılarsa
        // alan $data'ya eklenir. Yetkisiz kullanıcı edit ederse mevcut featured değeri
        // korunur (Post::update sadece $data anahtarlarını günceller).
        if (function_exists('feature') && feature('editors_pick_enabled')) {
            $authUser = \App\Services\AuthService::user();
            $role = (string) ($authUser['role'] ?? '');
            if (in_array($role, ['admin', 'editor'], true)) {
                $data['featured'] = ((int) $req->input('featured', 0)) === 1 ? 1 : 0;
            }
        }

        // Series (Dizi) — feature aktifse series_id + position kaydedilir.
        if (function_exists('feature') && feature('series_enabled')) {
            $sid = (int) $req->input('series_id', 0);
            $spos = (int) $req->input('series_position', 0);
            $data['series_id'] = $sid > 0 ? $sid : null;
            $data['series_position'] = ($sid > 0 && $spos > 0) ? min($spos, 999) : null;
        }

        // Sponsorlu içerik — Tier 7
        if (function_exists('feature') && feature('sponsored_post_enabled')) {
            $authUser = \App\Services\AuthService::user();
            $role = (string) ($authUser['role'] ?? '');
            if (in_array($role, ['admin', 'editor'], true)) {
                $data['is_sponsored']  = ((int) $req->input('is_sponsored', 0)) === 1 ? 1 : 0;
                $data['sponsor_name']  = mb_substr(trim((string) $req->input('sponsor_name', '')), 0, 160) ?: null;
                $data['sponsor_url']   = mb_substr(trim((string) $req->input('sponsor_url', '')), 0, 300) ?: null;
            }
        }

        // Yazı şablonu — Tier 7
        if (function_exists('feature') && feature('post_templates_enabled')) {
            $tpl = trim((string) $req->input('template_key', ''));
            if ($tpl !== '') {
                $data['template_key'] = mb_substr($tpl, 0, 60);
            }
        }

        // Proje ilişkisi — Tier 9
        if (function_exists('feature') && feature('project_portfolio_enabled')) {
            $pid = (int) $req->input('project_id', 0);
            $data['project_id'] = $pid > 0 ? $pid : null;
        }

        // Paywall — Tier 9
        if (function_exists('feature') && feature('paywall_enabled')) {
            $authUser = \App\Services\AuthService::user();
            $role = (string) ($authUser['role'] ?? '');
            // Sadece editor/admin paywall toggle edebilir
            if (in_array($role, ['admin', 'editor'], true)) {
                $data['paywall'] = ((int) $req->input('paywall', 0)) === 1 ? 1 : 0;
                $excerpt = trim((string) $req->input('paywall_excerpt', ''));
                $data['paywall_excerpt'] = $excerpt !== '' ? mb_substr($excerpt, 0, 1000) : null;
            }
        }

        return [$data, $errors];
    }

    /**
     * HowTo form girdisini normalize et: total_time, supply, tool, steps.
     * Beklenen $raw yapısı: ['total_time_minutes' => '30', 'supply' => "x\ny\nz",
     *                       'tool' => "a\nb", 'steps' => [[name,text,image], ...]]
     */
    public static function normalizeHowto(array $raw): array
    {
        $totalMin = (int) ($raw['total_time_minutes'] ?? 0);
        $supplyRaw = (string) ($raw['supply'] ?? '');
        $toolRaw   = (string) ($raw['tool'] ?? '');
        $stepsRaw  = (array)  ($raw['steps'] ?? []);

        $splitLines = static function (string $s): array {
            $lines = preg_split('/\r?\n/', $s) ?: [];
            return array_values(array_filter(array_map('trim', $lines), static fn($v) => $v !== ''));
        };

        $steps = [];
        foreach ($stepsRaw as $s) {
            if (!is_array($s)) continue;
            $name = trim((string) ($s['name'] ?? ''));
            $text = trim((string) ($s['text'] ?? ''));
            $image = trim((string) ($s['image'] ?? ''));
            if ($name === '' && $text === '') continue;
            $row = [
                'name' => mb_substr($name, 0, 220),
                'text' => mb_substr($text, 0, 2000),
            ];
            if ($image !== '') {
                $row['image'] = mb_substr($image, 0, 255);
            }
            $steps[] = $row;
        }

        return [
            'total_time_minutes' => $totalMin > 0 ? min($totalMin, 100000) : 0,
            'supply'             => $splitLines($supplyRaw),
            'tool'               => $splitLines($toolRaw),
            'steps'              => $steps,
        ];
    }

    public static function resolveCover(Request $req, int $userId): string
    {
        $upload = $req->files['cover_image_file'] ?? null;
        if (is_array($upload) && (int) ($upload['size'] ?? 0) > 0
            && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $r = MediaService::uploadFromForm($upload, $userId ?: null);
            if ($r['ok']) {
                return (string) $r['media']['path'];
            }
            flash('error_cover_image', $r['error'] ?? 'Görsel yüklenemedi.');
        }
        return mb_substr((string) $req->input('cover_image', ''), 0, 255);
    }

    public static function blank(): array
    {
        $old = $_SESSION['_old_post'] ?? [];
        unset($_SESSION['_old_post']);
        return array_merge([
            'id' => null, 'title' => '', 'slug' => '', 'excerpt' => '',
            'body' => '', 'body_format' => 'html', 'category_id' => null,
            'cover_image' => '', 'og_image' => '',
            'article_type' => 'BlogPosting',
            'status' => Post::STATUS_DRAFT, 'meta_title' => '', 'meta_description' => '',
            'faq_json' => null, 'howto_steps_json' => null, 'published_at' => null,
            'featured' => 0,
            'series_id' => null, 'series_position' => null,
        ], $old);
    }

    public static function flashErrors(array $errors): void
    {
        foreach ($errors as $k => $v) {
            flash('error_' . $k, $v);
        }
    }
}
