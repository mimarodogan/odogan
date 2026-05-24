<?php
/**
 * 503 — Maintenance Mode
 *
 * Bu view BAĞIMSIZ HTML döndürür — `\App\Core\View::layout('base')` veya
 * herhangi bir helper'a güvenmez. Bakım modu DB/cache/migration sorunlarında
 * da güvenle dönebilmeli. Bu sebeple esc/url helper'ları INLINE.
 *
 * bootstrap.php integration snippet (raporda detay):
 *
 *     if (is_file(__DIR__ . '/storage/.maintenance') && PHP_SAPI !== 'cli') {
 *         http_response_code(503);
 *         header('Retry-After: 600');
 *         header('Content-Type: text/html; charset=utf-8');
 *         readfile(__DIR__ . '/app/Views/errors/503-maintenance.php');
 *         exit;
 *     }
 *
 * NOT: bu doğrudan readfile() ile basılırsa PHP execute edilmez — saf HTML
 * çıkar. Eğer dinamik mesaj istersek (örn. storage/.maintenance içeriğini
 * okumak), bootstrap'tan `require` etmeli. Aşağıdaki sürüm her iki kullanım
 * için de doğru renderlanır.
 */

// İsteğe bağlı: storage/.maintenance içinde bir mesaj satırı varsa göster.
$_mflag = dirname(__DIR__, 3) . '/storage/.maintenance';
$_msg = '';
if (is_file($_mflag)) {
    $_raw = @file_get_contents($_mflag);
    if (is_string($_raw)) {
        $_msg = trim(mb_substr($_raw, 0, 240));
    }
}
$_safeMsg = htmlspecialchars($_msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Bakım Çalışması — Geçici Olarak Hizmet Dışı</title>
<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; height: 100%; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background: #faf8f3;
    color: #1f2937;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    line-height: 1.6;
}
.wrap { max-width: 560px; text-align: center; }
.glyph {
    display: inline-block;
    font-size: 3rem;
    color: #1f3a8a;
    margin-bottom: 1rem;
    font-family: "Georgia", "Times New Roman", serif;
}
.code {
    font-family: "Georgia", "Times New Roman", serif;
    font-size: 5rem;
    line-height: 1;
    color: #1f3a8a;
    margin: 0 0 1rem;
    font-weight: 400;
    letter-spacing: -2px;
}
.eyebrow {
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.75rem;
    color: #6b7280;
    margin: 0 0 0.5rem;
}
h1 {
    font-family: "Georgia", "Times New Roman", serif;
    font-weight: 400;
    font-size: 1.6rem;
    margin: 0 0 1rem;
    color: #111827;
}
p { margin: 0 0 0.75rem; color: #374151; }
.msg {
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    background: #fff7ed;
    border-left: 3px solid #d97706;
    text-align: left;
    color: #78350f;
    font-size: 0.95rem;
    border-radius: 2px;
}
.footer {
    margin-top: 2rem;
    font-size: 0.85rem;
    color: #9ca3af;
}
</style>
</head>
<body>
    <main class="wrap" aria-labelledby="m503-heading">
        <div class="glyph" aria-hidden="true">◇</div>
        <div class="code" aria-hidden="true">503</div>
        <p class="eyebrow">Geçici Hizmet Kesintisi</p>
        <h1 id="m503-heading">Bakım çalışması sürüyor.</h1>
        <p>Site kısa süreliğine hizmet dışı. Genellikle bir kaç dakika içinde geri dönüyoruz.</p>
        <?php if ($_safeMsg !== ''): ?>
        <div class="msg" role="status"><?= $_safeMsg ?></div>
        <?php endif; ?>
        <p class="footer">Bu sayfa <code>Retry-After</code> başlığıyla birlikte 503 döndürür — arama motorları yeniden indekslemeyi geciktirir.</p>
    </main>
</body>
</html>
