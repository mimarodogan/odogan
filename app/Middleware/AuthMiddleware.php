<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthMiddleware
{
    public function handle(Request $req, callable $next): Response
    {
        if (AuthService::check()) {
            return $next($req);
        }

        // AJAX / JSON istekleri için HTML redirect yerine JSON 401 dön.
        // CsrfMiddleware ile tutarlı pattern — fetch() çağrıları JSON parse
        // hatası almaz, frontend "Oturum süresi doldu" mesajı gösterebilir.
        if ($req->isAjax() || str_contains((string) $req->header('accept'), 'application/json')) {
            return Response::json([
                'ok'      => false,
                'error'   => 'unauthenticated',
                'message' => 'Oturum süresi doldu — sayfayı yenileyip tekrar giriş yapın.',
            ], 401);
        }

        flash('error', 'Bu sayfayı görmek için giriş yapmalısınız.');
        // Only remember internal, same-origin paths — never user-controlled URLs.
        $path = (string) ($req->path ?? '/');
        if (self::isSafeInternalPath($path)) {
            $_SESSION['_redirect_after_login'] = $path;
        }
        return Response::redirect(url('/giris'));
    }

    /**
     * A path is safe to store for post-login redirect when it points strictly
     * to a relative location on this site (no protocol, no host, no
     * protocol-relative '//evil.com' bypass).
     */
    public static function isSafeInternalPath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] !== '/') {
            return false;            // must start with /
        }
        if (str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            return false;            // protocol-relative bypass
        }
        if (preg_match('#[\r\n\t]#', $path)) {
            return false;            // header-injection guard
        }
        return true;
    }
}
