<?php
/**
 * Kategori formu (yeni/düzenle) — Atelier post-editor patternine uyumlu.
 *
 * @var array $category
 * @var bool  $is_edit
 * @var array $categories  Parent dropdown için
 */
\App\Core\View::layout('base');

$action = $is_edit
    ? url('/admin/kategoriler/' . (int) $category['id'])
    : url('/admin/kategoriler');
?>

<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/admin/kategoriler')) ?>" class="muted">← Tüm Kategoriler</a>
        </p>
        <h1><?= $is_edit ? 'Kategoriyi Düzenle' : 'Yeni Kategori' ?></h1>
        <?php if ($is_edit): ?>
            <p class="post-editor-meta">
                <span class="badge <?= !empty($category['is_active']) ? 'badge-published' : 'badge-draft' ?>">
                    <?= !empty($category['is_active']) ? 'Aktif' : 'Gizli' ?>
                </span>
                <span class="muted">·</span>
                <span class="muted">URL: /<?= esc((string) $category['slug']) ?></span>
            </p>
        <?php endif; ?>
    </div>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc($action) ?>" class="post-editor" id="cat-form">
    <?= csrf_field() ?>

    <header class="post-editor-head">
        <input type="text"
               name="name"
               class="post-title-input"
               required minlength="2" maxlength="150"
               placeholder="Kategori adı…"
               value="<?= esc((string) $category['name']) ?>">
    </header>

    <div class="post-editor-grid">
        <div class="post-editor-main">

            <section class="pe-section">
                <h2 class="pe-section-title">Açıklama</h2>
                <p class="pe-section-hint">Kategori sayfası tepesinde gösterilir. SEO için faydalı, kısa ve net tut.</p>
                <label class="pe-label-hidden">
                    <span class="visually-hidden">Açıklama</span>
                    <textarea name="description" rows="6"
                              placeholder="Bu kategori hangi konuları kapsar?"><?= esc((string) $category['description']) ?></textarea>
                </label>
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">SEO</h2>
                <p class="pe-section-hint">Boş bırakırsan kategori adı + site adı otomatik kullanılır.</p>
                <label>
                    <span>Meta Başlık</span>
                    <input type="text" name="meta_title" maxlength="180"
                           value="<?= esc((string) $category['meta_title']) ?>">
                </label>
                <label>
                    <span>Meta Açıklama</span>
                    <textarea name="meta_description" rows="2" maxlength="255"><?= esc((string) $category['meta_description']) ?></textarea>
                </label>
            </section>

        </div>

        <aside class="post-editor-side">

            <section class="pe-card">
                <h2 class="pe-section-title">Yayınla</h2>
                <label class="checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($category['is_active']) ? 'checked' : '' ?>>
                    <span>Aktif (sitede görünür)</span>
                </label>
                <p class="pe-helper">Pasif kategoriler menüde ve URL'lerde görünmez ama içerikleri kaybolmaz.</p>
                <div class="pe-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?= $is_edit ? 'Güncelle' : 'Oluştur' ?>
                    </button>
                </div>
            </section>

            <?php if ($is_edit): ?>
            <section class="pe-card">
                <h2 class="pe-section-title">URL</h2>
                <label>
                    <span>Slug</span>
                    <input type="text" name="slug" maxlength="180"
                           value="<?= esc((string) $category['slug']) ?>">
                </label>
                <p class="pe-helper">URL'de görünen kısım. Değiştirirsen eski URL 404 olur — gerekirse <a href="<?= esc(url('/admin/yonlendirmeler')) ?>">301 yönlendirme</a> ekle.</p>
            </section>
            <?php endif; ?>

            <section class="pe-card">
                <h2 class="pe-section-title">Hiyerarşi</h2>
                <label>
                    <span>Üst Kategori</span>
                    <select name="parent_id">
                        <option value="">— Yok (kök) —</option>
                        <?php foreach ($categories as $c):
                            if ((int) $c['id'] === (int) $category['id']) continue; // self-parent engelle
                        ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= ((int) ($category['parent_id'] ?? 0) === (int) $c['id']) ? 'selected' : '' ?>>
                                <?= esc($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Sıra</span>
                    <input type="number" name="position" min="0" max="999"
                           value="<?= (int) $category['position'] ?>">
                </label>
                <p class="pe-helper">Düşük sayı → menüde önce görünür.</p>
            </section>

        </aside>
    </div>
</form>
