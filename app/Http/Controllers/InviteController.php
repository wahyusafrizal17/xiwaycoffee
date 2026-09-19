<?php

namespace App\Http\Controllers;

use App\Models\InviteGuest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InviteController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        return view('invites.index', [
            'guests' => InviteGuest::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function show(string $slug): View
    {
        $guest = InviteGuest::query()->where('slug', $slug)->firstOrFail();

        return view('invites.show', [
            'guest' => $guest,
            'mapsUrl' => 'https://share.google/489YxsQigXiiFQWzE',
        ]);
    }
}
