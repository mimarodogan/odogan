<?php \App\Core\View::layout('base'); ?>
<?php $_status = (string) ($applicant['author_application_status'] ?? 'none'); ?>
<section class="hero">
    <p class="muted"><a href="<?= esc(url('/admin/yazar-basvurulari')) ?>">← Tüm Başvurular</a></p>
    <h1>Başvuru: <?= esc($applicant['name']) ?></h1>
    <p class="lead">
        <?= esc($applicant['email']) ?>
        ·
        <?php if (!empty($applicant['author_application_at'])): ?>
            <?= esc(tr_date($applicant['author_application_at'], true)) ?>
        <?php endif; ?>
        · Durum: <strong><?= esc($_status) ?></strong>
    </p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<article class="aa-detail">
    <section class="aa-detail-block">
        <h2>Tanıtım & Biyografi</h2>
        <?php if (!empty($data['headline'])): ?>
            <p><strong><?= esc($data['headline']) ?></strong></p>
        <?php endif; ?>
        <?php if (!empty($data['bio'])): ?>
            <p style="white-space:pre-wrap"><?= esc($data['bio']) ?></p>
        <?php else: ?>
            <p class="muted">Biyografi yok.</p>
        <?php endif; ?>
    </section>

    <section class="aa-detail-block">
        <h2>Uzmanlık</h2>
        <p><?= esc((string) ($data['expertise'] ?? '—')) ?></p>
    </section>

    <section class="aa-detail-block">
        <h2>Motivasyon</h2>
        <?php if (!empty($data['motivation'])): ?>
            <p style="white-space:pre-wrap"><?= esc($data['motivation']) ?></p>
        <?php else: ?>
            <p class="muted">Motivasyon yok.</p>
        <?php endif; ?>
    </section>

    <section class="aa-detail-block">
        <h2>Örnek Yazı</h2>
        <?php if (!empty($data['sample_url'])): ?>
            <p><a href="<?= esc($data['sample_url']) ?>" target="_blank" rel="noopener noreferrer">
                <?= esc($data['sample_url']) ?> ↗
            </a></p>
        <?php endif; ?>
        <?php if (!empty($data['sample_text'])): ?>
            <details>
                <summary>Yapıştırılmış metni göster (<?= mb_strlen((string) $data['sample_text']) ?> karakter)</summary>
                <pre style="white-space:pre-wrap;background:var(--bone-2);padding:1.25rem;margin-top:.75rem;font-size:.95rem;font-family:var(--serif);line-height:1.6;color:var(--soot-2);border-left:2px solid var(--cobalt)"><?= esc($data['sample_text']) ?></pre>
            </details>
        <?php endif; ?>
        <?php if (empty($data['sample_url']) && empty($data['sample_text'])): ?>
            <p class="muted">Örnek paylaşılmamış.</p>
        <?php endif; ?>
    </section>

    <?php if ($_status === 'pending'): ?>
    <section class="aa-detail-block aa-actions-block" style="border-top:2px solid var(--cobalt,#1e3a8a);padding-top:1.5rem">
        <h2>Karar</h2>
        <form method="post"
              action="<?= esc(url('/admin/yazar-basvurulari/' . (int) $applicant['id'] . '/onayla')) ?>"
              style="display:inline-block;margin-right:.5rem"
              onsubmit="return confirm('Bu başvuruyu onayla? Kullanıcının role\'ü AUTHOR olacak.');">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">✓ Onayla &amp; Yazar Yap</button>
        </form>

        <form method="post"
              action="<?= esc(url('/admin/yazar-basvurulari/' . (int) $applicant['id'] . '/reddet')) ?>"
              style="display:inline-block;margin-top:1rem;width:100%"
              onsubmit="return confirm('Bu başvuruyu reddet?');">
            <?= csrf_field() ?>
            <label for="reject-reason" style="display:block;margin-top:1rem">
                <span style="font-weight:600">Ret nedeni (opsiyonel — mail'e dahil edilir)</span>
                <textarea id="reject-reason" name="reason" rows="3" maxlength="1000"
                          placeholder="Örn: Örnek yazı hedef konuyla uyuşmuyor."></textarea>
            </label>
            <button class="btn" type="submit">✗ Reddet</button>
        </form>
    </section>
    <?php endif; ?>
</article>
