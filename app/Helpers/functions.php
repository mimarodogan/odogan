<?php
declare(strict_types=1);

use App\Core\Config;

if (!function_exists('esc')) {
    function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return esc($value);
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        $base = rtrim((string) Config::get('APP_URL', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $rel = ltrim($path, '/');
        $base = url('assets/' . $rel);

        // Cache-buster: dosya mtime'ı ile ?v=... ekle ki .htaccess'in
        // 30-günlük max-age header'ı CSS/JS güncellemelerini bloklamasın.
        // Browser yeni URL'i fresh request olarak görür → hard-refresh
        // zorunluluğu biter. Dosya yoksa veya publicRoot bilinmiyorsa
        // sessizce orijinal URL döner (önceki davranış).
        try {
            $root = \App\Core\Config::publicRoot();
            $abs  = rtrim($root, '/') . '/assets/' . $rel;
            if (is_file($abs)) {
                $mt = filemtime($abs);
                if ($mt !== false) {
                    $sep = str_contains($base, '?') ? '&' : '?';
                    return $base . $sep . 'v=' . $mt;
                }
            }
        } catch (\Throwable) {
            // publicRoot çağrısı (cPanel sorunları, vb.) başarısızsa
            // — orijinal URL'yi döndür, hiçbir şey kırılmasın.
        }
        return $base;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): \App\Core\Response
    {
        return \App\Core\Response::redirect($url, $status);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . esc(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token): bool
    {
        $stored = $_SESSION['_csrf'] ?? '';
        return is_string($token) && $stored !== '' && hash_equals($stored, $token);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $val = $_SESSION['_flash'][$key] ?? null;
        if (isset($_SESSION['_flash'][$key])) {
            unset($_SESSION['_flash'][$key]);
        }
        return $val;
    }
}

if (!function_exists('form_error')) {
    /**
     * Form alan hatası render helper — flash session'da 'error_X' kaydını okur,
     * varsa <small class="form-error">…</small> döndürür. Form view'larında kullanılır.
     *
     *   <?= form_error('email') ?>  → reads flash('error_email')
     */
    function form_error(string $field): string
    {
        $msg = flash('error_' . $field);
        if ($msg === null || $msg === '') {
            return '';
        }
        return '<small class="form-error" role="alert" style="color:#b91c1c;font-size:.85rem">'
            . esc($msg) . '</small>';
    }
}

if (!function_exists('tr_date')) {
    /**
     * Türkiye tarih formatlama — gg/aa/yyyy (varsayılan) veya gg/aa/yyyy HH:MM.
     *
     * Sadece kullanıcıya gösterilen tarihler için kullan. Schema.org datePublished,
     * sitemap lastmod, <input type="datetime-local"> gibi makine-okunur yerlerde
     * ISO 8601 (date('c', $ts)) kalmalı.
     *
     *   tr_date('2026-05-13 14:30:00')        → '13/05/2026'
     *   tr_date('2026-05-13 14:30:00', true)  → '13/05/2026 14:30'
     *   tr_date('') / tr_date(null)           → ''
     */
    function tr_date(?string $datetime, bool $withTime = false): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }
        $ts = strtotime($datetime);
        if (!$ts) {
            return '';
        }
        return $withTime ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $tr = ['ç'=>'c','Ç'=>'c','ğ'=>'g','Ğ'=>'g','ı'=>'i','İ'=>'i','ö'=>'o','Ö'=>'o','ş'=>'s','Ş'=>'s','ü'=>'u','Ü'=>'u'];
        $text = strtr($text, $tr);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
        $text = preg_replace('~[^-a-z0-9]+~', '', $text) ?? '';
        return trim($text, '-');
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): \App\Core\Response
    {
        return \App\Core\Response::html(\App\Core\View::render($template, $data));
    }
}

if (!function_exists('cache')) {
    function cache(): \App\Core\Cache\CacheInterface
    {
        return \App\Core\Cache\CacheManager::driver();
    }
}

if (!function_exists('db')) {
    function db(): \App\Core\Database
    {
        return \App\Core\Database::instance();
    }
}

if (!function_exists('fmt_bytes')) {
    function fmt_bytes(int $b): string
    {
        if ($b < 1024) return $b . ' B';
        if ($b < 1048576) return number_format($b / 1024, 1) . ' KB';
        return number_format($b / 1048576, 1) . ' MB';
    }
}

if (!function_exists('feature')) {
    /**
     * "Features" Settings grubundan bir özelliğin aktif olup olmadığını kontrol eder.
     * View ve Controller'larda kullanılır:
     *   if (feature('footnotes_enabled')) { ... }
     *
     * Default false — bir özellik açıkça aktive edilmediği sürece görünmez.
     * Setting cache request-level, her sayfada DB tek bir kere okunur.
     */
    function feature(string $name, bool $default = false): bool
    {
        $val = \App\Models\Setting::get($name, $default, 'features');
        return $val === true || $val === '1' || $val === 1;
    }
}

if (!function_exists('csp_nonce')) {
    /**
     * Per-request CSP nonce — inline <script nonce="..."> için.
     *
     * Response::send() HTML yanıtlarda nonce'lu Content-Security-Policy başlığı
     * yollar; sanitizer bypass'ından gelen nonce'suz <script> çalışmaz.
     */
    function csp_nonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(16));
        }
        return $nonce;
    }
}
