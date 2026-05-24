<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\UserSession;
use App\Services\AuthService;

/**
 * Active Sessions (Tier 8 — Privacy).
 *
 * /panel/oturumlar — kullanıcı tüm cihazlarını görür, uzakta çıkış yapabilir.
 */
final class SessionController
{
    public function index(Request $req): Response
    {
        if (!function_exists('feature') || !feature('active_sessions_enabled')) {
            return Response::notFound();
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::redirect(url('/giris'));
        }
        $sessions = UserSession::forUser((int) $user['id']);
        // Mevcut session'ı işaretle
        $currentSid = session_id();
        foreach ($sessions as &$s) {
            $s['is_current'] = $s['session_id'] === $currentSid;
        }
        unset($s);
        return view('panel.sessions', [
            'title'    => 'Aktif Oturumlar',
            'sessions' => $sessions,
        ]);
    }

    public function delete(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('active_sessions_enabled')) {
            return Response::notFound();
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::redirect(url('/giris'));
        }
        $id = (int) ($args['id'] ?? 0);
        UserSession::delete((int) $user['id'], $id);
        flash('success', 'Oturum sonlandırıldı.');
        return Response::redirect(url('/panel/oturumlar'));
    }
}
