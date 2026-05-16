<?php
/**
 * Dizi formu (yeni/düzenle) — Atelier post-editor patternine uyumlu.
 * @var array $series
 * @var array $posts  Bu diziye atfedilen yazılar (sadece edit'te)
 */
\App\Core\View::layout('base');

$isEdit = !empty($series['id']);
$action = $isEdit ? url('/admin/diziler/' . (int) $series['id']) : url('/admin/diziler');
?>

<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/admin/diziler')) ?>" class="muted">← Tüm Diziler</a>
        </p>
        <h1><?= $isEdit ? 'Diziyi Düzenle' : 'Yeni Dizi' ?></h1>
        <p class="post-editor-meta">
            <?php if ($isEdit): ?>
                <span class="badge badge-published"><?= (int) $series['post_count'] ?> bölüm</span>
                <span class="muted">·</span>
                <span class="muted">/dizi/<?= esc((string) ($series['slug'] ?? '')) ?></span>
            <?php else: ?>
                <span class="badge badge-draft">Yeni</span>
            <?php endif; ?>
        </p>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc($action) ?>" class="post-editor" id="series-form">
    <?= csrf_field() ?>

    <header class="post-editor-head">
        <input type="text"
               name="name"
               class="post-title-input"
               required minlength="2" maxlength="180"
               placeholder="Dizi adı (örn: Sinan Külliyatı)…"
               value="<?= esc((string) ($series['name'] ?? '')) ?>">
    </header>

    <div class="post-editor-grid">
        <div class="post-editor-main">

            <section class="pe-section">
                <h2 class="pe-section-title">Tanıtım</h2>
                <p class="pe-section-hint">Dizinin amacı, kapsamı ve hedef kitlesi. Yayında ve SEO description olarak kullanılır.</p>
                <span class="visually-hidden" id="rich-body-label">Açıklama</span>
                <textarea id="rich-body"
                          name="description"
                          rows="8"
                          data-format="html"
                          maxlength="5000"
                          aria-labelledby="rich-body-label"><?= esc((string) ($series['description'] ?? '')) ?></textarea>
                <input type="hidden" name="body_format" value="html">
            </section>

            <?php if ($isEdit && !empty($posts)): ?>
            <section class="pe-section">
                <h2 class="pe-section-title">Bu Dizideki Yazılar</h2>
                <p class="pe-section-hint">Sıra düzenlemek için yazı düzenleme ekranındaki "Dizi Sırası" alanını kullan.</p>
                <table class="table">
                    <caption class="visually-hidden">Dizideki yazılar — <?= count($posts) ?> kayıt</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width:60px">Sıra</th>
                            <th scope="col">Başlık</th>
                            <th scope="col">Durum</th>
                            <th scope="col">Yayın</th>
                            <th scope="col"><span class="visually-hidden">İşlemler</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $p): ?>
                            <tr>
                                <td><strong><?= (int) ($p['series_position'] ?? 0) ?></strong></td>
                                <td><?= esc($p['title']) ?></td>
                                <td><span class="badge badge-<?= esc($p['status']) ?>"><?= esc($p['status']) ?></span></td>
                                <td><small class="muted"><?= !empty($p['published_at']) ? esc(date('d/m/Y', strtotime((string) $p['published_at']))) : '—' ?></small></td>
                                <td>
                                    <a class="btn btn-link" href="<?= esc(url('/panel/yazilar/' . (int) $p['id'] . '/duzenle')) ?>">Düzenle</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <?php endif; ?>

        </div>

        <aside class="post-editor-side">

            <section class="pe-card">
                <h2 class="pe-section-title">Yayınla</h2>
                <p class="pe-helper">Diziye bağlanan yazılar dizi sayfasında otomatik sıralanır.</p>
                <div class="pe-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?= $isEdit ? 'Güncelle' : 'Oluştur' ?>
                    </button>
                </div>
            </section>

            <?php if ($isEdit): ?>
            <section class="pe-card">
                <h2 class="pe-section-title">URL</h2>
                <label>
                    <span>Slug</span>
                    <input type="text" name="slug" maxlength="220"
                           value="<?= esc((string) ($series['slug'] ?? '')) ?>">
                </label>
                <p class="pe-helper">Yayında URL: <code>/dizi/<?= esc((string) ($series['slug'] ?? '')) ?></code></p>
            </section>
            <?php endif; ?>

            <section class="pe-card">
                <h2 class="pe-section-title">Kapak Görseli</h2>
                <?php
                $mi_name  = 'cover_image';
                $mi_value = (string) ($series['cover_image'] ?? '');
                $mi_label = 'Dizi Kapağı';
                $mi_hint  = 'Diziler listesinde ve dizi sayfası tepesinde görünür.';
                require dirname(__DIR__, 2) . '/partials/admin/media-input.php';
                ?>
            </section>

        </aside>
    </div>
</form>

<script src="<?= esc(asset('js/editor.js')) ?>" defer></script>
<script src="<?= esc(asset('js/media-picker.js')) ?>" defer></script>
<script src="<?= esc(asset('js/media-input.js')) ?>" defer></script>
