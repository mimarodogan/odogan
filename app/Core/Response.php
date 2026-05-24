<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {}

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        try {
            // View::render layout mekanizmasını (base.php) düzgün çalıştırır
            $body = View::render('errors.404', ['message' => $message]);
        } catch (\Throwable) {
            $view = Config::root() . '/app/Views/errors/404.php';
            $body = is_file($view) ? self::renderFile($view, ['message' => $message]) : $message;
        }
        return new self($body, 404);
    }

    public static function error(\Throwable $e): self
    {
        // Sentry'ye raporla (SDK yüklü + DSN tanımlı; aksi durumda no-op)
        if (function_exists('\\Sentry\\captureException')) {
            try { \Sentry\captureException($e); } catch (\Throwable) {}
        }

        $debug = Config::get('APP_DEBUG', 'false') === true || Config::get('APP_DEBUG', 'false') === 'true';
        if ($debug) {
            $body = '<pre style="white-space:pre-wrap;font:14px/1.4 monospace;padding:16px">'
                . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            return new self($body, 500);
        }
        try {
            $body = View::render('errors.500', []);
        } catch (\Throwable) {
            $view = Config::root() . '/app/Views/errors/500.php';
            $body = is_file($view) ? self::renderFile($view, []) : 'Internal Server Error';
        }
        return new self($body, 500);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header("$k: $v");
            }
        }
        echo $this->body;
    }

    private static function renderFile(string $file, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
