<?php
declare(strict_types=1);

namespace App\Controllers\Editor;

use App\Core\Request;
use App\Core\Response;
use App\Models\Post;
use App\Services\AuthService;
use App\Services\FaqService;
use App\Services\MarkdownService;
use App\Services\PostNotifier;

final class QueueController
{
    public function index(Request $req): Response
    {
        return view('editor.queue', [
            'title' => 'Onay Kuyruğu',
            'pending' => Post::listByStatus(Post::STATUS_PENDING),
            'recent' => array_slice(Post::listByStatus(Post::STATUS_PUBLISHED, 10), 0, 10),
        ]);
    }

    public function review(Request $req, array $args): Response
    {
        $post = Post::findById((int) $args['id']);
        if ($post === null) {
            return Response::notFound();
        }
        $author = \App\Models\User::findById((int) $post['user_id']);
        return view('editor.review', [
            'title' => 'İçerik İncele',
            'post' => $post,
            'author' => $author,
            'preview_html' => MarkdownService::render($post),
            'faq' => FaqService::decode($post['faq_json'] ?? null),
        ]);
    }

    public function approve(Request $req, array $args): Response
    {
        $editor = AuthService::user();
        $post = Post::findById((int) $args['id']);
        if ($post === null) {
            return Response::notFound();
        }
        if ($post['status'] !== Post::STATUS_PENDING) {
            flash('error', 'Bu içerik onay bekleyen statüde değil.');
            return Response::redirect(url('/editor/onay'));
        }
        Post::transition(
            (int) $post['id'],
            $post['status'],
            Post::STATUS_PUBLISHED,
            (int) $editor['id'],
            (string) $req->input('note', '')
        );
        $author = \App\Models\User::findById((int) $post['user_id']);
        if ($author) {
            $cat = \App\Models\Category::findById((int) $post['category_id']);
            $public = url('/' . ($cat['slug'] ?? '') . '/' . $post['slug']);
            PostNotifier::notifyAuthorOfApproval((string) $author['email'], (string) $post['title'], $public);
        }
        flash('success', 'İçerik yayınlandı.');
        return Response::redirect(url('/editor/onay'));
    }

    public function reject(Request $req, array $args): Response
    {
        $editor = AuthService::user();
        $post = Post::findById((int) $args['id']);
        if ($post === null) {
            return Response::notFound();
        }
        if ($post['status'] !== Post::STATUS_PENDING) {
            flash('error', 'Bu içerik onay bekleyen statüde değil.');
            return Response::redirect(url('/editor/onay'));
        }
        $reason = trim((string) $req->input('reason', ''));
        Post::transition(
            (int) $post['id'],
            $post['status'],
            Post::STATUS_REJECTED,
            (int) $editor['id'],
            $reason
        );
        $author = \App\Models\User::findById((int) $post['user_id']);
        if ($author) {
            PostNotifier::notifyAuthorOfRejection((string) $author['email'], (string) $post['title'], $reason);
        }
        flash('success', 'İçerik revizyon talebiyle yazara geri gönderildi.');
        return Response::redirect(url('/editor/onay'));
    }
}
