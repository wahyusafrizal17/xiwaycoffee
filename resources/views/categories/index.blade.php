@extends('layouts.app')
@section('title', 'Categories')
@section('breadcrumb', 'Catalog')
@section('content')
    @php
        $formError = $errors->any();
        $requestedModal = request('modal');
        $storeUrl = route('categories.store');
        $formOld = [
            'name' => old('name', data_get($focusPayload, 'name', '')),
            'station' => old('station', data_get($focusPayload, 'station', '')),
            'color' => old('color', data_get($focusPayload, 'color', '')),
            'sort_order' => old('sort_order', data_get($focusPayload, 'sort_order', 0)),
            'is_active' => old('is_active', data_get($focusPayload, 'is_active', true) ? '1' : '0') !== '0',
            'id' => old('_category_id', data_get($focusPayload, 'id')),
            'mode' => old('_form_mode', 'create'),
            'slug' => data_get($focusPayload, 'slug', ''),
            'update_url' => data_get($focusPayload, 'update_url', ''),
            'delete_url' => data_get($focusPayload, 'delete_url', ''),
        ];
    @endphp
    <div x-data="categoryPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total kategori</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua grup katalog</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h7v5H4zM13 6h7v5h-7zM4 13h7v5H4zM13 13h7v5h-7z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Aktif</p>
                    <p class="stat-value">{{ number_format($stats['active']) }}</p>
                    <p class="stat-hint">Tampil di POS</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Nonaktif</p>
                    <p class="stat-value">{{ number_format($stats['inactive']) }}</p>
                    <p class="stat-hint">Disembunyikan dari kasir</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar kategori</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    @can('products.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Tambah kategori
                        </button>
                    @endcan
                </div>
            </div>

            <form id="category-filters" method="GET" action="{{ route('categories.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Nama</th>
                            <th>Stasiun</th>
                            <th>Warna</th>
                            <th>Urutan</th>
                            <th>Produk</th>
                            <th>Status</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="category-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="category-filters" class="col-filter" name="station" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="kitchen" @selected(($filters['station'] ?? '') === 'kitchen')>Dapur</option>
                                    <option value="bar" @selected(($filters['station'] ?? '') === 'bar')>Bar</option>
                                    <option value="cashier" @selected(($filters['station'] ?? '') === 'cashier')>Kasir</option>
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th>
                                <select form="category-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                                </select>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            @php $row = $category->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $categories->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $category->name }}</button>
                                    <p class="mt-0.5 text-[12px] text-muted">{{ $category->slug }}</p>
                                </td>
                                <td>{{ $category->stationLabel() }}</td>
                                <td>
                                    @if ($category->color)
                                        <span class="inline-flex items-center gap-2">
                                            <span class="h-4 w-4 shrink-0 rounded-full border border-line" style="background: {{ $category->color }}"></span>
                                            <span class="text-[13px] text-muted">{{ $category->color }}</span>
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $category->sort_order }}</td>
                                <td class="font-semibold">{{ number_format($category->products_count) }}</td>
                                <td>
                                    <x-status :value="$category->is_active ? 'green' : 'gray'">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</x-status>
                                </td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                        @can('products.manage')
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
                                <td colspan="8" class="py-16 text-center text-sm text-slate-400">Tidak ada kategori yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($categories->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $categories->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 h-10 w-10 shrink-0 rounded-xl border border-line" :style="viewing?.color ? ('background:' + viewing.color) : 'background:#f5f5f5'"></span>
                            <div>
                                <h3 class="text-lg font-semibold text-heading" x-text="viewing?.name || 'Detail Kategori'"></h3>
                                <p class="mt-1 text-[13px] text-muted">
                                    <span x-text="viewing?.slug || '—'"></span>
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
                            <p class="stat-kicker">Produk</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.products_count ?? 0"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Stasiun</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.station_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Urutan</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.sort_order ?? 0"></p>
                        </div>
                    </div>
                </div>
                @can('products.manage')
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="confirmDelete(viewing)">Hapus</button>
                        <button type="button" class="btn-add" @click="openEdit(viewing)">Edit</button>
                    </div>
                @endcan
            </div>
        </div>

        @can('products.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" :action="formMode === 'edit' ? form.update_url : storeUrl">
                        @csrf
                        <input type="hidden" name="_form_mode" :value="formMode">
                        <input type="hidden" name="_category_id" :value="form.id || ''">
                        <template x-if="formMode === 'edit'">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading" x-text="formMode === 'edit' ? 'Edit Kategori' : 'Tambah Kategori'"></h3>
                                    <p class="mt-1 text-[13px] text-muted" x-text="formMode === 'edit' ? (form.slug ? 'Slug ' + form.slug : '') : 'Kelompokkan menu agar kasir lebih cepat memilih.'"></p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="label">Nama</label>
                                    <input class="input" name="name" required maxlength="80" x-model="form.name">
                                    @error('name')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Stasiun</label>
                                    <select name="station" class="input" x-model="form.station">
                                        <option value="">—</option>
                                        <option value="kitchen">Dapur</option>
                                        <option value="bar">Bar</option>
                                        <option value="cashier">Kasir</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Urutan</label>
                                    <input class="input" type="number" name="sort_order" x-model="form.sort_order">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Warna</label>
                                    <div class="flex items-center gap-2">
                                        <input class="h-11 w-11 shrink-0 cursor-pointer rounded-lg border border-line bg-white p-1" type="color" :value="form.color || '#c2410c'" @input="form.color = $event.target.value">
                                        <input class="input" name="color" maxlength="20" x-model="form.color" placeholder="#c2410c">
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
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
                    <h3 class="text-lg font-semibold text-heading">Hapus Kategori</h3>
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
        function categoryPage() {
            const emptyForm = () => ({
                id: null,
                name: '',
                slug: '',
                station: '',
                color: '',
                sort_order: 0,
                is_active: true,
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
                form = { ...form, ...formOld };
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
                },
                openEdit(row) {
                    if (! row) return;
                    this.formMode = 'edit';
                    this.form = { ...emptyForm(), ...row };
                    this.viewOpen = false;
                    this.deleteOpen = false;
                    this.serverFormError = false;
                    this.formOpen = true;
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
                closeTop() {
                    if (this.deleteOpen) this.deleteOpen = false;
                    else if (this.formOpen) this.formOpen = false;
                    else if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
