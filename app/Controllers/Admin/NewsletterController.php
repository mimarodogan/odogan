<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Services\NewsletterService;

final class NewsletterController
{
    public function index(Request $req): Response
    {
        return view('admin.newsletter.index', [
            'title' => 'Newsletter Aboneleri',
            'subscribers' => Subscriber::listAll(500),
            'stats' => Subscriber::stats(),
            'brevo_status' => NewsletterService::brevoStatus(),
        ]);
    }

    public function settings(Request $req): Response
    {
        return view('admin.newsletter.settings', [
            'title' => 'Newsletter Ayarları',
            'brevo_key' => (string) Setting::get('newsletter_brevo_key', ''),
            'brevo_list_id' => (string) Setting::get('newsletter_brevo_list_id', ''),
            'brevo_status' => NewsletterService::brevoStatus(),
        ]);
    }

    public function updateSettings(Request $req): Response
    {
        Setting::set('newsletter_brevo_key', trim((string) $req->input('brevo_key', '')));
        Setting::set('newsletter_brevo_list_id', trim((string) $req->input('brevo_list_id', '')));
        flash('success', 'Brevo ayarları kaydedildi.');
        return Response::redirect(url('/admin/newsletter/ayarlar'));
    }

    public function exportCsv(Request $req): Response
    {
        $rows = Subscriber::listAll(10000);
        $csv = "email,name,confirmed_at,brevo_contact_id,created_at\n";
        foreach ($rows as $r) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s"' . "\n",
                str_replace('"', '""', (string) $r['email']),
                str_replace('"', '""', (string) ($r['name'] ?? '')),
                (string) ($r['confirmed_at'] ?? ''),
                (string) ($r['brevo_contact_id'] ?? ''),
                (string) $r['created_at']
            );
        }
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="subscribers-' . date('Y-m-d') . '.csv"',
        ]);
    }
}
