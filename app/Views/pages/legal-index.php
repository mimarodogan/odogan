<?php \App\Core\View::layout('base'); ?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<section class="legal-page">
    <header class="legal-head">
        <p class="legal-eyebrow">YASAL METİNLER</p>
        <h1>Sözleşmeler</h1>
        <p style="font-family:var(--serif);font-style:italic;color:var(--soot-2);font-size:1.1rem;margin:.5rem 0 0">
            Üyelik koşulları, yazar sözleşmesi, gizlilik politikası ve kullanım koşulları.
        </p>
    </header>

    <ul class="legal-list" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0;border-top:1px solid var(--hair)">
        <?php foreach ($list as $doc): if (!$doc['is_active']) continue; ?>
            <li style="padding:1.5rem 0;border-bottom:1px solid var(--hair)">
                <a href="<?= esc(url('/sozlesmeler/' . $doc['slug'])) ?>" style="display:flex;justify-content:space-between;align-items:baseline;text-decoration:none;color:var(--soot)">
                    <span style="font-family:var(--serif);font-size:1.25rem;font-weight:600;letter-spacing:-.015em"><?= esc($doc['title']) ?></span>
                    <span style="font-family:var(--mono);font-size:.7rem;letter-spacing:var(--tracked);text-transform:uppercase;color:var(--cobalt)">v<?= (int) $doc['version'] ?> · OKU →</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<style>
.legal-page{ max-width:var(--read); margin:2rem auto 5rem; padding:0 var(--gutter); }
.legal-head{ margin-bottom:3rem; padding-bottom:2rem; border-bottom:1px solid var(--soot); }
.legal-eyebrow{
  font-family:var(--mono);
  font-size:.66rem;
  letter-spacing:var(--tracked-l);
  text-transform:uppercase;
  color:var(--ash);
  font-weight:600;
  margin:0 0 .75rem;
}
.legal-head h1{
  font-family:var(--serif);
  font-size:clamp(2rem,4vw,2.8rem);
  font-weight:600;
  margin:0;
  letter-spacing:-.025em;
  line-height:1.05;
}
.legal-list a:hover span:first-child{ color:var(--cobalt); }
</style>
