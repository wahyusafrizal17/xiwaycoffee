<?php

namespace App\Http\Middleware;

use App\Models\Outlet;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentOutlet
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($request->filled('switch_outlet') && $user->canAccessOutlet((int) $request->input('switch_outlet'))) {
            session(['current_outlet_id' => (int) $request->input('switch_outlet')]);
        }

        if (! session('current_outlet_id')) {
            $outlet = $user->defaultOutlet()
                ?? Outlet::query()->where('is_active', true)->first();

            if ($outlet) {
                session(['current_outlet_id' => $outlet->id]);
            }
        }

        $current = current_outlet_id();
        if ($current && ! $user->canAccessOutlet($current) && ! $user->isSuperAdmin() && ! $user->hasRole('admin')) {
            $fallback = $user->defaultOutlet();
            session(['current_outlet_id' => $fallback?->id]);
        }

        return $next($request);
    }
}
