<?php
/**
 * Post editor (yeni/düzenle) — kabuk. Section'lar partials/post-form/ altında.
 *
 * @var array  $post
 * @var array  $categories
 * @var array  $faq
 * @var string $title
 */
\App\Core\View::layout('base');

$isEdit = !empty($post['id']);
$action = $isEdit ? url('/panel/yazilar/' . $post['id']) : url('/panel/yazilar');
$status = (string) ($post['status'] ?? 'draft');
$statusLabels = [
    'draft'     => ['Taslak',         'badge-draft'],
    'pending'   => ['Onay Bekliyor',  'badge-pending'],
    'published' => ['Yayında',        'badge-published'],
    'scheduled' => ['Zamanlandı',     'badge-scheduled'],
    'rejected'  => ['Reddedildi',     'badge-rejected'],
    'archived'  => ['Arşiv',          'badge-archived'],
];
[$statusLabel, $statusBadge] = $statusLabels[$status] ?? [$status, 'badge-draft'];

$_pfPartials = dirname(__DIR__, 2) . '/partials/post-form';
?>

<section class="hero post-editor-hero">
    <div>
        <h1><?= esc($title) ?></h1>
        <p class="post-editor-meta">
            <?php if ($isEdit): ?>
                <span class="badge <?= esc($statusBadge) ?>"><?= esc($statusLabel) ?></span>
                <span class="muted">·</span>
                <a href="<?= esc(url('/panel/yazilar/' . $post['id'] . '/surumler')) ?>" class="muted">📜 Sürüm geçmişi</a>
            <?php else: ?>
                <span class="badge badge-draft">Yeni</span>
            <?php endif; ?>
        </p>
    </div>
    <?php require dirname(dirname(dirname(__FILE__))) . '/partials/flash.php'; ?>
    <?php foreach (['title','body','category_id'] as $f): if ($e = flash('error_' . $f)): ?>
        <div class="flash flash-error"><?= esc($e) ?></div>
    <?php endif; endforeach; ?>
</section>

<form method="post" action="<?= esc($action) ?>" class="post-editor" id="post-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="body_format" value="<?= esc((string) ($post['body_format'] ?? 'html')) ?>">

    <header class="post-editor-head">
        <input type="text"
               name="title"
               class="post-title-input"
               required minlength="4" maxlength="220"
               placeholder="Başlık…"
               value="<?= esc((string) $post['title']) ?>">
    </header>

    <div class="post-editor-grid">
        <?php require $_pfPartials . '/main.php'; ?>
        <?php require $_pfPartials . '/sidebar.php'; ?>
    </div>
</form>

<script src="<?= esc(asset('js/faq.js')) ?>" defer></script>
<script src="<?= esc(asset('js/howto.js')) ?>" defer></script>
<script src="<?= esc(asset('js/media-picker.js')) ?>" defer></script>
<script src="<?= esc(asset('js/editor.js')) ?>" defer></script>
<?php if ($isEdit): ?>
    <script src="<?= esc(asset('js/post-autosave.js')) ?>" defer></script>
<?php endif; ?>
<?php if (feature('footnotes_enabled')): ?>
    <script src="<?= esc(asset('js/footnotes-editor.js')) ?>" defer></script>
<?php endif; ?>
<?php if (feature('seo_score_enabled') || feature('readability_enabled')): ?>
    <script src="<?= esc(asset('js/post-analyze.js')) ?>" defer></script>
<?php endif; ?>
<?php if (feature('outline_panel_enabled')): ?>
    <script src="<?= esc(asset('js/post-outline.js')) ?>" defer></script>
<?php endif; ?>
<?php if (feature('slash_commands_enabled')): ?>
    <script src="<?= esc(asset('js/slash-commands.js')) ?>" defer></script>
<?php endif; ?>
<?php if (feature('co_author_enabled')): ?>
    <script src="<?= esc(asset('js/author-picker.js')) ?>" defer></script>
<?php endif; ?>
<?php if (feature('internal_link_suggest')): ?>
    <script src="<?= esc(asset('js/internal-link-suggest.js')) ?>" defer></script>
<?php endif; ?>

<?php require $_pfPartials . '/preview-script.php'; ?>
