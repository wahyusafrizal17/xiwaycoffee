@extends('layouts.app')
@section('title', 'Outlets')
@section('breadcrumb', 'Settings')
@section('content')
    @php
        $formError = $errors->any();
        $requestedModal = request('modal');
        $storeUrl = route('outlets.store');
        $formOld = [
            'code' => old('code', data_get($focusPayload, 'code', '')),
            'name' => old('name', data_get($focusPayload, 'name', '')),
            'city' => old('city', data_get($focusPayload, 'city', '')),
            'address' => old('address', data_get($focusPayload, 'address', '')),
            'phone' => old('phone', data_get($focusPayload, 'phone', '')),
            'opens_at' => old('opens_at', data_get($focusPayload, 'opens_at', '')),
            'closes_at' => old('closes_at', data_get($focusPayload, 'closes_at', '')),
            'is_central_kitchen' => old('is_central_kitchen', data_get($focusPayload, 'is_central_kitchen', false) ? '1' : '0') === '1',
            'is_active' => old('is_active', data_get($focusPayload, 'is_active', true) ? '1' : '0') !== '0',
            'id' => old('_outlet_id', data_get($focusPayload, 'id')),
            'mode' => old('_form_mode', 'create'),
            'update_url' => data_get($focusPayload, 'update_url', ''),
        ];
    @endphp
    <div x-data="outletPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total outlet</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua lokasi operasional</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Aktif</p>
                    <p class="stat-value">{{ number_format($stats['active']) }}</p>
                    <p class="stat-hint">Dapat dipilih di kasir</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Central kitchen</p>
                    <p class="stat-value">{{ number_format($stats['central_kitchen']) }}</p>
                    <p class="stat-hint">Lokasi produksi pusat</p>
                </div>
                <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 4v6c0 4.4-3.6 8-8 8s-8-3.6-8-8V7l8-4z"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar outlet</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    @can('outlets.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Tambah outlet
                        </button>
                    @endcan
                </div>
            </div>

            <form id="outlet-filters" method="GET" action="{{ route('outlets.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Kota</th>
                            <th>Telepon</th>
                            <th>Jam operasi</th>
                            <th>User</th>
                            <th>Status</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="outlet-filters" class="col-filter" type="search" name="code" value="{{ $filters['code'] ?? '' }}" placeholder="Kode..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="outlet-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="outlet-filters" class="col-filter" type="search" name="city" value="{{ $filters['city'] ?? '' }}" placeholder="Kota..." onchange="this.form.submit()">
                            </th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th>
                                <select form="outlet-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                                </select>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($outlets as $outlet)
                            @php $row = $outlet->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $outlets->firstItem() + $loop->index }}</td>
                                <td>{{ $outlet->code }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $outlet->name }}</button>
                                </td>
                                <td>{{ $outlet->city ?: '—' }}</td>
                                <td>{{ $outlet->phone ?: '—' }}</td>
                                <td>{{ $outlet->hoursLabel() }}</td>
                                <td class="font-semibold">{{ number_format($outlet->users_count) }}</td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <x-status :value="$outlet->is_active ? 'green' : 'gray'">{{ $outlet->is_active ? 'Aktif' : 'Nonaktif' }}</x-status>
                                        @if ($outlet->is_central_kitchen)
                                            <span class="badge-soft">Central kitchen</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                        @can('outlets.manage')
                                            <button type="button" class="table-action" title="Edit" @click="openEdit({{ Js::from($row) }})">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-16 text-center text-sm text-slate-400">Tidak ada outlet yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($outlets->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $outlets->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.name || 'Detail Outlet'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.code || '—'"></span>
                                ·
                                <span x-text="viewing?.status_label || '—'"></span>
                            </p>
                        </div>
                        <button type="button" class="modal-close" @click="viewOpen = false">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Kota</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.city || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Telepon</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.phone || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">User</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.users_count ?? 0"></p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Jam operasi</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.hours_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Tipe</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.is_central_kitchen ? 'Central kitchen' : 'Outlet reguler'"></p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl bg-[#fafafa] px-4 py-3">
                        <p class="stat-kicker">Alamat</p>
                        <p class="mt-1 text-sm font-semibold" x-text="viewing?.address || '—'"></p>
                    </div>
                </div>
                @can('outlets.manage')
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-add" @click="openEdit(viewing)">Edit</button>
                    </div>
                @endcan
            </div>
        </div>

        @can('outlets.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" :action="formMode === 'edit' ? form.update_url : storeUrl">
                        @csrf
                        <input type="hidden" name="_form_mode" :value="formMode">
                        <input type="hidden" name="_outlet_id" :value="form.id || ''">
                        <template x-if="formMode === 'edit'">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading" x-text="formMode === 'edit' ? 'Edit Outlet' : 'Tambah Outlet'"></h3>
                                    <p class="mt-1 text-[13px] text-muted" x-text="formMode === 'edit' ? 'Perbarui data lokasi operasional.' : 'Daftarkan outlet baru ke sistem POS.'"></p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label">Kode</label>
                                    <input class="input" name="code" required maxlength="20" x-model="form.code">
                                    @error('code')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Nama</label>
                                    <input class="input" name="name" required maxlength="120" x-model="form.name">
                                    @error('name')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Kota</label>
                                    <input class="input" name="city" maxlength="80" x-model="form.city">
                                </div>
                                <div>
                                    <label class="label">Telepon</label>
                                    <input class="input" name="phone" maxlength="30" x-model="form.phone">
                                </div>
                                <div>
                                    <label class="label">Buka</label>
                                    <input class="input" type="time" name="opens_at" x-model="form.opens_at">
                                </div>
                                <div>
                                    <label class="label">Tutup</label>
                                    <input class="input" type="time" name="closes_at" x-model="form.closes_at">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Alamat</label>
                                    <textarea class="input min-h-24" name="address" x-model="form.address"></textarea>
                                </div>
                                <div class="sm:col-span-2 flex flex-wrap gap-5">
                                    <label class="flex items-center gap-2.5 text-sm">
                                        <input type="hidden" name="is_central_kitchen" :value="form.is_central_kitchen ? 1 : 0">
                                        <input type="checkbox" class="h-4 w-4 rounded border-line" x-model="form.is_central_kitchen">
                                        Central kitchen
                                    </label>
                                    <label class="flex items-center gap-2.5 text-sm" x-show="formMode === 'edit'" x-cloak>
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
        function outletPage() {
            const emptyForm = () => ({
                id: null,
                code: '',
                name: '',
                city: '',
                address: '',
                phone: '',
                opens_at: '',
                closes_at: '',
                is_central_kitchen: false,
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
                form = { ...form, ...formOld, is_central_kitchen: !!formOld.is_central_kitchen, is_active: !!formOld.is_active };
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
                },
                openEdit(row) {
                    if (! row) return;
                    this.formMode = 'edit';
                    this.form = { ...emptyForm(), ...row, is_central_kitchen: !!row.is_central_kitchen, is_active: !!row.is_active };
                    this.viewOpen = false;
                    this.serverFormError = false;
                    this.formOpen = true;
                },
                openView(row) {
                    if (! row) return;
                    this.viewing = row;
                    this.formOpen = false;
                    this.viewOpen = true;
                },
                closeTop() {
                    if (this.formOpen) this.formOpen = false;
                    else if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
