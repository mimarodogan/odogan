<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Role-Based Access Control gate.
 * Usage in routes:
 *   $router->get('/admin', $h, ['App\Middleware\AuthMiddleware', 'App\Middleware\RbacMiddleware:admin']);
 *   $router->get('/editor', $h, ['App\Middleware\AuthMiddleware', 'App\Middleware\RbacMiddleware:admin,editor']);
 */
final class RbacMiddleware
{
    /** @var array<int,string> */
    private array $allowed;

    public function __construct(string ...$roles)
    {
        $this->allowed = array_values(array_filter(array_map(
            static fn(string $r) => trim($r),
            $roles
        )));
    }

    public function handle(Request $req, callable $next): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return Response::redirect(url('/giris'));
        }
        if ($this->allowed && !in_array($user['role'] ?? '', $this->allowed, true)) {
            return Response::html(self::renderForbidden($this->allowed), 403);
        }
        return $next($req);
    }

    private static function renderForbidden(array $allowed): string
    {
        $list = esc(implode(', ', $allowed));
        return '<!doctype html><meta charset="utf-8"><title>403</title>'
            . '<body style="font-family:system-ui;padding:2rem">'
            . '<h1>403 — Erişim Reddedildi</h1>'
            . "<p>Bu sayfa şu rollere açıktır: <code>$list</code></p>";
    }
}
