<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Comment;
use App\Models\Post;
use App\Services\AuthService;
use App\Services\Logger;

final class CommentController
{
    public function store(Request $req): Response
    {
        $postId = (int) $req->input('post_id', 0);
        $body = trim((string) $req->input('body', ''));
        $parentId = (int) $req->input('parent_id', 0) ?: null;
        $authorName = trim((string) $req->input('author_name', ''));
        $authorEmail = trim((string) $req->input('author_email', ''));

        $post = Post::findById($postId);
        if ($post === null || $post['status'] !== Post::STATUS_PUBLISHED) {
            return Response::redirect(url('/'));
        }

        // Honeypot — gizli alan bot tarafından doldurulduysa, sessizce 200 dön
        if (trim((string) $req->input('website', '')) !== '') {
            return $this->silentSpam($req, $postId, 'honeypot');
        }

        // Zaman gecikme — form çok hızlı veya çok yavaş submit edilmişse spam
        $formTs = (int) $req->input('form_ts', 0);
        $age = $formTs > 0 ? (time() - $formTs) : -1;
        if ($age >= 0 && $age < 3) {
            return $this->silentSpam($req, $postId, 'too_fast');
        }
        if ($age > 7200) {
            return $this->staleForm($post);
        }

        $errors = [];
        if (mb_strlen($body) < 3) {
            $errors[] = 'Yorum en az 3 karakter olmalı.';
        }
        if (mb_strlen($body) > 4000) {
            $errors[] = 'Yorum 4000 karakteri aşamaz.';
        }

        $user = AuthService::user();
        if ($user === null) {
            if (mb_strlen($authorName) < 2) {
                $errors[] = 'İsim en az 2 karakter olmalı.';
            }
            if (!filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Geçerli bir e-posta girin.';
            }
        }

        $ip = $req->ip();
        if (!Comment::rateLimitOk($ip)) {
            $errors[] = 'Çok hızlı yorum gönderiyorsunuz, lütfen biraz bekleyin.';
        }

        $backUrl = url('/' . ($post['category_slug'] ?? '') . '/' . $post['slug']);
        $cat = \App\Models\Category::findById((int) $post['category_id']);
        if ($cat) {
            $backUrl = url('/' . $cat['slug'] . '/' . $post['slug']);
        }

        if ($errors) {
            foreach ($errors as $e) {
                flash('error_comment', $e);
            }
            return Response::redirect($backUrl . '#yorumlar');
        }

        $id = Comment::create([
            'post_id' => $postId,
            'user_id' => $user['id'] ?? null,
            'parent_id' => $parentId,
            'author_name' => $user ? null : mb_substr($authorName, 0, 120),
            'author_email' => $user ? null : mb_substr($authorEmail, 0, 190),
            'body' => mb_substr($body, 0, 4000),
            'status' => Comment::STATUS_PENDING,
            'ip_address' => $ip,
            'user_agent' => mb_substr((string) $req->header('user-agent', ''), 0, 255),
        ]);

        Logger::info('comment.submitted', [
            'comment_id' => $id, 'post_id' => $postId, 'user_id' => $user['id'] ?? null,
        ], 'comments');

        // Yorum admin email bildirimi (Tier 5 feature 4.4)
        // Notifier feature flag'ini kendi içinde kontrol eder, koşulu burada tekrar etmeye gerek yok.
        try {
            $commenterName = $user
                ? (string) $user['name']
                : ($authorName !== '' ? $authorName : 'Anonim');
            $commenterEmail = $user
                ? (string) ($user['email'] ?? '')
                : $authorEmail;
            \App\Services\PostNotifier::notifyAdminOfComment(
                (int) $id,
                $postId,
                (string) ($post['title'] ?? 'Yazı'),
                $commenterName,
                $commenterEmail,
                $body
            );
        } catch (\Throwable $e) {
            Logger::warning('comment.notify_admin_failed', [
                'comment_id' => $id, 'error' => $e->getMessage(),
            ], 'comments');
        }

        flash('success_comment', 'Yorumunuz alındı, editör onayı sonrası yayında olacak.');
        return Response::redirect($backUrl . '#yorumlar');
    }

    /**
     * Bot yakalandı — kullanıcıya başarılı gibi göster (silent),
     * DB'ye yazma, sadece spam log channel'a kaydet.
     */
    private function silentSpam(Request $req, int $postId, string $reason): Response
    {
        Logger::warning('comment.spam', [
            'post_id' => $postId,
            'reason' => $reason,
            'ip' => $req->ip(),
            'ua' => mb_substr((string) $req->header('user-agent', ''), 0, 255),
        ], 'spam');

        // Bot'a normal başarı görünümü ver — geri zıplama yapmasın
        $post = Post::findById($postId);
        $backUrl = url('/');
        if ($post) {
            $cat = \App\Models\Category::findById((int) $post['category_id']);
            if ($cat) {
                $backUrl = url('/' . $cat['slug'] . '/' . $post['slug']);
            }
        }
        flash('success_comment', 'Yorumunuz alındı, editör onayı sonrası yayında olacak.');
        return Response::redirect($backUrl . '#yorumlar');
    }

    /**
     * Form 2 saatten eski — kullanıcıya yenileme öner.
     */
    private function staleForm(array $post): Response
    {
        $backUrl = url('/');
        $cat = \App\Models\Category::findById((int) $post['category_id']);
        if ($cat) {
            $backUrl = url('/' . $cat['slug'] . '/' . $post['slug']);
        }
        flash('error_comment', 'Form süresi doldu; sayfayı yenileyip tekrar deneyin.');
        return Response::redirect($backUrl . '#yorumlar');
    }
}
