<?php \App\Core\View::layout('base'); ?>
<section style="max-width:var(--read);margin:5rem auto;padding:0 var(--gutter);text-align:center">
    <p style="font-family:var(--mono);font-size:.66rem;letter-spacing:var(--tracked-l);text-transform:uppercase;color:var(--ash);font-weight:700">HESAP SİLİNDİ</p>
    <h1 style="font-family:var(--serif);font-size:2.4rem;margin:.75rem 0 1rem">Vedalaşıyoruz</h1>
    <p style="font-family:var(--serif);font-style:italic;color:var(--soot-2);font-size:1.15rem;line-height:1.6;margin:0 0 2rem">
        Hesabınız soft delete edildi. 30 gün içinde destek ekibimize ulaşırsanız geri alabilirsiniz; sonrasında veriler kalıcı silinir.
    </p>
    <a href="<?= esc(url('/')) ?>" class="btn btn-primary">Anasayfaya Dön</a>
</section>
