<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostAuthor;
use App\Models\PostRevision;
use App\Models\User;
use App\Services\AuthService;
use App\Services\FaqService;
use App\Services\MarkdownService;
use App\Services\PostFormService;
use App\Services\PostNotifier;
use App\Services\PostScheduler;

/**
 * Author-side CMS controller. Authors create/edit drafts and submit
 * for editorial review. Editors handle approval through EditorController.
 */
final class PostController
{
    public function index(Request $req): Response
    {
        $user = AuthService::user();
        $isAdminEditor = in_array($user['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR], true);
        // Admin/editor tüm yazıları görür; diğerleri yalnızca kendi yazılarını
        $posts = $isAdminEditor
            ? Post::listAllForPanel(200)
            : Post::listByUser((int) $user['id']);
        return view('panel.posts.index', [
            'title' => 'İçeriklerim',
            'user' => $user,
            'posts' => $posts,
            'categories' => Category::all(true),
        ]);
    }

    public function create(Request $req): Response
    {
        $blank = PostFormService::blank();
        $templates = [];
        // Yazı şablonu seçilmişse body ile doldur (Tier 7)
        if (function_exists('feature') && feature('post_templates_enabled')) {
            $tplKey = trim((string) $req->input('template', ''));
            if ($tplKey !== '') {
                $tpl = \App\Models\PostTemplate::findByKey($tplKey);
                if ($tpl) {
                    $blank['body'] = (string) ($tpl['body_html'] ?? '');
                    $blank['body_format'] = 'html';
                    $blank['template_key'] = $tplKey;
                }
            }
            $templates = \App\Models\PostTemplate::all(true);
        }
        return view('panel.posts.form', [
            'title' => 'Yeni İçerik',
            'post' => $blank,
            'categories' => Category::all(true),
            'faq' => [],
            'post_templates' => $templates,
        ]);
    }

    public function store(Request $req): Response
    {
        $user = AuthService::user();
        [$data, $errors] = PostFormService::validate($req, (int) $user['id']);
        if ($errors) {
            PostFormService::flashErrors($errors);
            $_SESSION['_old_post'] = $req->body;
            return Response::redirect(url('/panel/yazilar/yeni'));
        }
        $data['user_id'] = (int) $user['id'];
        $action = (string) $req->input('action', 'draft');
        $scheduledAt = PostScheduler::parseInput((string) $req->input('scheduled_at', ''));
        $bypassApproval = in_array($user['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR], true);

        if ($action === 'schedule' && $scheduledAt !== null) {
            $data['status'] = Post::STATUS_SCHEDULED;
            $data['published_at'] = $scheduledAt;
        } elseif ($action === 'submit') {
            if ($bypassApproval) {
                $data['status'] = Post::STATUS_PUBLISHED;
                $data['published_at'] = $data['published_at'] ?? date('Y-m-d H:i:s');
            } else {
                $data['status'] = Post::STATUS_PENDING;
            }
        } else {
            $data['status'] = Post::STATUS_DRAFT;
        }
        $fmt = (string) $data['body_format'];
        $data['reading_minutes'] = MarkdownService::readingMinutes((string) $data['body'], 200, $fmt);
        $data['excerpt'] = $data['excerpt'] !== ''
            ? $data['excerpt']
            : MarkdownService::plain((string) $data['body'], 280, $fmt);
        $id = Post::create($data);

        // Primary yazar her zaman pivot'a kayıt
        PostAuthor::setPrimary($id, (int) $user['id']);

        // Co-author senkronizasyonu (feature aktifse)
        if (feature('co_author_enabled')) {
            $coIds = array_map('intval', (array) ($req->body['co_authors'] ?? []));
            // Primary'i co-author listesinden çıkar
            $coIds = array_filter($coIds, fn($u) => $u !== (int) $user['id']);
            PostAuthor::syncCoAuthors($id, $coIds);
        }

        if ($data['status'] === Post::STATUS_PENDING) {
            self::recordTransition($id, null, Post::STATUS_PENDING, (int) $user['id'], 'submitted');
            self::notifyEditors($id, $data['title'], $user);
        } elseif ($data['status'] === Post::STATUS_PUBLISHED) {
            self::recordTransition($id, null, Post::STATUS_PUBLISHED, (int) $user['id'], 'auto-published (admin/editor)');
        }
        flash('success', match ($data['status']) {
            Post::STATUS_PUBLISHED => 'İçerik yayınlandı.',
            Post::STATUS_PENDING => 'İçerik onay için gönderildi.',
            Post::STATUS_SCHEDULED => 'İçerik zamanlandı.',
            default => 'Taslak kaydedildi.',
        });
        return Response::redirect(url('/panel/yazilar'));
    }

    public function edit(Request $req, array $args): Response
    {
        $user = AuthService::user();
        $post = self::ownedOrFail((int) $args['id'], (int) $user['id']);
        if ($post instanceof Response) {
            return $post;
        }
        return view('panel.posts.form', [
            'title' => 'İçeriği Düzenle',
            'post' => $post,
            'categories' => Category::all(true),
            'faq' => FaqService::decode($post['faq_json'] ?? null),
            'revisions' => PostRevision::listForPost((int) $post['id'], 10),
            'footnotes' => \App\Services\FootnoteService::decode($post['footnotes_json'] ?? null),
            'co_authors' => PostAuthor::coAuthorsFor((int) $post['id']),
        ]);
    }

    /**
     * Co-author autocomplete — JSON kullanıcı arama.
     */
    public function authorSearch(Request $req): Response
    {
        if (!feature('co_author_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        $q = trim((string) $req->input('q', ''));
        if (mb_strlen($q) < 2) {
            return Response::json(['ok' => true, 'results' => []]);
        }
        $currentId = (int) (AuthService::user()['id'] ?? 0);
        $results = PostAuthor::searchUsers($q, $currentId, 8);
        return Response::json([
            'ok' => true,
            'results' => array_map(fn($u) => [
                'id' => (int) $u['id'],
                'name' => (string) $u['name'],
                'email' => (string) ($u['email'] ?? ''),
                'role' => (string) ($u['role'] ?? ''),
                'avatar' => (string) ($u['avatar'] ?? ''),
            ], $results),
        ]);
    }

    /**
     * Internal link önerisi (Tier 5 feature 4.5).
     * Body'ye göre alakalı yazıları döner — debounced sidebar çağrısı.
     */
    public function suggestLinks(Request $req): Response
    {
        if (!function_exists('feature') || !feature('internal_link_suggest')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        try {
            $body = (string) $req->input('body', '');
            $excludeId = (int) $req->input('post_id', 0);
            $excludeId = $excludeId > 0 ? $excludeId : null;
            $suggestions = \App\Services\PostSuggestionService::findSimilar($body, $excludeId, 5);
            $out = [];
            foreach ($suggestions as $s) {
                $out[] = [
                    'id' => $s['id'],
                    'title' => $s['title'],
                    'url' => url('/' . $s['category_slug'] . '/' . $s['slug']),
                    'category' => $s['category_name'],
                    'score' => $s['score'],
                ];
            }
            return Response::json(['ok' => true, 'suggestions' => $out]);
        } catch (\Throwable $e) {
            \App\Services\Logger::error('panel.post.suggest_links.exception', [
                'msg'   => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 2000),
                'body_len' => isset($body) ? mb_strlen($body) : 0,
            ], 'editorial');
            return Response::json(['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Live analiz — SEO skoru + Okunabilirlik (Türkçe Ateşman).
     * Yazı yazarken sidebar'da göstermek için debounced çağrılır.
     */
    public function analyze(Request $req): Response
    {
        if (!feature('seo_score_enabled') && !feature('readability_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        try {
            $post = [
                'title' => trim((string) $req->input('title', '')),
                'slug'  => trim((string) $req->input('slug', '')),
                'excerpt' => trim((string) $req->input('excerpt', '')),
                'body' => (string) $req->input('body', ''),
                'body_format' => (string) $req->input('body_format', 'html'),
                'meta_title' => trim((string) $req->input('meta_title', '')),
                'meta_description' => trim((string) $req->input('meta_description', '')),
                'focus_keyword' => trim((string) $req->input('focus_keyword', '')),
                'secondary_keywords' => trim((string) $req->input('secondary_keywords', '')),
            ];

            // Bağlam: yazarın uzmanlık alanları (E-E-A-T konu eşleşmesi) + kategori adı.
            $expertise = [];
            $user = AuthService::user();
            if ($user) {
                $profile = \App\Services\ProfileService::decode($user['profile_json'] ?? null);
                $expertise = (array) ($profile['expertise'] ?? []);
            }
            $categoryName = '';
            $catId = (int) $req->input('category_id', 0);
            if ($catId > 0 && ($cat = \App\Models\Category::findById($catId)) !== null) {
                $categoryName = (string) ($cat['name'] ?? '');
            }

            $analysis = \App\Services\ContentAnalysisService::analyze($post, [
                'expertise'     => $expertise,
                'category_name' => $categoryName,
            ]);

            return Response::json(['ok' => true, 'analysis' => $analysis]);
        } catch (\Throwable $e) {
            \App\Services\Logger::error('panel.post.analyze.exception', [
                'msg'   => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 2000),
                'body_len' => isset($post['body']) ? mb_strlen($post['body']) : 0,
            ], 'editorial');
            return Response::json(['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AI Derin Analiz — talep-üzerine (Faz 5). Kural tabanlı analizin yapamadığı
     * öznel katmanı (niyet, içerik boşluğu, öneri) Claude API ile üretir.
     * Varsayılan kapalı; API anahtarı yoksa güvenli şekilde reddeder.
     */
    public function analyzeAi(Request $req): Response
    {
        if (!feature('ai_analysis_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        if (!\App\Services\AiAnalysisService::isEnabled()) {
            return Response::json(['ok' => false, 'message' => 'AI analizi etkin değil veya API anahtarı tanımlı değil.'], 400);
        }
        try {
            $post = [
                'title' => trim((string) $req->input('title', '')),
                'body' => (string) $req->input('body', ''),
                'meta_description' => trim((string) $req->input('meta_description', '')),
                'focus_keyword' => trim((string) $req->input('focus_keyword', '')),
                'secondary_keywords' => trim((string) $req->input('secondary_keywords', '')),
            ];
            $categoryName = '';
            $catId = (int) $req->input('category_id', 0);
            if ($catId > 0 && ($cat = \App\Models\Category::findById($catId)) !== null) {
                $categoryName = (string) ($cat['name'] ?? '');
            }
            $ai = \App\Services\AiAnalysisService::analyze($post, ['category_name' => $categoryName]);
            return Response::json(['ok' => true, 'ai' => $ai]);
        } catch (\Throwable $e) {
            \App\Services\Logger::error('panel.post.ai_analyze.exception', [
                'msg' => $e->getMessage(),
            ], 'editorial');
            return Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Auto-save endpoint — editor.js 30 saniyede bir POST JSON gönderir.
     * Throttle: aynı post için son auto-save 30sn içindeyse skip.
     */
    public function autoSave(Request $req, array $args): Response
    {
        $user = AuthService::user();
        $postId = (int) ($args['id'] ?? 0);
        if ($postId <= 0) {
            return Response::json(['ok' => false, 'error' => 'invalid_id'], 400);
        }
        $post = Post::findById($postId);
        if ($post === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        // Sahiplik veya admin/editor kontrolü
        if ((int) $post['user_id'] !== (int) $user['id']
            && !in_array($user['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR], true)) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        // Throttle: son auto-save 30sn içindeyse skip
        $last = PostRevision::lastAutosave($postId);
        if ($last && (time() - strtotime((string) $last['created_at'])) < 30) {
            return Response::json([
                'ok' => true,
                'throttled' => true,
                'saved_at' => $last['created_at'],
            ]);
        }

        $title = trim((string) $req->input('title', ''));
        $body  = (string) $req->input('body', '');
        $excerpt = trim((string) $req->input('excerpt', ''));

        if ($title === '' && trim($body) === '') {
            return Response::json(['ok' => false, 'error' => 'empty']);
        }

        $snapshot = $post;
        $snapshot['title']   = $title !== '' ? $title : (string) $post['title'];
        $snapshot['body']    = $body !== '' ? $body : (string) ($post['body'] ?? '');
        $snapshot['excerpt'] = $excerpt;

        $rid = PostRevision::snapshot($snapshot, (int) $user['id'], 'auto-save', true);
        return Response::json([
            'ok' => true,
            'revision_id' => $rid,
            'saved_at' => date('c'),
        ]);
    }

    public function update(Request $req, array $args): Response
    {
        $user = AuthService::user();
        $post = self::ownedOrFail((int) $args['id'], (int) $user['id']);
        if ($post instanceof Response) {
            return $post;
        }
        if ($post['status'] === Post::STATUS_PUBLISHED && $user['role'] === User::ROLE_AUTHOR) {
            flash('error', 'Yayında olan bir içeriği yalnızca editör düzenleyebilir.');
            return Response::redirect(url('/panel/yazilar'));
        }
        [$data, $errors] = PostFormService::validate($req, (int) $user['id']);
        if ($errors) {
            PostFormService::flashErrors($errors);
            return Response::redirect(url('/panel/yazilar/' . $post['id'] . '/duzenle'));
        }
        $action = (string) $req->input('action', 'draft');
        $scheduledAt = PostScheduler::parseInput((string) $req->input('scheduled_at', ''));
        $bypassApproval = in_array($user['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR], true);
        $newStatus = $post['status'];
        if ($action === 'submit') {
            if ($bypassApproval) {
                $newStatus = Post::STATUS_PUBLISHED;
                $data['published_at'] = $post['published_at'] ?? date('Y-m-d H:i:s');
            } else {
                $newStatus = Post::STATUS_PENDING;
            }
        } elseif ($action === 'schedule' && $scheduledAt !== null) {
            $newStatus = Post::STATUS_SCHEDULED;
            $data['published_at'] = $scheduledAt;
        }
        $data['status'] = $newStatus;
        $fmt = (string) $data['body_format'];
        $data['reading_minutes'] = MarkdownService::readingMinutes((string) $data['body'], 200, $fmt);
        if ($data['excerpt'] === '') {
            $data['excerpt'] = MarkdownService::plain((string) $data['body'], 280, $fmt);
        }
        // Snapshot the OLD post so the user can roll back.
        PostRevision::snapshot($post, (int) $user['id'], 'auto-snapshot before edit');
        Post::update((int) $post['id'], $data, (int) $data['category_id']);

        // Co-author senkronizasyonu (feature aktifse)
        if (feature('co_author_enabled')) {
            $coIds = array_map('intval', (array) ($req->body['co_authors'] ?? []));
            $primaryUserId = (int) ($post['user_id'] ?? $user['id']);
            $coIds = array_filter($coIds, fn($u) => $u !== $primaryUserId);
            PostAuthor::syncCoAuthors((int) $post['id'], $coIds);
            // Primary yazar pivot'ta yoksa ekle (eski kayıtlar için)
            PostAuthor::setPrimary((int) $post['id'], $primaryUserId);
        }
        if ($newStatus !== $post['status']) {
            if ($newStatus === Post::STATUS_PENDING) {
                self::recordTransition((int) $post['id'], $post['status'], $newStatus, (int) $user['id'], 'resubmitted');
                self::notifyEditors((int) $post['id'], $data['title'], $user);
            } elseif ($newStatus === Post::STATUS_PUBLISHED) {
                self::recordTransition((int) $post['id'], $post['status'], $newStatus, (int) $user['id'], 'auto-published (admin/editor)');
            } elseif ($newStatus === Post::STATUS_SCHEDULED) {
                self::recordTransition((int) $post['id'], $post['status'], $newStatus, (int) $user['id'],
                    'scheduled for ' . ($data['published_at'] ?? '?'));
            }
        }
        flash('success', match ($newStatus) {
            Post::STATUS_PUBLISHED => 'İçerik yayınlandı.',
            Post::STATUS_PENDING => 'İçerik onaya gönderildi.',
            Post::STATUS_SCHEDULED => 'İçerik ' . esc((string) ($data['published_at'] ?? '')) . ' tarihine zamanlandı.',
            default => 'Değişiklikler kaydedildi.',
        });
        return Response::redirect(url('/panel/yazilar'));
    }

    public function destroy(Request $req, array $args): Response
    {
        $user = AuthService::user();
        // Yazı silme YETKİSİ sadece admin'in. Editor/Author "Sil" göremez (UI'da gizli),
        // doğrudan POST gelirse 403 dönüyoruz.
        if (($user['role'] ?? '') !== User::ROLE_ADMIN) {
            flash('error', 'Yazıları sadece admin silebilir. Yayından kaldırmak için "Taslak" veya "Arşiv" durumuna alın.');
            return Response::redirect(url('/panel/yazilar'));
        }
        $post = Post::findById((int) $args['id']);
        if (!$post) return Response::notFound();
        Post::delete((int) $post['id']);
        flash('success', 'İçerik silindi.');
        return Response::redirect(url('/panel/yazilar'));
    }

    /**
     * Bulk Actions (Tier 5 feature 4.2) — birden çok yazıya tek seferde işlem.
     * Actions: publish, draft, archive, delete, change_category
     * Yetki: her yazı için ownedOrFail kontrolü (admin/editor tümünü görür).
     */
    public function bulk(Request $req): Response
    {
        if (!function_exists('feature') || !feature('bulk_actions_enabled')) {
            return Response::notFound();
        }
        $user = AuthService::user();
        $action = (string) $req->input('bulk_action', '');
        $ids = (array) ($req->body['ids'] ?? []);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        if (!$ids || $action === '') {
            flash('error', 'Hiç yazı seçmediniz veya aksiyon belirtilmedi.');
            return Response::redirect(url('/panel/yazilar'));
        }

        $allowed = ['publish', 'draft', 'archive', 'delete', 'change_category'];
        if (!in_array($action, $allowed, true)) {
            flash('error', 'Geçersiz toplu işlem.');
            return Response::redirect(url('/panel/yazilar'));
        }

        $isAdmin = ($user['role'] ?? '') === User::ROLE_ADMIN;
        $isAdminEditor = in_array($user['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR], true);

        // Toplu silme YETKİSİ sadece admin'in
        if ($action === 'delete' && !$isAdmin) {
            flash('error', 'Toplu silme yetkisi sadece admin\'dedir. Yayından kaldırmak için "Taslak" veya "Arşiv" kullanın.');
            return Response::redirect(url('/panel/yazilar'));
        }

        $applied = 0;
        $skipped = 0;

        foreach ($ids as $pid) {
            $post = Post::findById($pid);
            if ($post === null) { $skipped++; continue; }
            // Yetki: sahip değilse + admin/editor değilse skip
            if (!$isAdminEditor && (int) $post['user_id'] !== (int) $user['id']) {
                $skipped++;
                continue;
            }
            $from = (string) $post['status'];

            try {
                if ($action === 'delete') {
                    // Yukarıda zaten admin kontrolü yapıldı, burada sadece admin gelir
                    Post::delete($pid);
                    $applied++;
                } elseif ($action === 'publish') {
                    // Sadece admin/editor yayınlayabilir (mevcut iş kuralı)
                    if (!$isAdminEditor) { $skipped++; continue; }
                    if ($from !== Post::STATUS_PUBLISHED) {
                        Post::transition($pid, $from, Post::STATUS_PUBLISHED, (int) $user['id'], 'bulk publish');
                    }
                    $applied++;
                } elseif ($action === 'draft') {
                    if ($from !== Post::STATUS_DRAFT) {
                        Post::transition($pid, $from, Post::STATUS_DRAFT, (int) $user['id'], 'bulk draft');
                    }
                    $applied++;
                } elseif ($action === 'archive') {
                    if (!$isAdminEditor) { $skipped++; continue; }
                    Post::transition($pid, $from, Post::STATUS_ARCHIVED, (int) $user['id'], 'bulk archive');
                    $applied++;
                } elseif ($action === 'change_category') {
                    $newCat = (int) $req->input('new_category_id', 0);
                    if ($newCat <= 0 || \App\Models\Category::findById($newCat) === null) {
                        $skipped++;
                        continue;
                    }
                    Post::update($pid, ['category_id' => $newCat], $newCat);
                    $applied++;
                }
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        $msg = $applied . ' yazıya işlem uygulandı.';
        if ($skipped > 0) {
            $msg .= ' ' . $skipped . ' yazı atlandı (yetki / hata).';
        }
        flash('success', $msg);
        return Response::redirect(url('/panel/yazilar'));
    }

    /**
     * Quick Edit (Tier 5 feature 4.3) — JSON endpoint, title/slug/status/featured update.
     */
    public function quickUpdate(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('quick_edit_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }
        $user = AuthService::user();
        $postId = (int) ($args['id'] ?? 0);
        $post = self::ownedOrFail($postId, (int) $user['id']);
        if ($post instanceof Response) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $title = trim((string) $req->input('title', ''));
        $slug  = trim((string) $req->input('slug', ''));
        $status = (string) $req->input('status', '');

        $patch = [];
        if ($title !== '' && mb_strlen($title) >= 4) {
            $patch['title'] = mb_substr($title, 0, 220);
        }
        if ($slug !== '') {
            $patch['slug'] = $slug; // Post::update unique slug uygular
        }

        $isAdminEditor = in_array($user['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR], true);

        // Status değişikliği — sadece admin/editor + transition kuralları
        $statusChanged = false;
        $allowedStatuses = [Post::STATUS_DRAFT, Post::STATUS_PUBLISHED, Post::STATUS_ARCHIVED, Post::STATUS_PENDING];
        if ($status !== '' && $status !== (string) $post['status'] && in_array($status, $allowedStatuses, true)) {
            if ($status === Post::STATUS_PUBLISHED && !$isAdminEditor) {
                return Response::json(['ok' => false, 'error' => 'publish_forbidden'], 403);
            }
            $statusChanged = true;
        }

        // Featured — sadece admin/editor + editors_pick feature aktif
        if (function_exists('feature') && feature('editors_pick_enabled') && $isAdminEditor) {
            $patch['featured'] = ((int) $req->input('featured', 0)) === 1 ? 1 : 0;
        }

        if (!$patch && !$statusChanged) {
            return Response::json(['ok' => false, 'error' => 'no_changes']);
        }
        if ($patch) {
            Post::update((int) $post['id'], $patch);
        }
        if ($statusChanged) {
            Post::transition((int) $post['id'], (string) $post['status'], $status, (int) $user['id'], 'quick-edit');
        }
        $fresh = Post::findById((int) $post['id']);
        return Response::json([
            'ok' => true,
            'post' => [
                'id' => (int) $fresh['id'],
                'title' => (string) $fresh['title'],
                'slug' => (string) $fresh['slug'],
                'status' => (string) $fresh['status'],
                'featured' => (int) ($fresh['featured'] ?? 0),
            ],
        ]);
    }

    private static function ownedOrFail(int $id, int $userId): array|Response
    {
        $post = Post::findById($id);
        if ($post === null) {
            return Response::notFound();
        }
        $user = AuthService::user();
        if ((int) $post['user_id'] !== $userId
            && !in_array($user['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR], true)) {
            return Response::html('<h1>403</h1>', 403);
        }
        return $post;
    }

    private static function recordTransition(int $postId, ?string $from, string $to, int $actor, ?string $note): void
    {
        \App\Core\Database::instance()->insert('post_status_history', [
            'post_id' => $postId,
            'actor_id' => $actor,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }

    private static function notifyEditors(int $postId, string $title, array $author): void
    {
        PostNotifier::notifyEditorsOfSubmission($postId, $title, (string) ($author['name'] ?? 'Yazar'));
    }

}
