<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\SponsorSlot;

/**
 * Sponsor slot admin CRUD (Tier 9).
 */
final class SponsorController
{
    public function index(Request $req): Response
    {
        $items = SponsorSlot::all(500);
        return view('admin.sponsor.index', [
            'title' => 'Sponsor Slotları',
            'items' => $items,
        ]);
    }

    public function create(Request $req): Response
    {
        return view('admin.sponsor.form', [
            'title' => 'Yeni Sponsor Slot',
            'slot' => $this->empty(),
            'is_edit' => false,
        ]);
    }

    public function edit(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $slot = SponsorSlot::findById($id);
        if (!$slot) return Response::notFound();
        return view('admin.sponsor.form', [
            'title' => 'Sponsor Slot',
            'slot' => $slot,
            'is_edit' => true,
        ]);
    }

    public function store(Request $req): Response
    {
        $data = $this->parse($req);
        $id = SponsorSlot::create($data);
        AuditLog::record('sponsor.created', 'sponsor', $id, (string) $data['name']);
        flash('success', 'Sponsor eklendi.');
        return Response::redirect(url('/admin/sponsor'));
    }

    public function update(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $data = $this->parse($req);
        SponsorSlot::update($id, $data);
        AuditLog::record('sponsor.updated', 'sponsor', $id, (string) $data['name']);
        flash('success', 'Sponsor güncellendi.');
        return Response::redirect(url('/admin/sponsor/' . $id . '/duzenle'));
    }

    public function delete(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        SponsorSlot::delete($id);
        AuditLog::record('sponsor.deleted', 'sponsor', $id);
        flash('success', 'Sponsor silindi.');
        return Response::redirect(url('/admin/sponsor'));
    }

    private function empty(): array
    {
        return [
            'id' => 0, 'name' => '', 'tagline' => '', 'image_url' => '', 'target_url' => '',
            'placement' => 'newsletter', 'weight' => 1, 'view_count' => 0, 'click_count' => 0,
            'starts_at' => null, 'ends_at' => null, 'active' => 1,
        ];
    }

    private function parse(Request $req): array
    {
        return [
            'name' => trim((string) $req->input('name', '')),
            'tagline' => trim((string) $req->input('tagline', '')) ?: null,
            'image_url' => trim((string) $req->input('image_url', '')) ?: null,
            'target_url' => trim((string) $req->input('target_url', '')),
            'placement' => in_array($req->input('placement'), ['newsletter','sidebar','below_post','header'], true)
                ? $req->input('placement') : 'newsletter',
            'weight' => max(1, (int) $req->input('weight', 1)),
            'starts_at' => $req->input('starts_at') ?: null,
            'ends_at' => $req->input('ends_at') ?: null,
            'active' => $req->input('active', '0') === '1' ? 1 : 0,
        ];
    }
}
