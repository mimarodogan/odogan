<?php \App\Core\View::layout('base'); ?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<article class="legal-page">
    <header class="legal-head">
        <p class="legal-eyebrow">SÖZLEŞME · YASAL METİN</p>
        <h1><?= esc($doc['title']) ?></h1>
        <p class="legal-version">
            Sürüm <strong>v<?= (int) $doc['version'] ?></strong>
            · Son güncelleme: <time datetime="<?= esc(date('c', strtotime((string) $doc['updated_at']))) ?>"><?= esc(tr_date($doc['updated_at'])) ?></time>
        </p>
    </header>

    <section class="legal-body">
        <?= $doc['body_html'] ?>
    </section>
</article>

<style>
.legal-page{ max-width:var(--read); margin:2rem auto 5rem; padding:0 var(--gutter); }
.legal-head{
  margin-bottom:3rem;
  padding-bottom:2rem;
  border-bottom:1px solid var(--soot);
}
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
  margin:0 0 .75rem;
  letter-spacing:-.025em;
  line-height:1.05;
}
.legal-version{
  font-family:var(--mono);
  font-size:.7rem;
  letter-spacing:var(--tracked);
  text-transform:uppercase;
  color:var(--ash);
  margin:0;
}
.legal-body{
  font-family:var(--serif);
  font-size:1.05rem;
  line-height:1.7;
  color:var(--soot);
}
.legal-body h2{
  font-family:var(--serif);
  font-size:1.4rem;
  font-weight:600;
  margin:2.5rem 0 .75rem;
  letter-spacing:-.015em;
}
.legal-body h2::before{ content:"§ "; color:var(--cobalt); font-style:italic; font-weight:500; }
.legal-body h3{ font-family:var(--serif); font-size:1.15rem; margin:1.75rem 0 .5rem; font-weight:600; }
.legal-body p{ margin:1rem 0; }
.legal-body ul, .legal-body ol{ margin:1rem 0 1rem 1.5rem; }
.legal-body li{ margin:.4rem 0; }
.legal-body a{ color:var(--cobalt); text-decoration:underline; }
.legal-body strong{ font-weight:700; }
.legal-body blockquote{
  border-left:2px solid var(--cobalt);
  padding:.5rem 0 .5rem 1.25rem;
  margin:1.25rem 0;
  font-style:italic;
  color:var(--soot-2);
}
</style>
