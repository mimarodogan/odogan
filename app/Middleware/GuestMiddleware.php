<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class GuestMiddleware
{
    public function handle(Request $req, callable $next): Response
    {
        if (AuthService::check()) {
            return Response::redirect(url('/panel'));
        }
        return $next($req);
    }
}
