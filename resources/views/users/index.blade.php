@extends('layouts.app')
@section('title', 'Users')
@section('breadcrumb', 'Settings')
@section('content')
    @php
        $formError = $errors->any();
        $requestedModal = request('modal');
        $storeUrl = route('users.store');
        $oldOutletIds = array_map('strval', (array) old('outlet_ids', []));
        $formOld = [
            'name' => old('name', data_get($focusPayload, 'name', '')),
            'email' => old('email', data_get($focusPayload, 'email', '')),
            'phone' => old('phone', data_get($focusPayload, 'phone', '')),
            'role_id' => (string) old('role_id', data_get($focusPayload, 'role_id', '')),
            'outlet_ids' => $oldOutletIds ?: data_get($focusPayload, 'outlet_ids', []),
            'is_active' => old('is_active', data_get($focusPayload, 'is_active', true) ? '1' : '0') !== '0',
            'id' => old('_user_id', data_get($focusPayload, 'id')),
            'mode' => old('_form_mode', 'create'),
            'update_url' => data_get($focusPayload, 'update_url', ''),
        ];
    @endphp
    <div x-data="userPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total pengguna</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua akun sistem</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 14a4 4 0 10-8 0m8 0a6 6 0 10-12 0m12 0v1a3 3 0 01-3 3H9a3 3 0 01-3-3v-1m12 0a9 9 0 10-18 0"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Aktif</p>
                    <p class="stat-value">{{ number_format($stats['active']) }}</p>
                    <p class="stat-hint">Dapat login</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Nonaktif</p>
                    <p class="stat-value">{{ number_format($stats['inactive']) }}</p>
                    <p class="stat-hint">Akun dinonaktifkan</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar pengguna</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    @can('users.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Tambah pengguna
                        </button>
                    @endcan
                </div>
            </div>

            <form id="user-filters" method="GET" action="{{ route('users.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Telepon</th>
                            <th>Role</th>
                            <th>Outlet</th>
                            <th>Status</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="user-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="user-filters" class="col-filter" type="search" name="email" value="{{ $filters['email'] ?? '' }}" placeholder="Email..." onchange="this.form.submit()">
                            </th>
                            <th></th>
                            <th>
                                <select form="user-filters" class="col-filter" name="role_id" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected((string) ($filters['role_id'] ?? '') === (string) $role->id)>{{ $role->label }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th>
                                <select form="user-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                                </select>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php $row = $user->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $users->firstItem() + $loop->index }}</td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">{{ $user->initials() }}</span>
                                        <button type="button" class="text-left font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $user->name }}</button>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone ?: '—' }}</td>
                                <td>
                                    @if ($user->roles->first())
                                        <span class="badge-soft">{{ $user->roles->first()->label }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($user->outlets->take(2) as $outlet)
                                            <span class="badge-soft">{{ $outlet->name }}</span>
                                        @empty
                                            <span class="text-[12px] text-muted">—</span>
                                        @endforelse
                                        @if ($user->outlets->count() > 2)
                                            <span class="text-[12px] text-muted">+{{ $user->outlets->count() - 2 }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <x-status :value="$user->is_active ? 'green' : 'gray'">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</x-status>
                                </td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                        @can('users.manage')
                                            <button type="button" class="table-action" title="Edit" @click="openEdit({{ Js::from($row) }})">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-16 text-center text-sm text-slate-400">Tidak ada pengguna yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $users->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-soft text-base font-semibold text-brand" x-text="viewing?.initials || 'U'"></span>
                            <div>
                                <h3 class="text-lg font-semibold text-heading" x-text="viewing?.name || 'Detail Pengguna'"></h3>
                                <p class="mt-1 text-[13px] text-muted">
                                    <span x-text="viewing?.email || '—'"></span>
                                    ·
                                    <span x-text="viewing?.status_label || '—'"></span>
                                </p>
                            </div>
                        </div>
                        <button type="button" class="modal-close" @click="viewOpen = false">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Telepon</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.phone || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Role</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.role_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3 sm:col-span-1">
                            <p class="stat-kicker">Outlet</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.outlets_label || '—'"></p>
                        </div>
                    </div>
                </div>
                @can('users.manage')
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-add" @click="openEdit(viewing)">Edit</button>
                    </div>
                @endcan
            </div>
        </div>

        @can('users.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" :action="formMode === 'edit' ? form.update_url : storeUrl">
                        @csrf
                        <input type="hidden" name="_form_mode" :value="formMode">
                        <input type="hidden" name="_user_id" :value="form.id || ''">
                        <template x-if="formMode === 'edit'">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading" x-text="formMode === 'edit' ? 'Edit Pengguna' : 'Tambah Pengguna'"></h3>
                                    <p class="mt-1 text-[13px] text-muted" x-text="formMode === 'edit' ? 'Perbarui akun dan hak akses pengguna.' : 'Buat akun baru dengan role dan outlet.'"></p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="label">Nama</label>
                                    <input class="input" name="name" required maxlength="150" x-model="form.name">
                                    @error('name')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Email</label>
                                    <input class="input" type="email" name="email" required x-model="form.email">
                                    @error('email')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Telepon</label>
                                    <input class="input" name="phone" maxlength="30" x-model="form.phone">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label" x-text="formMode === 'edit' ? 'Password baru' : 'Password'"></label>
                                    <input class="input" type="password" name="password" minlength="8" :required="formMode === 'create'" :placeholder="formMode === 'edit' ? 'Kosongkan jika tidak diubah' : ''">
                                    @error('password')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Role</label>
                                    <select name="role_id" class="input" required x-model="form.role_id">
                                        <option value="">Pilih role</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}">{{ $role->label }}</option>
                                        @endforeach
                                    </select>
                                    @error('role_id')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Outlet</label>
                                    <select name="outlet_ids[]" class="input min-h-28" multiple x-ref="outletSelect" @change="syncOutletIds()">
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-2" x-show="formMode === 'edit'" x-cloak>
                                    <label class="flex items-center gap-2.5 text-sm">
                                        <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                                        <input type="checkbox" class="h-4 w-4 rounded border-line" x-model="form.is_active">
                                        Aktif
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="crud-modal-footer">
                            <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                            <button class="btn-add" type="submit" x-text="formMode === 'edit' ? 'Simpan perubahan' : 'Simpan'"></button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        function userPage() {
            const emptyForm = () => ({
                id: null,
                name: '',
                email: '',
                phone: '',
                role_id: '',
                outlet_ids: [],
                is_active: true,
                update_url: '',
            });

            const focus = @json($focusPayload);
            const formError = @json($formError);
            const requestedModal = @json($requestedModal);
            const formOld = @json($formOld);

            let form = emptyForm();
            if (focus) {
                form = { ...emptyForm(), ...focus };
            }
            if (formError) {
                form = { ...form, ...formOld, is_active: !!formOld.is_active, outlet_ids: formOld.outlet_ids || [] };
            }

            return {
                storeUrl: @json($storeUrl),
                formOpen: formError || requestedModal === 'create' || (requestedModal === 'edit' && !!focus),
                formMode: formError ? formOld.mode : (requestedModal === 'edit' ? 'edit' : 'create'),
                form,
                viewOpen: !formError && requestedModal === 'view' && !!focus,
                viewing: focus,
                serverFormError: formError,
                openCreate() {
                    this.formMode = 'create';
                    this.form = emptyForm();
                    this.viewOpen = false;
                    this.serverFormError = false;
                    this.formOpen = true;
                    this.$nextTick(() => this.applyOutletSelection());
                },
                openEdit(row) {
                    if (! row) return;
                    this.formMode = 'edit';
                    this.form = { ...emptyForm(), ...row, is_active: !!row.is_active, outlet_ids: row.outlet_ids || [] };
                    this.viewOpen = false;
                    this.serverFormError = false;
                    this.formOpen = true;
                    this.$nextTick(() => this.applyOutletSelection());
                },
                openView(row) {
                    if (! row) return;
                    this.viewing = row;
                    this.formOpen = false;
                    this.viewOpen = true;
                },
                syncOutletIds() {
                    const select = this.$refs.outletSelect;
                    if (! select) return;
                    this.form.outlet_ids = Array.from(select.selectedOptions).map((option) => option.value);
                },
                applyOutletSelection() {
                    const select = this.$refs.outletSelect;
                    if (! select) return;
                    const selected = (this.form.outlet_ids || []).map(String);
                    Array.from(select.options).forEach((option) => {
                        option.selected = selected.includes(option.value);
                    });
                },
                closeTop() {
                    if (this.formOpen) this.formOpen = false;
                    else if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
