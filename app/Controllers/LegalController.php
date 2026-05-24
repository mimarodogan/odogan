<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\LegalDocument;
use App\Models\Setting;

/**
 * /sozlesmeler/{slug} — public sözleşme görüntüleyici (Tier 6).
 *
 * F3.1: KVKK Aydınlatma Metni gibi belgelerde Settings'ten okunan
 * placeholder'lar render aşamasında otomatik doldurulur. Admin bilgi
 * değişikliklerinde her belgeyi tek tek güncellemek zorunda kalmaz.
 */
final class LegalController
{
    public function show(Request $req, array $args): Response
    {
        $slug = (string) ($args['slug'] ?? '');
        $doc = LegalDocument::findBySlug($slug);
        if (!$doc) {
            return Response::notFound();
        }
        // F3.1: KVKK placeholder'larını Settings'ten doldur.
        $doc['body_html'] = self::renderPlaceholders((string) ($doc['body_html'] ?? ''));

        $url = absolute_url('/sozlesmeler/' . $slug);
        return view('pages.legal', [
            'title'       => $doc['title'],
            'description' => 'Yasal sözleşme — ' . $doc['title'],
            'canonical'   => $url,
            'robots'      => 'noindex, follow', // hukuki belgeler indexlenmesin
            'doc'         => $doc,
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Sözleşmeler', 'url' => url('/sozlesmeler')],
                ['name' => $doc['title'], 'url' => $url],
            ],
        ]);
    }

    /**
     * KVKK Aydınlatma Metni + diğer belgelerdeki `{{placeholder}}` tokenlarını
     * Settings → organization grubu değerleriyle değiştirir.
     *
     *   {{kvkk_data_controller}} → settings.kvkk_data_controller veya org_founder
     *   {{org_address}}          → birleşik adres satırı (street, city, postal_code, country)
     *   {{org_email}}            → settings.org_email
     *   {{org_phone}}            → settings.org_phone
     *   {{verbis_status}}        → VERBİS sicil no varsa "VERBİS sicil no: XYZ",
     *                              yoksa "VERBİS kayıt zorunluluğu kapsamında değildir"
     */
    private static function renderPlaceholders(string $html): string
    {
        if ($html === '' || !str_contains($html, '{{')) {
            return $html;
        }

        $dataController = trim((string) Setting::get('kvkk_data_controller', '', 'organization'));
        if ($dataController === '') {
            $dataController = trim((string) Setting::get('org_founder', '', 'organization'));
        }
        if ($dataController === '') {
            $dataController = trim((string) Setting::get('site_name', '', 'general')) ?: '—';
        }

        $email   = trim((string) Setting::get('org_email', '', 'organization')) ?: '—';
        $phone   = trim((string) Setting::get('org_phone', '', 'organization')) ?: '—';

        // Adres satırı: "Street, Postal City, Country"
        $street  = trim((string) Setting::get('org_street_address', '', 'organization'));
        $city    = trim((string) Setting::get('org_city', '', 'organization'));
        $zip     = trim((string) Setting::get('org_postal_code', '', 'organization'));
        $country = trim((string) Setting::get('org_country', '', 'organization'));
        $addrParts = array_values(array_filter([
            $street,
            trim(trim($zip . ' ' . $city)),
            $country !== '' ? strtoupper($country) : '',
        ], static fn($p) => $p !== ''));
        $address = $addrParts !== [] ? implode(', ', $addrParts) : '—';

        $verbisNo = trim((string) Setting::get('kvkk_verbis_no', '', 'organization'));
        $verbisStatus = $verbisNo !== ''
            ? 'VERBİS sicil numarası: <strong>' . htmlspecialchars($verbisNo, ENT_QUOTES, 'UTF-8') . '</strong>'
            : 'Veri Sorumluları Sicili (VERBİS) kayıt zorunluluğu kapsamında değildir';

        $replacements = [
            '{{kvkk_data_controller}}' => htmlspecialchars($dataController, ENT_QUOTES, 'UTF-8'),
            '{{org_address}}'          => htmlspecialchars($address, ENT_QUOTES, 'UTF-8'),
            '{{org_email}}'            => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
            '{{org_phone}}'            => htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'),
            '{{verbis_status}}'        => $verbisStatus, // HTML serbest
        ];
        return strtr($html, $replacements);
    }

    /**
     * /sozlesmeler — tüm aktif sözleşmelerin listesi.
     */
    public function index(Request $req): Response
    {
        return view('pages.legal-index', [
            'title' => 'Sözleşmeler',
            'description' => 'Üyelik sözleşmesi, yazar sözleşmesi, gizlilik politikası ve kullanım koşulları.',
            'canonical' => absolute_url('/sozlesmeler'),
            'robots'    => 'noindex, follow', // hukuki belgeler indexlenmesin
            'list'  => LegalDocument::all(),
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Sözleşmeler', 'url' => url('/sozlesmeler')],
            ],
        ]);
    }
}
