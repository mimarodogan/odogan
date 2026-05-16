<?php
/**
 * Local dev server router — mimics .htaccess behavior for `php -S`.
 *
 * Usage:
 *     php -S localhost:8000 router.php
 *
 * Why this file exists:
 *   PHP's built-in web server ignores .htaccess. In the flat (cPanel-style)
 *   layout, that means files like .env / vendor/ / bootstrap.php sit next to
 *   index.php in the document root and would otherwise be served as plain
 *   text. This script enforces the same denylist Apache/LiteSpeed would
 *   apply on the production host.
 */

$path = ltrim((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Deny dotfiles (.env, .git, .htaccess, ...) and a fixed list of project
// internals. Same set the production .htaccess protects.
$blockedPrefixes = ['app/', 'bootstrap.php', 'composer.json', 'composer.lock',
                    'config/', 'database/', 'storage/', 'vendor/',
                    'KURULUM-OKU-BENI.md', 'README.md', 'tani.php', 'tani2.php',
                    'router.php', '.env', '.htaccess', '.gitignore'];

if (str_starts_with($path, '.') && $path !== '') {
    http_response_code(403);
    exit('Forbidden');
}
foreach ($blockedPrefixes as $b) {
    if ($path === $b || str_starts_with($path, $b)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Real static file? Let PHP serve it (return false → built-in handler).
if ($path !== '' && is_file(__DIR__ . '/' . $path)) {
    return false;
}

// Otherwise route through the front controller.
require __DIR__ . '/index.php';
