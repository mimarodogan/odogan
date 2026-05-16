<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\AffiliateLink;
use App\Models\AuditLog;

/**
 * Admin → Affiliate Link Manager (Tier 8).
 */
final class AffiliateController
{
    private static function gate(): ?Response
    {
        if (!function_exists('feature') || !feature('affiliate_enabled')) {
            return Response::notFound();
        }
        return null;
    }

    public function index(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        return view('admin.affiliate.index', [
            'title' => 'Affiliate Linkleri',
            'list'  => AffiliateLink::all(),
        ]);
    }

    public function store(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        $code = mb_substr(trim((string) $req->input('code', '')), 0, 40);
        $code = preg_replace('/[^a-z0-9-]/i', '', $code) ?: '';
        $label = trim((string) $req->input('label', ''));
        $to = trim((string) $req->input('to_url', ''));
        if (mb_strlen($code) < 2 || mb_strlen($label) < 2 || mb_strlen($to) < 4) {
            flash('error', 'Code, label, URL gerekli.');
            return Response::redirect(url('/admin/affiliate'));
        }
        $id = AffiliateLink::create([
            'code'       => $code,
            'label'      => mb_substr($label, 0, 160),
            'to_url'     => mb_substr($to, 0, 500),
            'partner'    => mb_substr(trim((string) $req->input('partner', '')), 0, 120) ?: null,
            'commission' => $req->input('commission') ? (float) $req->input('commission') : null,
            'is_active'  => 1,
            'note'       => mb_substr(trim((string) $req->input('note', '')), 0, 500),
        ]);
        AuditLog::record('affiliate.created', 'affiliate', $id, "$code → $to");
        flash('success', 'Affiliate link eklendi. URL: ' . url('/git/' . $code));
        return Response::redirect(url('/admin/affiliate'));
    }

    public function destroy(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        AffiliateLink::delete($id);
        AuditLog::record('affiliate.deleted', 'affiliate', $id);
        flash('success', 'Link silindi.');
        return Response::redirect(url('/admin/affiliate'));
    }
}
