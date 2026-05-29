<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Setting;

/**
 * Critical CSS yöneticisi (Tier 9).
 *
 * Setting key: features.critical_css_content (longtext)
 * Public layout head'de `<style>{content}</style>` inline'lanır;
 * normal CSS rel=preload + onload="this.rel='stylesheet'" ile yüklenir.
 */
final class CriticalCssController
{
    public function index(Request $req): Response
    {
        $content = (string) Setting::get('critical_css_content', '', 'features');
        return view('admin.critical-css.index', [
            'title' => 'Critical CSS',
            'content' => $content,
            'enabled' => (bool) Setting::get('critical_css_enabled', false, 'features'),
        ]);
    }

    public function save(Request $req): Response
    {
        $content = (string) $req->input('content', '');
        // Boyut kontrolü — 100KB üstü inline anlamsız
        if (mb_strlen($content) > 100 * 1024) {
            flash('error', 'Critical CSS 100KB üzeri olamaz; lütfen kısaltın.');
            return Response::redirect(url('/admin/critical-css'));
        }
        // XSS guard — CSS gövdesinde HTML tag'i (</style>, <script ...) ya da
        // HTML yorumu bulunmamalı. head-meta.php bunu <style>{...}</style>
        // içine inline koyduğu için "</style><script>" payload'ı persistent XSS
        // doğurur. CSS gramerinde '<' meşru bir karakter değildir → guard.
        if (preg_match('#<|-->|<!--#', $content)) {
            flash('error', 'Critical CSS içinde "<" karakteri veya HTML yorumu bulunamaz (XSS koruması).');
            return Response::redirect(url('/admin/critical-css'));
        }
        Setting::set('critical_css_content', $content, 'string', 'features');
        Setting::flushCache();
        AuditLog::record('critical_css.updated', 'setting', 0, mb_strlen($content) . ' karakter');
        flash('success', 'Critical CSS kaydedildi (' . number_format(mb_strlen($content)) . ' karakter).');
        return Response::redirect(url('/admin/critical-css'));
    }
}
