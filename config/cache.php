<?php
return [
    'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
    'path' => $_ENV['CACHE_PATH'] ?? 'storage/cache',
    'default_ttl' => (int) ($_ENV['CACHE_DEFAULT_TTL'] ?? 3600),
];
