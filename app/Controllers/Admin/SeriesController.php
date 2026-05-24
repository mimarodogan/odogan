<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Series;

/**
 * Series / Dizi yazılar — admin CRUD.
 * Feature flag: series_enabled. Off ise tüm endpoint'ler 404.
 */
final class SeriesController
{
    private static function gate(): ?Response
    {
        if (!function_exists('feature') || !feature('series_enabled')) {
            return Response::notFound();
        }
        return null;
    }

    public function index(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        return view('admin.series.index', [
            'title' => 'Diziler',
            'list'  => Series::all(200),
            'edit'  => null,
        ]);
    }

    public function create(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        return view('admin.series.form', [
            'title' => 'Yeni Dizi',
            'series' => [
                'id' => null, 'name' => '', 'slug' => '',
                'description' => '', 'cover_image' => '', 'post_count' => 0,
            ],
            'posts' => [],
        ]);
    }

    public function edit(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        $s = Series::findById($id);
        if (!$s) return Response::notFound();
        return view('admin.series.form', [
            'title' => 'Dizi Düzenle — ' . $s['name'],
            'series' => $s,
            'posts'  => Series::postsFor($id, false),
        ]);
    }

    public function store(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        $errors = self::validateInput($req, $name, $patch);
        if ($errors) {
            foreach ($errors as $k => $v) flash('error_' . $k, $v);
            return Response::redirect(url('/admin/diziler/yeni'));
        }
        $id = Series::create($patch);
        flash('success', 'Dizi oluşturuldu.');
        return Response::redirect(url('/admin/diziler/' . $id . '/duzenle'));
    }

    public function update(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        $s = Series::findById($id);
        if (!$s) return Response::notFound();
        $errors = self::validateInput($req, $name, $patch);
        if ($errors) {
            foreach ($errors as $k => $v) flash('error_' . $k, $v);
            return Response::redirect(url('/admin/diziler/' . $id . '/duzenle'));
        }
        // Slug değişti mi
        $newSlug = trim((string) $req->input('slug', ''));
        if ($newSlug !== '' && $newSlug !== $s['slug']) {
            $patch['slug'] = $newSlug;
        }
        Series::update($id, $patch);
        Series::recountPosts($id);
        flash('success', 'Dizi güncellendi.');
        return Response::redirect(url('/admin/diziler/' . $id . '/duzenle'));
    }

    public function destroy(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        Series::delete($id);
        flash('success', 'Dizi silindi.');
        return Response::redirect(url('/admin/diziler'));
    }

    /**
     * Form girdisini doğrula → ($name, $patch) referansları doldurur.
     * Boş array dönerse validation OK.
     *
     * @return array<string,string>
     */
    private static function validateInput(Request $req, ?string &$name, ?array &$patch): array
    {
        $errors = [];
        $name = trim((string) $req->input('name', ''));
        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Dizi adı en az 2 karakter olmalı.';
        }
        // Description WYSIWYG HTML kabul ediyor — Sanitizer ile temizle
        $rawDesc = trim((string) $req->input('description', ''));
        if ($rawDesc !== '') {
            $rawDesc = \App\Services\Sanitizer::clean($rawDesc);
        }
        $patch = [
            'name'        => mb_substr($name, 0, 180),
            'description' => mb_substr($rawDesc, 0, 5000),
            'cover_image' => mb_substr(trim((string) $req->input('cover_image', '')), 0, 255),
        ];
        return $errors;
    }
}
