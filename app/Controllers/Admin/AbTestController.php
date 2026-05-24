<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\AbTest;
use App\Models\AuditLog;
use App\Models\Post;

/**
 * A/B Test admin yönetimi (Tier 9).
 */
final class AbTestController
{
    public function index(Request $req): Response
    {
        $items = AbTest::all(200);
        // CTR hesapla
        foreach ($items as &$it) {
            $it['ctr_a'] = AbTest::ctr((int) $it['views_a'], (int) $it['clicks_a']);
            $it['ctr_b'] = AbTest::ctr((int) $it['views_b'], (int) $it['clicks_b']);
        }
        unset($it);
        return view('admin.abtests.index', [
            'title' => 'A/B Başlık Testleri',
            'items' => $items,
        ]);
    }

    public function create(Request $req): Response
    {
        $postId = (int) $req->input('post', 0);
        $post = $postId > 0 ? Post::findById($postId) : null;
        return view('admin.abtests.form', [
            'title' => 'Yeni A/B Test',
            'post' => $post,
        ]);
    }

    public function store(Request $req): Response
    {
        $postId = (int) $req->input('post_id', 0);
        $a = trim((string) $req->input('variant_a', ''));
        $b = trim((string) $req->input('variant_b', ''));
        if ($postId <= 0 || $a === '' || $b === '') {
            flash('error', 'Yazı ve iki varyant başlık zorunlu.');
            return Response::redirect(url('/admin/ab-test/yeni'));
        }
        $post = Post::findById($postId);
        if (!$post) {
            flash('error', 'Yazı bulunamadı.');
            return Response::redirect(url('/admin/ab-test'));
        }
        // Önceki testi sil (varsa) — yeniden başlat
        AbTest::delete($postId);
        $id = AbTest::create($postId, $a, $b);
        AuditLog::record('abtest.created', 'ab_test', $id, 'post #' . $postId);
        flash('success', 'A/B test başlatıldı.');
        return Response::redirect(url('/admin/ab-test'));
    }

    public function declareWinner(Request $req, array $args): Response
    {
        $postId = (int) ($args['id'] ?? 0);
        $winner = (string) $req->input('winner', 'tie');
        AbTest::declareWinner($postId, $winner);

        // Kazananı yazıya uygula
        $test = AbTest::findByPost($postId);
        if ($test && in_array($winner, ['a', 'b'], true)) {
            $newTitle = $winner === 'a' ? $test['variant_a'] : $test['variant_b'];
            Post::update($postId, ['title' => $newTitle]);
        }
        AuditLog::record('abtest.winner', 'ab_test', $postId, 'winner: ' . $winner);
        flash('success', 'Kazanan ilan edildi.');
        return Response::redirect(url('/admin/ab-test'));
    }

    public function delete(Request $req, array $args): Response
    {
        $postId = (int) ($args['id'] ?? 0);
        AbTest::delete($postId);
        AuditLog::record('abtest.deleted', 'ab_test', $postId);
        flash('success', 'Test silindi.');
        return Response::redirect(url('/admin/ab-test'));
    }
}
