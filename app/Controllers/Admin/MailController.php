<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Services\AuthService;
use App\Services\Crypto;
use App\Services\MailService;

final class MailController
{
    private const FIELDS = [
        'driver'       => ['type' => 'string', 'default' => 'smtp'],
        'preset'       => ['type' => 'string', 'default' => 'custom'],
        'host'         => ['type' => 'string', 'default' => ''],
        'port'         => ['type' => 'int',    'default' => 587],
        'username'     => ['type' => 'string', 'default' => ''],
        'password'     => ['type' => 'string', 'default' => ''],
        'encryption'   => ['type' => 'string', 'default' => 'tls'],
        'from_address' => ['type' => 'string', 'default' => ''],
        'from_name'    => ['type' => 'string', 'default' => ''],
        'log_only'     => ['type' => 'bool',   'default' => false],
    ];

    public function index(Request $req): Response
    {
        $values = [];
        foreach (self::FIELDS as $key => $def) {
            $values[$key] = Setting::get($key, $def['default'], 'mail');
        }
        return view('admin.mail', [
            'title'   => 'E-posta (SMTP) Ayarları',
            'user'    => AuthService::user(),
            'values'  => $values,
            'presets' => MailService::PRESETS,
        ]);
    }

    public function update(Request $req): Response
    {
        $payload = [];
        $types = [];
        foreach (self::FIELDS as $key => $def) {
            $types[$key] = $def['type'];
            $raw = $req->input($key, null);
            $payload[$key] = self::coerce($raw, $def['type']);
        }
        // Password güvenliği: kullanıcı boş bırakırsa eski parolayı (encrypted
        // form'da bile) koru. Yeni parola geldiyse APP_KEY ile şifrele.
        if ($payload['password'] === '') {
            $existing = (string) Setting::get('password', '', 'mail');
            if ($existing !== '') {
                $payload['password'] = $existing; // already encrypted in DB
            }
        } else {
            try {
                $payload['password'] = Crypto::encrypt((string) $payload['password']);
            } catch (\Throwable $e) {
                // APP_KEY yoksa parolayı plaintext olarak kaydetmek YERINE
                // hata göster ve eski değeri koru — admin sorunu fark etsin.
                flash('error', 'SMTP parolası şifrelenemedi: ' . $e->getMessage()
                    . ' — APP_KEY .env\'de tanımlı değil. Üretmek için: openssl rand -base64 32');
                $existing = (string) Setting::get('password', '', 'mail');
                $payload['password'] = $existing;
            }
        }
        Setting::saveGroup('mail', $payload, $types);
        Setting::flushCache();
        flash('success', 'E-posta ayarları kaydedildi.');
        return Response::redirect(url('/admin/mail'));
    }

    public function sendTest(Request $req): Response
    {
        $to = trim((string) $req->input('to', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Geçerli bir e-posta adresi girin.');
            return Response::redirect(url('/admin/mail'));
        }
        $result = MailService::sendTest($to);
        if ($result['ok']) {
            flash('success', sprintf(
                'Test e-postası %s adresine gönderildi (transport: %s).',
                $to,
                $result['transport']
            ));
        } else {
            flash('error', 'Gönderim başarısız: ' . ($result['error'] ?? 'bilinmeyen hata'));
        }
        if (!empty($result['debug'])) {
            flash('mail_debug', (string) $result['debug']);
        }
        return Response::redirect(url('/admin/mail'));
    }

    private static function coerce(mixed $raw, string $type): mixed
    {
        if ($type === 'bool') {
            return $raw === '1' || $raw === 'on' || $raw === 'true' || $raw === true;
        }
        if ($type === 'int') {
            return (int) ($raw ?? 0);
        }
        return trim((string) ($raw ?? ''));
    }
}
