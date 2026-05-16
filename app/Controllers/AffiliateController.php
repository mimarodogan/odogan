<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AffiliateLink;

/**
 * Affiliate redirect (Tier 8).
 *
 * /git/{code} → counter artırır, hedef URL'e 301 yönlendirme yapar.
 */
final class AffiliateController
{
    public function go(Request $req, array $args): Response
    {
        $code = (string) ($args['code'] ?? '');
        $link = AffiliateLink::findByCode($code);
        if (!$link) {
            return Response::notFound();
        }
        AffiliateLink::bumpClick((int) $link['id']);
        // X-Robots-Tag — bu redirect URL'ini Google asla index etmesin.
        // (Hedef sayfa kendi noindex/follow politikasıyla işlenir.)
        return new Response('', 302, [
            'Location'       => (string) $link['to_url'],
            'X-Robots-Tag'   => 'noindex, nofollow',
            'Cache-Control'  => 'no-store',
        ]);
    }
}
