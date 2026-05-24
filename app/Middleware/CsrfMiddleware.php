<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Verifies CSRF token on every state-changing request (POST/PUT/PATCH/DELETE).
 * Tokens come either from `_csrf` body field or `X-CSRF-Token` header.
 */
final class CsrfMiddleware
{
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $req, callable $next): Response
    {
        if (!in_array($req->method, self::PROTECTED_METHODS, true)) {
            return $next($req);
        }

        $token = (string) ($req->input('_csrf', '') ?: $req->header('x-csrf-token', ''));
        if (!csrf_verify($token)) {
            if ($req->isAjax() || str_contains((string) $req->header('accept'), 'application/json')) {
                return Response::json(['error' => 'CSRF token invalid'], 419);
            }
            return Response::html(self::renderBlock(), 419);
        }
        return $next($req);
    }

    private static function renderBlock(): string
    {
        $back = esc((string) ($_SERVER['HTTP_REFERER'] ?? '/'));
        return '<!doctype html><meta charset="utf-8"><title>419</title>'
            . '<body style="font-family:system-ui;padding:2rem">'
            . '<h1>419 — Oturum süresi doldu</h1>'
            . '<p>Güvenlik nedeniyle istek reddedildi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.</p>'
            . "<p><a href=\"$back\">Geri dön</a></p>";
    }
}
