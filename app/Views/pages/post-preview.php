<?php
/**
 * Yayınlanmamış taslak önizleme — token URL ile (/onizleme/{token}).
 * PreviewController::show() tarafından render edilir. noindex (robots view'e geçer).
 *
 * @var array       $post       Post satırı (+ category_name, author_name)
 * @var array|null  $author     User::findById sonucu
 * @var string      $body_html  MarkdownService::render() çıktısı (güvenli HTML)
 * @var string|null $preview_status
 */
\App\Core\View::layout('base');
?>

<div class="preview-banner" role="status"
     style="background:#8C6A12;color:#fff;padding:.7rem 1rem;border-radius:8px;margin:1rem 0 1.5rem;font-size:.9rem;line-height:1.4;text-align:center">
    <strong>TASLAK ÖNİZLEME</strong> — Bu sayfa yayında değildir; yalnızca bu özel bağlantıyı bilenler görebilir.
    <?php if (!empty($preview_status)): ?>
        &nbsp;·&nbsp; Durum: <strong><?= esc((string) $preview_status) ?></strong>
    <?php endif; ?>
</div>

<article class="post">
    <header>
        <?php if (!empty($post['category_name'])): ?>
            <p class="card-meta"><span><?= esc((string) $post['category_name']) ?></span></p>
        <?php endif; ?>

        <h1><?= esc((string) ($post['title'] ?? 'Başlıksız taslak')) ?></h1>

        <?php if (!empty($post['excerpt'])): ?>
            <aside class="post-tldr" aria-label="Özet">
                <span class="post-tldr-label" aria-hidden="true">ÖZ</span>
                <p><?= esc((string) $post['excerpt']) ?></p>
            </aside>
        <?php endif; ?>

        <p class="post-meta">
            <?php $au = (string) ($author['name'] ?? $post['author_name'] ?? ''); ?>
            <?php if ($au !== ''): ?>
                <span><?= esc($au) ?></span>
            <?php endif; ?>
            <?php if (!empty($post['reading_minutes'])): ?>
                <span class="post-meta-sep" aria-hidden="true">·</span>
                <span><?= (int) $post['reading_minutes'] ?> DK OKUMA</span>
            <?php endif; ?>
        </p>

        <?php if (!empty($post['cover_image'])): ?>
            <figure class="post-cover">
                <img src="<?= esc(url((string) $post['cover_image'])) ?>"
                     alt="<?= esc((string) ($post['title'] ?? '')) ?>" loading="eager">
            </figure>
        <?php endif; ?>
    </header>

    <div class="post-body">
        <?= $body_html ?? '' ?>
    </div>
</article>
