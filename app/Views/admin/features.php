<?php
/**
 * @var array<string,array{label:string,desc:string}> $flags
 * @var array<string,bool> $current
 * @var int $active_count
 * @var int $total_count
 */
\App\Core\View::layout('base');
?>
<section class="hero">
    <h1>Özellikler</h1>
    <p class="lead muted">
        Tier 5 özellikleri — her birini buradan açıp kapatabilirsin. Kapalı bir özellik
        public ve admin tarafta tamamen gizlenir, ilgili endpoint'ler 404 döner.
    </p>
    <p>
        <span class="badge badge-published"><?= (int) $active_count ?> aktif</span>
        <span class="badge badge-draft"><?= (int) ($total_count - $active_count) ?> kapalı</span>
        <span class="muted">· Toplam <?= (int) $total_count ?></span>
    </p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc(url('/admin/ozellikler')) ?>">
    <?= csrf_field() ?>

    <div class="features-grid">
        <?php foreach ($flags as $key => $meta): ?>
            <label class="feature-card <?= !empty($current[$key]) ? 'is-on' : 'is-off' ?>">
                <input type="hidden" name="<?= esc($key) ?>" value="0">
                <input type="checkbox" name="<?= esc($key) ?>" value="1"
                       <?= !empty($current[$key]) ? 'checked' : '' ?>>
                <div class="feature-body">
                    <strong><?= esc($meta['label']) ?></strong>
                    <small class="muted"><?= esc($meta['desc']) ?></small>
                    <code class="feature-key"><?= esc($key) ?></code>
                </div>
                <span class="feature-toggle" aria-hidden="true">
                    <span class="toggle-track"></span>
                    <span class="toggle-knob"></span>
                </span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="form-actions" style="margin-top:2rem">
        <button class="btn btn-primary" type="submit">Değişiklikleri Kaydet</button>
        <a href="<?= esc(url('/admin/ayarlar')) ?>" class="btn"
           title="Tüm Site Ayarları (62+ ayar)">
            Site Ayarları'na Git
        </a>
    </div>
</form>

<style>
.features-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
  gap:1rem;
  margin-top:2rem;
}
.feature-card{
  display:flex;
  align-items:flex-start;
  gap:.85rem;
  padding:1rem 1.25rem;
  background:var(--bone);
  border:1px solid var(--hair-2);
  cursor:pointer;
  transition:background 200ms ease, border-color 200ms ease, transform 200ms ease;
  position:relative;
}
.feature-card:hover{background:var(--bone-2);border-color:var(--soot)}
.feature-card.is-on{
  border-color:var(--cobalt);
  background:rgba(31,58,138,.03);
}
.feature-card input[type="checkbox"]{display:none}
.feature-body{
  flex:1;
  display:flex;
  flex-direction:column;
  gap:.25rem;
}
.feature-body strong{
  font-family:var(--serif);
  font-size:1.02rem;
  font-weight:600;
  color:var(--soot);
}
.feature-body small{
  font-size:.82rem;
  line-height:1.45;
}
.feature-key{
  font-family:var(--mono);
  font-size:.68rem;
  color:var(--ash);
  background:var(--bone-2);
  padding:.1em .45em;
  width:fit-content;
  margin-top:.25rem;
}
.feature-toggle{
  position:relative;
  flex-shrink:0;
  width:38px;
  height:22px;
}
.toggle-track{
  position:absolute;
  inset:0;
  background:var(--hair-2);
  border-radius:999px;
  transition:background 200ms ease;
}
.toggle-knob{
  position:absolute;
  top:2px;
  left:2px;
  width:18px;
  height:18px;
  background:var(--bone);
  border-radius:50%;
  box-shadow:0 1px 3px rgba(0,0,0,.2);
  transition:transform 200ms ease;
}
.feature-card.is-on .toggle-track{background:var(--cobalt)}
.feature-card.is-on .toggle-knob{transform:translateX(16px)}
</style>

<script>
// Live toggle visual feedback — backend submit'i değiştirmez, sadece sınıf değişir.
document.querySelectorAll('.feature-card').forEach(function(card){
    var cb = card.querySelector('input[type="checkbox"]');
    if (!cb) return;
    cb.addEventListener('change', function(){
        card.classList.toggle('is-on', cb.checked);
        card.classList.toggle('is-off', !cb.checked);
    });
});
</script>
