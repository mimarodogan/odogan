<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\Logger;
use App\Services\LinkChecker;

final class LinkController
{
    public function index(Request $req): Response
    {
        return view('admin.links', [
            'title' => 'Kırık Link Dedektörü',
            'broken' => LinkChecker::listBroken(200),
        ]);
    }

    public function scan(Request $req): Response
    {
        $r = LinkChecker::scanAll(25, 100);
        Logger::info('linkcheck.run', $r, 'admin');
        flash('success', sprintf(
            '%d link kontrol edildi · %d tanesi şu an kırık.',
            $r['checked'], $r['broken']
        ));
        return Response::redirect(url('/admin/linkler'));
    }
}
