<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Logger;

/**
 * Proje CRUD — Admin + Editor + Author erişebilir; yetki kontrolü
 * controller içinde (route grubu sadece AuthMiddleware).
 *
 * Davranış matrisi:
 *   Admin   → tüm projeleri görür, her status'ta kaydedebilir, onaylar, siler
 *   Editor  → tüm projeleri görür, kendi yarattığını edit eder, draft kaydeder,
 *             onay isteyebilir (Author'unkini onaylayamaz; final onay admin'in)
 *   Author  → sadece KENDİ projelerini görür, draft kaydeder, onay ister
 *             (kendisi published yapamaz)
 *
 * Yayınlanma:
 *   - Admin: status='published' doğrudan
 *   - Editor/Author: status='draft' + approval_stage='review' → admin onayı bekler
 */
final class ProjectController
{
    public function index(Request $req): Response
    {
        $user = AuthService::user();
        if (!$user) return Response::redirect(url('/giris'));
        if (!$this->canAccess($user)) {
            flash('error', 'Bu sayfaya erişiminiz yok.');
            return Response::redirect(url('/panel'));
        }

        $items = $this->isAdminOrEditor($user)
            ? Project::all(500)
            : Project::allForUser((int) $user['id'], 500);

        return view('admin.projects.index', [
            'title' => 'Projeler',
            'items' => $items,
            'is_admin' => $this->isAdmin($user),
            'is_admin_or_editor' => $this->isAdminOrEditor($user),
        ]);
    }

    public function create(Request $req): Response
    {
        $user = AuthService::user();
        if (!$user || !$this->canAccess($user)) {
            return Response::redirect(url('/panel'));
        }
        return view('admin.projects.form', [
            'title' => 'Yeni Proje',
            'project' => $this->emptyProject(),
            'is_edit' => false,
            'is_admin' => $this->isAdmin($user),
            'is_admin_or_editor' => $this->isAdminOrEditor($user),
        ]);
    }

    public function edit(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $user = AuthService::user();
        if (!$user) return Response::redirect(url('/giris'));
        $project = Project::findById($id);
        if (!$project) return Response::notFound();
        if (!$this->canEdit($user, $project)) {
            flash('error', 'Bu projeyi düzenleme yetkiniz yok.');
            return Response::redirect(url('/panel/projeler'));
        }
        return view('admin.projects.form', [
            'title' => 'Projeyi Düzenle',
            'project' => $project,
            'is_edit' => true,
            'is_admin' => $this->isAdmin($user),
            'is_admin_or_editor' => $this->isAdminOrEditor($user),
        ]);
    }

    public function store(Request $req): Response
    {
        $user = AuthService::user();
        if (!$user || !$this->canAccess($user)) {
            flash('error', 'Erişiminiz yok.');
            return Response::redirect(url('/panel'));
        }
        $data = $this->parseForm($req);
        $errors = $this->validate($data);
        if (!empty($errors)) {
            flash('error', implode(' · ', $errors));
            return Response::redirect(url('/panel/projeler/yeni'));
        }

        // Status sınırlandır: sadece admin "published" yapabilir
        $data = $this->applyStatusLimits($data, $user);
        $data['user_id'] = (int) $user['id'];
        if (!$this->isAdmin($user) && ($data['status'] === 'published')) {
            $data['status'] = 'draft';
            $data['approval_stage'] = 'review';
            $data['submitted_at'] = date('Y-m-d H:i:s');
        }

        $id = Project::create($data);
        AuditLog::record('project.created', 'project', $id, (string) ($data['name'] ?? ''));
        Logger::info('project.created', ['id' => $id, 'by' => $user['id']], 'admin');

        if (!$this->isAdmin($user) && ($data['approval_stage'] ?? '') === 'review') {
            flash('success', 'Proje admin onayına gönderildi.');
        } else {
            flash('success', 'Proje oluşturuldu.');
        }
        return Response::redirect(url('/panel/projeler/' . $id . '/duzenle'));
    }

    public function update(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $user = AuthService::user();
        if (!$user) return Response::redirect(url('/giris'));
        $project = Project::findById($id);
        if (!$project) return Response::notFound();
        if (!$this->canEdit($user, $project)) {
            flash('error', 'Bu projeyi düzenleme yetkiniz yok.');
            return Response::redirect(url('/panel/projeler'));
        }

        $data = $this->parseForm($req);
        $errors = $this->validate($data);
        if (!empty($errors)) {
            flash('error', implode(' · ', $errors));
            return Response::redirect(url('/panel/projeler/' . $id . '/duzenle'));
        }

        $data = $this->applyStatusLimits($data, $user);

        // Admin değilse ve published yapmaya çalışıyorsa: review'a düşür
        if (!$this->isAdmin($user)) {
            if ($data['status'] === 'published') {
                $data['status'] = 'draft';
                $data['approval_stage'] = 'review';
                $data['submitted_at'] = date('Y-m-d H:i:s');
            }
        } else {
            // Admin published yaptıysa approved işaretle
            if ($data['status'] === 'published') {
                $data['approval_stage'] = 'approved';
                $data['approved_by'] = (int) $user['id'];
                $data['approved_at'] = date('Y-m-d H:i:s');
            }
        }

        Project::update($id, $data);
        AuditLog::record('project.updated', 'project', $id, (string) ($data['name'] ?? ''));
        flash('success', 'Proje güncellendi.');
        return Response::redirect(url('/panel/projeler/' . $id . '/duzenle'));
    }

    /** Author/Editor "İncelemeye Gönder" butonu */
    public function submit(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $user = AuthService::user();
        if (!$user) return Response::redirect(url('/giris'));
        $project = Project::findById($id);
        if (!$project) return Response::notFound();
        if (!$this->canEdit($user, $project)) {
            return Response::redirect(url('/panel/projeler'));
        }
        Database::instance()->update('projects', [
            'approval_stage' => 'review',
            'submitted_at' => date('Y-m-d H:i:s'),
            'status' => 'draft',
        ], 'id = :wid', [':wid' => $id]);
        AuditLog::record('project.submitted', 'project', $id, (string) $project['name']);
        flash('success', 'Proje admin onayına gönderildi.');
        return Response::redirect(url('/panel/projeler/' . $id . '/duzenle'));
    }

    /** Admin onayla (route /admin altında, RBAC ':admin') */
    public function approve(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $user = AuthService::user();
        if (!$this->isAdmin($user)) return Response::redirect(url('/panel/projeler'));
        $project = Project::findById($id);
        if (!$project) return Response::notFound();
        $now = date('Y-m-d H:i:s');
        Database::instance()->update('projects', [
            'status' => 'published',
            'approval_stage' => 'approved',
            'approved_by' => (int) $user['id'],
            'approved_at' => $now,
            'published_at' => $project['published_at'] ?? $now,
        ], 'id = :wid', [':wid' => $id]);
        AuditLog::record('project.approved', 'project', $id, (string) $project['name']);
        flash('success', 'Proje yayına alındı.');
        return Response::redirect(url('/panel/projeler'));
    }

    /** Admin reddet (yazara geri gönder) */
    public function reject(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $user = AuthService::user();
        if (!$this->isAdmin($user)) return Response::redirect(url('/panel/projeler'));
        $project = Project::findById($id);
        if (!$project) return Response::notFound();
        Database::instance()->update('projects', [
            'status' => 'draft',
            'approval_stage' => 'rejected',
        ], 'id = :wid', [':wid' => $id]);
        AuditLog::record('project.rejected', 'project', $id, (string) $project['name']);
        flash('success', 'Proje yazara geri gönderildi.');
        return Response::redirect(url('/panel/projeler'));
    }

    /** Admin silme (sadece admin) */
    public function delete(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $user = AuthService::user();
        if (!$this->isAdmin($user)) {
            flash('error', 'Sadece admin proje silebilir.');
            return Response::redirect(url('/panel/projeler'));
        }
        $project = Project::findById($id);
        if (!$project) return Response::notFound();
        Project::delete($id);
        AuditLog::record('project.deleted', 'project', $id, (string) ($project['name'] ?? ''));
        flash('success', 'Proje silindi.');
        return Response::redirect(url('/panel/projeler'));
    }

    // ─────────────── Yetki yardımcıları ───────────────

    private function canAccess(?array $user): bool
    {
        if (!$user) return false;
        return in_array($user['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR], true);
    }

    private function isAdmin(?array $user): bool
    {
        return ($user['role'] ?? '') === User::ROLE_ADMIN;
    }

    private function isAdminOrEditor(?array $user): bool
    {
        return in_array($user['role'] ?? '', [User::ROLE_ADMIN, User::ROLE_EDITOR], true);
    }

    private function canEdit(array $user, array $project): bool
    {
        if ($this->isAdmin($user)) return true;
        if ($this->isAdminOrEditor($user)) return true; // editor tüm projeleri edit edebilir (admin onayı şart)
        // Author sadece kendi projesini
        return (int) ($project['user_id'] ?? 0) === (int) $user['id'];
    }

    private function applyStatusLimits(array $data, array $user): array
    {
        if (!$this->isAdmin($user)) {
            // Admin değilse: featured yetkisi sadece admin'in
            $data['featured'] = 0;
            // Author/Editor "archived" yapamaz
            if (($data['status'] ?? '') === 'archived') {
                $data['status'] = 'draft';
            }
        }
        return $data;
    }

    private function emptyProject(): array
    {
        return [
            'id' => 0, 'user_id' => 0,
            'name' => '', 'slug' => '', 'subtitle' => '', 'description' => '',
            'cover_image' => '', 'location' => '', 'lat' => '', 'lng' => '',
            'year_started' => '', 'year_completed' => '', 'surface_m2' => '',
            'role' => 'arsitekt', 'building_type' => 'diger', 'client' => '',
            'address_locality' => '', 'address_region' => '', 'postal_code' => '',
            'partners_json' => [],
            'team_json' => ['architects' => [], 'engineers' => [], 'consultants' => []],
            'gallery_json' => [], 'tags_json' => [], 'links_json' => [],
            'status' => 'draft', 'featured' => 0,
            'approval_stage' => 'none',
            'meta_title' => '', 'meta_description' => '',
            'published_at' => null, 'submitted_at' => null,
        ];
    }

    private function parseForm(Request $req): array
    {
        $now = date('Y-m-d H:i:s');
        $status = (string) $req->input('status', 'draft');
        $publishedAt = $req->input('published_at', null);
        if ($status === 'published' && !$publishedAt) {
            $publishedAt = $now;
        }

        $galleryRaw = trim((string) $req->input('gallery', ''));
        $gallery = [];
        if ($galleryRaw !== '') {
            foreach (preg_split('/[\r\n,]+/', $galleryRaw) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $gallery[] = is_numeric($line) ? ['media_id' => (int) $line] : ['url' => $line];
            }
        }
        $partnersRaw = trim((string) $req->input('partners', ''));
        $partners = [];
        if ($partnersRaw !== '') {
            foreach (preg_split('/[\r\n]+/', $partnersRaw) as $line) {
                $line = trim($line);
                if ($line !== '') $partners[] = $line;
            }
        }
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) $req->input('tags', '')))));
        $linksRaw = trim((string) $req->input('links', ''));
        $links = [];
        if ($linksRaw !== '') {
            foreach (preg_split('/[\r\n]+/', $linksRaw) as $line) {
                if (str_contains($line, '|')) {
                    [$label, $u] = array_map('trim', explode('|', $line, 2));
                    if ($u !== '') $links[] = ['label' => $label, 'url' => $u];
                } else {
                    $line = trim($line);
                    if ($line !== '') $links[] = ['url' => $line];
                }
            }
        }

        // Yapı tipi validasyonu — geçersizse 'diger'
        $buildingType = (string) $req->input('building_type', 'diger');
        if (!array_key_exists($buildingType, Project::BUILDING_TYPES)) {
            $buildingType = 'diger';
        }

        // Ekip yapısı: architects[name][], architects[title][], architects[url][]
        $team = $this->parseTeam($req);

        return [
            'name' => trim((string) $req->input('name', '')),
            'slug' => trim((string) $req->input('slug', '')),
            'subtitle' => trim((string) $req->input('subtitle', '')) ?: null,
            'description' => (function ($d) {
                $d = trim((string) $d);
                return $d !== '' ? \App\Services\Sanitizer::clean($d) : null;
            })($req->input('description', '')),
            'cover_image' => trim((string) $req->input('cover_image', '')) ?: null,
            'location' => trim((string) $req->input('location', '')) ?: null,
            'lat' => $this->parseCoord($req->input('lat', '')),
            'lng' => $this->parseCoord($req->input('lng', '')),
            'year_started' => $req->input('year_started', '') !== '' ? (int) $req->input('year_started') : null,
            'year_completed' => $req->input('year_completed', '') !== '' ? (int) $req->input('year_completed') : null,
            'surface_m2' => $req->input('surface_m2', '') !== '' ? (int) $req->input('surface_m2') : null,
            'role' => (string) $req->input('role', 'arsitekt'),
            'building_type' => $buildingType,
            'client' => trim((string) $req->input('client', '')) ?: null,
            'address_locality' => trim((string) $req->input('address_locality', '')) ?: null,
            'address_region'   => trim((string) $req->input('address_region', '')) ?: null,
            'postal_code'      => trim((string) $req->input('postal_code', '')) ?: null,
            'partners_json' => $partners,
            'team_json' => $team,
            'gallery_json' => $gallery,
            'tags_json' => $tags,
            'links_json' => $links,
            'status' => in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft',
            'featured' => $req->input('featured', '0') === '1' ? 1 : 0,
            'meta_title' => trim((string) $req->input('meta_title', '')) ?: null,
            'meta_description' => trim((string) $req->input('meta_description', '')) ?: null,
            'published_at' => $publishedAt,
        ];
    }

    /**
     * Form'dan gelen architects/engineers/consultants paralel array'lerini
     * structured team_json'a çevirir. Boş satırları atar.
     */
    private function parseTeam(Request $req): array
    {
        $team = [];
        foreach (array_keys(Project::TEAM_GROUPS) as $group) {
            $names  = (array) $req->input($group . '_name', []);
            $titles = (array) $req->input($group . '_title', []);
            $urls   = (array) $req->input($group . '_url', []);
            $rows = [];
            $max = max(count($names), count($titles), count($urls));
            for ($i = 0; $i < $max; $i++) {
                $name  = trim((string) ($names[$i]  ?? ''));
                $title = trim((string) ($titles[$i] ?? ''));
                $url   = trim((string) ($urls[$i]   ?? ''));
                if ($name === '' && $title === '' && $url === '') continue;
                if ($name === '') continue; // ad zorunlu
                // URL normalize — http(s) yoksa ekle
                if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . $url;
                }
                $rows[] = [
                    'name'  => $name,
                    'title' => $title,
                    'url'   => $url,
                ];
            }
            $team[$group] = $rows;
        }
        return $team;
    }

    /**
     * Lat/Lng için esnek parser: trim, virgül → nokta dönüşümü,
     * boş/non-numeric → null.
     */
    private function parseCoord(mixed $raw): ?float
    {
        $s = trim((string) $raw);
        if ($s === '') return null;
        $s = str_replace(',', '.', $s);
        if (!is_numeric($s)) return null;
        return (float) $s;
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (($data['name'] ?? '') === '') $errors[] = 'Proje adı zorunlu.';
        if (mb_strlen($data['name'] ?? '') > 220) $errors[] = 'Proje adı en fazla 220 karakter.';
        if ($data['lat'] !== null && ($data['lat'] < -90 || $data['lat'] > 90)) $errors[] = 'Geçersiz enlem.';
        if ($data['lng'] !== null && ($data['lng'] < -180 || $data['lng'] > 180)) $errors[] = 'Geçersiz boylam.';
        return $errors;
    }
}
