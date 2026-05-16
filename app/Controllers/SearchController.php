<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Post;

/**
 * Site içi full-text arama (/ara?q=...).
 * MySQL FULLTEXT index'i kullanır (migration 018).
 */
final class SearchController
{
    public function index(Request $req): Response
    {
        $q = trim((string) $req->input('q', ''));
        $results = [];
        $popular = [];

        if ($q !== '' && mb_strlen($q) >= 2) {
            $results = Post::search($q, 20, 0);
            if (!$results) {
                // 0 sonuç → fallback "popüler" yazılar
                $popular = Post::trending(5, 30);
            }
        }

        return view('pages.search', [
            'title'      => $q !== '' ? 'Arama: ' . $q : 'Arama',
            'description' => $q !== '' ? '"' . $q . '" için arama sonuçları' : 'Site içi arama',
            'canonical'  => absolute_url('/ara'),
            'q'          => $q,
            'results'    => $results,
            'popular'    => $popular,
            'robots'     => 'noindex, follow', // arama sayfalarını indexleme (head-meta.php'de override)
        ]);
    }
}
