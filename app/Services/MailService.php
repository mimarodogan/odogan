<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Setting;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Thin SMTP wrapper. Falls back to PHP mail() if PHPMailer is unavailable.
 * Configuration order of preference: Settings (mail group) → .env Config → defaults.
 * When the driver is "log" (or host missing/example), the message is appended
 * to storage/logs/mail-YYYY-MM-DD.log so the workflow stays observable.
 */
final class MailService
{
    /**
     * Known SMTP presets surfaced in the admin UI. They are not enforced —
     * users can still override per field — but provide one-click defaults.
     *
     * @var array<string, array{host:string,port:int,encryption:string,hint:string}>
     */
    public const PRESETS = [
        'custom' => [
            'host' => '', 'port' => 587, 'encryption' => 'tls',
            'hint' => 'Tüm alanları kendin doldur.',
        ],
        'gmail' => [
            'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls',
            'hint' => 'Gmail: Hesap → Güvenlik → "Uygulama parolası" oluştur ve buraya yaz. Normal parola çalışmaz.',
        ],
        'gmail-ssl' => [
            'host' => 'smtp.gmail.com', 'port' => 465, 'encryption' => 'ssl',
            'hint' => 'Gmail (SSL/465). Uygulama parolası gerekir.',
        ],
        'hotmail' => [
            'host' => 'smtp-mail.outlook.com', 'port' => 587, 'encryption' => 'tls',
            'hint' => 'Hotmail (@hotmail.com / @hotmail.com.tr). account.live.com → Güvenlik → "Uygulama parolası" oluştur. Normal hesap parolası SMTP\'de çalışmaz.',
        ],
        'outlook' => [
            'host' => 'smtp-mail.outlook.com', 'port' => 587, 'encryption' => 'tls',
            'hint' => 'Outlook.com / Live.com / MSN.com kişisel hesapları. account.live.com → Güvenlik → Uygulama parolası gerekir.',
        ],
        'office365' => [
            'host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls',
            'hint' => 'Office 365 / Microsoft 365 iş hesapları. 2FA açık olmalı + uygulama parolası.',
        ],
        'yandex' => [
            'host' => 'smtp.yandex.com', 'port' => 465, 'encryption' => 'ssl',
            'hint' => 'Yandex Mail. Hesap → "Uygulama parolaları" üret.',
        ],
        'sendgrid' => [
            'host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls',
            'hint' => 'Kullanıcı adı: "apikey", parola: SendGrid API anahtarın.',
        ],
        'mailgun' => [
            'host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls',
            'hint' => 'Kullanıcı adı: postmaster@mail.alanın.com, parola: Mailgun SMTP şifren.',
        ],
        'amazon-ses' => [
            'host' => 'email-smtp.eu-west-1.amazonaws.com', 'port' => 587, 'encryption' => 'tls',
            'hint' => 'AWS SES → IAM altında SMTP kimlik bilgisi oluştur. Region\'a göre host değişebilir.',
        ],
    ];

    /**
     * @return array{ok:bool, error?:string, transport:string}
     */
    public static function send(string $to, string $subject, string $bodyHtml, ?string $bodyText = null): array
    {
        $bodyText = $bodyText ?? trim((string) preg_replace('/\s+/u', ' ', strip_tags($bodyHtml)));

        $cfg = self::loadConfig();

        if ($cfg['driver'] === 'log' || self::shouldLogOnly($cfg)) {
            self::logToFile($to, $subject, $bodyHtml);
            return ['ok' => true, 'transport' => 'log'];
        }

        if ($cfg['driver'] === 'smtp') {
            if (!class_exists(PHPMailer::class)) {
                return [
                    'ok' => false,
                    'transport' => 'smtp',
                    'error' => 'PHPMailer kütüphanesi yüklü değil. Sunucuda `composer install --no-dev` çalıştır veya driver olarak "PHP mail()" / "Yalnız log" seç.',
                ];
            }
            return self::sendViaPhpMailer($cfg, $to, $subject, $bodyHtml, $bodyText);
        }
        return self::sendViaMail($cfg, $to, $subject, $bodyHtml);
    }

    /**
     * Active mail configuration. Settings (DB) overrides .env when present.
     *
     * SMTP password is stored at-rest with Crypto::encrypt() (envelope
     * `enc:v1:…`). loadConfig() transparently decrypts it; legacy plaintext
     * rows stay readable until the next /admin/mail save re-encrypts them.
     *
     * @return array{driver:string,host:string,port:int,username:string,password:string,encryption:string,from_address:string,from_name:string,log_only:bool}
     */
    public static function loadConfig(): array
    {
        $driverRaw = (string) Setting::get('driver', Config::get('MAIL_DRIVER', 'smtp'), 'mail');
        $driver = in_array($driverRaw, ['smtp', 'log', 'mail'], true) ? $driverRaw : 'smtp';

        $rawPassword = (string) Setting::get('password', Config::get('MAIL_PASSWORD', ''), 'mail');
        $password = $rawPassword;
        if ($rawPassword !== '' && Crypto::isEncrypted($rawPassword)) {
            $decoded = Crypto::decrypt($rawPassword);
            if ($decoded !== null) {
                $password = $decoded;
            } else {
                // Tamper / wrong APP_KEY — better to fall back to empty than
                // ship ciphertext to the SMTP server.
                $password = '';
            }
        }

        return [
            'driver'       => $driver,
            'host'         => (string) Setting::get('host',         Config::get('MAIL_HOST', ''), 'mail'),
            'port'         => (int)    Setting::get('port',         Config::get('MAIL_PORT', 587), 'mail'),
            'username'     => (string) Setting::get('username',     Config::get('MAIL_USERNAME', ''), 'mail'),
            'password'     => $password,
            'encryption'   => strtolower((string) Setting::get('encryption', Config::get('MAIL_ENCRYPTION', 'tls'), 'mail')),
            'from_address' => (string) Setting::get('from_address', Config::get('MAIL_FROM_ADDRESS', 'no-reply@example.com'), 'mail'),
            'from_name'    => (string) Setting::get('from_name',    Config::get('MAIL_FROM_NAME', 'Otorite Yayin'), 'mail'),
            'log_only'     => (bool)   Setting::get('log_only', false, 'mail'),
        ];
    }

    /**
     * Public test hook used by the admin "send test email" button.
     * Captures the full SMTP conversation in verbose mode so admins can
     * see the real server response (e.g. Microsoft's exact auth refusal
     * reason) and not just PHPMailer's generic "Could not authenticate".
     *
     * @return array{ok:bool, error?:string, transport:string, debug?:string}
     */
    /**
     * Şablon bazlı mail gönderimi (Tier 6).
     *
     * Anahtarla mail_templates'tan şablonu çeker, değişkenleri yerine koyup gönderir.
     * Şablon yoksa veya inactive ise false döner — caller fallback yapabilir.
     *
     * @param string $key  mail_templates.key_name (örn: 'verify_email')
     * @param string $to   alıcı e-posta
     * @param array<string,string|int> $vars  Şablondaki yer tutucular için değerler
     *                                         {site_name} otomatik eklenir.
     * @return array{ok:bool, error?:string, transport?:string}
     */
    public static function sendTemplate(string $key, string $to, array $vars = []): array
    {
        // {site_name} ve {site_url} her şablon için otomatik
        $defaults = [
            'site_name' => (string) Setting::get('site_name', Config::get('APP_NAME', 'Otorite Yayin')),
            'site_url'  => rtrim((string) (function_exists('absolute_url') ? absolute_url('/') : Config::get('APP_URL', '/')), '/'),
            'date_time' => date('d/m/Y H:i'),
        ];
        $vars = array_merge($defaults, $vars);

        $rendered = \App\Models\MailTemplate::render($key, $vars);
        if ($rendered === null) {
            return ['ok' => false, 'error' => 'Şablon yok veya pasif: ' . $key];
        }

        return self::send($to, $rendered['subject'], $rendered['body']);
    }

    public static function sendTest(string $to): array
    {
        $cfg = self::loadConfig();
        $siteName = (string) Setting::get('site_name', Config::get('APP_NAME', 'Otorite Yayin'));
        $subject = $siteName . ' · sunucu kurulum doğrulaması';
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . esc($subject) . '</title></head>'
              . '<body style="font-family:Arial,sans-serif;color:#222;line-height:1.5;max-width:560px;margin:0 auto;padding:24px">'
              . '<h2 style="font-family:Georgia,serif;margin:0 0 16px;color:#111">Merhaba,</h2>'
              . '<p>Bu mesaj <strong>' . esc($siteName) . '</strong> sitesinin e-posta yapılandırmasını doğrulamak için gönderildi. '
              . 'Bu mesajı aldıysanız, sistem e-postaları (bildirim, parola sıfırlama vb.) düzgün çalışıyor demektir.</p>'
              . '<p style="color:#666;font-size:13px;border-top:1px solid #eee;padding-top:12px;margin-top:24px">'
              . 'Gönderim zamanı: ' . esc(date('Y-m-d H:i')) . '<br>'
              . 'Sunucu: ' . esc($cfg['host']) . ':' . (int) $cfg['port'] . ' (' . esc($cfg['encryption']) . ')<br>'
              . 'Bu mesaj otomatik üretilmiştir; cevap vermenize gerek yoktur.'
              . '</p>'
              . '</body></html>';

        if ($cfg['driver'] === 'log' || self::shouldLogOnly($cfg)) {
            return self::send($to, $subject, $html);
        }
        if ($cfg['driver'] === 'smtp') {
            if (!class_exists(PHPMailer::class)) {
                return [
                    'ok' => false,
                    'transport' => 'smtp',
                    'error' => 'PHPMailer yüklü değil. composer install yap veya vendor/ klasörünü sunucuya yükle.',
                ];
            }
            return self::sendViaPhpMailerVerbose($cfg, $to, $subject, $html);
        }
        return self::send($to, $subject, $html);
    }

    /**
     * Same as sendViaPhpMailer but captures the full SMTP transcript.
     */
    private static function sendViaPhpMailerVerbose(array $cfg, string $to, string $subject, string $html): array
    {
        $log = [];
        try {
            $m = new PHPMailer(true);
            $m->SMTPDebug = 2; // CONNECTION + AUTH conversation
            $m->Debugoutput = function ($str, $level) use (&$log) {
                $log[] = '[' . $level . '] ' . trim($str);
            };
            $m->isSMTP();
            $m->Host = $cfg['host'] !== '' ? $cfg['host'] : 'localhost';
            $m->Port = $cfg['port'] > 0 ? $cfg['port'] : 587;
            if ($cfg['username'] !== '') {
                $m->SMTPAuth = true;
                $m->Username = $cfg['username'];
                $m->Password = $cfg['password'];
            }
            $enc = $cfg['encryption'];
            if ($enc === 'ssl') {
                $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $m->SMTPSecure = false;
                $m->SMTPAutoTLS = false;
            }
            $m->CharSet = 'UTF-8';
            $m->Encoding = 'base64';
            $m->Timeout = 15;
            self::applyAntiSpamHeaders($m, $cfg, $to);
            $m->setFrom($cfg['from_address'], $cfg['from_name']);
            $m->addAddress($to);
            $m->isHTML(true);
            $m->Subject = $subject;
            $m->Body = $html;
            $m->AltBody = trim(strip_tags($html));
            $m->send();
            return [
                'ok' => true,
                'transport' => 'smtp',
                'debug' => self::redactDebug($log, $cfg),
            ];
        } catch (MailException | \Throwable $e) {
            return [
                'ok' => false,
                'transport' => 'smtp',
                'error' => $e->getMessage(),
                'debug' => self::redactDebug($log, $cfg),
            ];
        }
    }

    /**
     * Strip the password from the SMTP transcript before showing it to humans.
     * Server-side log file still contains the redacted version.
     */
    private static function redactDebug(array $log, array $cfg): string
    {
        $joined = implode("\n", $log);
        if ($cfg['password'] !== '') {
            $joined = str_replace(
                base64_encode($cfg['password']),
                '***REDACTED***',
                $joined
            );
            $joined = str_replace($cfg['password'], '***REDACTED***', $joined);
        }
        // Keep last ~80 lines so the flash message doesn't explode
        $lines = explode("\n", $joined);
        if (count($lines) > 80) {
            $lines = array_slice($lines, -80);
        }
        return implode("\n", $lines);
    }

    /**
     * @param array{driver:string,host:string,log_only:bool} $cfg
     */
    private static function shouldLogOnly(array $cfg): bool
    {
        if ($cfg['log_only']) {
            return true;
        }
        $host = $cfg['host'];
        return $host === '' || str_contains($host, 'example.com');
    }

    /**
     * @param array $cfg loadConfig() output
     * @return array{ok:bool, error?:string, transport:string}
     */
    private static function sendViaPhpMailer(array $cfg, string $to, string $subject, string $html, string $text): array
    {
        try {
            $m = new PHPMailer(true);
            $m->isSMTP();
            $m->Host = $cfg['host'] !== '' ? $cfg['host'] : 'localhost';
            $m->Port = $cfg['port'] > 0 ? $cfg['port'] : 587;
            if ($cfg['username'] !== '') {
                $m->SMTPAuth = true;
                $m->Username = $cfg['username'];
                $m->Password = $cfg['password'];
            }
            $enc = $cfg['encryption'];
            if ($enc === 'ssl') {
                $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $m->SMTPSecure = false;
                $m->SMTPAutoTLS = false;
            }
            $m->CharSet = 'UTF-8';
            $m->Encoding = 'base64';
            $m->Timeout = 15;
            self::applyAntiSpamHeaders($m, $cfg, $to);
            $m->setFrom($cfg['from_address'], $cfg['from_name']);
            $m->addAddress($to);
            $m->isHTML(true);
            $m->Subject = $subject;
            $m->Body = $html;
            $m->AltBody = $text !== '' ? $text : trim(strip_tags($html));
            $m->send();
            return ['ok' => true, 'transport' => 'smtp'];
        } catch (MailException | \Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'transport' => 'smtp'];
        }
    }

    /**
     * Add the headers Gmail / Outlook spam filters look for:
     *  - Reply-To equal to From (avoids "via" warning when SMTP user ≠ From)
     *  - Stable Message-ID with the From domain
     *  - X-Mailer omitted (default value reveals PHPMailer, mild signal)
     *  - List-Unsubscribe (transactional → required by Yahoo/Gmail since Feb 2024)
     *  - Auto-Submitted: auto-generated (clarifies it's not personal correspondence)
     */
    private static function applyAntiSpamHeaders(PHPMailer $m, array $cfg, string $to): void
    {
        $fromAddr = $cfg['from_address'];
        $domain = self::extractDomain($fromAddr) ?: 'localhost';

        $m->addReplyTo($fromAddr, $cfg['from_name']);

        try {
            $m->MessageID = '<' . bin2hex(random_bytes(12)) . '@' . $domain . '>';
        } catch (\Throwable) {}

        $m->XMailer = ' ';

        $unsub = 'mailto:' . $fromAddr . '?subject=unsubscribe';
        $m->addCustomHeader('List-Unsubscribe', '<' . $unsub . '>');
        $m->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $m->addCustomHeader('Auto-Submitted', 'auto-generated');
        $m->addCustomHeader('Precedence', 'bulk');
    }

    private static function extractDomain(string $email): ?string
    {
        $at = strrpos($email, '@');
        if ($at === false) return null;
        $d = substr($email, $at + 1);
        return $d !== '' ? strtolower($d) : null;
    }

    /**
     * @param array $cfg loadConfig() output
     * @return array{ok:bool, error?:string, transport:string}
     */
    private static function sendViaMail(array $cfg, string $to, string $subject, string $html): array
    {
        if (!function_exists('mail')) {
            return [
                'ok' => false,
                'transport' => 'mail()',
                'error' => 'PHP mail() fonksiyonu bu sunucuda devre dışı (php.ini → disable_functions). SMTP kullanmak için /admin/mail sayfasında Transport = "SMTP" seç ve vendor/phpmailer/ klasörünün sunucuda yüklü olduğundan emin ol.',
            ];
        }
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sprintf('"%s" <%s>', $cfg['from_name'], $cfg['from_address']),
        ];
        $ok = @\mail($to, $subject, $html, implode("\r\n", $headers));
        return $ok
            ? ['ok' => true, 'transport' => 'mail()']
            : ['ok' => false, 'transport' => 'mail()', 'error' => 'mail() çağrıldı ama gönderim başarısız döndü. SMTP transport\'una geç.'];
    }

    private static function logToFile(string $to, string $subject, string $html): void
    {
        $dir = Config::root() . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] TO=%s SUBJECT=%s\n%s\n--\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $html
        );
        @file_put_contents(
            $dir . '/mail-' . date('Y-m-d') . '.log',
            $line,
            FILE_APPEND | LOCK_EX
        );
    }
}
