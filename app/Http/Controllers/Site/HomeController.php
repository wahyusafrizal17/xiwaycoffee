<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\SiteMenuCatalog;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $site = config('site');
        $wa = preg_replace('/\D+/', '', (string) ($site['whatsapp'] ?? '')) ?: null;

        return view('site.home', [
            'site' => $site,
            'groups' => SiteMenuCatalog::groups(),
            'whatsapp' => $wa,
        ]);
    }
}
