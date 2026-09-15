<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoyaltyController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasPermission('loyalty.view'), 403);

        $filters = $request->only(['name', 'phone', 'membership_level']);
        $query = Customer::query();

        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
        if (filled($filters['phone'] ?? null)) {
            $query->where('phone', 'like', '%'.$filters['phone'].'%');
        }
        if (filled($filters['membership_level'] ?? null)) {
            $query->where('membership_level', $filters['membership_level']);
        }

        return view('loyalty.index', [
            'customers' => $query->orderByDesc('points')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'rewards' => Reward::query()->where('is_active', true)->orderBy('points_required')->get(),
            'stats' => [
                'members' => Customer::query()->count(),
                'points' => (int) Customer::query()->sum('points'),
                'rewards' => Reward::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
