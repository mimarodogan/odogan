<?php
/**
 * @var string $brevo_key
 * @var string $brevo_list_id
 * @var array  $brevo_status
 */
\App\Core\View::layout('base');
?>
<section class="hero">
    <h1>Newsletter Ayarları</h1>
    <p class="lead muted">Brevo (Sendinblue) API anahtarı + abone listesi.</p>
    <?php require dirname(__DIR__, 1) . '/../partials/flash.php'; ?>
</section>

<div class="grid">
    <article class="card">
        <h2>Brevo Durumu</h2>
        <ul style="font-size:.9rem;line-height:1.7">
            <li>SDK yüklü: <?= !empty($brevo_status['sdk_loaded']) ? '✓ Evet' : '✗ Hayır (composer install gerekli)' ?></li>
            <li>API key tanımlı: <?= !empty($brevo_status['key_set']) ? '✓ Evet' : '✗ Hayır' ?></li>
            <li>Bağlantı: <?= !empty($brevo_status['ok']) ? '✓ Başarılı' : '✗ ' . esc((string) ($brevo_status['error'] ?? '?')) ?></li>
        </ul>
        <p class="muted" style="font-size:.85rem;margin-top:1rem">
            <a href="https://app.brevo.com/settings/keys/api" target="_blank" rel="noopener" title="Brevo API anahtarları sayfası">
                Brevo dashboard → Settings → API keys
            </a>
        </p>
    </article>

    <article class="card">
        <h2>Ayarlar</h2>
        <form method="post" action="<?= esc(url('/admin/newsletter/ayarlar')) ?>" class="form form-wide">
            <?= csrf_field() ?>
            <label>
                <span>Brevo API Key</span>
                <input type="text" name="brevo_key" maxlength="200"
                       value="<?= esc($brevo_key) ?>"
                       placeholder="xkeysib-..."
                       autocomplete="off">
                <small class="muted">v3 API anahtarı. Brevo dashboard → Settings → API keys.</small>
            </label>
            <label>
                <span>Liste ID (opsiyonel)</span>
                <input type="number" name="brevo_list_id" min="0"
                       value="<?= esc($brevo_list_id) ?>"
                       placeholder="örn. 2">
                <small class="muted">Boş bırakılırsa kontak listeye eklenmez, sadece Brevo'da kayıt olur.</small>
            </label>
            <button class="btn btn-primary" type="submit">Kaydet</button>
        </form>
    </article>
</div>
