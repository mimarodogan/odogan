<?php
declare(strict_types=1);

// Composer autoload
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    // Fallback minimal PSR-4 autoloader (App\ -> app/) for environments without composer
    spl_autoload_register(function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }
        $relative = substr($class, 4);
        $path = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
    require __DIR__ . '/app/Helpers/functions.php';
    require __DIR__ . '/app/Helpers/media.php';
    require __DIR__ . '/app/Helpers/seo.php';
}

use App\Core\Config;

// Load .env (best-effort)
if (class_exists(\Dotenv\Dotenv::class) && is_file(__DIR__ . '/.env')) {
    \Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
} elseif (is_file(__DIR__ . '/.env')) {
    Config::loadEnv(__DIR__ . '/.env');
}

Config::bootstrap(__DIR__);

// Maintenance mode — storage/.maintenance bayrağı varsa tüm web istekleri 503.
// bin/maintenance.php on|off ile yönetilir. CLI script'leri etkilenmez.
if (PHP_SAPI !== 'cli' && is_file(__DIR__ . '/storage/.maintenance')) {
    http_response_code(503);
    header('Retry-After: 600');
    header('Cache-Control: no-store, max-age=0');
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/app/Views/errors/503-maintenance.php';
    exit;
}

// Error reporting
$debug = Config::get('APP_DEBUG', 'false') === 'true';
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : (E_ALL & ~E_DEPRECATED));

// Sentry error tracking — sadece production'da ve DSN tanımlıysa
$sentryDsn = (string) Config::get('SENTRY_DSN', '');
if ($sentryDsn !== '' && function_exists('\\Sentry\\init')) {
    \Sentry\init([
        'dsn' => $sentryDsn,
        'environment' => (string) Config::get('APP_ENV', 'production'),
        'traces_sample_rate' => (float) Config::get('SENTRY_TRACES_SAMPLE_RATE', '0.1'),
        'send_default_pii' => false,
    ]);
}

// Default timezone
date_default_timezone_set(Config::get('APP_TIMEZONE', 'UTC'));

// Session start (cookies for CSRF/auth later)
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $appEnv = Config::get('APP_ENV', 'local');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    // Server-side session data TTL — PHP default 1440sn (24 dk) çok kısa,
    // admin uzun yazı yazarken CSRF token kaybolup 419 alıyordu. 4 saat
    // yeterli pratik bir denge: yazma seansını kapsar, brute-force riskini
    // de fazla genişletmez.
    ini_set('session.gc_maxlifetime', '14400');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_set_cookie_params([
        'lifetime' => 0, // browser kapanınca cookie sil (oturum cookie)
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps || $appEnv === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('odogan_sid');
    session_start();
}

// Opportunistic scheduled-publish trigger.
// Runs at most every 60s so we don't hammer the DB on every request.
if (PHP_SAPI !== 'cli' && class_exists(\App\Services\PostScheduler::class)) {
    $stamp = APP_ROOT . '/storage/cache/.last_schedule_check';
    $now = time();
    $last = is_file($stamp) ? (int) @file_get_contents($stamp) : 0;
    if ($now - $last >= 60) {
        @file_put_contents($stamp, (string) $now, LOCK_EX);
        try {
            \App\Services\PostScheduler::publishDue();
        } catch (\Throwable) {
            // Never fail a request because of the scheduler.
        }
    }
}
