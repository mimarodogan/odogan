<?php \App\Core\View::layout('base'); ?>
<?php
$activePreset = (string) ($values['preset'] ?? 'custom');
$adminUser = $user ?? null;
?>

<section class="hero">
    <h1>E-posta (SMTP) Ayarları</h1>
    <p class="lead">
        Gmail, Outlook/Hotmail veya başka bir SMTP sağlayıcı seç; alanlar otomatik dolar. Test e-postası göndererek yapılandırmayı doğrula.
    </p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <?php if ($dbg = flash('mail_debug')): ?>
        <details class="smtp-debug" open>
            <summary>SMTP konuşma kaydı (debug)</summary>
            <pre><?= esc((string) $dbg) ?></pre>
            <p class="muted">
                Sunucunun döndüğü gerçek cevaplar yukarıda. <strong>"535 5.7.8"</strong> veya
                <strong>"AUTH … failed"</strong> görüyorsan kimlik bilgisi sorunu;
                <strong>"basic authentication is disabled"</strong> ya da <strong>"modern authentication"</strong>
                görüyorsan sağlayıcı artık şifre tabanlı girişe izin vermiyor → aşağıdaki Alternatifler bölümüne bak.
            </p>
        </details>
    <?php endif; ?>
</section>

<aside class="smtp-notice">
    <h2>⚠ Hotmail / Outlook.com kullanıcıları için önemli</h2>
    <p>
        Microsoft, <strong>Eylül 2024'ten itibaren</strong> consumer Outlook/Hotmail hesaplarında
        Basic Auth'u (uygulama parolası dahil) aşamalı olarak kaldırıyor.
        Artık yalnızca <strong>OAuth2 / Modern Kimlik Doğrulaması</strong> kabul ediyor.
        Eğer "Could not authenticate" hatası alıyorsan ve parolanın doğru olduğundan eminsen,
        Microsoft'un servisi senin hesabın için Basic Auth'u kapatmış olabilir.
    </p>
    <p><strong>Pratik alternatifler:</strong></p>
    <ul>
        <li><strong>Gmail</strong> — uygulama parolasıyla hâlâ sorunsuz çalışıyor. Hesabın varsa preset'ten "Gmail · STARTTLS" seç.</li>
        <li><strong>Brevo / SendGrid / Mailgun</strong> — ücretsiz tier sunan transactional mail servisleri.
            Üyelik açtıktan sonra SMTP credentials veriyorlar, kendi domain'inden gönderebiliyorsun.</li>
        <li><strong>Hosting sağlayıcının kendi SMTP'si</strong> — cPanel'de "Email Accounts" altında oluşturduğun bir adresle
            kendi sunucu üzerinden gönderebilirsin (host: mail.alanın.com, port 465 SSL veya 587 STARTTLS).</li>
    </ul>
</aside>

<form method="post" action="<?= esc(url('/admin/mail')) ?>" class="settings-form" id="mail-form">
    <?= csrf_field() ?>

    <fieldset class="settings-group">
        <legend>Sağlayıcı</legend>
        <p class="settings-hint">
            Hızlı başlangıç için bir preset seç. Host/port/encryption otomatik dolar; sadece kullanıcı adı + parola gir.
            Gmail ve Outlook için "uygulama parolası" oluşturman gerekir (normal hesap parolası SMTP'de çalışmaz).
        </p>
        <div class="settings-fields">
            <label for="mail-preset">
                <span>Sağlayıcı Preset'i</span>
                <select id="mail-preset" name="preset">
                    <?php foreach ($presets as $key => $p): ?>
                        <option value="<?= esc($key) ?>"
                                data-host="<?= esc($p['host']) ?>"
                                data-port="<?= esc((string) $p['port']) ?>"
                                data-enc="<?= esc($p['encryption']) ?>"
                                data-hint="<?= esc($p['hint']) ?>"
                                <?= $activePreset === $key ? 'selected' : '' ?>>
                            <?= esc(self_label($key)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small id="preset-hint" class="muted"><?= esc($presets[$activePreset]['hint'] ?? '') ?></small>
            </label>

            <label for="mail-driver">
                <span>Transport</span>
                <select id="mail-driver" name="driver">
                    <option value="smtp" <?= ($values['driver'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP (önerilen)</option>
                    <option value="mail" <?= ($values['driver'] ?? '') === 'mail' ? 'selected' : '' ?>>PHP mail() (paylaşımlı hosting)</option>
                    <option value="log"  <?= ($values['driver'] ?? '') === 'log'  ? 'selected' : '' ?>>Yalnız log (test)</option>
                </select>
            </label>
        </div>
    </fieldset>

    <fieldset class="settings-group">
        <legend>SMTP Bağlantısı</legend>
        <p class="settings-hint">Sağlayıcının verdiği bilgilerle eşleşmeli. Parolalar şifrelenerek saklanmaz — DB erişimi olan herkes görebilir, bu yüzden hosting kullanıcısının yetkisini sınırla.</p>
        <div class="settings-fields">
            <label for="mail-host">
                <span>Host</span>
                <input type="text" id="mail-host" name="host" value="<?= esc((string) ($values['host'] ?? '')) ?>"
                       placeholder="smtp.gmail.com" autocomplete="off">
            </label>
            <label for="mail-port">
                <span>Port</span>
                <input type="number" id="mail-port" name="port" value="<?= esc((string) ($values['port'] ?? 587)) ?>"
                       min="1" max="65535">
            </label>
            <label for="mail-enc">
                <span>Şifreleme</span>
                <select id="mail-enc" name="encryption">
                    <option value="tls"  <?= ($values['encryption'] ?? '') === 'tls'  ? 'selected' : '' ?>>STARTTLS (port 587)</option>
                    <option value="ssl"  <?= ($values['encryption'] ?? '') === 'ssl'  ? 'selected' : '' ?>>SSL/TLS (port 465)</option>
                    <option value="none" <?= ($values['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Yok (sadece test)</option>
                </select>
            </label>
            <label for="mail-username">
                <span>Kullanıcı Adı</span>
                <input type="text" id="mail-username" name="username" value="<?= esc((string) ($values['username'] ?? '')) ?>"
                       placeholder="ornek@gmail.com" autocomplete="username">
            </label>
            <label for="mail-password">
                <span>Parola / Uygulama Parolası</span>
                <input type="password" id="mail-password" name="password" value=""
                       placeholder="<?= !empty($values['password']) ? '••••••••  (kayıtlı — boş bırakırsan değişmez)' : 'Buraya yaz' ?>"
                       autocomplete="new-password">
                <small class="muted">Boş bırakırsan mevcut parola korunur.</small>
            </label>
        </div>
    </fieldset>

    <fieldset class="settings-group">
        <legend>Gönderen Bilgileri</legend>
        <p class="settings-hint">Tüm sistem e-postalarında görünür. Gönderen adresi SMTP kullanıcısıyla eşleşmeli (aksi halde teslim sorunu yaşarsın).</p>
        <div class="settings-fields">
            <label for="mail-from-addr">
                <span>Gönderen Adres</span>
                <input type="email" id="mail-from-addr" name="from_address"
                       value="<?= esc((string) ($values['from_address'] ?? '')) ?>"
                       placeholder="no-reply@alanın.com" autocomplete="email">
            </label>
            <label for="mail-from-name">
                <span>Gönderen Adı</span>
                <input type="text" id="mail-from-name" name="from_name"
                       value="<?= esc((string) ($values['from_name'] ?? '')) ?>"
                       placeholder="Otorite Yayın">
            </label>

            <label class="settings-toggle" for="mail-log-only">
                <input type="hidden" name="log_only" value="0">
                <input type="checkbox" id="mail-log-only" name="log_only" value="1"
                       <?= !empty($values['log_only']) ? 'checked' : '' ?>>
                <span>Sadece log modu — gerçek e-posta gönderilmez, storage/logs/ içine yazılır (test ortamı için).</span>
            </label>
        </div>
    </fieldset>

    <div class="form-actions sticky">
        <button class="btn btn-primary" type="submit">Ayarları Kaydet</button>
        <a class="btn btn-ghost" href="<?= esc(url('/admin/')) ?>">Vazgeç</a>
    </div>
</form>

<!-- Test mail gönderimi — ayrı form -->
<section class="mail-test">
    <h2>Test E-postası Gönder</h2>
    <p class="muted">Yukarıdaki ayarları kaydettikten sonra burada kendi adresine test gönderebilirsin. Hata varsa SMTP cevabı bir flash mesajı olarak görünür.</p>
    <form method="post" action="<?= esc(url('/admin/mail/test')) ?>" class="mail-test-form">
        <?= csrf_field() ?>
        <label>
            <span>Alıcı E-posta</span>
            <input type="email" name="to" required
                   value="<?= esc((string) ($adminUser['email'] ?? '')) ?>"
                   placeholder="senin@adresin.com">
        </label>
        <button class="btn" type="submit">📨 Test Gönder</button>
    </form>
</section>

<script nonce="<?= esc(csp_nonce()) ?>">
(function () {
    var sel = document.getElementById('mail-preset');
    if (!sel) return;
    var host = document.getElementById('mail-host');
    var port = document.getElementById('mail-port');
    var enc  = document.getElementById('mail-enc');
    var hint = document.getElementById('preset-hint');
    sel.addEventListener('change', function () {
        var o = sel.options[sel.selectedIndex];
        var h = o.dataset.host || '';
        var p = o.dataset.port || '';
        var e = o.dataset.enc || '';
        if (h) host.value = h;
        if (p) port.value = p;
        if (e) enc.value = e;
        if (hint) hint.textContent = o.dataset.hint || '';
    });
})();
</script>

<?php
function self_label(string $key): string {
    return [
        'custom'     => 'Custom (manuel)',
        'gmail'      => 'Gmail · STARTTLS (587)',
        'gmail-ssl'  => 'Gmail · SSL (465)',
        'hotmail'    => 'Hotmail · @hotmail.com',
        'outlook'    => 'Outlook.com / Live / MSN',
        'office365'  => 'Office 365 / Microsoft 365 (iş)',
        'yandex'     => 'Yandex Mail',
        'sendgrid'   => 'SendGrid',
        'mailgun'    => 'Mailgun',
        'amazon-ses' => 'Amazon SES (EU West)',
    ][$key] ?? ucfirst($key);
}
