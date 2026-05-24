<?php
/**
 * Quick Edit Modal (Tier 5 feature 4.3)
 * — posts listesinde "⚡ Hızlı düzenle" butonuyla açılır
 * — title / slug / status / featured (admin-editor) inline güncelleme
 * — AJAX POST → JSON response → row in-place güncellenir
 *
 * Feature flag: quick_edit_enabled (parent view kontrol eder)
 */
$_canFeature = function_exists('feature') && feature('editors_pick_enabled')
    && in_array(\App\Services\AuthService::user()['role'] ?? '', ['admin', 'editor'], true);
?>
<div class="qe-modal" id="quick-edit-modal" data-csrf="<?= esc(csrf_token()) ?>" hidden role="dialog" aria-modal="true" aria-labelledby="qe-title">
    <div class="qe-backdrop" data-qe-close></div>
    <div class="qe-panel">
        <header class="qe-head">
            <h2 id="qe-title">⚡ Hızlı Düzenle</h2>
            <button type="button" class="qe-close" data-qe-close aria-label="Kapat">×</button>
        </header>
        <form class="qe-form" data-qe-form>
            <label>
                <span>Başlık</span>
                <input type="text" name="title" minlength="4" maxlength="220" required>
            </label>
            <label>
                <span>Slug</span>
                <input type="text" name="slug" maxlength="240" placeholder="otomatik üretilir">
                <small class="muted">Boş bırakırsan başlıktan üretilir.</small>
            </label>
            <label>
                <span>Durum</span>
                <select name="status">
                    <option value="draft">Taslak</option>
                    <option value="pending">Onay Bekliyor</option>
                    <option value="published">Yayında</option>
                    <option value="archived">Arşiv</option>
                </select>
            </label>
            <?php if ($_canFeature): ?>
            <label class="qe-featured">
                <input type="hidden" name="featured" value="0">
                <input type="checkbox" name="featured" value="1">
                <span>⭐ Editörün Seçimi</span>
            </label>
            <?php endif; ?>
            <div class="qe-actions">
                <button type="button" class="btn" data-qe-close>İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
            <p class="qe-status muted" data-qe-status></p>
        </form>
    </div>
</div>

<style>
.qe-modal{ position:fixed; inset:0; z-index:9000; display:flex; align-items:center; justify-content:center; padding:1rem; }
.qe-modal[hidden]{ display:none; }
.qe-backdrop{ position:absolute; inset:0; background:rgba(17,17,17,.6); animation:qe-in 180ms var(--ease); }
.qe-panel{ position:relative; width:100%; max-width:540px; background:var(--bone); border:1px solid var(--soot); border-top:3px solid var(--cobalt); padding:1.75rem 2rem; animation:qe-up 240ms var(--ease); }
.qe-head{ display:flex; justify-content:space-between; align-items:center; margin:0 0 1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--hair); }
.qe-head h2{ margin:0; font-family:var(--serif); font-size:1.2rem; font-weight:600; letter-spacing:-.015em; color:var(--soot); }
.qe-close{ background:transparent; border:0; font-size:1.6rem; line-height:1; cursor:pointer; color:var(--ash); padding:0; width:32px; height:32px; transition:color 200ms var(--ease); }
.qe-close:hover{ color:var(--soot); }
.qe-form{ display:flex; flex-direction:column; gap:1.25rem; }
.qe-form label{ display:flex; flex-direction:column; gap:.4rem; }
.qe-form label > span{ font-family:var(--mono); font-size:.66rem; letter-spacing:var(--tracked); text-transform:uppercase; font-weight:700; color:var(--soot); }
.qe-form input[type="text"], .qe-form select{ padding:.65rem 0; border:0; border-bottom:1px solid var(--hair-2); background:transparent; font-family:var(--sans); font-size:1rem; color:var(--soot); transition:border-color 200ms var(--ease); }
.qe-form input[type="text"]:focus, .qe-form select:focus{ outline:0; border-bottom-color:var(--cobalt); }
.qe-featured{ flex-direction:row !important; align-items:center; gap:.6rem !important; padding-top:.5rem; }
.qe-featured input[type="checkbox"]{ width:18px; height:18px; accent-color:var(--cobalt); }
.qe-actions{ display:flex; gap:.75rem; justify-content:flex-end; margin-top:1rem; padding-top:1rem; border-top:1px solid var(--hair); }
.qe-status{ font-size:.85rem; margin:.25rem 0 0; min-height:1.1em; }
.qe-status.is-ok{ color:#15803d; }
.qe-status.is-err{ color:#b91c1c; }
@keyframes qe-in { from { opacity:0 } to { opacity:1 } }
@keyframes qe-up { from { opacity:0; transform:translateY(8px) } to { opacity:1; transform:none } }
</style>
