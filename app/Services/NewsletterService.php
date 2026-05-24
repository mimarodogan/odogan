<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Setting;
use App\Models\Subscriber;

/**
 * Brevo (Sendinblue) entegrasyonu + lokal abone DB.
 *
 * Akış:
 *  1) Kullanıcı email gönderir → subscribe() → DB'ye unconfirmed satır + confirm mail
 *  2) Confirm linke tıklar → confirm() → DB onayla + Brevo'ya contact gönder
 *  3) Unsubscribe linki → unsubscribe() → DB sil + Brevo'dan kaldır
 *
 * Brevo SDK yoksa: senkronizasyon skip edilir (lokal-only mod).
 * Yine de subscribe/confirm/unsub akışı çalışır.
 */
final class NewsletterService
{
    /**
     * @return array{ok:bool, error?:string, already_confirmed?:bool}
     */
    public static function subscribe(string $email, ?string $name = null, ?string $ip = null): array
    {
        $email = trim(mb_strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Geçerli bir e-posta girin.'];
        }

        $existing = Subscriber::findByEmail($email);
        if ($existing) {
            if (!empty($existing['confirmed_at'])) {
                return ['ok' => true, 'already_confirmed' => true];
            }
            // Confirmed değilse: yeni token üret, tekrar mail
            self::sendConfirmEmail((string) $existing['email'], (string) ($existing['name'] ?? ''), (string) $existing['confirm_token']);
            return ['ok' => true];
        }

        $sub = Subscriber::create($email, $name, $ip);
        self::sendConfirmEmail($sub['email'], (string) ($sub['name'] ?? ''), (string) $sub['confirm_token']);

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool, error?:string}
     */
    public static function confirm(string $token): array
    {
        $sub = Subscriber::findByConfirmToken($token);
        if (!$sub) {
            return ['ok' => false, 'error' => 'Geçersiz veya süresi dolmuş onay bağlantısı.'];
        }
        // Brevo'ya gönder (opsiyonel)
        $brevoId = self::pushToBrevo((string) $sub['email'], (string) ($sub['name'] ?? ''));
        Subscriber::confirm((int) $sub['id'], $brevoId);
        return ['ok' => true];
    }

    /**
     * @return array{ok:bool, error?:string}
     */
    public static function unsubscribe(string $token): array
    {
        $sub = Subscriber::findByUnsubToken($token);
        if (!$sub) {
            return ['ok' => false, 'error' => 'Geçersiz veya süresi dolmuş bağlantı.'];
        }
        // Brevo'dan kaldır
        self::removeFromBrevo((string) $sub['email']);
        Subscriber::deleteById((int) $sub['id']);
        return ['ok' => true];
    }

    // ─── Mail ────────────────────────────────────────────────────

    private static function sendConfirmEmail(string $email, string $name, string $token): void
    {
        $confirmUrl = absolute_url('/newsletter/onay/' . $token);
        $siteName = (string) Setting::get('site_name', Config::get('APP_NAME', 'Otorite Yayin'));
        $html = sprintf(
            '<p>Merhaba %s,</p>'
            . '<p>%s bültenine abone olmak istediğinizi doğrulayın:</p>'
            . '<p><a href="%s">%s</a></p>'
            . '<p>Bu maili siz istemediyseniz görmezden gelin.</p>',
            esc($name ?: $email),
            esc($siteName),
            esc($confirmUrl),
            esc($confirmUrl)
        );
        MailService::send($email, $siteName . ' bültenine abone olun', $html);
    }

    // ─── Brevo SDK Wrapper (opsiyonel) ──────────────────────────

    private static function brevoApiKey(): string
    {
        $key = (string) Setting::get('newsletter_brevo_key', Config::get('BREVO_API_KEY', ''));
        return $key;
    }

    private static function brevoListId(): int
    {
        return (int) Setting::get('newsletter_brevo_list_id', (int) Config::get('BREVO_LIST_ID', 0));
    }

    /**
     * Brevo Contacts API'ye gönder. SDK yoksa veya hata olursa null döner — DB akışı bozulmaz.
     */
    private static function pushToBrevo(string $email, string $name): ?string
    {
        $apiKey = self::brevoApiKey();
        if ($apiKey === '' || !class_exists(\Brevo\Client\Api\ContactsApi::class)) {
            return null;
        }
        try {
            $config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $apiKey);
            $api = new \Brevo\Client\Api\ContactsApi(new \GuzzleHttp\Client(), $config);
            $contact = new \Brevo\Client\Model\CreateContact([
                'email' => $email,
                'attributes' => $name !== '' ? ['FIRSTNAME' => $name] : null,
                'listIds' => self::brevoListId() > 0 ? [self::brevoListId()] : null,
                'updateEnabled' => true,
            ]);
            $result = $api->createContact($contact);
            return $result && method_exists($result, 'getId') ? (string) $result->getId() : null;
        } catch (\Throwable $e) {
            Logger::warning('newsletter.brevo.push_failed', [
                'email' => $email, 'error' => $e->getMessage(),
            ], 'newsletter');
            return null;
        }
    }

    private static function removeFromBrevo(string $email): void
    {
        $apiKey = self::brevoApiKey();
        if ($apiKey === '' || !class_exists(\Brevo\Client\Api\ContactsApi::class)) {
            return;
        }
        try {
            $config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $apiKey);
            $api = new \Brevo\Client\Api\ContactsApi(new \GuzzleHttp\Client(), $config);
            $api->deleteContact($email);
        } catch (\Throwable $e) {
            Logger::warning('newsletter.brevo.remove_failed', [
                'email' => $email, 'error' => $e->getMessage(),
            ], 'newsletter');
        }
    }

    /**
     * Brevo bağlantı testi — admin panelden kullanılır.
     * @return array{ok:bool, error?:string, sdk_loaded:bool, key_set:bool}
     */
    public static function brevoStatus(): array
    {
        $sdkLoaded = class_exists(\Brevo\Client\Api\AccountApi::class);
        $key = self::brevoApiKey();
        if (!$sdkLoaded) {
            return ['ok' => false, 'sdk_loaded' => false, 'key_set' => $key !== '',
                    'error' => 'Brevo SDK yüklü değil. composer install gerekli.'];
        }
        if ($key === '') {
            return ['ok' => false, 'sdk_loaded' => true, 'key_set' => false,
                    'error' => 'API key tanımlı değil.'];
        }
        try {
            $config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $key);
            $api = new \Brevo\Client\Api\AccountApi(new \GuzzleHttp\Client(), $config);
            $api->getAccount();
            return ['ok' => true, 'sdk_loaded' => true, 'key_set' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'sdk_loaded' => true, 'key_set' => true,
                    'error' => $e->getMessage()];
        }
    }
}
