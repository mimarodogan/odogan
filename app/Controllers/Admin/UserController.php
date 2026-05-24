<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Logger;

final class UserController
{
    public function index(Request $req): Response
    {
        return view('admin.users', [
            'title' => 'Kullanıcılar',
            'users' => User::listAll(200),
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $target = User::findById($id);
        if ($target === null) {
            return Response::notFound();
        }
        $role = (string) $req->input('role', $target['role']);
        $status = (string) $req->input('status', $target['status']);
        if (!in_array($role, User::ROLES, true)) {
            $role = $target['role'];
        }
        if (!in_array($status, ['active', 'pending', 'banned'], true)) {
            $status = $target['status'];
        }
        // Don't let the last admin demote themselves into a corner.
        if ($role !== User::ROLE_ADMIN && $target['role'] === User::ROLE_ADMIN) {
            $remaining = (int) Database::instance()->fetchColumn(
                "SELECT COUNT(*) FROM users WHERE role='admin' AND status='active' AND id <> :id",
                [':id' => $id]
            );
            if ($remaining < 1) {
                flash('error', 'Son admin\'i düşüremezsiniz; önce başka bir admin atayın.');
                return Response::redirect(url('/admin/kullanicilar'));
            }
        }
        User::update($id, ['role' => $role, 'status' => $status]);
        Logger::info('user.role-changed', [
            'user_id' => $id, 'role' => $role, 'status' => $status,
            'actor' => AuthService::id(),
        ], 'admin');
        flash('success', $target['name'] . ' güncellendi.');
        return Response::redirect(url('/admin/kullanicilar'));
    }

    public function destroy(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $target = User::findById($id);
        if ($target === null) {
            return Response::notFound();
        }
        if ((int) AuthService::id() === $id) {
            flash('error', 'Kendi hesabınızı buradan silemezsiniz.');
            return Response::redirect(url('/admin/kullanicilar'));
        }
        if ($target['role'] === User::ROLE_ADMIN) {
            $remaining = (int) Database::instance()->fetchColumn(
                "SELECT COUNT(*) FROM users WHERE role='admin' AND status='active' AND id <> :id",
                [':id' => $id]
            );
            if ($remaining < 1) {
                flash('error', 'Son admin\'i devre dışı bırakamazsınız.');
                return Response::redirect(url('/admin/kullanicilar'));
            }
        }

        // SOFT DELETE — kullanıcı satırı DB'de kalır, sadece deleted_at + status=disabled set edilir.
        // Yazılar (posts.user_id FK CASCADE) silinmez çünkü row hala mevcut.
        // Yazar tekrar login olamaz; public yazılarda byline'da adı görünmeye devam eder.
        if (!empty($target['deleted_at'])) {
            flash('warning', $target['name'] . ' zaten kapatılmış.');
            return Response::redirect(url('/admin/kullanicilar'));
        }
        User::update($id, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_reason' => 'Admin tarafından kapatıldı (actor: ' . (int) AuthService::id() . ')',
            'status' => 'disabled',
        ]);
        AuditLog::record('user.disabled', 'user', $id, $target['name']);
        Logger::warning('user.disabled', [
            'user_id' => $id, 'name' => $target['name'], 'actor' => AuthService::id(),
        ], 'admin');
        flash('success', $target['name'] . ' kapatıldı — yazıları korundu, kullanıcı tekrar giriş yapamaz.');
        return Response::redirect(url('/admin/kullanicilar'));
    }
}
