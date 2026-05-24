<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\AuditLog;

/**
 * Admin → 404 Log + öneriler (Tier 7).
 *
 * Bulunamayan URL'leri kaydeder. Admin "Yönlendirme oluştur" diyebilir.
 */
final class NotFoundController
{
    public function index(Request $req): Response
    {
        if (!function_exists('feature') || !feature('not_found_logger_enabled')) {
            return Response::notFound();
        }
        $unresolvedOnly = (int) $req->input('all', 0) !== 1;
        $logs = NotFoundLog::list(200, $unresolvedOnly);

        // Her log için en yakın eşleşmeleri hesapla
        $enriched = [];
        foreach ($logs as $log) {
            $log['suggestions'] = NotFoundLog::suggestSimilar((string) $log['path'], 3);
            $enriched[] = $log;
        }

        return view('admin.not-found', [
            'title' => '404 Logları',
            'logs'  => $enriched,
            'unresolved_only' => $unresolvedOnly,
        ]);
    }

    /**
     * Hızlı redirect oluştur — 404 log satırından.
     */
    public function createRedirectFromLog(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('not_found_logger_enabled')) {
            return Response::notFound();
        }
        $id = (int) ($args['id'] ?? 0);
        $log = NotFoundLog::findById($id);
        if (!$log) return Response::notFound();

        $to = trim((string) $req->input('to_url', ''));
        if (mb_strlen($to) < 2) {
            flash('error', 'Hedef URL gerekli.');
            return Response::redirect(url('/admin/404-loglari'));
        }
        try {
            $rid = Redirect::create([
                'from_path' => $log['path'],
                'to_url'    => mb_substr($to, 0, 500),
                'code'      => 301,
                'note'      => '404 logundan otomatik oluşturuldu',
                'is_active' => 1,
            ]);
            NotFoundLog::markResolved($id);
            AuditLog::record('redirect.from_404', 'redirect', $rid, $log['path'] . ' → ' . $to);
            flash('success', 'Yönlendirme oluşturuldu ve log "çözüldü" olarak işaretlendi.');
        } catch (\Throwable $e) {
            flash('error', 'Yönlendirme oluşturulamadı: ' . $e->getMessage());
        }
        return Response::redirect(url('/admin/404-loglari'));
    }

    public function destroy(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('not_found_logger_enabled')) {
            return Response::notFound();
        }
        $id = (int) ($args['id'] ?? 0);
        NotFoundLog::delete($id);
        flash('success', 'Log silindi.');
        return Response::redirect(url('/admin/404-loglari'));
    }
}
