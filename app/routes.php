<?php
declare(strict_types=1);

/** @var \App\Core\Router $router */

use App\Controllers\HomeController;
use App\Controllers\HealthController;
use App\Controllers\SearchController;
use App\Controllers\FeedController;
use App\Controllers\CategoryController;
use App\Controllers\PostController;
use App\Controllers\AuthController;
use App\Controllers\AuthorController;
use App\Controllers\PasswordResetController;
use App\Controllers\EmailChangeVerifyController;
use App\Controllers\ProfileController;
use App\Controllers\Panel\PostController as PanelPostController;
use App\Controllers\Panel\MediaController as PanelMediaController;
use App\Controllers\Panel\MediaLibraryController as PanelMediaLibraryController;
use App\Controllers\Panel\RevisionController as PanelRevisionController;
use App\Controllers\Admin\LinkController as AdminLinkController;
use App\Controllers\Admin\UserController as AdminUserController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Controllers\Admin\MailController as AdminMailController;
use App\Controllers\EmailVerificationController;
use App\Controllers\Editor\QueueController as EditorQueueController;
use App\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Controllers\Admin\LogController as AdminLogController;
use App\Controllers\Admin\FeaturesController as AdminFeaturesController;
use App\Controllers\Admin\MaintenanceController as AdminMaintenanceController;
use App\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Controllers\Admin\SeriesController as AdminSeriesController;
use App\Controllers\SeriesController as PublicSeriesController;
use App\Controllers\AuthorApplicationController;
use App\Controllers\Admin\AuthorApplicationController as AdminAuthorApplicationController;
use App\Controllers\LegalController;
use App\Controllers\Admin\LegalController as AdminLegalController;
use App\Controllers\Admin\MailTemplateController as AdminMailTemplateController;
// Tier 7
use App\Controllers\EngagementController;
use App\Controllers\AccountController;
use App\Controllers\PreviewController;
use App\Controllers\PwaController;
use App\Controllers\GlossaryController;
use App\Controllers\Admin\AuditController as AdminAuditController;
use App\Controllers\Admin\RedirectController as AdminRedirectController;
use App\Controllers\Admin\NotFoundController as AdminNotFoundController;
use App\Controllers\Admin\GlossaryController as AdminGlossaryController;
// Tier 8
use App\Controllers\ReactionController;
use App\Controllers\AnalyticsController;
use App\Controllers\AffiliateController;
use App\Controllers\SessionController;
use App\Controllers\Admin\AffiliateController as AdminAffiliateController;
use App\Controllers\Editor\CommentController as EditorCommentController;
use App\Controllers\CommentController;
use App\Controllers\NewsletterController;
use App\Controllers\SitemapController;
use App\Controllers\RobotsController;
use App\Controllers\LlmsController;
use App\Controllers\IndexNowKeyController;
// Tier 9
use App\Controllers\ProjectController;
use App\Controllers\SponsorController;
use App\Controllers\Admin\ProjectController as AdminProjectController;
use App\Controllers\Admin\AbTestController as AdminAbTestController;
use App\Controllers\Admin\SponsorController as AdminSponsorController;
use App\Controllers\Admin\CriticalCssController as AdminCriticalCssController;
use App\Controllers\Editor\ApprovalController as EditorApprovalController;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RbacMiddleware;

// Global: CSRF check on every state-changing request.
$router->use(CsrfMiddleware::class);

// Public
$router->get('/', [HomeController::class, 'index']);
$router->get('/health', [HealthController::class, 'index']);
$router->get('/sitemap.xml',          [SitemapController::class, 'index']);    // Sitemap Index
$router->get('/sitemap-pages.xml',    [SitemapController::class, 'pages']);    // Static + listing URLs
$router->get('/sitemap-posts.xml',    [SitemapController::class, 'posts']);    // Posts + cover image
$router->get('/sitemap-projects.xml', [SitemapController::class, 'projects']); // Projects + cover + gallery
$router->get('/sitemap-images.xml',   [SitemapController::class, 'images']);   // Dedicated image sitemap
$router->get('/robots.txt',           [RobotsController::class, 'index']);
$router->get('/llms.txt',             [LlmsController::class, 'index']);
// IndexNow key verification — pattern /<32-hex-token>.txt
$router->get('/{token}.txt',          [IndexNowKeyController::class, 'show']);
$router->get('/ara', [SearchController::class, 'index']);

// Feeds (RSS 2.0, Atom 1.0, JSON Feed 1.1)
$router->get('/rss',       [FeedController::class, 'rss']);
$router->get('/atom.xml',  [FeedController::class, 'atom']);
$router->get('/atom',      [FeedController::class, 'atom']);   // /atom alias → /atom.xml
$router->get('/feed.json', [FeedController::class, 'json']);

// Auth
$router->get('/giris', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
$router->post('/giris', [AuthController::class, 'login'], [GuestMiddleware::class]);
$router->get('/giris/dogrulama', [AuthController::class, 'showTotpChallenge'], [GuestMiddleware::class]);
$router->post('/giris/dogrulama', [AuthController::class, 'verifyTotp'], [GuestMiddleware::class]);
$router->get('/kayit', [AuthController::class, 'showRegister'], [GuestMiddleware::class]);
$router->post('/kayit', [AuthController::class, 'register'], [GuestMiddleware::class]);
$router->post('/cikis', [AuthController::class, 'logout'], [AuthMiddleware::class]);

// E-posta doğrulama
$router->get('/dogrula/{token}', [EmailVerificationController::class, 'verify']);
$router->post('/dogrula/yenile', [EmailVerificationController::class, 'resend'], [AuthMiddleware::class]);

// Şifremi unuttum (Y1)
$router->get('/sifremi-unuttum',             [PasswordResetController::class, 'showRequest'],   [GuestMiddleware::class]);
$router->post('/sifremi-unuttum',            [PasswordResetController::class, 'submitRequest'], [GuestMiddleware::class]);
$router->get('/sifremi-unuttum/gonderildi',  [PasswordResetController::class, 'sentNotice'],    [GuestMiddleware::class]);
$router->get('/sifre-sifirla/{token}',       [PasswordResetController::class, 'showReset'],     [GuestMiddleware::class]);
$router->post('/sifre-sifirla/{token}',      [PasswordResetController::class, 'submitReset'],   [GuestMiddleware::class]);

// E-posta değişimi onay (K6 pending pattern)
$router->get('/eposta/onayla/{token}',       [EmailChangeVerifyController::class, 'confirm']);

// Authors (public profiles)
$router->get('/yazarlar', [AuthorController::class, 'index']);
$router->get('/yazar/{slug}', [AuthorController::class, 'show']);

// Saved posts (LocalStorage list — data client-side)
$router->get('/kaydedilenler', [HomeController::class, 'saved']);

// Series / Diziler (public)
$router->get('/diziler', [PublicSeriesController::class, 'index']);
$router->get('/dizi/{slug}', [PublicSeriesController::class, 'show']);

// Legal docs (public)
$router->get('/sozlesmeler', [LegalController::class, 'index']);
$router->get('/sozlesmeler/{slug}', [LegalController::class, 'show']);

// Glossary (Tier 7 — Architecture niche)
$router->get('/sozluk', [GlossaryController::class, 'index']);
$router->get('/sozluk/{slug}', [GlossaryController::class, 'show']);

// PWA (Tier 7)
$router->get('/manifest.webmanifest', [PwaController::class, 'manifest']);
$router->get('/sw.js', [PwaController::class, 'serviceWorker']);

// Engagement (Tier 7)
$router->post('/etkilesim/clap/{postId}', [EngagementController::class, 'clap']);
$router->post('/etkilesim/bookmark/{postId}', [EngagementController::class, 'bookmark']);
$router->post('/etkilesim/takip/{authorId}', [EngagementController::class, 'follow']);
$router->get('/etkilesim/durum/{postId}', [EngagementController::class, 'state']);

// Draft preview (Tier 7)
$router->get('/onizleme/{token}', [PreviewController::class, 'show']);

// Tier 8 — Reactions
$router->post('/etkilesim/reaksiyon/{postId}/{emoji}', [ReactionController::class, 'toggle']);
$router->get('/etkilesim/reaksiyon/{postId}', [ReactionController::class, 'summary']);

// Tier 8 — Analytics event ingest
$router->post('/analytics/event', [AnalyticsController::class, 'event']);

// Tier 8 — Affiliate redirect
$router->get('/git/{code}', [AffiliateController::class, 'go']);

// Tier 9 — Project Portfolio (public)
$router->get('/projeler',        [ProjectController::class, 'index']);
$router->get('/proje/{slug}',    [ProjectController::class, 'show']);
$router->get('/harita',          [ProjectController::class, 'map']);

// Tier 9 — Sponsor click redirect
$router->get('/sponsor/git/{id}', [SponsorController::class, 'go']);

// Author application — /yazar-ol multi-step wizard (auth required)
$router->get('/yazar-ol', [AuthorApplicationController::class, 'showForm'], [AuthMiddleware::class]);
$router->post('/yazar-ol', [AuthorApplicationController::class, 'submitStep'], [AuthMiddleware::class]);
$router->get('/yazar-ol/tesekkurler', [AuthorApplicationController::class, 'thanks'], [AuthMiddleware::class]);

// Tag archives
$router->get('/etiket/{slug}', [PostController::class, 'tag']);

// Public comment submission
$router->post('/yorum', [CommentController::class, 'store']);

// Newsletter (public)
$router->post('/newsletter/abone-ol', [NewsletterController::class, 'subscribe']);
$router->get('/newsletter/onay/{token}', [NewsletterController::class, 'confirm']);
$router->get('/newsletter/cikis/{token}', [NewsletterController::class, 'unsubscribe']);

// Author panel (auth required)
$router->group('/panel', function ($r) {
    $r->get('/', [ProfileController::class, 'dashboard']);
    $r->get('/profil', [ProfileController::class, 'edit']);
    $r->post('/profil', [ProfileController::class, 'update']);
    $r->post('/profil/sifre', [ProfileController::class, 'changePassword']);
    $r->post('/profil/eposta', [ProfileController::class, 'changeEmail']);

    // Account (Tier 7)
    $r->get('/hesap/verilerim', [AccountController::class, 'exportData']);
    $r->get('/hesap/sil', [AccountController::class, 'showDelete']);
    $r->post('/hesap/sil', [AccountController::class, 'requestDelete']);
    $r->post('/hesap/sil/dogrula', [AccountController::class, 'destroy']);
    $r->post('/hesap/sil/iptal', [AccountController::class, 'cancelDelete']);
    // Sunucu-tarafı bookmarks
    $r->get('/kaydedilenler', [EngagementController::class, 'myBookmarks']);
    // Draft preview token
    $r->post('/yazilar/{id}/onizleme-token', [PreviewController::class, 'generateToken']);
    // Active sessions (Tier 8)
    $r->get('/oturumlar', [SessionController::class, 'index']);
    $r->post('/oturumlar/{id}/sil', [SessionController::class, 'delete']);

    // 2FA TOTP setup
    $r->get('/iki-fa', [ProfileController::class, 'show2fa']);
    $r->post('/iki-fa/baslat', [ProfileController::class, 'start2fa']);
    $r->post('/iki-fa/aktiflestir', [ProfileController::class, 'activate2fa']);
    $r->post('/iki-fa/pasiflestir', [ProfileController::class, 'disable2fa']);
    $r->post('/iki-fa/recovery-yenile', [ProfileController::class, 'regenerateRecoveryCodes']);

    // Posts (CRUD + submit)
    $r->get('/yazilar', [PanelPostController::class, 'index']);
    $r->get('/yazilar/yeni', [PanelPostController::class, 'create']);
    $r->post('/yazilar', [PanelPostController::class, 'store']);
    $r->get('/yazilar/{id}/duzenle', [PanelPostController::class, 'edit']);
    $r->post('/yazilar/{id}', [PanelPostController::class, 'update']);
    $r->post('/yazilar/{id}/auto-save', [PanelPostController::class, 'autoSave']);
    $r->post('/yazilar/analiz', [PanelPostController::class, 'analyze']);
    $r->get('/yazilar/yazar-ara', [PanelPostController::class, 'authorSearch']);

    // Bulk + Quick edit + Link suggest (Tier 5)
    $r->post('/yazilar/toplu', [PanelPostController::class, 'bulk']);
    $r->post('/yazilar/{id}/hizli-guncelle', [PanelPostController::class, 'quickUpdate']);
    $r->post('/yazilar/onerile', [PanelPostController::class, 'suggestLinks']);
    $r->post('/yazilar/{id}/sil', [PanelPostController::class, 'destroy']);

    // Tier 9 — Approval workflow (yazar tarafı: gönder)
    $r->post('/yazilar/{id}/gonder', [EditorApprovalController::class, 'submit']);

    // Tier 9.2 — Projects CRUD (Author/Editor/Admin, controller'da yetki kontrolü)
    $r->get('/projeler',                  [AdminProjectController::class, 'index']);
    $r->get('/projeler/yeni',             [AdminProjectController::class, 'create']);
    $r->post('/projeler/kaydet',          [AdminProjectController::class, 'store']);
    $r->get('/projeler/{id}/duzenle',     [AdminProjectController::class, 'edit']);
    $r->post('/projeler/{id}/guncelle',   [AdminProjectController::class, 'update']);
    $r->post('/projeler/{id}/gonder',     [AdminProjectController::class, 'submit']);

    // Revisions
    $r->get('/yazilar/{id}/surumler', [PanelRevisionController::class, 'index']);
    $r->get('/yazilar/{id}/surumler/{rid}', [PanelRevisionController::class, 'show']);
    $r->post('/yazilar/{id}/surumler/{rid}/geri-yukle', [PanelRevisionController::class, 'restore']);

    // AJAX media upload (returns JSON)
    $r->post('/medya/yukle', [PanelMediaController::class, 'upload']);

    // Reindex orphaned files (those on disk but missing from the media table)
    $r->get('/medya/reindex', [PanelMediaController::class, 'reindex']);
    $r->post('/medya/reindex', [PanelMediaController::class, 'reindexRun']);
    // Cleanup DB rows whose master file is missing (e.g. wrong-directory uploads)
    $r->post('/medya/cleanup', [PanelMediaController::class, 'cleanupOrphanRows']);
    // Move files from /public/uploads/ back to /uploads/ (publicRoot bug fix)
    $r->post('/medya/relocate', [PanelMediaController::class, 'relocate']);

    // Media library
    $r->get('/medya', [PanelMediaLibraryController::class, 'index']);
    $r->get('/medya/picker.json', [PanelMediaLibraryController::class, 'listJson']);
    $r->post('/medya/{id}', [PanelMediaLibraryController::class, 'update']);
    $r->post('/medya/{id}/sil', [PanelMediaLibraryController::class, 'destroy']);
}, [AuthMiddleware::class]);

// Editor area (admin or editor)
$router->group('/editor', function ($r) {
    $r->get('/', [EditorQueueController::class, 'index']);
    $r->get('/onay', [EditorQueueController::class, 'index']);
    $r->get('/onay/{id}', [EditorQueueController::class, 'review']);
    $r->post('/onay/{id}/onayla', [EditorQueueController::class, 'approve']);
    $r->post('/onay/{id}/reddet', [EditorQueueController::class, 'reject']);

    // Comment moderation
    $r->get('/yorumlar', [EditorCommentController::class, 'index']);
    $r->post('/yorumlar/{id}/onayla', [EditorCommentController::class, 'approve']);
    $r->post('/yorumlar/{id}/reddet', [EditorCommentController::class, 'reject']);
    $r->post('/yorumlar/{id}/spam', [EditorCommentController::class, 'spam']);
    $r->post('/yorumlar/{id}/sil', [EditorCommentController::class, 'destroy']);

    // Tier 9 — Multi-stage approval workflow
    $r->get('/onaylar',                    [EditorApprovalController::class, 'index']);
    $r->get('/onaylar/{id}',               [EditorApprovalController::class, 'show']);
    $r->post('/onaylar/{id}/onayla',       [EditorApprovalController::class, 'approve']);
    $r->post('/onaylar/{id}/reddet',       [EditorApprovalController::class, 'reject']);
    $r->post('/onaylar/{id}/yayinla',      [EditorApprovalController::class, 'publish']);
}, [AuthMiddleware::class, RbacMiddleware::class . ':admin,editor']);

// Admin area
$router->group('/admin', function ($r) {
    $r->get('/', [AdminDashboardController::class, 'index']);
    $r->get('/kategoriler', [AdminCategoryController::class, 'index']);
    $r->get('/kategoriler/yeni', [AdminCategoryController::class, 'create']);
    $r->get('/kategoriler/{id}/duzenle', [AdminCategoryController::class, 'edit']);
    $r->post('/kategoriler', [AdminCategoryController::class, 'store']);
    $r->post('/kategoriler/{id}', [AdminCategoryController::class, 'update']);
    $r->post('/kategoriler/{id}/sil', [AdminCategoryController::class, 'destroy']);
    $r->get('/loglar', [AdminLogController::class, 'index']);

    // Bakım & tazelik kontrolü + log purge + cache flush + yedekler
    $r->get('/bakim', [AdminMaintenanceController::class, 'index']);
    $r->post('/bakim/log-temizle', [AdminMaintenanceController::class, 'purgeLogs']);
    $r->post('/bakim/cache-temizle', [AdminMaintenanceController::class, 'flushCache']);
    $r->post('/bakim/tazele/{id}', [AdminMaintenanceController::class, 'refreshStale']);
    $r->get('/bakim/yedekler', [AdminMaintenanceController::class, 'backups']);
    $r->post('/bakim/yedekler/db', [AdminMaintenanceController::class, 'runBackupDb']);
    $r->post('/bakim/yedekler/uploads', [AdminMaintenanceController::class, 'runBackupUploads']);
    $r->get('/bakim/yedekler/indir/{name}', [AdminMaintenanceController::class, 'downloadBackup']);
    $r->post('/bakim/yedekler/sil/{name}', [AdminMaintenanceController::class, 'deleteBackup']);

    // Migrations (DB şema güncellemeleri)
    $r->get('/bakim/migrasyonlar', [AdminMaintenanceController::class, 'migrations']);
    $r->post('/bakim/migrasyonlar/calistir', [AdminMaintenanceController::class, 'runPendingMigrations']);
    $r->post('/bakim/migrasyonlar/calistir/{name}', [AdminMaintenanceController::class, 'runOneMigration']);
    $r->post('/bakim/migrasyonlar/uygulandi-isaretle/{name}', [AdminMaintenanceController::class, 'markMigrationApplied']);
    $r->post('/bakim/migrasyonlar/isareti-kaldir/{name}', [AdminMaintenanceController::class, 'unmarkMigration']);

    // Kırık link dedektörü
    $r->get('/linkler', [AdminLinkController::class, 'index']);
    $r->post('/linkler/tara', [AdminLinkController::class, 'scan']);

    // Kullanıcı yönetimi
    $r->get('/kullanicilar', [AdminUserController::class, 'index']);
    $r->post('/kullanicilar/{id}', [AdminUserController::class, 'update']);
    $r->post('/kullanicilar/{id}/sil', [AdminUserController::class, 'destroy']);

    // Site ayarları (genel, SEO, analitik, sosyal, içerik politikası)
    $r->get('/ayarlar',  [AdminSettingsController::class, 'index']);
    $r->post('/ayarlar', [AdminSettingsController::class, 'update']);

    // Özellikler — Tier 5 feature flag özet paneli
    $r->get('/ozellikler', [AdminFeaturesController::class, 'index']);
    $r->post('/ozellikler', [AdminFeaturesController::class, 'update']);

    // Series / Dizi yönetimi (CRUD)
    $r->get('/diziler',                  [AdminSeriesController::class, 'index']);
    $r->get('/diziler/yeni',             [AdminSeriesController::class, 'create']);
    $r->post('/diziler',                 [AdminSeriesController::class, 'store']);
    $r->get('/diziler/{id}/duzenle',     [AdminSeriesController::class, 'edit']);
    $r->post('/diziler/{id}',            [AdminSeriesController::class, 'update']);
    $r->post('/diziler/{id}/sil',        [AdminSeriesController::class, 'destroy']);

    // Yazar başvuruları (Tier 5)
    $r->get('/yazar-basvurulari',           [AdminAuthorApplicationController::class, 'index']);
    $r->get('/yazar-basvurulari/{id}',      [AdminAuthorApplicationController::class, 'show']);
    $r->post('/yazar-basvurulari/{id}/onayla', [AdminAuthorApplicationController::class, 'approve']);
    $r->post('/yazar-basvurulari/{id}/reddet', [AdminAuthorApplicationController::class, 'reject']);

    // Sözleşmeler (Tier 6)
    $r->get('/sozlesmeler',              [AdminLegalController::class, 'index']);
    $r->get('/sozlesmeler/{id}/duzenle', [AdminLegalController::class, 'edit']);
    $r->post('/sozlesmeler/{id}',        [AdminLegalController::class, 'update']);

    // Mail Şablonları (Tier 6)
    $r->get('/mail-sablonlari',                [AdminMailTemplateController::class, 'index']);
    $r->get('/mail-sablonlari/{id}/duzenle',   [AdminMailTemplateController::class, 'edit']);
    $r->post('/mail-sablonlari/{id}',          [AdminMailTemplateController::class, 'update']);
    $r->post('/mail-sablonlari/{id}/test',     [AdminMailTemplateController::class, 'sendTest']);

    // Audit Log (Tier 7)
    $r->get('/audit', [AdminAuditController::class, 'index']);

    // 301 Redirect Manager (Tier 7)
    $r->get('/yonlendirmeler',        [AdminRedirectController::class, 'index']);
    $r->post('/yonlendirmeler',       [AdminRedirectController::class, 'store']);
    $r->post('/yonlendirmeler/{id}',  [AdminRedirectController::class, 'update']);
    $r->post('/yonlendirmeler/{id}/sil', [AdminRedirectController::class, 'destroy']);

    // 404 Logger (Tier 7)
    $r->get('/404-loglari',                [AdminNotFoundController::class, 'index']);
    $r->post('/404-loglari/{id}/yonlendir', [AdminNotFoundController::class, 'createRedirectFromLog']);
    $r->post('/404-loglari/{id}/sil',       [AdminNotFoundController::class, 'destroy']);

    // Glossary (Tier 7)
    $r->get('/sozluk',              [AdminGlossaryController::class, 'index']);
    $r->get('/sozluk/yeni',         [AdminGlossaryController::class, 'create']);
    $r->post('/sozluk',             [AdminGlossaryController::class, 'store']);
    $r->get('/sozluk/{id}/duzenle', [AdminGlossaryController::class, 'edit']);
    $r->post('/sozluk/{id}',        [AdminGlossaryController::class, 'update']);
    $r->post('/sozluk/{id}/sil',    [AdminGlossaryController::class, 'destroy']);

    // Affiliate (Tier 8)
    $r->get('/affiliate',           [AdminAffiliateController::class, 'index']);
    $r->post('/affiliate',          [AdminAffiliateController::class, 'store']);
    $r->post('/affiliate/{id}/sil', [AdminAffiliateController::class, 'destroy']);

    // Tier 9 — Project approval (sadece admin onaylar)
    $r->post('/projeler/{id}/onayla',       [AdminProjectController::class, 'approve']);
    $r->post('/projeler/{id}/reddet',       [AdminProjectController::class, 'reject']);
    $r->post('/projeler/{id}/sil',          [AdminProjectController::class, 'delete']);

    // Tier 9 — A/B Test admin
    $r->get('/ab-test',                     [AdminAbTestController::class, 'index']);
    $r->get('/ab-test/yeni',                [AdminAbTestController::class, 'create']);
    $r->post('/ab-test/kaydet',             [AdminAbTestController::class, 'store']);
    $r->post('/ab-test/{id}/sonuc',         [AdminAbTestController::class, 'declareWinner']);
    $r->post('/ab-test/{id}/sil',           [AdminAbTestController::class, 'delete']);

    // Tier 9 — Sponsor slots
    $r->get('/sponsor',                     [AdminSponsorController::class, 'index']);
    $r->get('/sponsor/yeni',                [AdminSponsorController::class, 'create']);
    $r->post('/sponsor/kaydet',             [AdminSponsorController::class, 'store']);
    $r->get('/sponsor/{id}/duzenle',        [AdminSponsorController::class, 'edit']);
    $r->post('/sponsor/{id}/guncelle',      [AdminSponsorController::class, 'update']);
    $r->post('/sponsor/{id}/sil',           [AdminSponsorController::class, 'delete']);

    // Tier 9 — Critical CSS
    $r->get('/critical-css',                [AdminCriticalCssController::class, 'index']);
    $r->post('/critical-css/kaydet',        [AdminCriticalCssController::class, 'save']);

    // E-posta (SMTP) yapılandırması
    $r->get('/mail',       [AdminMailController::class, 'index']);
    $r->post('/mail',      [AdminMailController::class, 'update']);
    $r->post('/mail/test', [AdminMailController::class, 'sendTest']);

    // Newsletter yönetimi
    $r->get('/newsletter',         [AdminNewsletterController::class, 'index']);
    $r->get('/newsletter/ayarlar', [AdminNewsletterController::class, 'settings']);
    $r->post('/newsletter/ayarlar', [AdminNewsletterController::class, 'updateSettings']);
    $r->get('/newsletter/csv',     [AdminNewsletterController::class, 'exportCsv']);
}, [AuthMiddleware::class, RbacMiddleware::class . ':admin']);

// Silo URL — kategori/içerik (en sona)
$router->get('/{category}', [CategoryController::class, 'show']);
$router->get('/{category}/{slug}', [PostController::class, 'show']);
