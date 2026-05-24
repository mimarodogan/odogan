<?php
/**
 * @var array<int,array{name:string,applied:bool,executed_at:?string,batch:?int,size:int}> $migrations
 * @var int $applied_count
 * @var int $pending_count
 * @var int $last_batch
 * @var array|null $last_results
 */
\App\Core\View::layout('base');

$humanSize = static function (int $b): string {
    if ($b < 1024) return $b . ' B';
    if ($b < 1024 * 1024) return number_format($b / 1024, 1) . ' KB';
    return number_format($b / (1024 * 1024), 1) . ' MB';
};
?>
<section class="hero">
    <h1>Migrasyonlar</h1>
    <p class="lead muted">
        <code>database/migrations/</code> altındaki <strong><?= count($migrations) ?></strong> dosya.
        <span class="badge badge-published"><?= (int) $applied_count ?> uygulanmış</span>
        <span class="badge badge-pending"><?= (int) $pending_count ?> bekliyor</span>
        <?php if ($last_batch > 0): ?>
            <span class="muted">· son batch: #<?= (int) $last_batch ?></span>
        <?php endif; ?>
    </p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<div class="grid">
    <article class="card">
        <h2>⚡ Çalıştır</h2>
        <p class="muted">
            Bekleyen tüm migration'ları sırayla uygula.
        </p>
        <form method="post" action="<?= esc(url('/admin/bakim/migrasyonlar/calistir')) ?>"
              onsubmit="return confirm('Bekleyen <?= (int) $pending_count ?> migration uygulansın mı? Bu işlem geri alınamaz (DDL).');">
            <?= csrf_field() ?>

            <label style="display:flex;align-items:flex-start;gap:.6rem;margin:.75rem 0 1.25rem;cursor:pointer;font-family:var(--sans);font-size:.92rem">
                <input type="checkbox" name="smart_mode" value="1" checked
                       style="margin-top:.25rem;width:18px;height:18px;cursor:pointer">
                <span>
                    <strong>🧠 Akıllı Mod</strong> (önerilen)
                    <br>
                    <span class="muted" style="font-size:.82rem">
                        "Zaten var" hataları (Duplicate column/Table exists)
                        otomatik atlanır → migration <em>uygulanmış</em> işaretlenir,
                        zincir devam eder. Bu sayede daha önce manuel uygulanmış
                        şemalar sorun çıkarmaz.
                    </span>
                </span>
            </label>

            <button class="btn btn-primary" type="submit"<?= $pending_count === 0 ? ' disabled' : '' ?>>
                <?= (int) $pending_count ?> bekleyen migration uygula
            </button>
        </form>
    </article>

    <article class="card">
        <h2>ℹ Hakkında</h2>
        <ul style="font-size:.9rem;line-height:1.7;list-style:disc;padding-left:1.2rem">
            <li><code>migrations</code> tablosu kayıt tutar (dosya adı + batch).</li>
            <li>DDL statement'lar transaction'a alınamaz — bir statement fail ederse oraya kadar geçenler kalır.</li>
            <li>Migration dosyaları <code>IF NOT EXISTS</code> + <code>ADD COLUMN</code> gibi idempotent yapılar kullanır.</li>
            <li>Manuel olarak tek migration çalıştırmak için "Çalıştır" butonunu kullanın.</li>
        </ul>
    </article>
</div>

<?php if ($last_results): ?>
<section style="margin-top:2rem">
    <h2>Son Çalıştırma Raporu</h2>
    <p class="muted">
        Batch #<?= (int) $last_results['batch'] ?> · Toplam <?= (int) $last_results['total'] ?>
        · <strong style="color:#2f6a3e"><?= (int) $last_results['ok'] ?> başarılı</strong>
        <?php if (!empty($last_results['auto_skipped'])): ?>
            · <strong style="color:#8C6A12"><?= (int) $last_results['auto_skipped'] ?> akıllı atlama</strong>
        <?php endif; ?>
        <?php if ($last_results['failed']): ?>
            · <strong style="color:#b0241d"><?= (int) $last_results['failed'] ?> başarısız</strong>
        <?php endif; ?>
        <?php if (!empty($last_results['smart_mode'])): ?>
            <span class="badge badge-scheduled" style="margin-left:.5rem">🧠 Akıllı Mod</span>
        <?php endif; ?>
    </p>
    <div class="table-wrap">
        <table class="table">
            <caption class="visually-hidden">Son migration çalışma sonuçları — <?= count($last_results['results']) ?> kayıt</caption>
            <thead>
                <tr>
                    <th scope="col">Dosya</th>
                    <th scope="col">Durum</th>
                    <th scope="col">Statement</th>
                    <th scope="col">Süre</th>
                    <th scope="col">Detay</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($last_results['results'] as $r): ?>
                    <tr class="<?= ($r['ok'] ?? false) ? (!empty($r['auto_skipped']) ? 'mig-skip' : 'mig-ok') : 'mig-fail' ?>">
                        <td><code><?= esc($r['name']) ?></code></td>
                        <td>
                            <?php if (!empty($r['auto_skipped'])): ?>
                                <span class="badge" style="color:#8C6A12;border-color:#8C6A12" title="Zaten DB'de var, kayıt atıldı">🧠 Atlandı</span>
                            <?php elseif ($r['ok'] ?? false): ?>
                                <span class="badge badge-published">✓ OK</span>
                            <?php else: ?>
                                <span class="badge badge-rejected">✗ Hata</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) ($r['statements_run'] ?? 0) ?></td>
                        <td><?= (int) ($r['duration_ms'] ?? 0) ?> ms</td>
                        <td>
                            <?php if (!empty($r['error'])): ?>
                                <details open>
                                    <summary style="cursor:pointer;color:#b0241d">Hata detayı</summary>
                                    <pre style="white-space:pre-wrap;background:#fff5f5;padding:.75rem;border-radius:.25rem;font-size:.85rem;margin-top:.5rem"><?= esc((string) $r['error']) ?></pre>

                                    <?php if (\App\Services\MigrationRunner::isAlreadyExistsError((string) $r['error'])): ?>
                                        <div style="background:#fff7cc;border-left:3px solid #8C6A12;padding:.75rem 1rem;margin-top:.75rem;font-size:.85rem">
                                            <strong>💡 Olası neden:</strong> Bu MySQL hata kodu (Duplicate column / Table exists vb.)
                                            genellikle dosyadaki şemanın zaten DB'de bulunduğunu gösterir.
                                            Migration daha önce manuel uygulanmış olabilir.
                                            <br><br>
                                            <strong>Çözüm:</strong> Aşağıdaki "Uygulanmış İşaretle" butonuyla bu dosyayı
                                            çalıştırmadan kayıt altına alabilirsin (dosya tekrar denenmez).
                                            <form method="post"
                                                  action="<?= esc(url('/admin/bakim/migrasyonlar/uygulandi-isaretle/' . $r['name'])) ?>"
                                                  style="margin-top:.75rem"
                                                  onsubmit="return confirm('<?= esc($r['name']) ?> dosyası ÇALIŞTIRILMADAN uygulanmış işaretlenecek. Emin misin?');">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm" type="submit"
                                                        style="background:#8C6A12;color:#fff;border-color:#8C6A12">
                                                    ✓ Uygulanmış İşaretle
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($r['error_sql'])): ?>
                                        <p style="margin-top:.75rem;font-size:.85rem"><strong>Sorunlu SQL:</strong></p>
                                        <pre style="white-space:pre-wrap;background:#f3f1ec;padding:.75rem;border-radius:.25rem;font-size:.8rem;max-height:200px;overflow:auto"><?= esc((string) $r['error_sql']) ?></pre>
                                    <?php endif; ?>
                                </details>
                            <?php elseif (!empty($r['ok'])): ?>
                                <span class="muted" style="font-size:.85rem">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section style="margin-top:2rem">
    <h2>Tüm Migration Dosyaları (<?= count($migrations) ?>)</h2>
    <div class="table-wrap">
        <table class="table">
            <caption class="visually-hidden">Tüm migration dosyaları — <?= count($migrations) ?> kayıt</caption>
            <thead>
                <tr>
                    <th scope="col">Dosya</th>
                    <th scope="col">Durum</th>
                    <th scope="col">Batch</th>
                    <th scope="col">Uygulanma</th>
                    <th scope="col">Boyut</th>
                    <th scope="col">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($migrations as $m): ?>
                    <tr class="<?= $m['applied'] ? 'mig-applied' : 'mig-pending' ?>">
                        <td><code><?= esc($m['name']) ?></code></td>
                        <td>
                            <?php if ($m['applied']): ?>
                                <span class="badge badge-published">✓ Uygulanmış</span>
                            <?php else: ?>
                                <span class="badge badge-pending">⏳ Bekliyor</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $m['batch'] !== null ? '#' . (int) $m['batch'] : '—' ?></td>
                        <td>
                            <?php if ($m['executed_at']): ?>
                                <?= esc(date('d/m/Y H:i', strtotime((string) $m['executed_at']))) ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($humanSize($m['size'])) ?></td>
                        <td>
                            <?php if (!$m['applied']): ?>
                                <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                                    <form method="post"
                                          action="<?= esc(url('/admin/bakim/migrasyonlar/calistir/' . $m['name'])) ?>"
                                          onsubmit="return confirm('<?= esc($m['name']) ?> çalıştırılsın mı?');"
                                          style="margin:0">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-primary" type="submit"
                                                title="Bu migration'ı çalıştır">
                                            ▶ Çalıştır
                                        </button>
                                    </form>
                                    <form method="post"
                                          action="<?= esc(url('/admin/bakim/migrasyonlar/uygulandi-isaretle/' . $m['name'])) ?>"
                                          onsubmit="return confirm('<?= esc($m['name']) ?> ÇALIŞTIRILMADAN uygulanmış işaretlenecek. Bu yalnızca dosyadaki şema DB''de zaten varsa kullanılır. Emin misin?');"
                                          style="margin:0">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-ghost" type="submit"
                                                title="Çalıştırmadan 'uygulanmış' olarak işaretle (zaten DB'de varsa)">
                                            ✓ İşaretle
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <form method="post"
                                      action="<?= esc(url('/admin/bakim/migrasyonlar/isareti-kaldir/' . $m['name'])) ?>"
                                      onsubmit="return confirm('<?= esc($m['name']) ?> kaydı silinecek. Dosyayı tekrar çalıştırabileceksin (ama DB''de değişiklik olmayacak). Emin misin?');"
                                      style="margin:0">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-ghost" type="submit"
                                            title="Uygulanmış kaydını sil (dosya yeniden çalıştırılabilir hale gelir)"
                                            style="opacity:.5">
                                        ↶ İşareti Kaldır
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
.mig-applied { opacity: .7; }
.mig-pending td:first-child { font-weight: 600; }
.mig-fail td { background: #fff5f5; }
.mig-ok td { background: #f5fff8; }
.mig-skip td { background: #fff7cc; }
</style>
