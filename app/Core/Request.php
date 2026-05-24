<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $server,
        public readonly array $files,
        public readonly array $cookies,
        public readonly array $headers,
    ) {}

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim((string) $path, '/');
        if ($path === '') {
            $path = '/';
        }
        return new self(
            method: $method,
            path: $path,
            query: $_GET,
            body: self::parseBody($method),
            server: $_SERVER,
            files: $_FILES,
            cookies: $_COOKIE,
            headers: self::parseHeaders($_SERVER),
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('x-requested-with')) === 'xmlhttprequest';
    }

    public function ip(): string
    {
        // RealIpService trusted_proxies listesini dikkate alır;
        // X-Forwarded-For sadece güvenilen proxy'den geldiğinde onurlanır.
        return \App\Services\RealIpService::ip();
    }

    private static function parseBody(string $method): array
    {
        if ($method === 'GET' || $method === 'HEAD') {
            return [];
        }
        if (!empty($_POST)) {
            return $_POST;
        }
        $raw = file_get_contents('php://input') ?: '';
        $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if ($raw !== '' && str_contains($ct, 'application/json')) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private static function parseHeaders(array $server): array
    {
        $out = [];
        foreach ($server as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $out[$name] = (string) $v;
            }
        }
        if (isset($server['CONTENT_TYPE'])) {
            $out['content-type'] = (string) $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $out['content-length'] = (string) $server['CONTENT_LENGTH'];
        }
        return $out;
    }
}
