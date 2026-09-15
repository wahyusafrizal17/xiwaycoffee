@extends('layouts.app')
@section('title', 'Printers')
@section('breadcrumb', 'Settings')
@section('content')
    @php
        $formError = $errors->any();
        $requestedModal = request('modal');
        $storeUrl = route('printers.store');
        $oldCategoryIds = array_map('strval', (array) old('category_ids', []));
        $formOld = [
            'name' => old('name', data_get($focusPayload, 'name', '')),
            'station' => old('station', data_get($focusPayload, 'station', 'kitchen')),
            'ip_address' => old('ip_address', data_get($focusPayload, 'ip_address', '')),
            'port' => old('port', data_get($focusPayload, 'port', 9100)),
            'is_active' => old('is_active', data_get($focusPayload, 'is_active', true) ? '1' : '0') !== '0',
            'category_ids' => $oldCategoryIds ?: data_get($focusPayload, 'category_ids', []),
            'id' => old('_printer_id', data_get($focusPayload, 'id')),
            'mode' => old('_form_mode', 'create'),
            'update_url' => data_get($focusPayload, 'update_url', ''),
            'delete_url' => data_get($focusPayload, 'delete_url', ''),
        ];
    @endphp
    <div x-data="printerPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total printer</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Outlet aktif</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7zM6 9V3h12v6"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Aktif</p>
                    <p class="stat-value">{{ number_format($stats['active']) }}</p>
                    <p class="stat-hint">Siap cetak</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Nonaktif</p>
                    <p class="stat-value">{{ number_format($stats['inactive']) }}</p>
                    <p class="stat-hint">Tidak digunakan</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar printer</h5>
                    <p class="card-header-subtitle">Konfigurasi printer dan routing kategori per outlet.</p>
                </div>
                <div class="card-header-actions">
                    @can('printers.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Tambah printer
                        </button>
                    @endcan
                </div>
            </div>

            <form id="printer-filters" method="GET" action="{{ route('printers.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Nama</th>
                            <th>Stasiun</th>
                            <th>Alamat</th>
                            <th>Routing</th>
                            <th>Status</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="printer-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="printer-filters" class="col-filter" name="station" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach ($stations as $station)
                                        <option value="{{ $station->value }}" @selected(($filters['station'] ?? '') === $station->value)>{{ $station->label() }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                            <th>
                                <select form="printer-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                                </select>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($printers as $printer)
                            @php $row = $printer->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $printers->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $printer->name }}</button>
                                </td>
                                <td>{{ $printer->station?->label() ?? '—' }}</td>
                                <td class="text-[13px] text-muted">{{ $printer->ip_address ?: 'Tanpa IP' }}:{{ $printer->port }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($printer->routes as $route)
                                            @if ($route->category)
                                                <span class="badge-soft">{{ $route->category->name }}</span>
                                            @endif
                                        @empty
                                            <span class="text-[12px] text-muted">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    <x-status :value="$printer->is_active ? 'green' : 'gray'">{{ $printer->is_active ? 'Aktif' : 'Nonaktif' }}</x-status>
                                </td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                        @can('printers.manage')
                                            <button type="button" class="table-action" title="Edit" @click="openEdit({{ Js::from($row) }})">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                            </button>
                                            <button type="button" class="table-action table-action-danger" title="Hapus" @click="confirmDelete({{ Js::from($row) }})">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M9 7V5h6v2m-7 0v12a1 1 0 001 1h6a1 1 0 001-1V7"/></svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-16 text-center text-sm text-slate-400">Tidak ada printer yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($printers->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $printers->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.name || 'Detail Printer'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.station_label || '—'"></span>
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
                            <p class="stat-kicker">Alamat</p>
                            <p class="mt-1 text-sm font-semibold" x-text="(viewing?.ip_address || 'Tanpa IP') + ':' + (viewing?.port ?? 9100)"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Outlet</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.outlet_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3 sm:col-span-1">
                            <p class="stat-kicker">Routing</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.categories_label || '—'"></p>
                        </div>
                    </div>
                </div>
                @can('printers.manage')
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="confirmDelete(viewing)">Hapus</button>
                        <button type="button" class="btn-add" @click="openEdit(viewing)">Edit</button>
                    </div>
                @endcan
            </div>
        </div>

        @can('printers.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" :action="formMode === 'edit' ? form.update_url : storeUrl">
                        @csrf
                        <input type="hidden" name="_form_mode" :value="formMode">
                        <input type="hidden" name="_printer_id" :value="form.id || ''">
                        <template x-if="formMode === 'edit'">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading" x-text="formMode === 'edit' ? 'Edit Printer' : 'Tambah Printer'"></h3>
                                    <p class="mt-1 text-[13px] text-muted" x-text="formMode === 'edit' ? 'Perbarui konfigurasi printer outlet ini.' : 'Atur printer dan routing kategori cetak.'"></p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="label">Nama</label>
                                    <input class="input" name="name" required maxlength="80" x-model="form.name" placeholder="GEZHI micro-printer">
                                    <p class="mt-1 text-[12px] text-muted">Samakan dengan nama printer di sistem (QZ Tray).</p>
                                    @error('name')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Stasiun</label>
                                    <select name="station" class="input" required x-model="form.station">
                                        @foreach ($stations as $station)
                                            <option value="{{ $station->value }}">{{ $station->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('station')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Port</label>
                                    <input class="input" type="number" name="port" min="1" x-model="form.port">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">IP address</label>
                                    <input class="input" name="ip_address" placeholder="192.168.1.50" x-model="form.ip_address">
                                    @error('ip_address')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Kategori routing</label>
                                    <select name="category_ids[]" class="input min-h-28" multiple x-ref="categorySelect" @change="syncCategoryIds()">
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-[12px] text-muted">Pilih kategori yang akan dicetak ke printer ini.</p>
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

            <div class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 p-4" x-show="deleteOpen" x-cloak @click.self="deleteOpen = false">
                <div class="crud-modal-sm">
                    <h3 class="text-lg font-semibold text-heading">Hapus Printer</h3>
                    <p class="mt-2 text-sm text-muted">
                        Hapus <span class="font-medium text-heading" x-text="pendingDelete?.name"></span>? Data akan diarsipkan.
                    </p>
                    <form method="POST" class="mt-6 flex justify-end gap-2" :action="pendingDelete?.delete_url">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn-ghost" @click="deleteOpen = false">Batal</button>
                        <button class="btn-danger" type="submit">Hapus</button>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        function printerPage() {
            const emptyForm = () => ({
                id: null,
                name: '',
                station: 'kitchen',
                ip_address: '',
                port: 9100,
                is_active: true,
                category_ids: [],
                update_url: '',
                delete_url: '',
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
                form = { ...form, ...formOld, category_ids: formOld.category_ids || [] };
            }

            return {
                storeUrl: @json($storeUrl),
                formOpen: formError || requestedModal === 'create' || (requestedModal === 'edit' && !!focus),
                formMode: formError ? formOld.mode : (requestedModal === 'edit' ? 'edit' : 'create'),
                form,
                viewOpen: !formError && requestedModal === 'view' && !!focus,
                viewing: focus,
                deleteOpen: false,
                pendingDelete: null,
                serverFormError: formError,
                openCreate() {
                    this.formMode = 'create';
                    this.form = emptyForm();
                    this.viewOpen = false;
                    this.serverFormError = false;
                    this.formOpen = true;
                    this.$nextTick(() => this.applyCategorySelection());
                },
                openEdit(row) {
                    if (! row) return;
                    this.formMode = 'edit';
                    this.form = { ...emptyForm(), ...row, category_ids: row.category_ids || [] };
                    this.viewOpen = false;
                    this.deleteOpen = false;
                    this.serverFormError = false;
                    this.formOpen = true;
                    this.$nextTick(() => this.applyCategorySelection());
                },
                openView(row) {
                    if (! row) return;
                    this.viewing = row;
                    this.formOpen = false;
                    this.viewOpen = true;
                },
                confirmDelete(row) {
                    if (! row) return;
                    this.pendingDelete = row;
                    this.deleteOpen = true;
                },
                syncCategoryIds() {
                    const select = this.$refs.categorySelect;
                    if (! select) return;
                    this.form.category_ids = Array.from(select.selectedOptions).map((option) => option.value);
                },
                applyCategorySelection() {
                    const select = this.$refs.categorySelect;
                    if (! select) return;
                    const selected = (this.form.category_ids || []).map(String);
                    Array.from(select.options).forEach((option) => {
                        option.selected = selected.includes(option.value);
                    });
                },
                closeTop() {
                    if (this.deleteOpen) this.deleteOpen = false;
                    else if (this.formOpen) this.formOpen = false;
                    else if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
