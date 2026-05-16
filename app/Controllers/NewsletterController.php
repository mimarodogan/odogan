<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\Logger;
use App\Services\NewsletterService;
use App\Services\RateLimiter;

/**
 * Public newsletter işlemleri:
 *   POST /newsletter/abone-ol        → subscribe
 *   GET  /newsletter/onay/{token}    → confirm (double opt-in landing)
 *   GET  /newsletter/cikis/{token}   → unsubscribe
 */
final class NewsletterController
{
    public function subscribe(Request $req): Response
    {
        $email = trim((string) $req->input('email', ''));
        $name = trim((string) $req->input('name', ''));
        $ip = RateLimiter::clientIp();

        // Rate limit: 5 deneme / saat / IP
        $rl = RateLimiter::hit('newsletter:ip:' . $ip, 5, 3600);
        if (!$rl['ok']) {
            flash('error_newsletter', 'Çok hızlı denedin. ' . ceil($rl['retry_after'] / 60) . ' dk sonra tekrar dene.');
            return Response::redirect(self::backTo($req));
        }

        $result = NewsletterService::subscribe($email, $name !== '' ? $name : null, $ip);
        if (!$result['ok']) {
            flash('error_newsletter', $result['error'] ?? 'Abone olunamadı.');
            return Response::redirect(self::backTo($req));
        }
        if (!empty($result['already_confirmed'])) {
            flash('success_newsletter', 'Zaten abonesin — bültenleri kaçırmazsın.');
        } else {
            flash('success_newsletter', 'Onay maili gönderildi. E-postanı kontrol et.');
        }

        Logger::info('newsletter.subscribed', ['email' => $email], 'newsletter');
        return Response::redirect(self::backTo($req));
    }

    public function confirm(Request $req, array $args): Response
    {
        $token = (string) ($args['token'] ?? '');
        $result = NewsletterService::confirm($token);
        // Token'lı landing: kişiye özel, index'lenmemeli.
        return view('pages.newsletter-confirm', [
            'title'     => 'Abonelik Onayı',
            'canonical' => absolute_url('/newsletter'),
            'robots'    => 'noindex, nofollow',
            'ok'        => $result['ok'],
            'error'     => $result['error'] ?? '',
        ]);
    }

    public function unsubscribe(Request $req, array $args): Response
    {
        $token = (string) ($args['token'] ?? '');
        $result = NewsletterService::unsubscribe($token);
        // Token'lı landing: kişiye özel, index'lenmemeli.
        return view('pages.newsletter-confirm', [
            'title'      => 'Abonelikten Çıkış',
            'canonical'  => absolute_url('/newsletter'),
            'robots'     => 'noindex, nofollow',
            'ok'         => $result['ok'],
            'error'      => $result['error'] ?? '',
            'unsub_mode' => true,
        ]);
    }

    private static function backTo(Request $req): string
    {
        $ref = $req->header('referer', '');
        if (is_string($ref) && $ref !== '' && str_contains($ref, $_SERVER['HTTP_HOST'] ?? '')) {
            return $ref;
        }
        return url('/');
    }
}
