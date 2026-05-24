<?php
/**
 * /iletisim — public iletişim formu.
 *
 * G2 + G3 (2026-05): Sayfa metinleri admin-yönetilebilir (Setting → pages),
 * tasarım dili ATELIER (hairline / eyebrow / monumental serif / mono labels).
 *
 * @var string $org_email
 * @var string $org_phone
 */
\App\Core\View::layout('base');

// Admin'den okunan metinler — boşsa makul varsayılan göster.
$_pageTitle     = trim((string) \App\Models\Setting::get('contact_page_title',    '', 'pages')) ?: 'Bana yazın';
$_pageLead      = trim((string) \App\Models\Setting::get('contact_page_lead',     '', 'pages'))
    ?: 'Mimarlık, yapı kültürü, işbirliği önerileri ya da bir yazı hakkında yorum — '
     . 'hepsi açık. Yazılı her şeyi okuyorum, uygun olanlara elimden geldiğince hızlı dönüş yapıyorum.';
$_responseText  = trim((string) \App\Models\Setting::get('contact_response_time', '', 'pages'))
    ?: 'Uygun mesajlara genellikle 1-3 iş günü içinde dönüş yapılır. '
     . 'Spam ve genel pazarlama içeriklerini yanıtlamıyorum.';
$_collaboration = trim((string) \App\Models\Setting::get('contact_collaboration', '', 'pages'))
    ?: 'Mimari konularda konuk yazar, akademik atıf, podcast veya konferans daveti için '
     . 'yazabilirsiniz. Sözlük katkıları da memnuniyetle.';
?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<article class="contact-page">
    <header class="contact-head">
        <p class="contact-eyebrow"><span aria-hidden="true">§</span> İLETİŞİM</p>
        <h1 class="contact-title"><?= esc($_pageTitle) ?></h1>
        <p class="contact-lead"><?= esc($_pageLead) ?></p>
    </header>

    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>

    <div class="contact-grid">
        <section class="contact-form-section">
            <form method="post" action="<?= esc(url('/iletisim')) ?>" class="contact-form" autocomplete="on" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="rendered_at" value="<?= time() ?>">

                <!-- Honeypot — sadece botlar doldurur, gerçek kullanıcı görmez -->
                <div class="contact-honeypot" aria-hidden="true">
                    <label>Website (boş bırakın)
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                <div class="contact-row">
                    <label class="contact-field">
                        <span class="contact-label">Ad Soyad <em>*</em></span>
                        <input type="text" name="name" required minlength="2" maxlength="100"
                               autocomplete="name" placeholder="örn. Ayşe Yılmaz">
                    </label>
                    <label class="contact-field">
                        <span class="contact-label">E-posta <em>*</em></span>
                        <input type="email" name="email" required maxlength="200"
                               autocomplete="email" placeholder="adres@ornek.com">
                    </label>
                </div>

                <div class="contact-row">
                    <label class="contact-field">
                        <span class="contact-label">Telefon <small>opsiyonel</small></span>
                        <input type="tel" name="phone" maxlength="30"
                               autocomplete="tel" placeholder="+90 5xx xxx xx xx">
                    </label>
                    <label class="contact-field">
                        <span class="contact-label">Konu <em>*</em></span>
                        <input type="text" name="subject" required minlength="3" maxlength="150"
                               placeholder="örn. Konut projesinde danışmanlık">
                    </label>
                </div>

                <label class="contact-field">
                    <span class="contact-label">Mesaj <em>*</em></span>
                    <textarea name="message" required minlength="10" maxlength="5000" rows="8"
                              placeholder="Birkaç cümle yazın — proje bağlamı, soru, beklenti…"></textarea>
                </label>

                <p class="contact-helper">
                    <em>*</em> zorunlu alan. Mesaj e-posta ile iletilir;
                    yanıtlamak için adresinize geri yazarım. Veriler 3. taraflarla paylaşılmaz.
                </p>

                <div class="contact-actions">
                    <button type="submit" class="contact-submit">
                        Gönder <span aria-hidden="true">→</span>
                    </button>
                </div>
            </form>
        </section>

        <aside class="contact-info">
            <?php if ($org_email !== '' || $org_phone !== ''): ?>
            <section class="contact-info-block">
                <p class="contact-info-eyebrow"><span aria-hidden="true">§</span> Doğrudan Bağlantı</p>
                <?php if ($org_email !== ''): ?>
                    <p class="contact-info-row">
                        <span class="contact-info-label">E-posta</span>
                        <a class="contact-info-value" href="mailto:<?= esc($org_email) ?>"><?= esc($org_email) ?></a>
                    </p>
                <?php endif; ?>
                <?php if ($org_phone !== ''): ?>
                    <p class="contact-info-row">
                        <span class="contact-info-label">Telefon</span>
                        <a class="contact-info-value" href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $org_phone)) ?>"><?= esc($org_phone) ?></a>
                    </p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <section class="contact-info-block">
                <p class="contact-info-eyebrow"><span aria-hidden="true">§</span> Cevap Süresi</p>
                <p class="contact-info-body"><?= esc($_responseText) ?></p>
            </section>

            <section class="contact-info-block">
                <p class="contact-info-eyebrow"><span aria-hidden="true">§</span> İşbirliği</p>
                <p class="contact-info-body"><?= esc($_collaboration) ?></p>
            </section>
        </aside>
    </div>
</article>
