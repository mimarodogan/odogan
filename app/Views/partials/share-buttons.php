<?php
/**
 * Yazı paylaşım butonları — yazı altında, sticky desktop varyantı için
 * `vertical` flag.
 *
 * @var array  $post       ['title' bekleniyor]
 * @var string $url        Yazının absolute URL'si
 * @var bool   $vertical   Sticky kutuda kullanılırsa true
 */
$shareTitle = (string) ($post['title'] ?? '');
$shareUrl   = (string) ($url ?? '');
$enc = static fn(string $s): string => rawurlencode($s);

$twitter   = 'https://twitter.com/intent/tweet?url=' . $enc($shareUrl) . '&text=' . $enc($shareTitle);
$linkedin  = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $enc($shareUrl);
$whatsapp  = 'https://wa.me/?text=' . $enc($shareTitle . ' ' . $shareUrl);
$mailto    = 'mailto:?subject=' . $enc($shareTitle) . '&body=' . $enc($shareTitle . "\n\n" . $shareUrl);
$facebook  = 'https://www.facebook.com/sharer/sharer.php?u=' . $enc($shareUrl);
$_postId = (int) ($post['id'] ?? 0);
$_postCover = (string) ($post['cover_image'] ?? '');
$_postCat = (string) ($post['category_slug'] ?? '');
$_postSlug = (string) ($post['slug'] ?? '');
$_postExcerpt = mb_substr((string) ($post['excerpt'] ?? ''), 0, 200);
$_saveEnabled = function_exists('feature') && feature('save_post_enabled') && $_postId > 0;
?>
<aside class="share-buttons" aria-label="Bu yazıyı paylaş"
       data-share-url="<?= esc($shareUrl) ?>" data-share-title="<?= esc($shareTitle) ?>">
    <span class="share-label">Paylaş:</span>
    <a href="<?= esc($twitter) ?>" class="share-btn share-twitter"
       title="Twitter/X üzerinde paylaş" target="_blank" rel="noopener nofollow"
       data-share="twitter">
        <span aria-hidden="true">𝕏</span><span class="visually-hidden">Twitter / X</span>
    </a>
    <a href="<?= esc($linkedin) ?>" class="share-btn share-linkedin"
       title="LinkedIn üzerinde paylaş" target="_blank" rel="noopener nofollow"
       data-share="linkedin">
        <span aria-hidden="true">in</span><span class="visually-hidden">LinkedIn</span>
    </a>
    <a href="<?= esc($facebook) ?>" class="share-btn share-facebook"
       title="Facebook üzerinde paylaş" target="_blank" rel="noopener nofollow"
       data-share="facebook">
        <span aria-hidden="true">f</span><span class="visually-hidden">Facebook</span>
    </a>
    <a href="<?= esc($whatsapp) ?>" class="share-btn share-whatsapp"
       title="WhatsApp üzerinde paylaş" target="_blank" rel="noopener nofollow"
       data-share="whatsapp">
        <span aria-hidden="true">✆</span><span class="visually-hidden">WhatsApp</span>
    </a>
    <a href="<?= esc($mailto) ?>" class="share-btn share-mail"
       title="E-posta olarak gönder"
       data-share="mail">
        <span aria-hidden="true">✉</span><span class="visually-hidden">E-posta</span>
    </a>
    <button type="button" class="share-btn share-copy"
            title="Linki panoya kopyala" data-share="copy">
        <span aria-hidden="true">⎘</span><span class="visually-hidden">Linki kopyala</span>
    </button>
    <?php if ($_saveEnabled): ?>
    <button type="button" class="share-btn share-save"
            title="Bu yazıyı kaydet"
            data-save-post="<?= $_postId ?>"
            data-save-title="<?= esc($shareTitle) ?>"
            data-save-url="<?= esc(url('/' . $_postCat . '/' . $_postSlug)) ?>"
            data-save-cover="<?= esc($_postCover) ?>"
            data-save-excerpt="<?= esc($_postExcerpt) ?>">
        <span aria-hidden="true" class="save-icon">♡</span><span class="visually-hidden">Yazıyı kaydet</span>
    </button>
    <?php endif; ?>
</aside>
