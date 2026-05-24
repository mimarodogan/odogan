<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Services\Logger;
use App\Services\MailService;
use App\Services\Schema\Breadcrumb;
use App\Services\Schema\Renderer;
use App\Services\Schema\WebPage as SchemaWebPage;

/**
 * /iletisim — public iletişim formu.
 *
 *  GET  /iletisim → form view
 *  POST /iletisim → spam koruma + mail gönder + flash + redirect
 *
 * Spam koruma katmanları:
 *   1) CSRF token (global middleware)
 *   2) Honeypot field "website" — bot dolduruncu reject
 *   3) Min süre (5 sn) — form çok hızlı submit edildiyse reject
 *   4) IP başına saatte max 3 submit (rate limit)
 */
final class ContactController
{
    public function showForm(Request $req): Response
    {
        $url = absolute_url('/iletisim');
        $siteName = (string) Setting::get('site_name', 'Bu site', 'general');

        // Schema graph
        $breadcrumbId = $url . '#breadcrumb';
        $schema = (new Renderer())
            ->add(Renderer::siteOrganization())
            ->add(Renderer::siteWebsite())
            ->add(SchemaWebPage::build($url, 'İletişim — ' . $siteName, [
                'type'          => 'ContactPage',
                'description'   => $siteName . ' ile iletişim formu — mimarlık, yapı, işbirliği ve yorum talepleri için.',
                'breadcrumb_id' => $breadcrumbId,
            ]))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'İletişim',  'url' => $url],
            ], $breadcrumbId));

        return view('pages.contact', [
            'title'         => 'İletişim — ' . $siteName,
            'description'   => $siteName . ' ile iletişim formu. Mimarlık, yapı kültürü, işbirliği veya basit yorumlar için.',
            'canonical'     => $url,
            'schema_jsonld' => $schema->emit(),
            'org_email'     => (string) Setting::get('org_email',   '', 'organization'),
            'org_phone'     => (string) Setting::get('org_phone',   '', 'organization'),
            'breadcrumbs'   => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'İletişim',  'url' => $url],
            ],
        ]);
    }

    public function submit(Request $req): Response
    {
        // 1. Honeypot (gizli "website" alanı)
        if (trim((string) $req->input('website', '')) !== '') {
            return $this->reject('spam_honeypot');
        }

        // 2. Min form süresi (frontend "rendered_at" hidden field koyar)
        $renderedAt = (int) $req->input('rendered_at', 0);
        if ($renderedAt > 0 && (time() - $renderedAt) < 5) {
            return $this->reject('spam_too_fast');
        }

        // 3. Rate limit — IP başına saatte 3
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!$this->checkRateLimit($ip)) {
            flash('error', 'Saatte en fazla 3 mesaj gönderebilirsiniz. Lütfen biraz sonra tekrar deneyin.');
            return Response::redirect(url('/iletisim'));
        }

        // 4. Alanları doğrula
        $name    = trim((string) $req->input('name',    ''));
        $email   = trim((string) $req->input('email',   ''));
        $phone   = trim((string) $req->input('phone',   ''));
        $subject = trim((string) $req->input('subject', ''));
        $message = trim((string) $req->input('message', ''));

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Ad en az 2, en fazla 100 karakter olmalı.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta adresi girin.';
        }
        if ($phone !== '' && mb_strlen($phone) > 30) {
            $errors[] = 'Telefon en fazla 30 karakter olmalı.';
        }
        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
            $errors[] = 'Konu en az 3, en fazla 150 karakter olmalı.';
        }
        if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
            $errors[] = 'Mesaj en az 10, en fazla 5000 karakter olmalı.';
        }
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            return Response::redirect(url('/iletisim'));
        }

        // 5. Mail gönder — admin/yazar adresine
        $toAddress = trim((string) Setting::get('org_email', '', 'organization'));
        if ($toAddress === '') {
            $toAddress = trim((string) Setting::get('admin_notify_email', '', 'general'));
        }
        if ($toAddress === '' || !filter_var($toAddress, FILTER_VALIDATE_EMAIL)) {
            // Mail adresi tanımlı değil — gönderilemiyor; ama kullanıcıya teşekkür et,
            // mesajı sadece logla.
            if (class_exists(Logger::class)) {
                Logger::warning('contact.no_admin_email', [
                    'name' => $name, 'email' => $email, 'subject' => $subject,
                ], 'contact');
            }
            flash('success', 'Mesajınız alındı, en kısa sürede dönüş yapılacak.');
            return Response::redirect(url('/iletisim'));
        }

        try {
            MailService::sendTemplate('contact_form_received', $toAddress, [
                'visitor_name'  => $name,
                'visitor_email' => $email,
                'visitor_phone' => $phone !== '' ? $phone : '—',
                'subject_line'  => $subject,
                'message'       => nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')),
                'site_name'     => (string) Setting::get('site_name', '', 'general'),
                'ip_address'    => $ip,
            ]);
            $this->recordSubmit($ip);
            flash('success', 'Mesajınız alındı, teşekkürler. En kısa sürede dönüş yapılacak.');
        } catch (\Throwable $e) {
            if (class_exists(Logger::class)) {
                Logger::error('contact.send_failed', [
                    'msg' => $e->getMessage(),
                    'visitor_email' => $email,
                ], 'contact');
            }
            flash('error', 'Mesaj gönderilemedi. Lütfen daha sonra tekrar deneyin veya doğrudan e-posta ile ulaşın.');
        }

        return Response::redirect(url('/iletisim'));
    }

    /** Honeypot/spam reject — sessizce kullanıcıyı yönlendir (bot'a ipucu verme). */
    private function reject(string $reason): Response
    {
        if (class_exists(Logger::class)) {
            Logger::info('contact.spam_blocked', ['reason' => $reason], 'contact');
        }
        // Bot için success gibi gösterir; gerçek mail yok.
        flash('success', 'Mesajınız alındı.');
        return Response::redirect(url('/iletisim'));
    }

    /** IP başına saatte 3 limit. Settings tablosunu kısa süreli sayaç olarak kullanırız. */
    private function checkRateLimit(string $ip): bool
    {
        $key = 'contact_rate_' . md5($ip) . '_' . date('YmdH');
        try {
            $val = (int) Setting::get($key, 0, '__contact_rate');
            return $val < 3;
        } catch (\Throwable) {
            return true; // hata olursa engelleme
        }
    }

    private function recordSubmit(string $ip): void
    {
        $key = 'contact_rate_' . md5($ip) . '_' . date('YmdH');
        try {
            $current = (int) Setting::get($key, 0, '__contact_rate');
            // Setting::set veya doğrudan DB update — basit insert/update
            $db = Database::instance();
            $sql = 'INSERT INTO settings (group_name, key_name, value, value_type) '
                 . 'VALUES (:g, :k, :v, "string") '
                 . 'ON DUPLICATE KEY UPDATE value = :v2';
            $db->run($sql, [
                ':g'  => '__contact_rate',
                ':k'  => $key,
                ':v'  => (string) ($current + 1),
                ':v2' => (string) ($current + 1),
            ]);
        } catch (\Throwable) {
            // Sessiz başarısızlık — rate limit kritik değil
        }
    }
}
