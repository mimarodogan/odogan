<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\AuthService;

/**
 * /yazar-ol — public yazar başvuru sayfası (Tier 5 feature 5.1).
 *
 * 3 adım wizard:
 *   1) Kişisel bilgi (giriş yapmamışsa) + headline + bio
 *   2) Uzmanlık alanları + motivasyon
 *   3) Örnek yazı (URL ya da paste edilmiş metin) + onay
 *
 * Session'da step state tutulur. Final submit'te users tablosuna
 * author_application_json + status=pending kaydedilir, admin'e mail gider.
 *
 * Feature flag: author_application (default false) — off ise tüm endpoint'ler 404.
 */
final class AuthorApplicationController
{
    private const SESSION_KEY = '_author_app';

    private static function gate(): ?Response
    {
        if (!function_exists('feature') || !feature('author_application')) {
            return Response::notFound();
        }
        return null;
    }

    private static function state(): array
    {
        return $_SESSION[self::SESSION_KEY] ?? [
            'step' => 1,
            'data' => [
                'headline'   => '',
                'bio'        => '',
                'expertise'  => '',
                'motivation' => '',
                'sample_url' => '',
                'sample_text'=> '',
                'agree'      => 0,
            ],
        ];
    }

    private static function setState(array $st): void
    {
        $_SESSION[self::SESSION_KEY] = $st;
    }

    /**
     * GET /yazar-ol — wizard giriş; step state'e göre uygun adımı göster.
     */
    public function showForm(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        $user = AuthService::user();
        // Zaten admin/editor/author ise yönlendir
        if ($user && in_array($user['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR], true)) {
            flash('info', 'Hesabınız zaten yazar yetkisine sahip.');
            return Response::redirect(url('/panel'));
        }
        // Zaten başvuru pending'se mevcut durumu göster
        if ($user && self::statusOf((int) $user['id']) === 'pending') {
            return view('auth.author-application.pending', [
                'title' => 'Başvurunuz incelemede',
                'user'  => $user,
            ]);
        }

        $st = self::state();
        return view('auth.author-application.step' . (int) $st['step'], [
            'title' => 'Yazar Başvurusu · Adım ' . (int) $st['step'] . '/3',
            'state' => $st,
            'user'  => $user,
        ]);
    }

    /**
     * POST /yazar-ol — step submit; validate edip session'da state güncelle, ileri/geri git.
     */
    public function submitStep(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        $user = AuthService::user();
        if (!$user) {
            flash('error', 'Başvuru için önce giriş yapmalısınız.');
            return Response::redirect(url('/giris'));
        }
        $st = self::state();
        $step = (int) $req->input('step', $st['step'] ?? 1);
        $direction = (string) $req->input('direction', 'next');

        // Geri butonu — validate yapma, sadece azalt
        if ($direction === 'back') {
            $st['step'] = max(1, ((int) $st['step']) - 1);
            self::setState($st);
            return Response::redirect(url('/yazar-ol'));
        }

        // Validate & merge step data
        $errors = self::validateStep($step, $req, $st);
        if ($errors) {
            foreach ($errors as $k => $v) flash('error_' . $k, $v);
            return Response::redirect(url('/yazar-ol'));
        }

        // İleri git veya final submit
        if ($step < 3) {
            $st['step'] = $step + 1;
            self::setState($st);
            return Response::redirect(url('/yazar-ol'));
        }

        // Final — kaydet + admin'e bildir + teşekkür sayfası
        return self::finalize((int) $user['id'], $user, $st);
    }

    /**
     * Final submit: users tablosuna kaydet + admin'e mail.
     */
    private static function finalize(int $userId, array $user, array $st): Response
    {
        $data = $st['data'];
        $json = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Database::instance()->update('users', [
            'author_application_json'   => $json,
            'author_application_at'     => date('Y-m-d H:i:s'),
            'author_application_status' => 'pending',
        ], 'id = :wid', [':wid' => $userId]);

        unset($_SESSION[self::SESSION_KEY]);

        // Admin'e bildirim — try/catch ile sarılı, fail success'i bloklamaz
        try {
            \App\Services\PostNotifier::notifyAdminOfAuthorApplication(
                $userId,
                (string) $user['name'],
                (string) $user['email'],
                $data
            );
        } catch (\Throwable $e) {
            \App\Services\Logger::warning('author_app.notify_failed', [
                'user_id' => $userId, 'error' => $e->getMessage(),
            ], 'editorial');
        }

        \App\Services\Logger::info('author_app.submitted', [
            'user_id' => $userId,
        ], 'editorial');

        return Response::redirect(url('/yazar-ol/tesekkurler'));
    }

    public function thanks(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        return view('auth.author-application.thanks', [
            'title' => 'Başvurunuz alındı',
        ]);
    }

    /**
     * Step input'larını valide et + session'da state'i güncelle.
     *
     * @return array<string,string>
     */
    private static function validateStep(int $step, Request $req, array &$st): array
    {
        $errors = [];
        if ($step === 1) {
            $headline = trim((string) $req->input('headline', ''));
            $bio = trim((string) $req->input('bio', ''));
            if (mb_strlen($headline) < 10) {
                $errors['headline'] = 'Tek satırlık tanımınız en az 10 karakter olmalı.';
            }
            if (mb_strlen($bio) < 80) {
                $errors['bio'] = 'Biyografiniz en az 80 karakter olmalı.';
            }
            if (!$errors) {
                $st['data']['headline'] = mb_substr($headline, 0, 160);
                $st['data']['bio']      = mb_substr($bio, 0, 2000);
            }
        } elseif ($step === 2) {
            $expertise = trim((string) $req->input('expertise', ''));
            $motivation = trim((string) $req->input('motivation', ''));
            if (mb_strlen($expertise) < 5) {
                $errors['expertise'] = 'En az bir uzmanlık alanı belirtin.';
            }
            if (mb_strlen($motivation) < 80) {
                $errors['motivation'] = 'Motivasyon yazınız en az 80 karakter olmalı.';
            }
            if (!$errors) {
                $st['data']['expertise']  = mb_substr($expertise, 0, 500);
                $st['data']['motivation'] = mb_substr($motivation, 0, 2000);
            }
        } elseif ($step === 3) {
            $sampleUrl = trim((string) $req->input('sample_url', ''));
            $sampleText = trim((string) $req->input('sample_text', ''));
            $agree = $req->input('agree') ? 1 : 0;

            if ($sampleUrl === '' && mb_strlen($sampleText) < 200) {
                $errors['sample'] = 'Örnek yazınızın URL\'sini girin veya en az 200 karakterlik metin yapıştırın.';
            }
            if ($sampleUrl !== '' && !filter_var($sampleUrl, FILTER_VALIDATE_URL)) {
                $errors['sample_url'] = 'Geçerli bir URL girin (https://…)';
            }
            if (!$agree) {
                $errors['agree'] = 'Şartları kabul etmeniz gerekiyor.';
            }
            if (!$errors) {
                $st['data']['sample_url']  = mb_substr($sampleUrl, 0, 500);
                $st['data']['sample_text'] = mb_substr($sampleText, 0, 6000);
                $st['data']['agree']       = 1;
            }
        }
        if (!$errors) {
            self::setState($st);
        }
        return $errors;
    }

    private static function statusOf(int $userId): string
    {
        try {
            return (string) Database::instance()->fetchColumn(
                'SELECT author_application_status FROM users WHERE id = :id LIMIT 1',
                [':id' => $userId]
            );
        } catch (\Throwable) {
            return 'none';
        }
    }
}
