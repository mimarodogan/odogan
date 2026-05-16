<?php
return [
    'name' => $_ENV['APP_NAME'] ?? 'Otorite Yayin',
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/'),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Istanbul',
    'locale' => $_ENV['APP_LOCALE'] ?? 'tr',
];
