<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\MailTemplate;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\Sanitizer;

/**
 * Admin → Mail Şablonları (Tier 6).
 *
 * Tüm sistem mailleri burada düzenlenir. Yer tutucu: {user_name}, {site_name}, vb.
 */
final class MailTemplateController
{
    public function index(Request $req): Response
    {
        return view('admin.mail-templates.index', [
            'title' => 'Mail Şablonları',
            'list'  => MailTemplate::all(),
        ]);
    }

    public function edit(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $tpl = MailTemplate::findById($id);
        if (!$tpl) {
            return Response::notFound();
        }
        return view('admin.mail-templates.form', [
            'title' => 'Mail Şablonu — ' . $tpl['label'],
            'tpl'   => $tpl,
        ]);
    }

    public function update(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $tpl = MailTemplate::findById($id);
        if (!$tpl) {
            return Response::notFound();
        }

        $subject  = trim((string) $req->input('subject', ''));
        $bodyHtml = (string) $req->input('body_html', '');
        $isActive = ((int) $req->input('is_active', 1)) === 1 ? 1 : 0;

        if (mb_strlen($subject) < 3) {
            flash('error', 'Konu en az 3 karakter olmalı.');
            return Response::redirect(url('/admin/mail-sablonlari/' . $id . '/duzenle'));
        }

        // HTML sanitize — XSS koruması
        $bodyHtml = Sanitizer::clean($bodyHtml);

        MailTemplate::update($id, [
            'subject'   => mb_substr($subject, 0, 255),
            'body_html' => $bodyHtml,
            'is_active' => $isActive,
        ]);
        flash('success', 'Mail şablonu güncellendi.');
        return Response::redirect(url('/admin/mail-sablonlari/' . $id . '/duzenle'));
    }

    /**
     * Şablonu test maili olarak gönder — admin'in e-postasına.
     * Yer tutucular örnek değerlerle doldurulur.
     */
    public function sendTest(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $tpl = MailTemplate::findById($id);
        if (!$tpl) {
            return Response::notFound();
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::redirect(url('/giris'));
        }

        // Örnek değerler — yer tutucu varsa "Test Değer X" olarak doldur
        $vars = self::sampleVarsFor($tpl);

        $result = MailService::sendTemplate((string) $tpl['key_name'], (string) $user['email'], $vars);
        if ($result['ok'] ?? false) {
            flash('success', 'Test maili ' . $user['email'] . ' adresine gönderildi.');
        } else {
            flash('error', 'Test maili gönderilemedi: ' . ($result['error'] ?? 'bilinmeyen hata'));
        }
        return Response::redirect(url('/admin/mail-sablonlari/' . $id . '/duzenle'));
    }

    /**
     * Şablondaki yer tutucular için örnek değerler üret.
     */
    private static function sampleVarsFor(array $tpl): array
    {
        $samples = [
            'user_name'         => 'Örnek Ad',
            'applicant_name'    => 'Örnek Başvurucu',
            'applicant_email'   => 'ornek@example.com',
            'commenter_name'    => 'Örnek Yorumcu',
            'commenter_email'   => 'yorumcu@example.com',
            'comment_excerpt'   => 'Bu örnek bir yorum metnidir.',
            'author_name'       => 'Örnek Yazar',
            'post_title'        => 'Örnek Yazı Başlığı',
            'verification_link' => '#test-link',
            'review_link'       => '#test-link',
            'public_link'       => '#test-link',
            'panel_link'        => '#test-link',
            'moderation_link'   => '#test-link',
            'new_email'         => 'yeni@example.com',
            'reason'            => 'Örnek red gerekçesi.',
            'headline'          => 'Örnek tek satır tanıtım.',
            'expertise'         => 'mimari, restorasyon, BIM',
            'ip_address'        => '192.168.1.1',
        ];
        // Sadece şablonda gerçekten kullanılan değişkenleri döndür (substitute her halükarda hatasız çalışır)
        return $samples;
    }
}
