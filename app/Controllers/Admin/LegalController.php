<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\LegalDocument;
use App\Services\Sanitizer;

/**
 * Admin → Sözleşmeler (Tier 6).
 *
 * Üyelik, yazar, gizlilik, kullanım koşulları — admin'de HTML editor ile düzenle.
 */
final class LegalController
{
    public function index(Request $req): Response
    {
        return view('admin.legal.index', [
            'title' => 'Sözleşmeler',
            'list'  => LegalDocument::all(),
        ]);
    }

    public function edit(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $doc = LegalDocument::findById($id);
        if (!$doc) {
            return Response::notFound();
        }
        return view('admin.legal.form', [
            'title' => 'Sözleşmeyi Düzenle — ' . $doc['title'],
            'doc'   => $doc,
        ]);
    }

    public function update(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $doc = LegalDocument::findById($id);
        if (!$doc) {
            return Response::notFound();
        }
        $title    = trim((string) $req->input('title', ''));
        $bodyHtml = (string) $req->input('body_html', '');
        $isActive = ((int) $req->input('is_active', 1)) === 1 ? 1 : 0;

        if (mb_strlen($title) < 3) {
            flash('error', 'Başlık en az 3 karakter olmalı.');
            return Response::redirect(url('/admin/sozlesmeler/' . $id . '/duzenle'));
        }

        // HTML temizle (XSS koruması)
        $bodyHtml = Sanitizer::clean($bodyHtml);

        LegalDocument::update($id, [
            'title'     => mb_substr($title, 0, 200),
            'body_html' => $bodyHtml,
            'is_active' => $isActive,
        ]);
        flash('success', 'Sözleşme güncellendi.');
        return Response::redirect(url('/admin/sozlesmeler/' . $id . '/duzenle'));
    }
}
