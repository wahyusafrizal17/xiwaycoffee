<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('users.view'), 403);

        $filters = $request->only(['name', 'email', 'role_id', 'status']);
        $query = User::query()->with(['roles', 'outlets']);

        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
        if (filled($filters['email'] ?? null)) {
            $query->where('email', 'like', '%'.$filters['email'].'%');
        }
        if (filled($filters['role_id'] ?? null)) {
            $query->whereHas('roles', fn ($role) => $role->where('roles.id', $filters['role_id']));
        }
        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        $focusUser = $request->filled('user')
            ? User::query()->with(['roles', 'outlets'])->find($request->integer('user'))
            : null;

        return view('users.index', [
            'users' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'roles' => Role::query()->orderBy('label')->get(),
            'outlets' => Outlet::query()->where('is_active', true)->orderBy('name')->get(),
            'stats' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
                'inactive' => User::query()->where('is_active', false)->count(),
            ],
            'focusPayload' => $focusUser?->toModalArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', 'exists:outlets,id'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah digunakan.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'role_id.required' => 'Role wajib dipilih.',
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);
        $user->roles()->sync([$data['role_id']]);
        $user->outlets()->sync($request->input('outlet_ids', []));

        return redirect()->route('users.index')->with('success', 'Pengguna ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', 'exists:outlets,id'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah digunakan.',
            'password.min' => 'Password minimal 8 karakter.',
            'role_id.required' => 'Role wajib dipilih.',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active', $user->is_active),
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        $user->roles()->sync([$data['role_id']]);
        $user->outlets()->sync($request->input('outlet_ids', []));

        return redirect()->route('users.index')->with('success', 'Pengguna diperbarui.');
    }
}
