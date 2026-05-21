<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,params:array<int,string>,handler:mixed,middleware:array<int,string>}> */
    private array $routes = [];
    private string $prefix = '';
    /** @var array<int,string> */
    private array $groupMiddleware = [];
    /** @var array<int,mixed> */
    private array $globalMiddleware = [];

    public function use(mixed $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function get(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function any(string $method, string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add(strtoupper($method), $pattern, $handler, $middleware);
    }

    public function group(string $prefix, callable $fn, array $middleware = []): void
    {
        $oldPrefix = $this->prefix;
        $oldMw = $this->groupMiddleware;
        $this->prefix = rtrim($oldPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = array_merge($oldMw, $middleware);
        $fn($this);
        $this->prefix = $oldPrefix;
        $this->groupMiddleware = $oldMw;
    }

    private function add(string $method, string $pattern, mixed $handler, array $middleware): void
    {
        $full = '/' . trim($this->prefix . '/' . trim($pattern, '/'), '/');
        if ($full === '/') {
            $full = '/';
        }
        [$regex, $params] = $this->compile($full);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $full,
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
            'middleware' => array_merge($this->globalMiddleware, $this->groupMiddleware, $middleware),
        ];
    }

    private function compile(string $pattern): array
    {
        $params = [];
        // {name} → herhangi bir segment; {name:\d+} → özel regex kısıtı (constraint).
        // Constraint opsiyonel olduğu için mevcut {name} route'lar geriye uyumlu kalır.
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            $constraint = ($m[2] ?? '') !== '' ? $m[2] : '[^/]+';
            return '(?P<' . $m[1] . '>' . $constraint . ')';
        }, $pattern);
        return ['#^' . rtrim((string) $regex, '/') . '/?$#', $params];
    }

    public function dispatch(Request $req): Response
    {
        // Trailing slash normalizasyonu — /sayfa/ → /sayfa (301 kalıcı yönlendirme).
        // Kök (/) hariç; yalnızca GET/HEAD (POST'ta method değişim riskini önle).
        // Request::path zaten trim'li olduğu için ham REQUEST_URI'ye bakılır.
        if ($req->method === 'GET' || $req->method === 'HEAD') {
            $rawPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            if (is_string($rawPath) && $rawPath !== '/' && str_ends_with($rawPath, '/')) {
                $clean = '/' . trim($rawPath, '/');
                $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
                return new Response('', 301, ['Location' => $clean . ($qs !== '' ? '?' . $qs : '')]);
            }
        }

        foreach ($this->routes as $r) {
            if ($r['method'] !== $req->method && !($r['method'] === 'GET' && $req->method === 'HEAD')) {
                continue;
            }
            if (preg_match($r['regex'], $req->path, $m)) {
                $args = [];
                foreach ($r['params'] as $p) {
                    $args[$p] = $m[$p] ?? null;
                }
                return $this->run($r, $req, $args);
            }
        }

        // 404 öncesi: 301 Redirect tablosunda eşleşme var mı? (Tier 7)
        if ($req->method === 'GET' && function_exists('feature') && feature('redirect_manager_enabled')) {
            try {
                $redirect = \App\Models\Redirect::findByPath($req->path);
                if ($redirect && (int) $redirect['is_active'] === 1) {
                    \App\Models\Redirect::bumpHit((int) $redirect['id']);
                    $code = (int) $redirect['code'];
                    if (!in_array($code, [301, 302, 307, 308], true)) $code = 301;
                    return new Response('', $code, ['Location' => (string) $redirect['to_url']]);
                }
            } catch (\Throwable) {}
        }

        // 404 logger (Tier 7) — sadece GET istekleri kayıt edilir
        if ($req->method === 'GET' && function_exists('feature') && feature('not_found_logger_enabled')) {
            try {
                \App\Models\NotFoundLog::record(
                    $req->path,
                    (string) ($_SERVER['HTTP_REFERER'] ?? ''),
                    (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
                );
            } catch (\Throwable) {}
        }

        return Response::notFound();
    }

    private function run(array $route, Request $req, array $args): Response
    {
        $core = function (Request $req) use ($route, $args): Response {
            $h = $route['handler'];
            if (is_callable($h)) {
                $result = $h($req, $args);
            } elseif (is_array($h) && count($h) === 2) {
                [$class, $method] = $h;
                $instance = is_object($class) ? $class : new $class();
                $result = $instance->{$method}($req, $args);
            } else {
                throw new \RuntimeException('Invalid route handler');
            }
            if ($result instanceof Response) {
                return $result;
            }
            if (is_array($result) || is_object($result)) {
                return Response::json($result);
            }
            return Response::html((string) $result);
        };

        $stack = $core;
        foreach (array_reverse($route['middleware']) as $mw) {
            $next = $stack;
            $stack = function (Request $req) use ($mw, $next): Response {
                if (is_object($mw) && !($mw instanceof \Closure)) {
                    return $mw->handle($req, $next);
                }
                if (is_callable($mw)) {
                    return $mw($req, $next);
                }
                if (is_string($mw)) {
                    $args = [];
                    if (str_contains($mw, ':')) {
                        [$class, $rest] = explode(':', $mw, 2);
                        $args = array_map('trim', explode(',', $rest));
                    } else {
                        $class = $mw;
                    }
                    $instance = $args ? new $class(...$args) : new $class();
                    return $instance->handle($req, $next);
                }
                throw new \RuntimeException('Invalid middleware');
            };
        }
        return $stack($req);
    }
}
