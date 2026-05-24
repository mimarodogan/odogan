<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Category;

final class CategoryController
{
    public function index(Request $req): Response
    {
        return view('admin.categories', [
            'title' => 'Kategoriler',
            'categories' => Category::all(false),
        ]);
    }

    public function create(Request $req): Response
    {
        return view('admin.categories-form', [
            'title' => 'Yeni Kategori',
            'category' => $this->emptyCategory(),
            'is_edit' => false,
            'categories' => Category::all(false),
        ]);
    }

    public function edit(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $cat = Category::findById($id);
        if ($cat === null) {
            return Response::notFound();
        }
        return view('admin.categories-form', [
            'title' => 'Kategoriyi Düzenle',
            'category' => $cat,
            'is_edit' => true,
            'categories' => Category::all(false),
        ]);
    }

    private function emptyCategory(): array
    {
        return [
            'id' => 0, 'name' => '', 'slug' => '', 'description' => '',
            'parent_id' => null, 'position' => 0, 'is_active' => 1,
            'meta_title' => '', 'meta_description' => '',
        ];
    }

    public function store(Request $req): Response
    {
        $name = trim((string) $req->input('name', ''));
        if (mb_strlen($name) < 2) {
            flash('error', 'Kategori adı en az 2 karakter olmalı.');
            return Response::redirect(url('/admin/kategoriler'));
        }
        Category::create([
            'name' => mb_substr($name, 0, 150),
            'description' => mb_substr((string) $req->input('description', ''), 0, 1000),
            'parent_id' => self::nullableInt($req->input('parent_id')),
            'position' => (int) $req->input('position', 0),
            'is_active' => $req->input('is_active') ? 1 : 1,
            'meta_title' => mb_substr((string) $req->input('meta_title', ''), 0, 180),
            'meta_description' => mb_substr((string) $req->input('meta_description', ''), 0, 255),
        ]);
        flash('success', 'Kategori oluşturuldu.');
        return Response::redirect(url('/admin/kategoriler'));
    }

    public function update(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $cat = Category::findById($id);
        if ($cat === null) {
            return Response::notFound();
        }
        $name = trim((string) $req->input('name', ''));
        if (mb_strlen($name) < 2) {
            flash('error', 'Kategori adı en az 2 karakter olmalı.');
            return Response::redirect(url('/admin/kategoriler'));
        }
        $patch = [
            'name' => mb_substr($name, 0, 150),
            'description' => mb_substr((string) $req->input('description', ''), 0, 1000),
            'parent_id' => self::nullableInt($req->input('parent_id')),
            'position' => (int) $req->input('position', 0),
            'is_active' => $req->input('is_active') === '1' ? 1 : 0,
            'meta_title' => mb_substr((string) $req->input('meta_title', ''), 0, 180),
            'meta_description' => mb_substr((string) $req->input('meta_description', ''), 0, 255),
        ];
        $newSlug = trim((string) $req->input('slug', ''));
        if ($newSlug !== '' && $newSlug !== $cat['slug']) {
            $patch['slug'] = $newSlug;
        }
        Category::update($id, $patch);
        flash('success', 'Kategori güncellendi.');
        return Response::redirect(url('/admin/kategoriler'));
    }

    public function destroy(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        Category::delete($id);
        flash('success', 'Kategori silindi.');
        return Response::redirect(url('/admin/kategoriler'));
    }

    private static function nullableInt(mixed $v): ?int
    {
        $v = is_string($v) ? trim($v) : $v;
        if ($v === '' || $v === null) {
            return null;
        }
        $i = (int) $v;
        return $i > 0 ? $i : null;
    }
}
