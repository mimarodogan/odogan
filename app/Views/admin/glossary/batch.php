<?php
/**
 * Sözlük toplu AI üretim sayfası.
 *
 * @var array $queue   son 100 kuyruk satırı
 * @var array $counts  ['pending'=>n,'processing'=>n,'done'=>n,'error'=>n,'skipped'=>n]
 */
\App\Core\View::layout('base');
?>

<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/admin/sozluk')) ?>" class="muted">← Sözlük Listesi</a>
        </p>
        <h1>Toplu AI Üretim</h1>
        <p class="post-editor-meta">
            <span class="badge"><?= (int) $counts['pending'] ?> bekliyor</span>
            <span class="badge badge-published"><?= (int) $counts['done'] ?> tamam</span>
            <?php if ((int) $counts['error'] > 0): ?>
                <span class="badge badge-draft"><?= (int) $counts['error'] ?> hata</span>
            <?php endif; ?>
        </p>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<section class="pe-section" style="max-width:880px;margin:1.5rem auto">
    <h2 class="pe-section-title">Yeni Terimler Kuyruğa Ekle</h2>
    <p class="pe-section-hint">
        Her satıra bir terim yaz. Yorum satırı (<code>#</code>) atlanır.
        Eklendikten sonra "Sonraki işle" tuşuyla tek tek üretirsin —
        her üretim 30-90 saniye sürer ve <strong>taslak (Aktif değil)</strong>
        olarak kaydedilir. İncele, gerekirse düzenle, sonra Aktif et.
    </p>
    <form method="post" action="<?= esc(url('/admin/sozluk/toplu')) ?>" class="post-editor">
        <?= csrf_field() ?>
        <label>
            <span>Terim Listesi</span>
            <textarea name="terms" rows="10" required minlength="2"
                      placeholder="konsol kiriş&#10;modülasyon&#10;tektonik&#10;biyofilik tasarım&#10;# bu satır atlanır&#10;panoptikon"></textarea>
        </label>
        <fieldset class="glossary-ai-depth" aria-label="Derinlik" style="margin:1rem 0">
            <legend>Derinlik</legend>
            <label><input type="radio" name="depth" value="kisa"> Kısa</label>
            <label><input type="radio" name="depth" value="orta" checked> Orta</label>
            <label><input type="radio" name="depth" value="derin"> Derin</label>
        </fieldset>
        <div class="pe-actions">
            <button type="submit" class="btn btn-primary">Kuyruğa Ekle</button>
        </div>
    </form>
</section>

<?php if ((int) $counts['pending'] > 0): ?>
<section class="pe-section" style="max-width:880px;margin:1.5rem auto;background:rgba(31,58,138,.04);border-left:3px solid var(--cobalt);padding:1rem 1.2rem">
    <p style="margin:0 0 .7rem"><strong><?= (int) $counts['pending'] ?> terim bekliyor.</strong> Her üretim 30-90 sn sürer.</p>
    <form method="post" action="<?= esc(url('/admin/sozluk/toplu/isle')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">Sonrakini İşle →</button>
    </form>
</section>
<?php endif; ?>

<section class="pe-section" style="max-width:880px;margin:1.5rem auto">
    <h2 class="pe-section-title">Kuyruk Durumu</h2>
    <?php if (empty($queue)): ?>
        <p class="muted">Henüz kuyruk yok. Yukarıdan terim ekle.</p>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:.92rem">
            <thead>
                <tr style="border-bottom:1px solid var(--hair);text-align:left">
                    <th style="padding:.5rem">#</th>
                    <th style="padding:.5rem">Terim</th>
                    <th style="padding:.5rem">Derinlik</th>
                    <th style="padding:.5rem">Durum</th>
                    <th style="padding:.5rem">İşlendi</th>
                    <th style="padding:.5rem">Hata / Sonuç</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($queue as $q): ?>
                <tr style="border-bottom:1px solid var(--hair)">
                    <td style="padding:.5rem;font-family:var(--mono);font-size:.8rem">#<?= (int) $q['id'] ?></td>
                    <td style="padding:.5rem"><?= esc($q['term']) ?></td>
                    <td style="padding:.5rem;font-family:var(--mono);font-size:.8rem"><?= esc($q['depth']) ?></td>
                    <td style="padding:.5rem">
                        <?php
                        $statusBadges = [
                            'pending'    => 'badge',
                            'processing' => 'badge badge-scheduled',
                            'done'       => 'badge badge-published',
                            'error'      => 'badge badge-draft',
                            'skipped'    => 'badge',
                        ];
                        $cls = $statusBadges[$q['status']] ?? 'badge';
                        ?>
                        <span class="<?= esc($cls) ?>"><?= esc($q['status']) ?></span>
                    </td>
                    <td style="padding:.5rem;font-family:var(--mono);font-size:.78rem">
                        <?= !empty($q['processed_at']) ? esc(date('d.m H:i', strtotime((string) $q['processed_at']))) : '—' ?>
                    </td>
                    <td style="padding:.5rem;font-size:.85rem">
                        <?php if ($q['status'] === 'done' && !empty($q['created_glossary_id'])): ?>
                            <a href="<?= esc(url('/admin/sozluk/' . (int) $q['created_glossary_id'] . '/duzenle')) ?>">→ İncele</a>
                        <?php elseif ($q['status'] === 'error' && !empty($q['error_message'])): ?>
                            <span class="muted" style="color:#B0241D"><?= esc(mb_substr((string) $q['error_message'], 0, 100)) ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
