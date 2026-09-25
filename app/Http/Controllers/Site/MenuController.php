<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class MenuController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->to(route('home').'#menu');
    }
}
