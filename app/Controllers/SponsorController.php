<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Models\SponsorSlot;

/**
 * Public sponsor click tracker — /sponsor/git/{id}.
 * Tıklamayı +1'le, hedef URL'e 302 redirect et.
 */
final class SponsorController
{
    public function go(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if (!Setting::get('sponsor_slot_enabled', false, 'features')) {
            return Response::notFound();
        }
        $slot = SponsorSlot::findById($id);
        if (!$slot || !$slot['active'] || !$slot['target_url']) {
            return Response::notFound();
        }
        SponsorSlot::bumpClick($id);
        $url = $slot['target_url'];
        // External URL — basic safety
        if (!preg_match('#^https?://#i', $url)) {
            return Response::notFound();
        }
        return Response::redirect($url, 302);
    }
}
