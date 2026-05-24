<?php \App\Core\View::layout('base'); ?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<section class="glossary-page" style="max-width:880px;margin:2rem auto 5rem;padding:0 var(--gutter)">
    <header style="margin-bottom:2.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--soot)">
        <p style="font-family:var(--mono);font-size:.66rem;letter-spacing:var(--tracked-l);text-transform:uppercase;color:var(--ash);margin:0 0 .75rem;font-weight:700">SÖZLÜK ARAMA</p>
        <h1 style="font-family:var(--serif);font-size:2rem;margin:0;letter-spacing:-.02em">"<?= esc($query) ?>" için sonuçlar</h1>
        <p style="font-family:var(--serif);font-style:italic;color:var(--soot-2);margin:.5rem 0 0"><?= count($results) ?> eşleşme</p>
    </header>

    <?php if (empty($results)): ?>
        <p class="muted" style="text-align:center;padding:3rem 0">Eşleşme bulunamadı. <a href="<?= esc(url('/sozluk')) ?>" style="color:var(--cobalt)">Tüm sözlüğe dön →</a></p>
    <?php else: ?>
        <dl class="glossary-list" style="margin:0;padding:0">
            <?php foreach ($results as $g): ?>
                <dt style="font-family:var(--serif);font-size:1.2rem;font-weight:600;margin:1.5rem 0 .4rem">
                    <a href="<?= esc(url('/sozluk/' . $g['slug'])) ?>" style="color:var(--soot);text-decoration:none"><?= esc($g['term']) ?></a>
                </dt>
                <dd style="font-family:var(--serif);font-size:.98rem;line-height:1.55;color:var(--soot-2);margin:0"><?= mb_substr(strip_tags((string) $g['definition']), 0, 240) ?>…</dd>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>
</section>
