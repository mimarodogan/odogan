<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Loglar</h1>
    <p class="lead muted">DB veya dosya kaynağından, tarih/kanal/seviye filtreli.</p>
</section>

<form method="get" action="<?= esc(url('/admin/loglar')) ?>" class="form form-wide log-filters">
    <fieldset>
        <legend>Filtreler</legend>
        <div class="filter-grid">
            <label><span>Tarih</span>
                <input type="date" name="date" value="<?= esc($filters['date']) ?>">
            </label>
            <label><span>Kaynak</span>
                <select name="source">
                    <option value="db" <?= $filters['source'] === 'db' ? 'selected' : '' ?>>Veritabanı</option>
                    <option value="file" <?= $filters['source'] === 'file' ? 'selected' : '' ?>>Dosya (streaming tail)</option>
                </select>
            </label>
            <label><span>Seviye</span>
                <select name="level">
                    <option value="">— Tümü —</option>
                    <?php foreach ($levels as $l): ?>
                        <option value="<?= esc($l) ?>" <?= $filters['level'] === $l ? 'selected' : '' ?>>
                            <?= esc(strtoupper($l)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Kanal</span>
                <select name="channel">
                    <option value="">— Tümü —</option>
                    <?php foreach ($channels as $c): ?>
                        <option value="<?= esc($c) ?>" <?= $filters['channel'] === $c ? 'selected' : '' ?>>
                            <?= esc($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Limit</span>
                <input type="number" name="limit" min="50" max="1000" step="50" value="<?= (int) $filters['limit'] ?>">
            </label>
        </div>
        <button class="btn btn-primary" type="submit">Filtrele</button>
    </fieldset>
</form>

<?php if (!$entries): ?>
    <p class="muted">Bu kriterlerle eşleşen log satırı yok.</p>
<?php else: ?>
<table class="table log-table">
    <caption class="visually-hidden">Sistem log kayıtları — <?= count($entries) ?> satır</caption>
    <thead>
        <tr><th scope="col">Zaman</th><th scope="col">Kanal</th><th scope="col">Seviye</th><th scope="col">Mesaj</th><th scope="col">Bağlam</th></tr>
    </thead>
    <tbody>
    <?php foreach ($entries as $e): ?>
        <tr class="log-row log-<?= esc((string) ($e['level'] ?? 'info')) ?>">
            <td class="muted nowrap"><?= esc((string) ($e['created_at'] ?? '')) ?></td>
            <td class="nowrap"><code><?= esc((string) ($e['channel'] ?? 'app')) ?></code></td>
            <td><span class="log-badge"><?= esc(strtoupper((string) ($e['level'] ?? 'info'))) ?></span></td>
            <td><?= esc((string) ($e['message'] ?? '')) ?></td>
            <td>
                <?php if (!empty($e['context_json'])): ?>
                    <details><summary class="muted">{…}</summary>
                        <pre class="log-ctx"><?= esc(is_string($e['context_json']) ? $e['context_json'] : json_encode($e['context_json'])) ?></pre>
                    </details>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
