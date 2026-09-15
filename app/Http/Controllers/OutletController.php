<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutletController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('outlets.view'), 403);

        $filters = $request->only(['code', 'name', 'city', 'status']);
        $query = Outlet::query()->withCount('users');

        if (filled($filters['code'] ?? null)) {
            $query->where('code', 'like', '%'.$filters['code'].'%');
        }
        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
        if (filled($filters['city'] ?? null)) {
            $query->where('city', 'like', '%'.$filters['city'].'%');
        }
        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        $focusOutlet = $request->filled('outlet')
            ? Outlet::query()->withCount('users')->find($request->integer('outlet'))
            : null;

        return view('outlets.index', [
            'outlets' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => Outlet::query()->count(),
                'active' => Outlet::query()->where('is_active', true)->count(),
                'central_kitchen' => Outlet::query()->where('is_central_kitchen', true)->count(),
            ],
            'focusPayload' => $focusOutlet?->toModalArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('outlets.manage'), 403);

        Outlet::query()->create($request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:outlets,code'],
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_central_kitchen' => ['sometimes', 'boolean'],
            'opens_at' => ['nullable'],
            'closes_at' => ['nullable'],
        ], [
            'code.required' => 'Kode outlet wajib diisi.',
            'code.unique' => 'Kode outlet sudah digunakan.',
            'name.required' => 'Nama outlet wajib diisi.',
        ]) + [
            'is_central_kitchen' => $request->boolean('is_central_kitchen'),
            'is_active' => true,
        ]);

        return redirect()->route('outlets.index')->with('success', 'Outlet ditambahkan.');
    }

    public function update(Request $request, Outlet $outlet): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('outlets.manage'), 403);

        $outlet->update($request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:outlets,code,'.$outlet->id],
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_central_kitchen' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'opens_at' => ['nullable'],
            'closes_at' => ['nullable'],
        ], [
            'code.required' => 'Kode outlet wajib diisi.',
            'code.unique' => 'Kode outlet sudah digunakan.',
            'name.required' => 'Nama outlet wajib diisi.',
        ]) + [
            'is_central_kitchen' => $request->boolean('is_central_kitchen'),
            'is_active' => $request->boolean('is_active', $outlet->is_active),
        ]);

        return redirect()->route('outlets.index')->with('success', 'Outlet diperbarui.');
    }
}
