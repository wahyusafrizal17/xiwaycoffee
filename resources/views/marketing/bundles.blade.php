@extends('layouts.app')
@section('title', 'Bundles')
@section('breadcrumb', 'Marketing')
@section('content')
    @php
        $formError = $errors->any();
        $oldOutlets = array_map('strval', (array) old('outlet_ids', []));
        $formItems = collect((array) old('items', [
            ['product_id' => '', 'quantity' => 1],
            ['product_id' => '', 'quantity' => 1],
        ]))->map(fn ($item) => [
            'product_id' => (string) data_get($item, 'product_id', ''),
            'quantity' => (float) data_get($item, 'quantity', 1) ?: 1,
        ])->values()->all();
        if (count($formItems) < 2) {
            $formItems[] = ['product_id' => '', 'quantity' => 1];
        }
    @endphp
    <div x-data="bundlePage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total bundle</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua paket terdaftar</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 12v10H4V12M2 7h20v5H2V7zM12 22V7M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Sedang berjalan</p>
                    <p class="stat-value">{{ number_format($stats['active']) }}</p>
                    <p class="stat-hint">Aktif dalam periode hari ini</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Nonaktif</p>
                    <p class="stat-value">{{ number_format($stats['inactive']) }}</p>
                    <p class="stat-hint">Tidak dipakai di kasir</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar bundle</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    @can('marketing.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Tambah bundle
                        </button>
                    @endcan
                </div>
            </div>

            <form id="bundle-filters" method="GET" action="{{ route('marketing.bundles') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Nama</th>
                            <th>SKU</th>
                            <th>Harga</th>
                            <th>Isi paket</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="bundle-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="bundle-filters" class="col-filter" type="search" name="sku" value="{{ $filters['sku'] ?? '' }}" placeholder="SKU..." onchange="this.form.submit()">
                            </th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th>
                                <select form="bundle-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                                </select>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bundles as $bundle)
                            @php $row = $bundle->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $bundles->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $bundle->name }}</button>
                                </td>
                                <td>{{ $bundle->sku ?: '—' }}</td>
                                <td class="font-semibold">{{ money($bundle->price) }}</td>
                                <td>
                                    <span class="badge-soft">{{ $bundle->items->count() }} item</span>
                                </td>
                                <td class="text-[13px] text-muted">{{ $bundle->periodLabel() }}</td>
                                <td>
                                    <x-status :value="$bundle->statusColor()">{{ $bundle->statusLabel() }}</x-status>
                                </td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                        @can('marketing.manage')
                                            <form method="POST" action="{{ route('marketing.bundles.toggle', $bundle) }}">
                                                @csrf
                                                <button type="submit" class="table-action {{ $bundle->is_active ? '' : 'table-action-danger' }}" title="{{ $bundle->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                    @if ($bundle->is_active)
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    @else
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    @endif
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-16 text-center text-sm text-slate-400">Tidak ada bundle yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($bundles->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $bundles->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.name || 'Detail Bundle'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.sku || '—'"></span>
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
                            <p class="stat-kicker">Harga</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.price_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Isi paket</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.items_count_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Status</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.status_label || '—'"></p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[12px] text-muted">Periode</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.period_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Jam berlaku</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.time_label || '—'"></dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[12px] text-muted">Outlet</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.outlets_label || '—'"></dd>
                        </div>
                    </dl>

                    <div class="mt-5">
                        <p class="stat-kicker">Produk dalam paket</p>
                        <div class="mt-2 divide-y divide-[#f0f0f0] overflow-hidden rounded-xl border border-line">
                            <template x-for="(item, index) in (viewing?.items || [])" :key="index">
                                <div class="flex items-center justify-between bg-white px-4 py-2.5 text-sm">
                                    <span class="font-medium" x-text="item.name"></span>
                                    <span class="text-muted" x-text="'× ' + item.quantity"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                @can('marketing.manage')
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="viewOpen = false">Tutup</button>
                        <form method="POST" :action="viewing?.toggle_url">
                            @csrf
                            <button class="btn-add" type="submit" x-text="viewing?.is_active ? 'Nonaktifkan' : 'Aktifkan'"></button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        @can('marketing.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('marketing.bundles.store') }}">
                        @csrf
                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading">Tambah Bundle</h3>
                                    <p class="mt-1 text-[13px] text-muted">Paket aktif akan muncul di kasir sesuai periode dan outlet.</p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="label">Nama</label>
                                    <input class="input" name="name" required maxlength="120" value="{{ old('name') }}" placeholder="Contoh: Paket Hemat A">
                                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">SKU</label>
                                    <input class="input" name="sku" maxlength="40" value="{{ old('sku') }}" placeholder="Opsional">
                                    @error('sku')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Harga paket</label>
                                    <input class="input" type="number" step="0.01" min="0" name="price" required value="{{ old('price') }}" placeholder="0">
                                    @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Mulai</label>
                                    <input class="input" type="date" name="start_date" value="{{ old('start_date') }}">
                                </div>
                                <div>
                                    <label class="label">Selesai</label>
                                    <input class="input" type="date" name="end_date" value="{{ old('end_date') }}">
                                    @error('end_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Jam mulai</label>
                                    <input class="input" type="time" name="start_time" value="{{ old('start_time') }}">
                                </div>
                                <div>
                                    <label class="label">Jam selesai</label>
                                    <input class="input" type="time" name="end_time" value="{{ old('end_time') }}">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Outlet</label>
                                    <div class="max-h-36 space-y-0.5 overflow-y-auto rounded-lg border border-line bg-[#fafafa] p-2">
                                        @forelse ($outlets as $outlet)
                                            <label class="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-white">
                                                <input type="checkbox" name="outlet_ids[]" value="{{ $outlet->id }}" class="rounded border-line text-brand focus:ring-brand/20" @checked(in_array((string) $outlet->id, $oldOutlets, true))>
                                                {{ $outlet->name }}
                                            </label>
                                        @empty
                                            <p class="px-2 py-3 text-[13px] text-muted">Belum ada outlet aktif.</p>
                                        @endforelse
                                    </div>
                                    <p class="mt-1.5 text-[12px] text-muted">Kosongkan untuk berlaku di semua outlet.</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <div class="mb-2 flex items-center justify-between">
                                        <label class="label mb-0">Produk dalam paket</label>
                                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="items.push({ product_id: '', quantity: 1 })">Tambah item</button>
                                    </div>
                                    <p class="mb-3 text-[12px] text-muted">Minimal dua produk.</p>
                                    @error('items')<p class="mb-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                    <div class="space-y-2">
                                        <template x-for="(item, index) in items" :key="index">
                                            <div class="grid grid-cols-12 items-center gap-2">
                                                <div class="col-span-8 min-w-0">
                                                    <select class="input js-product-select" :name="`items[${index}][product_id]`" x-model="item.product_id" required>
                                                        <option value="">Pilih produk</option>
                                                        @foreach ($products as $product)
                                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <input class="input col-span-3" type="number" min="1" step="1" :name="`items[${index}][quantity]`" x-model="item.quantity" required>
                                                <button type="button" class="btn-ghost col-span-1 !px-2" @click="items.length > 2 && items.splice(index, 1)">×</button>
                                            </div>
                                        </template>
                                    </div>
                                    @error('items.0.product_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                    @error('items.1.product_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="crud-modal-footer">
                            <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                            <button class="btn-add" type="submit">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        function bundlePage() {
            const formError = @json($formError);
            const formItems = @json($formItems);

            return {
                formOpen: formError,
                viewOpen: false,
                viewing: null,
                items: formItems,
                openCreate() {
                    this.viewOpen = false;
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
