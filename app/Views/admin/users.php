<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Kullanıcı Yönetimi</h1>
    <p class="lead muted">Toplam <strong><?= count($users) ?></strong> kullanıcı.</p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<?php foreach ($users as $u): ?>
    <form id="u-up-<?= (int) $u['id'] ?>" method="post"
          action="<?= esc(url('/admin/kullanicilar/' . $u['id'])) ?>" hidden>
        <?= csrf_field() ?>
    </form>
    <form id="u-del-<?= (int) $u['id'] ?>" method="post"
          action="<?= esc(url('/admin/kullanicilar/' . $u['id'] . '/sil')) ?>" hidden
          onsubmit="return confirm('<?= esc($u['name']) ?> hesabı KAPATILACAK — yazıları korunur, kullanıcı tekrar giriş yapamaz. Devam?');">
        <?= csrf_field() ?>
    </form>
<?php endforeach; ?>

<table class="table">
    <caption class="visually-hidden">Kullanıcılar listesi — <?= count($users) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Ad</th><th scope="col">E-posta</th><th scope="col">Rol</th><th scope="col">Durum</th><th scope="col">Doğrulanmış</th><th scope="col">Son Giriş</th><th scope="col">Aksiyon</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): $f = 'u-up-' . (int) $u['id']; ?>
        <tr>
            <td><strong><?= esc($u['name']) ?></strong>
                <br><small class="muted"><a href="<?= esc(url('/yazar/' . $u['slug'])) ?>" target="_blank"><?= esc($u['slug']) ?></a></small>
            </td>
            <td class="muted"><?= esc($u['email']) ?></td>
            <td>
                <select form="<?= esc($f) ?>" name="role">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= esc($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>>
                            <?= esc(ucfirst($r)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select form="<?= esc($f) ?>" name="status">
                    <?php foreach (['active','pending','banned'] as $s): ?>
                        <option value="<?= $s ?>" <?= $u['status'] === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><?= $u['email_verified'] ? '✓' : '<span class="muted">—</span>' ?></td>
            <td class="muted nowrap">
                <?= $u['last_login_at'] ? esc(substr((string) $u['last_login_at'], 0, 16)) : '—' ?>
            </td>
            <td>
                <button class="btn" form="<?= esc($f) ?>" type="submit">Kaydet</button>
                <?php if (empty($u['deleted_at'])): ?>
                    <button class="btn btn-danger" form="u-del-<?= (int) $u['id'] ?>" type="submit" title="Hesabı kapat — yazıları korunur">Kapat</button>
                <?php else: ?>
                    <span class="badge badge-archived" title="<?= esc((string) $u['deleted_at']) ?>">Kapalı</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
