<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Redirect;

/**
 * Admin → 301 Redirect Manager (Tier 7 — Analytics).
 */
final class RedirectController
{
    public function index(Request $req): Response
    {
        if (!function_exists('feature') || !feature('redirect_manager_enabled')) {
            return Response::notFound();
        }
        return view('admin.redirects.index', [
            'title' => 'URL Yönlendirmeleri',
            'list'  => Redirect::all(),
        ]);
    }

    public function store(Request $req): Response
    {
        if (!function_exists('feature') || !feature('redirect_manager_enabled')) {
            return Response::notFound();
        }
        $from = '/' . ltrim(trim((string) $req->input('from_path', '')), '/');
        $to   = trim((string) $req->input('to_url', ''));
        $code = (int) $req->input('code', 301);
        $note = mb_substr(trim((string) $req->input('note', '')), 0, 255);

        if (mb_strlen($from) < 2) {
            flash('error', 'Kaynak yol gerekli.');
            return Response::redirect(url('/admin/yonlendirmeler'));
        }
        if (mb_strlen($to) < 2) {
            flash('error', 'Hedef URL gerekli.');
            return Response::redirect(url('/admin/yonlendirmeler'));
        }
        if (!in_array($code, [301, 302, 307, 308], true)) {
            $code = 301;
        }

        try {
            $id = Redirect::create([
                'from_path' => mb_substr($from, 0, 500),
                'to_url'    => mb_substr($to, 0, 500),
                'code'      => $code,
                'note'      => $note,
                'is_active' => 1,
            ]);
            AuditLog::record('redirect.created', 'redirect', $id, "$from → $to ($code)");
            flash('success', 'Yönlendirme eklendi.');
        } catch (\Throwable $e) {
            flash('error', 'Yönlendirme eklenemedi: ' . $e->getMessage());
        }
        return Response::redirect(url('/admin/yonlendirmeler'));
    }

    public function update(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('redirect_manager_enabled')) {
            return Response::notFound();
        }
        $id = (int) ($args['id'] ?? 0);
        $r = Redirect::findById($id);
        if (!$r) return Response::notFound();

        $patch = [
            'from_path' => '/' . ltrim(trim((string) $req->input('from_path', $r['from_path'])), '/'),
            'to_url'    => trim((string) $req->input('to_url', $r['to_url'])),
            'code'      => (int) $req->input('code', $r['code']),
            'note'      => mb_substr(trim((string) $req->input('note', $r['note'] ?? '')), 0, 255),
            'is_active' => ((int) $req->input('is_active', 0)) === 1 ? 1 : 0,
        ];
        Redirect::update($id, $patch);
        AuditLog::record('redirect.updated', 'redirect', $id, $patch['from_path'] . ' → ' . $patch['to_url']);
        flash('success', 'Yönlendirme güncellendi.');
        return Response::redirect(url('/admin/yonlendirmeler'));
    }

    public function destroy(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('redirect_manager_enabled')) {
            return Response::notFound();
        }
        $id = (int) ($args['id'] ?? 0);
        Redirect::delete($id);
        AuditLog::record('redirect.deleted', 'redirect', $id);
        flash('success', 'Yönlendirme silindi.');
        return Response::redirect(url('/admin/yonlendirmeler'));
    }
}
