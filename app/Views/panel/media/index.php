<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Görsel Kütüphanesi</h1>
    <p class="lead muted">Tüm görseller bir merkezde. <strong><?= (int) $total ?></strong> görsel.</p>
    <?php require dirname(dirname(__DIR__)) . '/partials/flash.php'; ?>
    <p class="muted">Yeni görseller, içerik düzenleme ekranındaki <strong>📷 Resim ekle</strong> butonu veya
        sürükle-bırak ile yüklenir; otomatik WebP/AVIF varyantları üretilir.</p>
</section>

<?php if (!$items): ?>
    <p class="muted">Henüz görsel yüklenmemiş.</p>
<?php else: ?>
<div class="media-grid">
    <?php foreach ($items as $m):
        $thumb = $m['variants'][320]['webp'] ?? $m['path'];
    ?>
    <figure class="media-tile">
        <a href="<?= esc(url($m['path'])) ?>" target="_blank" rel="noopener">
            <img src="<?= esc(url($thumb)) ?>" alt="<?= esc((string) ($m['alt'] ?? '')) ?>"
                 loading="lazy" decoding="async">
        </a>
        <figcaption>
            <form method="post" action="<?= esc(url('/panel/medya/' . $m['id'])) ?>" class="media-edit">
                <?= csrf_field() ?>
                <input type="hidden" name="page" value="<?= (int) ($page ?? 1) ?>">
                <input type="text" name="title" value="<?= esc((string) $m['original_name']) ?>"
                       placeholder="Başlık" maxlength="255">
                <input type="text" name="alt" value="<?= esc((string) ($m['alt'] ?? '')) ?>"
                       placeholder="Alt metin (SEO)" maxlength="255">
                <p class="muted media-info">
                    <?= (int) $m['width'] ?>×<?= (int) $m['height'] ?>
                    · <strong><?= esc(strtoupper(pathinfo((string) $m['path'], PATHINFO_EXTENSION))) ?></strong>
                    · <?= esc(fmt_bytes((int) $m['bytes'])) ?>
                    <?php if (!empty($m['uploader_name'])): ?>· <?= esc($m['uploader_name']) ?><?php endif; ?>
                </p>
                <div class="media-actions">
                    <button class="btn" type="submit">Kaydet</button>
                    <button class="btn copy-path" type="button" data-path="<?= esc(url($m['path'])) ?>">Yolu Kopyala</button>
                </div>
            </form>
            <form method="post" action="<?= esc(url('/panel/medya/' . $m['id'] . '/sil')) ?>"
                  onsubmit="return confirm('Bu görseli ve tüm varyantlarını kalıcı sil?');">
                <?= csrf_field() ?>
                <input type="hidden" name="page" value="<?= (int) ($page ?? 1) ?>">
                <button class="btn btn-danger" type="submit">Sil</button>
            </form>
        </figcaption>
    </figure>
    <?php endforeach; ?>
</div>

<?php if ($pages > 1): ?>
<nav class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="<?= $i === $page ? 'active' : '' ?>"
           href="<?= esc(url('/panel/medya?page=' . $i)) ?>"><?= $i ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<script nonce="<?= esc(csp_nonce()) ?>">
document.querySelectorAll('.copy-path').forEach(function(b){
    b.addEventListener('click', function(){
        navigator.clipboard.writeText(b.dataset.path).then(function(){
            var orig = b.textContent;
            b.textContent = '✓ Kopyalandı';
            setTimeout(function(){ b.textContent = orig; }, 1200);
        });
    });
});
</script>
