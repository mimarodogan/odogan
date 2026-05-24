<?php
/**
 * Proje formu (yeni/düzenle) — Atelier post-editor patternine uyumlu.
 * @var array $project
 * @var bool  $is_edit
 * @var bool  $is_admin
 * @var bool  $is_admin_or_editor
 */
\App\Core\View::layout('base');

$is_admin = $is_admin ?? false;
$is_admin_or_editor = $is_admin_or_editor ?? false;

$action = $is_edit
    ? url('/panel/projeler/' . $project['id'] . '/guncelle')
    : url('/panel/projeler/kaydet');

$partnersText = implode("\n", $project['partners_json'] ?? []);
$tagsText = implode(', ', $project['tags_json'] ?? []);
$galleryText = implode("\n", array_map(static function ($g) {
    if (!empty($g['url'])) return $g['url'];
    if (!empty($g['media_id'])) return (string) $g['media_id'];
    return '';
}, $project['gallery_json'] ?? []));
$linksText = implode("\n", array_map(static function ($l) {
    if (!empty($l['label'])) return $l['label'] . '|' . ($l['url'] ?? '');
    return $l['url'] ?? '';
}, $project['links_json'] ?? []));

$status = (string) ($project['status'] ?? 'draft');
$statusLabels = [
    'draft'     => ['Taslak',  'badge-draft'],
    'published' => ['Yayında', 'badge-published'],
    'archived'  => ['Arşiv',   'badge-archived'],
];
[$statusLabel, $statusBadge] = $statusLabels[$status] ?? [$status, 'badge-draft'];
?>

<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/panel/projeler')) ?>" class="muted">← Tüm Projeler</a>
        </p>
        <h1><?= $is_edit ? 'Projeyi Düzenle' : 'Yeni Proje' ?></h1>
        <p class="post-editor-meta">
            <?php if ($is_edit): ?>
                <span class="badge <?= esc($statusBadge) ?>"><?= esc($statusLabel) ?></span>
                <?php if (!empty($project['featured'])): ?>
                    <span class="muted">·</span>
                    <span class="badge badge-accent">Öne Çıkan</span>
                <?php endif; ?>
                <span class="muted">·</span>
                <span class="muted">/proje/<?= esc((string) $project['slug']) ?></span>
            <?php else: ?>
                <span class="badge badge-draft">Yeni</span>
            <?php endif; ?>
        </p>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc($action) ?>" class="post-editor" id="project-form">
    <?= csrf_field() ?>

    <header class="post-editor-head">
        <input type="text"
               name="name"
               class="post-title-input"
               required maxlength="220"
               placeholder="Proje adı (örn: Süleymaniye Cephe Restorasyonu)…"
               value="<?= esc((string) $project['name']) ?>">
    </header>

    <div class="post-editor-grid">
        <div class="post-editor-main">

            <section class="pe-section">
                <h2 class="pe-section-title">Tanıtım</h2>
                <p class="pe-section-hint">Projenin amacı, kapsamı ve yöntemi. HTML serbest — başlık, paragraf, görsel, alıntı kullanabilirsin.</p>
                <label>
                    <span>Alt Başlık</span>
                    <input type="text" name="subtitle" maxlength="255"
                           value="<?= esc((string) $project['subtitle']) ?>"
                           placeholder="Kısa, betimleyici bir satır">
                </label>
                <span class="visually-hidden" id="rich-body-label">Açıklama</span>
                <textarea id="rich-body"
                          name="description"
                          rows="14"
                          data-format="html"
                          aria-labelledby="rich-body-label"><?= esc((string) $project['description']) ?></textarea>
                <input type="hidden" name="body_format" value="html">
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">Galeri</h2>
                <p class="pe-section-hint">Kütüphaneden seç veya bilgisayardan yükle. Sürükleyerek sırala.</p>
                <?php
                $ml_name  = 'gallery';
                $ml_value = $galleryText;
                $ml_label = 'Galeri Görselleri';
                $ml_hint  = 'Her satıra bir görsel URL veya Medya Kütüphanesi ID.';
                require dirname(__DIR__, 2) . '/partials/admin/media-input-list.php';
                ?>
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">Ortak & Bağlantılar</h2>
                <label>
                    <span>Partner / Katkı</span>
                    <textarea name="partners" rows="4"
                              placeholder="Ahmet Yılmaz — yapı denetim&#10;XYZ Mimarlık — ortak müellif"><?= esc($partnersText) ?></textarea>
                </label>
                <label>
                    <span>Etiketler (virgülle)</span>
                    <input type="text" name="tags" maxlength="500"
                           value="<?= esc($tagsText) ?>"
                           placeholder="restorasyon, koruma, ahşap">
                </label>
                <label>
                    <span>Dış Bağlantılar</span>
                    <textarea name="links" rows="4"
                              placeholder="Resmi Site|https://example.com&#10;https://makale.com"><?= esc($linksText) ?></textarea>
                    <small class="muted">"Etiket|URL" formatında. Etiket yoksa direkt URL.</small>
                </label>
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">Künye — Mimari Ekip, Mühendisler, Danışmanlar</h2>
                <p class="pe-section-hint">
                    Projeyi gerçekleştiren ekip. Boş satırlar atılır; sadece adı dolu olanlar kaydedilir.
                    URL girersen ekip üyesinin sitesi yeni sekmede açılır. <code>http(s)://</code> olmasa da olur — otomatik eklenir.
                </p>
                <?php
                $teamGroups = [
                    'architects'  => ['title' => '◆ Mimari Ekip',  'titlePlaceholder' => 'Müellif Mimar / Yardımcı Mimar / Yüksek Mimar', 'addLabel' => 'Mimar Ekle'],
                    'engineers'   => ['title' => '◆ Mühendislik',  'titlePlaceholder' => 'Statik / Mekanik / Elektrik / Jeofizik', 'addLabel' => 'Mühendis Ekle'],
                    'consultants' => ['title' => '◆ Danışmanlar',  'titlePlaceholder' => 'Akustik / Peyzaj / İç Mimar / Aydınlatma', 'addLabel' => 'Danışman Ekle'],
                ];
                foreach ($teamGroups as $gKey => $gMeta):
                    $rows = $project['team_json'][$gKey] ?? [];
                    if (empty($rows)) $rows = [['name' => '', 'title' => '', 'url' => '']];
                ?>
                <div class="team-group" data-team-group="<?= esc($gKey) ?>">
                    <h3 class="team-group-title"><?= esc($gMeta['title']) ?></h3>
                    <div class="team-rows" data-team-rows>
                        <?php foreach ($rows as $r):
                            $name  = (string) ($r['name']  ?? '');
                            $titlV = (string) ($r['title'] ?? '');
                            $url   = (string) ($r['url']   ?? '');
                        ?>
                        <div class="team-row" data-team-row>
                            <input type="text" name="<?= esc($gKey) ?>_name[]"
                                   value="<?= esc($name) ?>"
                                   placeholder="Ad Soyad / Stüdyo Adı"
                                   class="team-input team-input-name">
                            <input type="text" name="<?= esc($gKey) ?>_title[]"
                                   value="<?= esc($titlV) ?>"
                                   placeholder="<?= esc($gMeta['titlePlaceholder']) ?>"
                                   class="team-input team-input-title">
                            <input type="url" name="<?= esc($gKey) ?>_url[]"
                                   value="<?= esc($url) ?>"
                                   placeholder="https://www.studio.com"
                                   class="team-input team-input-url">
                            <button type="button" class="team-row-remove" data-team-remove aria-label="Satırı sil">×</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="team-row-add" data-team-add="<?= esc($gKey) ?>">
                        + <?= esc($gMeta['addLabel']) ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">SEO</h2>
                <p class="pe-section-hint">Boş bırakırsan proje adı + site adı otomatik kullanılır.</p>
                <label>
                    <span>Meta Başlık</span>
                    <input type="text" name="meta_title" maxlength="180"
                           value="<?= esc((string) $project['meta_title']) ?>">
                </label>
                <label>
                    <span>Meta Açıklama</span>
                    <textarea name="meta_description" rows="2" maxlength="255"><?= esc((string) $project['meta_description']) ?></textarea>
                </label>
            </section>

        </div>

        <aside class="post-editor-side">

            <section class="pe-card">
                <h2 class="pe-section-title">Yayınla</h2>
                <?php if ($is_admin): ?>
                    <label>
                        <span>Durum</span>
                        <select name="status">
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Taslak</option>
                            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Yayında</option>
                            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Arşiv</option>
                        </select>
                    </label>
                    <label class="checkbox">
                        <input type="hidden" name="featured" value="0">
                        <input type="checkbox" name="featured" value="1" <?= !empty($project['featured']) ? 'checked' : '' ?>>
                        <span>Öne çıkan</span>
                    </label>
                    <p class="pe-helper">Admin olarak doğrudan yayına alabilirsiniz. Öne çıkan projeler portfolyo başında belirginleştirilir.</p>
                <?php else: ?>
                    <input type="hidden" name="status" value="draft">
                    <?php $stage = $project['approval_stage'] ?? 'none'; ?>
                    <?php if ($stage === 'review'): ?>
                        <p class="pe-helper"><span class="badge badge-pending">Onayda</span> Proje admin incelemesinde. Onay sonrası yayına çıkar.</p>
                    <?php elseif ($stage === 'rejected'): ?>
                        <p class="pe-helper"><span class="badge badge-rejected">Reddedildi</span> Proje yayına alınmadı — gerekli düzeltmeleri yapıp tekrar gönderebilirsiniz.</p>
                    <?php elseif ($stage === 'approved'): ?>
                        <p class="pe-helper"><span class="badge badge-published">Yayında</span> Admin tarafından onaylandı.</p>
                    <?php else: ?>
                        <p class="pe-helper">Taslak olarak kaydedilir. Hazır olduğunuzda "İncelemeye Gönder" ile admin onayına yollayın.</p>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="pe-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?= $is_edit ? 'Güncelle' : 'Oluştur' ?>
                    </button>
                </div>
            </section>

            <?php if (!$is_admin && $is_edit && in_array(($project['approval_stage'] ?? 'none'), ['none','rejected'], true)): ?>
            <section class="pe-card pe-approval">
                <h2 class="pe-section-title">◐ İncelemeye Gönder</h2>
                <p class="pe-helper">Hazır olduğunuzda projeyi admin onayına yollayın.</p>
                <form method="post" action="<?= esc(url('/panel/projeler/' . (int) $project['id'] . '/gonder')) ?>" onsubmit="return confirm('Proje admin onayına gönderilecek. Emin misin?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary btn-block">İncelemeye Gönder</button>
                </form>
            </section>
            <?php endif; ?>

            <section class="pe-card">
                <h2 class="pe-section-title">Kapak Görseli</h2>
                <?php
                $mi_name  = 'cover_image';
                $mi_value = (string) $project['cover_image'];
                $mi_label = 'Cover URL';
                $mi_hint  = 'Portfolyo listesinde ve proje sayfası tepesinde görünür.';
                require dirname(__DIR__, 2) . '/partials/admin/media-input.php';
                ?>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Lokasyon</h2>
                <label>
                    <span>Şehir / Bölge (serbest yazı)</span>
                    <input type="text" name="location" maxlength="180"
                           value="<?= esc((string) $project['location']) ?>"
                           placeholder="İstanbul, Süleymaniye">
                </label>
                <label>
                    <span>Enlem (lat)</span>
                    <input type="text" name="lat"
                           value="<?= esc((string) $project['lat']) ?>"
                           placeholder="41.0150">
                </label>
                <label>
                    <span>Boylam (lng)</span>
                    <input type="text" name="lng"
                           value="<?= esc((string) $project['lng']) ?>"
                           placeholder="28.9700">
                </label>
                <p class="pe-helper">Harita için koordinat şart. <a href="https://www.openstreetmap.org/" target="_blank" rel="noopener">openstreetmap.org</a>'dan al.</p>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Adres (Schema.org)</h2>
                <p class="pe-helper">
                    Boş bırakırsan bu alanlar Schema.org JSON-LD'de <strong>hiç gözükmez</strong>
                    (Google'a sallama bilgi vermez). Sadece bildiğin alanları doldur.
                </p>
                <label>
                    <span>İlçe (addressLocality)</span>
                    <input type="text" name="address_locality" maxlength="100"
                           value="<?= esc((string) ($project['address_locality'] ?? '')) ?>"
                           placeholder="Osmangazi">
                </label>
                <label>
                    <span>İl / Bölge (addressRegion)</span>
                    <input type="text" name="address_region" maxlength="100"
                           value="<?= esc((string) ($project['address_region'] ?? '')) ?>"
                           placeholder="Bursa">
                </label>
                <label>
                    <span>Posta Kodu (postalCode)</span>
                    <input type="text" name="postal_code" maxlength="20"
                           value="<?= esc((string) ($project['postal_code'] ?? '')) ?>"
                           placeholder="16050">
                </label>
                <p class="pe-helper">Ülke kodu otomatik <code>TR</code> olarak atanır.</p>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Detaylar</h2>
                <label>
                    <span>Başlangıç Yılı</span>
                    <input type="number" name="year_started" min="1500" max="2100"
                           value="<?= esc((string) $project['year_started']) ?>">
                </label>
                <label>
                    <span>Bitiş Yılı</span>
                    <input type="number" name="year_completed" min="1500" max="2100"
                           value="<?= esc((string) $project['year_completed']) ?>">
                </label>
                <label>
                    <span>Yüzölçümü (m²)</span>
                    <input type="number" name="surface_m2" min="0"
                           value="<?= esc((string) $project['surface_m2']) ?>">
                </label>
                <label>
                    <span>Yapı Tipi</span>
                    <select name="building_type">
                        <?php
                        $currentBt = (string) ($project['building_type'] ?? 'diger');
                        foreach (\App\Models\Project::BUILDING_TYPES as $btKey => $btLabel):
                        ?>
                            <option value="<?= esc($btKey) ?>" <?= $currentBt === $btKey ? 'selected' : '' ?>>
                                <?= esc($btLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="muted">Projenin TÜRÜ — konut, otel, kamu binası vb. Filtre ve haritada kategorize için.</small>
                </label>
                <label>
                    <span>Rol (sizin)</span>
                    <select name="role">
                        <option value="arsitekt" <?= $project['role'] === 'arsitekt' ? 'selected' : '' ?>>Müellif Mimar</option>
                        <option value="musavir" <?= $project['role'] === 'musavir' ? 'selected' : '' ?>>Mimari Müşavir</option>
                        <option value="kontrol" <?= $project['role'] === 'kontrol' ? 'selected' : '' ?>>Kontrol</option>
                        <option value="danisman" <?= $project['role'] === 'danisman' ? 'selected' : '' ?>>Danışman</option>
                        <option value="arastirma" <?= $project['role'] === 'arastirma' ? 'selected' : '' ?>>Araştırmacı</option>
                        <option value="diger" <?= $project['role'] === 'diger' ? 'selected' : '' ?>>Diğer</option>
                    </select>
                    <small class="muted">Bu projedeki SİZİN rolünüz — proje sayfasında "Osman Doğan, Müellif Mimar" şeklinde geçer.</small>
                </label>
                <label>
                    <span>Müşteri / Kurum</span>
                    <input type="text" name="client" maxlength="180"
                           value="<?= esc((string) $project['client']) ?>">
                </label>
            </section>

            <?php if ($is_edit): ?>
            <section class="pe-card">
                <h2 class="pe-section-title">URL</h2>
                <label>
                    <span>Slug</span>
                    <input type="text" name="slug"
                           value="<?= esc((string) $project['slug']) ?>"
                           placeholder="boş bırakırsan proje adından üretilir">
                </label>
                <p class="pe-helper">Public URL: <code>/proje/<?= esc((string) $project['slug']) ?></code></p>
                <p class="pe-helper">Slug'u boşaltıp <strong>Güncelle</strong> dersen, proje adından <code>slugify()</code> ile yeni slug üretilir (Türkçe karakterler ASCII'ye dönüşür).</p>
            </section>
            <?php endif; ?>

        </aside>
    </div>
</form>

<script src="<?= esc(asset('js/editor.js')) ?>" defer></script>
<script src="<?= esc(asset('js/media-picker.js')) ?>" defer></script>
<script src="<?= esc(asset('js/media-input.js')) ?>" defer></script>
<script src="<?= esc(asset('js/team-builder.js')) ?>" defer></script>
