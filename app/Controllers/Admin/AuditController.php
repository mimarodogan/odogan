<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;

/**
 * Admin → Audit Log görüntüleyici (Tier 7).
 *
 * Tüm hassas admin işlemleri tarih/aktör/aksiyon filtreli kayıtlı.
 */
final class AuditController
{
    public function index(Request $req): Response
    {
        if (!function_exists('feature') || !feature('audit_log_enabled')) {
            return Response::notFound();
        }
        $page = max(1, (int) $req->input('page', 1));
        $perPage = 50;
        $filters = [];
        $action = (string) $req->input('action', '');
        if ($action !== '') $filters['action'] = $action;

        $total = AuditLog::count($filters);
        $logs  = AuditLog::list($perPage, ($page - 1) * $perPage, $filters);
        $pages = max(1, (int) ceil($total / $perPage));

        return view('admin.audit', [
            'title'     => 'Audit Log',
            'logs'      => $logs,
            'page'      => $page,
            'pages'     => $pages,
            'total'     => $total,
            'filter_action' => $action,
        ]);
    }
}
