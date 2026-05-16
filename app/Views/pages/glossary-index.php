<?php \App\Core\View::layout('base'); ?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<section class="glossary-page">
    <header class="glossary-head">
        <p class="glossary-eyebrow">REFERANS · TERMİNOLOJİ</p>
        <h1>Mimari Sözlük</h1>
        <p class="glossary-lead">Mimari, yapı, restorasyon ve mühendislik terimleri.</p>

        <form method="get" class="glossary-search" role="search">
            <label for="glossary-search-input" class="visually-hidden">Sözlükte terim ara</label>
            <input type="search" id="glossary-search-input" name="q" placeholder="Terim ara…" minlength="2" aria-label="Sözlükte terim ara">
            <button type="submit">Ara</button>
        </form>
    </header>

    <?php if (empty($grouped)): ?>
        <p class="muted" style="text-align:center;padding:3rem 0">Henüz terim eklenmedi.</p>
    <?php else: ?>
        <nav class="glossary-letters" aria-label="Harfler">
            <?php foreach (array_keys($grouped) as $letter): ?>
                <a href="#letter-<?= esc($letter) ?>"><?= esc($letter) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php foreach ($grouped as $letter => $items): ?>
            <section id="letter-<?= esc($letter) ?>" class="glossary-section">
                <h2 class="glossary-letter"><?= esc($letter) ?></h2>
                <dl class="glossary-list">
                    <?php foreach ($items as $g): ?>
                        <dt><a href="<?= esc(url('/sozluk/' . $g['slug'])) ?>"><?= esc($g['term']) ?></a></dt>
                        <dd><?= mb_substr(strip_tags((string) $g['definition']), 0, 200) ?>…</dd>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<style>
.glossary-page{ max-width:880px; margin:2rem auto 5rem; padding:0 var(--gutter); }
.glossary-head{ margin-bottom:3rem; padding-bottom:2rem; border-bottom:1px solid var(--soot); }
.glossary-eyebrow{
  font-family:var(--mono);
  font-size:.66rem;
  letter-spacing:var(--tracked-l);
  text-transform:uppercase;
  color:var(--ash);
  font-weight:700;
  margin:0 0 .75rem;
}
.glossary-head h1{
  font-family:var(--serif);
  font-size:clamp(2rem,4vw,2.8rem);
  font-weight:600;
  margin:0 0 .75rem;
  letter-spacing:-.025em;
  line-height:1.05;
}
.glossary-lead{
  font-family:var(--serif);
  font-style:italic;
  font-size:1.1rem;
  color:var(--soot-2);
  margin:0 0 1.5rem;
}
.glossary-search{ display:flex; gap:.5rem; max-width:420px; }
.glossary-search input{
  flex:1;
  padding:.65rem 1rem;
  border:1px solid var(--hair-2);
  background:#fff;
  font-family:var(--serif);
  font-size:1rem;
}
.glossary-search button{
  padding:.65rem 1.25rem;
  border:1px solid var(--cobalt);
  background:var(--cobalt);
  color:#fff;
  font-family:var(--mono);
  font-size:.7rem;
  letter-spacing:var(--tracked);
  text-transform:uppercase;
  font-weight:700;
  cursor:pointer;
}
.glossary-letters{
  display:flex;
  flex-wrap:wrap;
  gap:.5rem;
  margin-bottom:2.5rem;
  padding-bottom:1.5rem;
  border-bottom:1px solid var(--hair);
}
.glossary-letters a{
  font-family:var(--serif);
  font-size:1.15rem;
  font-weight:600;
  color:var(--soot);
  text-decoration:none;
  width:2.4rem;
  height:2.4rem;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border:1px solid var(--hair);
  transition:all 200ms var(--ease);
}
.glossary-letters a:hover{ background:var(--cobalt); color:#fff; border-color:var(--cobalt); }
.glossary-section{ margin-bottom:3rem; }
.glossary-letter{
  font-family:var(--serif);
  font-size:3rem;
  font-weight:600;
  color:var(--cobalt);
  margin:0 0 1rem;
  letter-spacing:-.03em;
  border-bottom:2px solid var(--cobalt);
  padding-bottom:.5rem;
  width:fit-content;
}
.glossary-list{ margin:0; padding:0; }
.glossary-list dt{
  font-family:var(--serif);
  font-size:1.2rem;
  font-weight:600;
  margin:1.25rem 0 .4rem;
}
.glossary-list dt a{ color:var(--soot); text-decoration:none; }
.glossary-list dt a:hover{ color:var(--cobalt); }
.glossary-list dd{
  font-family:var(--serif);
  font-size:.98rem;
  line-height:1.55;
  color:var(--soot-2);
  margin:0;
}
</style>
