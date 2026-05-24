<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "migrate.php must be run from the CLI\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/bootstrap.php';

use App\Core\Database;

$dir = APP_ROOT . '/database/migrations';
$db = Database::instance();

// Bootstrap migrations table first.
$bootstrap = $dir . '/000_migrations.sql';
if (is_file($bootstrap)) {
    $db->pdo()->exec((string) file_get_contents($bootstrap));
}

$applied = array_column(
    $db->fetchAll('SELECT name FROM migrations ORDER BY id ASC'),
    'name'
);
$applied = array_flip($applied);

$batchRow = $db->fetch('SELECT COALESCE(MAX(batch),0) AS b FROM migrations');
$batch = ((int) ($batchRow['b'] ?? 0)) + 1;

$files = glob($dir . '/*.sql') ?: [];
sort($files);

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if ($name === '000_migrations.sql' || isset($applied[$name])) {
        continue;
    }
    fwrite(STDOUT, "→ Running $name ... ");
    try {
        $db->pdo()->exec((string) file_get_contents($file));
        $db->insert('migrations', ['name' => $name, 'batch' => $batch]);
        fwrite(STDOUT, "ok\n");
        $ran++;
    } catch (\Throwable $e) {
        fwrite(STDERR, "FAILED\n" . $e->getMessage() . "\n");
        exit(2);
    }
}

if ($ran === 0) {
    fwrite(STDOUT, "Nothing to migrate.\n");
} else {
    fwrite(STDOUT, "Done. $ran migration(s) applied (batch #$batch).\n");
}
