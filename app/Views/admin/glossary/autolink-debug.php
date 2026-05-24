<?php
/**
 * AutoLink debug — bir sözlük girdisi için aday skorları.
 *
 * @var array $item
 * @var array $debug ['total_candidates','matched'=>[...],'picked_keys'=>[...]]
 */
\App\Core\View::layout('base');
?>

<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/admin/sozluk/' . (int) $item['id'] . '/duzenle')) ?>" class="muted">
                ← Terim Düzenle
            </a>
        </p>
        <h1>AutoLink Debug — <?= esc($item['term']) ?></h1>
        <p class="post-editor-meta">
            <span class="badge"><?= (int) $debug['total_candidates'] ?> toplam aday</span>
            <span class="badge badge-published"><?= count($debug['matched']) ?> eşleşen</span>
            <span class="badge badge-scheduled"><?= count($debug['picked_keys']) ?> seçilen</span>
        </p>
    </div>
</section>

<section class="pe-section" style="max-width:1000px;margin:1.5rem auto">
    <h2 class="pe-section-title">Skor Tablosu</h2>
    <p class="pe-section-hint">
        AutoLinkService bu sözlük girdisinin body HTML'inde aday terimleri arıyor.
        <strong>skor = frekans × bağlam çarpanı × tazelik</strong>.
        En yüksek 2 aday public sayfada link olarak yer alır.
        Yeşil çerçeve = seçildi.
    </p>

    <?php if (empty($debug['matched'])): ?>
        <p class="muted">Bu girdide hiçbir adayla eşleşme bulunamadı.</p>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:.92rem">
            <thead>
                <tr style="border-bottom:1px solid var(--hair);text-align:left">
                    <th style="padding:.5rem">#</th>
                    <th style="padding:.5rem">Tip</th>
                    <th style="padding:.5rem">Aday</th>
                    <th style="padding:.5rem;text-align:right">Frekans</th>
                    <th style="padding:.5rem;text-align:right">Çarpan</th>
                    <th style="padding:.5rem;text-align:right">Tazelik</th>
                    <th style="padding:.5rem;text-align:right">Skor</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($debug['matched'] as $i => $m):
                $isPicked = in_array($m['key'], $debug['picked_keys'], true);
            ?>
                <tr style="border-bottom:1px solid var(--hair); <?= $isPicked ? 'background:rgba(47,106,62,.06)' : '' ?>">
                    <td style="padding:.5rem;font-family:var(--mono);font-size:.78rem">
                        <?= $isPicked ? '✓' : ($i + 1) ?>
                    </td>
                    <td style="padding:.5rem">
                        <span class="badge" style="font-size:.65rem"><?= esc($m['type']) ?></span>
                    </td>
                    <td style="padding:.5rem">
                        <a href="<?= esc($m['href']) ?>" target="_blank" rel="noopener">
                            <?= esc($m['label']) ?>
                        </a>
                    </td>
                    <td style="padding:.5rem;text-align:right;font-family:var(--mono)"><?= (int) $m['hits'] ?></td>
                    <td style="padding:.5rem;text-align:right;font-family:var(--mono)"><?= number_format((float) $m['multiplier'], 2) ?></td>
                    <td style="padding:.5rem;text-align:right;font-family:var(--mono)"><?= number_format((float) $m['recency'], 2) ?></td>
                    <td style="padding:.5rem;text-align:right;font-family:var(--mono);font-weight:600"><?= number_format((float) $m['score'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="muted" style="margin-top:1.5rem;font-size:.85rem">
        <strong>Açıklama:</strong> Frekans = body içinde aday kelimenin/aliasın geçiş sayısı (Türkçe ek toleranslı).
        Çarpan: aynı kategori varsa <code>1.4</code>, başka durumlarda <code>1.0-1.1</code>.
        Tazelik: post için 1 yıl içinde 1.0 → 0.1 arası, sözlük için her zaman 1.0.
        Skoru en yüksek 2 aday seçilir.
    </p>
</section>
