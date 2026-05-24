<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $items = [];
    private static string $root = '';

    public static function bootstrap(string $root): void
    {
        self::$root = rtrim($root, '/');
        $dir = self::$root . '/config';
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $data = require $file;
            if (is_array($data)) {
                self::$items[$name] = $data;
            }
        }
    }

    public static function root(): string
    {
        return self::$root;
    }

    /**
     * Filesystem path to the web-served directory. Supports both layouts:
     *   stock:  /project/public/   (Laravel-style — index.php lives in public/)
     *   flat:   /project/          (cPanel-style — index.php at the project root)
     *
     * We prefer the layout where index.php actually lives so URLs map to files
     * the web server can serve. Falling back to /public when it merely exists
     * caused uploads to land in the wrong directory on cPanel installs.
     */
    public static function publicRoot(): string
    {
        // 1) Flat layout (cPanel default): index.php at project root.
        if (is_file(self::$root . '/index.php')) {
            return self::$root;
        }
        // 2) Stock Laravel-style layout: /project/public/index.php
        $sub = self::$root . '/public';
        if (is_file($sub . '/index.php')) {
            return $sub;
        }
        // 3) Last-resort fallback
        return self::$root;
    }

    /**
     * Read env first, then config.dot.notation, with fallback default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $env = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($env !== false && $env !== null && $env !== '') {
            return self::cast($env);
        }
        if (str_contains($key, '.')) {
            $parts = explode('.', $key);
            $ref = self::$items;
            foreach ($parts as $p) {
                if (!is_array($ref) || !array_key_exists($p, $ref)) {
                    return $default;
                }
                $ref = $ref[$p];
            }
            return $ref;
        }
        return $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$items[$key] = $value;
    }

    /**
     * Lightweight .env loader (used only when phpdotenv is not installed).
     */
    public static function loadEnv(string $file): void
    {
        if (!is_readable($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if (strlen($v) >= 2) {
                $first = $v[0];
                $last = $v[strlen($v) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $v = substr($v, 1, -1);
                }
            }
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
            putenv("$k=$v");
        }
    }

    private static function cast(mixed $v): mixed
    {
        if (!is_string($v)) {
            return $v;
        }
        return match (strtolower($v)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $v,
        };
    }
}
