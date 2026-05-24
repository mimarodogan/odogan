<?php
/**
 * @var array<int,array{name:string,size:int,mtime:int,type:string}> $files
 * @var string $dir
 * @var bool $writable
 * @var string $php_bin
 */
\App\Core\View::layout('base');

$humanSize = static function (int $b): string {
    if ($b < 1024) return $b . ' B';
    if ($b < 1024 * 1024) return number_format($b / 1024, 1) . ' KB';
    if ($b < 1024 * 1024 * 1024) return number_format($b / (1024 * 1024), 1) . ' MB';
    return number_format($b / (1024 * 1024 * 1024), 2) . ' GB';
};
?>
<section class="hero">
    <h1>Yedekler</h1>
    <p class="lead muted">Otomatik gece yedekleri + manuel "Şimdi Yedekle" tetiği. 30 günlük retention.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<div class="grid">
    <article class="card">
        <h2>⚡ Şimdi Yedekle</h2>
        <p class="muted">Aşağıdaki butonlar <code>bin/backup-*.php</code> scriptlerini elden çalıştırır.</p>

        <?php if (!$writable): ?>
            <div class="flash flash-error">
                Yedek dizini yazılabilir değil: <code><?= esc($dir) ?></code>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= esc(url('/admin/bakim/yedekler/db')) ?>" style="margin-top:1rem">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit"<?= $writable ? '' : ' disabled' ?>>
                Veritabanı Yedeği Al
            </button>
        </form>

        <form method="post" action="<?= esc(url('/admin/bakim/yedekler/uploads')) ?>" style="margin-top:.75rem">
            <?= csrf_field() ?>
            <button class="btn" type="submit"<?= $writable ? '' : ' disabled' ?>>
                Uploads Yedeği Al
            </button>
        </form>
    </article>

    <article class="card">
        <h2>⏰ Otomatik Çalıştırma</h2>
        <p class="muted">cPanel → Cron Jobs altına aşağıdaki iki satırı ekleyin:</p>
        <pre class="log-ctx" style="user-select:all"><code>0 3 * * * <?= esc($php_bin) ?> <?= esc(\App\Core\Config::root()) ?>/bin/backup-db.php
0 4 * * 0 <?= esc($php_bin) ?> <?= esc(\App\Core\Config::root()) ?>/bin/backup-uploads.php</code></pre>
        <p class="muted" style="font-size:.85rem;margin-top:.5rem">
            DB: her gece 03:00. Uploads: haftalık (Pazar 04:00). Retention: 30 gün.
        </p>
    </article>
</div>

<section style="margin-top:2rem">
    <h2>Mevcut Yedekler (<?= count($files) ?>)</h2>
    <?php if (!$files): ?>
        <p class="muted">Henüz yedek yok. Yukarıdaki butonlardan birini kullanın.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="visually-hidden">Yedek dosyaları — <?= count($files) ?> kayıt</caption>
                <thead>
                    <tr>
                        <th scope="col">Dosya</th>
                        <th scope="col">Tip</th>
                        <th scope="col">Boyut</th>
                        <th scope="col">Tarih</th>
                        <th scope="col">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $f): ?>
                        <tr>
                            <td><code><?= esc($f['name']) ?></code></td>
                            <td>
                                <?php if ($f['type'] === 'db'): ?>
                                    <span class="badge badge-cobalt">DB</span>
                                <?php else: ?>
                                    <span class="badge">Uploads</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($humanSize($f['size'])) ?></td>
                            <td><?= esc(date('d/m/Y H:i', $f['mtime'])) ?></td>
                            <td style="display:flex;gap:.5rem;align-items:center">
                                <a class="btn btn-sm"
                                   href="<?= esc(url('/admin/bakim/yedekler/indir/' . $f['name'])) ?>"
                                   title="İndir">
                                   ⤓ İndir
                                </a>
                                <form method="post"
                                      action="<?= esc(url('/admin/bakim/yedekler/sil/' . $f['name'])) ?>"
                                      onsubmit="return confirm('<?= esc($f['name']) ?> kalıcı olarak silinsin mi?');"
                                      style="margin:0">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-danger" type="submit" title="Sil">✕</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<p class="muted" style="margin-top:2rem;font-size:.85rem">
    Yedekler <code><?= esc($dir) ?></code> altında tutulur ve doğrudan HTTP erişimi <code>.htaccess</code> ile engellenir.
    Sadece admin paneli üzerinden indirilebilir.
</p>
