<?php
/**
 * @var array  $user
 * @var bool   $enabled
 * @var string $pending_secret    Setup ortasında — secret henüz DB'de değil
 * @var array  $pending_codes     Setup ortasında — recovery codes
 * @var string $pending_otpauth   otpauth:// URL
 * @var string $title
 *
 * 2FA setup & yönetim sayfası.
 *  - Aktif değil + pending yok: "Etkinleştir" butonu
 *  - Aktif değil + pending var: QR + secret + recovery codes + kod doğrula formu
 *  - Aktif: aç/kapat + recovery code yenile
 */
\App\Core\View::layout('base');

// Recovery codes — yeniden üretildiyse session'da bir defa göster
$justGenerated = $_SESSION['_totp_recovery_show'] ?? null;
unset($_SESSION['_totp_recovery_show']);

// QR kod (SVG) — pending varsa otpauth URL'sinden üret. chillerlan/php-qrcode.
$qrSvg = '';
if (!empty($pending_otpauth) && class_exists(\chillerlan\QRCode\QRCode::class)) {
    try {
        $opts = new \chillerlan\QRCode\QROptions([
            'outputType'  => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
            'scale'       => 5,
            'svgViewBoxSize' => 250,
            'imageBase64' => false,
            'eccLevel'    => \chillerlan\QRCode\QRCode::ECC_M,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'svgConnectPaths' => true,
        ]);
        $qrSvg = (new \chillerlan\QRCode\QRCode($opts))->render($pending_otpauth);
    } catch (\Throwable) {
        $qrSvg = '';
    }
}
?>
<section class="hero">
    <h1>İki Adımlı Doğrulama (2FA)</h1>
    <p class="lead muted">
        Hesabınızı korumak için authenticator uygulaması üzerinden zaman tabanlı kodla giriş yapın.
    </p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <?php if ($e = flash('error_code')): ?>
        <div class="flash flash-error"><?= esc($e) ?></div>
    <?php endif; ?>
</section>

<?php if (!$enabled && $pending_secret === ''): ?>
    <article class="card">
        <h2>Etkinleştir</h2>
        <p>
            Önerilen uygulamalar: <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong>,
            <strong>1Password</strong>, <strong>Authy</strong>, <strong>FreeOTP</strong>.
        </p>
        <ol class="muted">
            <li>"Başlat" butonuna tıkla.</li>
            <li>QR kodu (veya secret'i) authenticator app'e ekle.</li>
            <li>App'in gösterdiği 6 haneli kodu sayfaya gir → 2FA aktif.</li>
            <li>10 recovery kodunu güvenli bir yere kaydet (örn. parola yöneticin).</li>
        </ol>
        <form method="post" action="<?= esc(url('/panel/iki-fa/baslat')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">2FA Etkinleştir</button>
        </form>
    </article>

<?php elseif (!$enabled && $pending_secret !== ''): ?>
    <article class="card">
        <h2>1. Authenticator'a Ekle</h2>
        <p class="muted">
            Uygulamanızda "yeni hesap ekle" → "QR tara" → aşağıdaki kareyi tara.
            Kameran yoksa <strong>manuel giriş</strong> bölümündeki secret'i kopyala.
        </p>

        <div class="totp-setup" style="display:grid;grid-template-columns:240px 1fr;gap:2rem;align-items:flex-start;margin:1.5rem 0">
            <?php if ($qrSvg !== ''): ?>
            <figure style="margin:0;padding:.85rem;background:#fff;border:1px solid var(--hair);border-radius:.5rem;text-align:center">
                <div style="width:100%;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center"><?= $qrSvg ?></div>
                <figcaption class="muted" style="margin-top:.5rem;font-family:var(--mono);font-size:.66rem;letter-spacing:var(--tracked);text-transform:uppercase">QR ile tarayın</figcaption>
            </figure>
            <?php else: ?>
            <div style="background:var(--bone-2);border:1px solid var(--hair);padding:1rem;text-align:center">
                <p class="muted">QR oluşturucu yüklenemedi.<br>Manuel girişi kullanın.</p>
            </div>
            <?php endif; ?>

            <div class="totp-manual">
                <h3 style="margin:0 0 .75rem;font-family:var(--mono);font-size:.7rem;letter-spacing:var(--tracked-l);text-transform:uppercase;color:var(--ash);font-weight:600">
                    veya Manuel Giriş
                </h3>
                <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:.5rem 1rem;font-size:.92rem">
                    <dt style="color:var(--ash)">Issuer</dt>
                    <dd style="margin:0"><code>Odogan</code></dd>
                    <dt style="color:var(--ash)">Account</dt>
                    <dd style="margin:0"><code><?= esc((string) $user['email']) ?></code></dd>
                    <dt style="color:var(--ash)">Secret</dt>
                    <dd style="margin:0">
                        <code style="font-size:1rem;letter-spacing:.15em;background:var(--bone-2);padding:.35rem .5rem;border:1px solid var(--hair);user-select:all;display:inline-block">
                            <?= esc(chunk_split($pending_secret, 4, ' ')) ?>
                        </code>
                    </dd>
                </dl>
                <p class="muted" style="margin-top:1rem;font-size:.85rem">
                    Mobil cihazda mısın?
                    <a href="<?= esc($pending_otpauth) ?>" title="Authenticator app'i otomatik aç">
                        Doğrudan authenticator'ı aç →
                    </a>
                </p>
            </div>
        </div>

        <h2 style="margin-top:1.5rem">2. Doğrula ve Aktive Et</h2>
        <form method="post" action="<?= esc(url('/panel/iki-fa/aktiflestir')) ?>" class="form form-wide">
            <?= csrf_field() ?>
            <label>
                <span>App'in gösterdiği 6 haneli kod</span>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" autocomplete="one-time-code" required
                       style="font-family:monospace;font-size:1.5rem;letter-spacing:.3em;text-align:center">
            </label>
            <button class="btn btn-primary" type="submit">Aktifleştir</button>
        </form>

        <h2 style="margin-top:1.5rem">3. Recovery Kodlarınızı Kaydedin</h2>
        <p class="muted">
            Telefonunuzu kaybederseniz, bu kodlardan biri ile bir defa giriş yapabilirsiniz.
            <strong>Şimdi kaydetmezseniz bir daha gösterilmeyecek.</strong>
        </p>
        <ul style="font-family:monospace;column-count:2;gap:.5rem;background:#f3f1ec;padding:1rem;border-radius:.5rem;list-style:none;user-select:all">
            <?php foreach (\App\Services\TotpService::formatRecoveryCodes($pending_codes) as $rc): ?>
                <li><?= esc($rc) ?></li>
            <?php endforeach; ?>
        </ul>
    </article>

<?php else: /* 2FA aktif */ ?>

    <article class="card">
        <h2>✓ 2FA Aktif</h2>
        <p class="muted">
            Hesabınız iki adımlı doğrulama ile korunuyor.
            Login sırasında authenticator uygulamanız 6 haneli kod isteyecek.
        </p>
        <?php if ($user['totp_enabled_at']): ?>
            <p class="muted" style="font-size:.85rem">
                Etkinleştirme tarihi: <?= esc(date('d/m/Y H:i', strtotime((string) $user['totp_enabled_at']))) ?>
            </p>
        <?php endif; ?>
    </article>

    <?php if ($justGenerated): ?>
    <article class="card">
        <h2>🆕 Yeni Recovery Kodları</h2>
        <p class="muted">
            <strong>Bu liste yalnızca şimdi gösterilir.</strong> Eski kodlar geçersizleşti.
            Bir parola yöneticinde veya güvenli bir yere kaydedin.
        </p>
        <ul style="font-family:monospace;column-count:2;gap:.5rem;background:#fff7cc;padding:1rem;border-radius:.5rem;list-style:none;user-select:all">
            <?php foreach (\App\Services\TotpService::formatRecoveryCodes($justGenerated) as $rc): ?>
                <li><?= esc($rc) ?></li>
            <?php endforeach; ?>
        </ul>
    </article>
    <?php endif; ?>

    <article class="card">
        <h2>Recovery Kodlarını Yenile</h2>
        <p class="muted">
            Eski kodlar geçersizleşir; 10 yeni kod üretilir. Mevcut authenticator kodunu girin.
        </p>
        <form method="post" action="<?= esc(url('/panel/iki-fa/recovery-yenile')) ?>" class="form">
            <?= csrf_field() ?>
            <label>
                <span>Mevcut 6 haneli kod</span>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                       autocomplete="one-time-code" required
                       style="font-family:monospace;letter-spacing:.3em;text-align:center">
            </label>
            <button class="btn" type="submit">Yeni Kodlar Üret</button>
        </form>
    </article>

    <article class="card" style="border-color:#c8421e">
        <h2 style="color:#c8421e">2FA'yı Devre Dışı Bırak</h2>
        <p class="muted">
            Mevcut authenticator kodunu girerek 2FA'yı kapatabilirsiniz.
            <strong>Önerilmez</strong> — 2FA hesap güvenliğinin temel taşıdır.
        </p>
        <form method="post" action="<?= esc(url('/panel/iki-fa/pasiflestir')) ?>"
              class="form" onsubmit="return confirm('2FA tamamen kapatılsın mı? Hesabınız sadece parola ile korunacak.');">
            <?= csrf_field() ?>
            <label>
                <span>Mevcut 6 haneli kod</span>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                       autocomplete="one-time-code" required
                       style="font-family:monospace;letter-spacing:.3em;text-align:center">
            </label>
            <button class="btn btn-danger" type="submit">2FA'yı Kapat</button>
        </form>
    </article>

<?php endif; ?>

<p style="margin-top:2rem">
    <a href="<?= esc(url('/panel/profil')) ?>" class="muted" title="Profil sayfasına dön">← Profile dön</a>
</p>
