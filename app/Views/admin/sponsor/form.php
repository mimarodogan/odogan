<?php
/**
 * Sponsor slot formu — Atelier post-editor patternine uyumlu.
 * @var array $slot
 * @var bool  $is_edit
 */
\App\Core\View::layout('base');

$action = $is_edit
    ? url('/admin/sponsor/' . $slot['id'] . '/guncelle')
    : url('/admin/sponsor/kaydet');

$ctr = ($slot['view_count'] ?? 0) > 0
    ? round(($slot['click_count'] / $slot['view_count']) * 100, 2)
    : 0;
?>

<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/admin/sponsor')) ?>" class="muted">← Tüm Sponsorlar</a>
        </p>
        <h1><?= $is_edit ? 'Sponsor Düzenle' : 'Yeni Sponsor Slot' ?></h1>
        <p class="post-editor-meta">
            <?php if ($is_edit): ?>
                <span class="badge <?= !empty($slot['active']) ? 'badge-published' : 'badge-draft' ?>">
                    <?= !empty($slot['active']) ? 'Aktif' : 'Pasif' ?>
                </span>
                <span class="muted">·</span>
                <span class="muted"><?= esc($slot['placement']) ?> yerleşimi</span>
            <?php else: ?>
                <span class="badge badge-draft">Yeni</span>
            <?php endif; ?>
        </p>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc($action) ?>" class="post-editor" id="sponsor-form">
    <?= csrf_field() ?>

    <header class="post-editor-head">
        <input type="text"
               name="name"
               class="post-title-input"
               required maxlength="180"
               placeholder="Sponsor adı (örn: Hassa Mimarlık)…"
               value="<?= esc((string) $slot['name']) ?>">
    </header>

    <div class="post-editor-grid">
        <div class="post-editor-main">

            <section class="pe-section">
                <h2 class="pe-section-title">Tanıtım</h2>
                <p class="pe-section-hint">Sponsor kartında görünür — kısa, etkileyici ve net bir cümle.</p>
                <label>
                    <span>Tagline</span>
                    <input type="text" name="tagline" maxlength="255"
                           value="<?= esc((string) $slot['tagline']) ?>"
                           placeholder="Restorasyon ve koruma — 25 yıl deneyim">
                </label>
                <?php
                $mi_name  = 'image_url';
                $mi_value = (string) $slot['image_url'];
                $mi_label = 'Sponsor Görseli';
                $mi_hint  = 'Önerilen: 160×90 px, optimize WebP/PNG. Logo veya banner.';
                require dirname(__DIR__, 2) . '/partials/admin/media-input.php';
                ?>
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">Yönlendirme</h2>
                <p class="pe-section-hint">Tıklama izleme URL: <code>/sponsor/git/<?= $is_edit ? (int) $slot['id'] : '{id}' ?></code> → bu hedefe redirect.</p>
                <label class="pe-label-hidden">
                    <span class="visually-hidden">Hedef URL</span>
                    <input type="url" name="target_url" required maxlength="500"
                           value="<?= esc((string) $slot['target_url']) ?>"
                           placeholder="https://sponsor-sitesi.com">
                </label>
            </section>

            <?php if ($is_edit): ?>
            <section class="pe-section">
                <h2 class="pe-section-title">Performans</h2>
                <div class="stat-row">
                    <div class="stat-pill">
                        <span>Görünüm</span>
                        <strong><?= number_format((int) $slot['view_count'], 0, ',', '.') ?></strong>
                    </div>
                    <div class="stat-pill">
                        <span>Tıklama</span>
                        <strong><?= number_format((int) $slot['click_count'], 0, ',', '.') ?></strong>
                    </div>
                    <div class="stat-pill">
                        <span>CTR</span>
                        <strong>%<?= number_format($ctr, 2) ?></strong>
                    </div>
                </div>
            </section>
            <?php endif; ?>

        </div>

        <aside class="post-editor-side">

            <section class="pe-card">
                <h2 class="pe-section-title">Yayınla</h2>
                <label class="checkbox">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" <?= !empty($slot['active']) ? 'checked' : '' ?>>
                    <span>Aktif (sitede dönüşümde)</span>
                </label>
                <p class="pe-helper">Pasif slotlar gösterilmez ama kayıt arşivde tutulur.</p>
                <div class="pe-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?= $is_edit ? 'Güncelle' : 'Ekle' ?>
                    </button>
                </div>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Yerleşim</h2>
                <label>
                    <span>Konum</span>
                    <select name="placement">
                        <option value="newsletter" <?= $slot['placement'] === 'newsletter' ? 'selected' : '' ?>>Bülten</option>
                        <option value="sidebar" <?= $slot['placement'] === 'sidebar' ? 'selected' : '' ?>>Sidebar</option>
                        <option value="below_post" <?= $slot['placement'] === 'below_post' ? 'selected' : '' ?>>Yazı Altı</option>
                        <option value="header" <?= $slot['placement'] === 'header' ? 'selected' : '' ?>>Header</option>
                    </select>
                </label>
                <label>
                    <span>Ağırlık</span>
                    <input type="number" name="weight" min="1" max="100"
                           value="<?= (int) ($slot['weight'] ?? 1) ?>">
                </label>
                <p class="pe-helper">Aynı yerleşimde birden çok aktif sponsor varsa, yüksek ağırlık → daha sık gösterim.</p>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Tarih Aralığı</h2>
                <label>
                    <span>Başlangıç</span>
                    <input type="datetime-local" name="starts_at"
                           value="<?= esc((string) $slot['starts_at']) ?>">
                </label>
                <label>
                    <span>Bitiş</span>
                    <input type="datetime-local" name="ends_at"
                           value="<?= esc((string) $slot['ends_at']) ?>">
                </label>
                <p class="pe-helper">Boş = hemen aktif / süresiz. Bitiş tarihi geçince otomatik gösterilmez.</p>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Etik</h2>
                <p class="pe-helper">
                    Sponsor kartı her yerleşimde "Sponsor" etiketiyle gösterilir.
                    Link <code>rel="nofollow sponsored noopener"</code> ile çıkar — SEO uyumlu ve şeffaf.
                </p>
            </section>

        </aside>
    </div>
</form>

<script src="<?= esc(asset('js/media-picker.js')) ?>" defer></script>
<script src="<?= esc(asset('js/media-input.js')) ?>" defer></script>
