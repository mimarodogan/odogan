<?php \App\Core\View::layout('base'); ?>
<?php
$groupLabels = [
    'general'      => 'Site Kimliği',
    'seo'          => 'SEO & Meta',
    'analytics'    => 'Analitik & Doğrulama',
    'privacy'      => 'Gizlilik & KVKK',
    'social'       => 'Sosyal Medya',
    'content'      => 'İçerik Politikası',
    'organization' => 'Kuruluş Bilgileri (Schema.org)',
    'ai'           => 'Yapay Zeka (Claude API)',
    'pages'        => 'Sayfa İçerikleri (Hakkımda + İletişim)',
];
$groupHints = [
    'general'      => 'Sitenin adı, sloganı, default meta açıklaması — başlık ve OG etiketlerine yansır.',
    'seo'          => 'Canonical URL tabanı, varsayılan paylaşım görseli, Twitter handle.',
    'analytics'    => 'Google Analytics ve doğrulama kodları. Public sayfaların <head>\'ine eklenir.',
    'privacy'      => 'Çerez onay banner\'ı ve KVKK / GDPR Consent Mode V2 ayarları.',
    'social'       => 'Footer ve about sayfasında gösterilecek sosyal hesap linkleri.',
    'content'      => 'Yayın yapan tüm kullanıcıları etkileyen genel davranışlar.',
    'organization' => 'Schema.org Organization JSON-LD\'ye yansır. Google Knowledge Panel adaylığı + telif/iletişim bilgileri için kullanılır. Boş bırakılan alanlar schema\'da yayınlanmaz.',
    'ai'           => 'Claude API anahtarı ve model seçimi. Anahtar yoksa .env ANTHROPIC_API_KEY kullanılır. Toggle\'lar için Özellikler sayfasına bakın.',
    'pages'        => '/hakkimda ve /iletisim sayfalarındaki düz-metin/manifesto bölümleri. Boş bırakılırsa her sayfa kendi varsayılan kopyasını gösterir. HTML serbest (<strong>, <em>, <a>).',
];
// Textarea olarak render edilecek key'ler — kısa metin yerine çok satırlı kutu.
// Hem klasik description/footer alanları, hem G2'de eklenen sayfa metinleri.
$textareaKeys = [
    'site_description',
    'footer_text',
    // pages grubu (G2) — manifesto + iletişim sayfa metinleri
    'about_manifesto_html',
    'contact_page_lead',
    'contact_response_time',
    'contact_collaboration',
];
// Büyük (geniş satırlı) textarea key'leri — şu an boş. Critical CSS
// /admin/critical-css ayrı sayfada yönetiliyor.
$bigTextareaKeys = [];
?>

<section class="hero">
    <h1>Site Ayarları</h1>
    <p class="lead">Site adı, meta açıklamaları, SEO ve analitik ayarları tek yerden.</p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc(url('/admin/ayarlar')) ?>" class="settings-form">
    <?= csrf_field() ?>

    <?php foreach ($schema as $group => $fields): ?>
        <fieldset class="settings-group">
            <legend><?= esc($groupLabels[$group] ?? ucfirst($group)) ?></legend>
            <p class="settings-hint"><?= esc($groupHints[$group] ?? '') ?></p>

            <div class="settings-fields">
                <?php foreach ($fields as $key => $def):
                    $val = $values[$group][$key] ?? '';
                    $name = $group . '[' . $key . ']';
                    $id   = 'set-' . $group . '-' . $key;
                ?>
                    <?php if ($def['type'] === 'bool'): ?>
                        <label class="settings-toggle" for="<?= esc($id) ?>">
                            <input type="hidden" name="<?= esc($name) ?>" value="0">
                            <input type="checkbox" id="<?= esc($id) ?>" name="<?= esc($name) ?>" value="1"
                                <?= $val ? 'checked' : '' ?>>
                            <span><?= esc($def['label']) ?></span>
                        </label>
                    <?php elseif (in_array($key, $textareaKeys, true)): ?>
                        <label for="<?= esc($id) ?>">
                            <span><?= esc($def['label']) ?></span>
                            <textarea id="<?= esc($id) ?>" name="<?= esc($name) ?>" rows="3" maxlength="500"><?= esc((string) $val) ?></textarea>
                            <?php if ($key === 'site_description'): ?>
                                <small class="muted">160 karaktere kadar — arama motorlarında snippet olarak görünür.</small>
                            <?php endif; ?>
                        </label>
                    <?php elseif ($def['type'] === 'int'): ?>
                        <label for="<?= esc($id) ?>">
                            <span><?= esc($def['label']) ?></span>
                            <input type="number" id="<?= esc($id) ?>" name="<?= esc($name) ?>"
                                   value="<?= esc((string) $val) ?>" min="0" max="500">
                        </label>
                    <?php else: ?>
                        <label for="<?= esc($id) ?>">
                            <span><?= esc($def['label']) ?></span>
                            <input type="text" id="<?= esc($id) ?>" name="<?= esc($name) ?>"
                                   value="<?= esc((string) $val) ?>" maxlength="255">
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <div class="form-actions sticky">
        <button class="btn btn-primary" type="submit">Ayarları Kaydet</button>
        <a class="btn btn-ghost" href="<?= esc(url('/admin/')) ?>">Vazgeç</a>
    </div>
</form>
