<?php
/**
 * Profil sayfası — Atelier post-editor patternine uyumlu.
 * @var array $user
 * @var array $profile
 */
\App\Core\View::layout('base');

$socialKeys = \App\Services\ProfileService::SOCIAL_KEYS;
$socialLabels = [
    'twitter'   => 'X / Twitter',
    'github'    => 'GitHub',
    'linkedin'  => 'LinkedIn',
    'instagram' => 'Instagram',
    'mastodon'  => 'Mastodon',
    'website'   => 'Web Sitesi',
];
$is2fa = ((int) ($user['totp_enabled'] ?? 0)) === 1;
?>

<section class="hero post-editor-hero">
    <div>
        <h1>Profilim</h1>
        <p class="post-editor-meta">
            <span class="badge badge-published">@<?= esc($user['email'] ?? '') ?></span>
            <span class="muted">·</span>
            <a class="muted" href="<?= esc(url('/panel/iki-fa')) ?>">
                🔒 2FA: <strong><?= $is2fa ? 'Aktif' : 'Pasif' ?></strong>
            </a>
        </p>
    </div>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<form method="post"
      action="<?= esc(url('/panel/profil')) ?>"
      class="post-editor"
      id="profile-form"
      enctype="multipart/form-data">
    <?= csrf_field() ?>

    <header class="post-editor-head">
        <input type="text"
               name="name"
               class="post-title-input"
               required minlength="2" maxlength="120"
               placeholder="Adınız…"
               value="<?= esc($user['name']) ?>">
    </header>

    <div class="post-editor-grid">
        <div class="post-editor-main">

            <section class="pe-section">
                <h2 class="pe-section-title">Profil Künyesi</h2>
                <p class="pe-section-hint">Yazılarınızın altında ve yazar sayfanızda görünür. SEO için <code>Person</code> JSON-LD'sine dönüşür.</p>
                <label>
                    <span>Başlık</span>
                    <input type="text" name="profile[headline]" maxlength="160"
                           value="<?= esc((string) ($profile['headline'] ?? '')) ?>"
                           placeholder="örn: Mimari Müşavir, Restorasyon Uzmanı">
                </label>
                <label>
                    <span>Konum</span>
                    <input type="text" name="profile[location]" maxlength="120"
                           value="<?= esc((string) ($profile['location'] ?? '')) ?>"
                           placeholder="örn: İstanbul, Türkiye">
                </label>
                <label>
                    <span>Hakkımda (Bio)</span>
                    <textarea name="bio" rows="6" maxlength="4000"
                              placeholder="Mesleki geçmişiniz, ilgi alanlarınız, çalıştığınız projeler…"><?= esc((string) ($profile['bio'] ?? $user['bio'] ?? '')) ?></textarea>
                    <small class="muted">En fazla 4000 karakter. Markdown desteklenir.</small>
                </label>
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">Uzmanlık</h2>
                <p class="pe-section-hint">Yetkin olduğunuz konular — yazılarda topic relevance için kullanılır.</p>
                <label>
                    <span>Etiketler (virgülle ayır)</span>
                    <input type="text" name="profile[expertise_csv]"
                           placeholder="Restorasyon, Mimari Tarih, BIM, Strüktürel Analiz"
                           value="<?= esc(implode(', ', (array) ($profile['expertise'] ?? []))) ?>">
                </label>
                <input type="hidden" name="profile[expertise][]" value="">
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">Sertifikalar</h2>
                <p class="pe-section-hint">Aldığınız eğitim/sertifikalar — en fazla 5 satır. Boş bırakılan satırlar kaydedilmez.</p>
                <?php
                $certs = $profile['certificates'] ?? [];
                for ($i = 0; $i < 5; $i++):
                    $c = $certs[$i] ?? ['name'=>'','issuer'=>'','year'=>'','url'=>''];
                ?>
                    <div class="pe-cert-row">
                        <input type="text" name="profile[certificates][<?= $i ?>][name]"
                               placeholder="Sertifika adı" maxlength="160"
                               value="<?= esc((string) ($c['name'] ?? '')) ?>">
                        <input type="text" name="profile[certificates][<?= $i ?>][issuer]"
                               placeholder="Veren kurum" maxlength="160"
                               value="<?= esc((string) ($c['issuer'] ?? '')) ?>">
                        <input type="number" name="profile[certificates][<?= $i ?>][year]"
                               placeholder="Yıl" min="1900" max="2100"
                               value="<?= esc((string) ($c['year'] ?? '')) ?>">
                        <input type="url" name="profile[certificates][<?= $i ?>][url]"
                               placeholder="Belge URL (opsiyonel)" maxlength="255"
                               value="<?= esc((string) ($c['url'] ?? '')) ?>">
                    </div>
                <?php endfor; ?>
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">Sosyal Bağlantılar</h2>
                <p class="pe-section-hint">Yazar sayfanızda küçük ikon olarak görünür. Boş bırakırsanız gizlenir.</p>
                <?php foreach ($socialKeys as $k): ?>
                    <label>
                        <span><?= esc($socialLabels[$k] ?? ucfirst($k)) ?></span>
                        <input type="url" name="profile[social][<?= esc($k) ?>]" maxlength="255"
                               placeholder="https://..."
                               value="<?= esc((string) ($profile['social'][$k] ?? '')) ?>">
                    </label>
                <?php endforeach; ?>
            </section>

        </div>

        <aside class="post-editor-side">

            <section class="pe-card">
                <h2 class="pe-section-title">Avatar</h2>
                <?php if (!empty($user['avatar'])): ?>
                    <div class="pe-avatar-preview">
                        <img src="<?= esc(url($user['avatar'])) ?>" alt="Avatar">
                    </div>
                <?php else: ?>
                    <div class="pe-avatar-preview pe-avatar-empty">
                        <span><?= esc(mb_strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span>
                    </div>
                <?php endif; ?>
                <label>
                    <span>Yeni avatar yükle</span>
                    <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp">
                </label>
                <p class="pe-helper">Otomatik kareye kırpılır ve WebP/AVIF varyantları üretilir.</p>
                <?php if ($e = flash('error_avatar')): ?>
                    <div class="flash flash-error"><?= esc($e) ?></div>
                <?php endif; ?>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Kaydet</h2>
                <p class="pe-helper">Tüm değişiklikler aynı anda kaydedilir.</p>
                <div class="pe-actions">
                    <button type="submit" class="btn btn-primary btn-block">Profili Kaydet</button>
                </div>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Hızlı Erişim</h2>
                <ul class="pe-quick-links">
                    <li><a href="<?= esc(url('/panel/iki-fa')) ?>">🔒 2FA Ayarları</a></li>
                    <?php if (function_exists('feature') && feature('active_sessions_enabled')): ?>
                        <li><a href="<?= esc(url('/panel/oturumlar')) ?>">⊟ Aktif Oturumlar</a></li>
                    <?php endif; ?>
                    <?php if (function_exists('feature') && feature('data_export_enabled')): ?>
                        <li><a href="<?= esc(url('/panel/hesap/verilerim')) ?>">⤓ Verilerimi İndir (KVKK)</a></li>
                    <?php endif; ?>
                    <?php if (function_exists('feature') && feature('account_delete_enabled')): ?>
                        <li><a href="<?= esc(url('/panel/hesap/sil')) ?>" class="pe-link-danger">✗ Hesabımı Sil</a></li>
                    <?php endif; ?>
                </ul>
            </section>

        </aside>
    </div>
</form>

<!-- ════════════════════════ GÜVENLİK BÖLÜMÜ ════════════════════════ -->
<section id="guvenlik" class="profile-security">
    <header class="profile-security-head">
        <p class="profile-security-eyebrow">HESAP GÜVENLİĞİ</p>
        <h2>Şifre &amp; E-posta</h2>
        <p class="muted">Hesap erişimini koruyan ana ayarlar. Her iki form da bağımsız kaydedilir.</p>
    </header>

    <div class="profile-security-grid">
        <form method="post" action="<?= esc(url('/panel/profil/sifre')) ?>" class="pe-card pe-card-form">
            <?= csrf_field() ?>
            <h3 class="pe-section-title">Şifreyi Değiştir</h3>
            <label>
                <span>Mevcut Şifre</span>
                <input type="password" name="current_password" autocomplete="current-password" required>
                <?= form_error('current_password') ?>
            </label>
            <label>
                <span>Yeni Şifre</span>
                <input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
                <small class="muted">En az 8 karakter.</small>
                <?= form_error('new_password') ?>
            </label>
            <label>
                <span>Yeni Şifre (Tekrar)</span>
                <input type="password" name="new_password_confirm" minlength="8" autocomplete="new-password" required>
                <?= form_error('new_password_confirm') ?>
            </label>
            <div class="pe-actions">
                <button type="submit" class="btn btn-primary btn-block">Şifreyi Güncelle</button>
            </div>
            <p class="pe-helper">Şifre değişikliği e-posta ile bildirilir.</p>
        </form>

        <form method="post" action="<?= esc(url('/panel/profil/eposta')) ?>" class="pe-card pe-card-form">
            <?= csrf_field() ?>
            <h3 class="pe-section-title">E-posta Adresi</h3>
            <p class="pe-current-email">
                Mevcut: <code><?= esc((string) $user['email']) ?></code>
                <?php if (empty($user['email_verified_at'])): ?>
                    <span class="badge badge-pending">Doğrulanmadı</span>
                <?php endif; ?>
            </p>
            <label>
                <span>Yeni E-posta</span>
                <input type="email" name="new_email" autocomplete="email" required>
                <?= form_error('new_email') ?>
            </label>
            <label>
                <span>Mevcut Şifre (doğrulama)</span>
                <input type="password" name="current_password" autocomplete="current-password" required>
                <?= form_error('current_password_email') ?>
            </label>
            <div class="pe-actions">
                <button type="submit" class="btn btn-primary btn-block">E-postayı Güncelle</button>
            </div>
            <p class="pe-helper">Yeni adrese doğrulama bağlantısı gönderilir. Eski adres de değişiklikten haberdar edilir.</p>
        </form>
    </div>
</section>

<script>
// expertise_csv → array (submit öncesi)
document.getElementById('profile-form')?.addEventListener('submit', function () {
    var csv = this.querySelector('input[name="profile[expertise_csv]"]');
    if (!csv) return;
    var parent = csv.parentElement;
    parent.querySelectorAll('input[name="profile[expertise][]"]').forEach(function (n) { n.remove(); });
    csv.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean).forEach(function (val) {
        var i = document.createElement('input');
        i.type = 'hidden';
        i.name = 'profile[expertise][]';
        i.value = val;
        parent.appendChild(i);
    });
});
</script>
