@extends('layouts.app')
@section('title', 'Stock')
@section('breadcrumb', 'Inventory')
@section('content')
    @php
        $formError = $errors->any();
        $formOld = [
            'product_id' => (string) old('product_id', ''),
            'quantity' => old('quantity', ''),
            'reason' => old('reason', ''),
        ];
    @endphp
    <div x-data="inventoryPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total item</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Stok di outlet ini</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7l9-4 9 4-9 4-9-4zM3 12l9 4 9-4M3 17l9 4 9-4"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Menipis</p>
                    <p class="stat-value">{{ number_format($stats['low']) }}</p>
                    <p class="stat-hint">Di bawah reorder</p>
                </div>
                <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.3 4.2L2.6 17.5A2 2 0 004.3 20.5h15.4a2 2 0 001.7-3L13.7 4.2a2 2 0 00-3.4 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Habis</p>
                    <p class="stat-value">{{ number_format($stats['out']) }}</p>
                    <p class="stat-hint">Tidak ada stok</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar stok</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    <a href="{{ route('inventory.movements') }}" class="btn-import">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h10M7 12h10M7 17h6"/></svg>
                        Riwayat mutasi
                    </a>
                    @can('inventory.manage')
                        <button type="button" class="btn-add" @click="openAdjust()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Sesuaikan stok
                        </button>
                    @endcan
                </div>
            </div>

            <form id="inventory-filters" method="GET" action="{{ route('inventory.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>SKU</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Stok</th>
                            <th>Reserved</th>
                            <th>Status</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="inventory-filters" class="col-filter" type="search" name="sku" value="{{ $filters['sku'] ?? '' }}" placeholder="SKU..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="inventory-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="inventory-filters" class="col-filter" name="category_id" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                            <th>
                                <select form="inventory-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="ok" @selected(($filters['status'] ?? '') === 'ok')>Aman</option>
                                    <option value="low" @selected(($filters['status'] ?? '') === 'low')>Menipis</option>
                                    <option value="out" @selected(($filters['status'] ?? '') === 'out')>Habis</option>
                                </select>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stocks as $stock)
                            @php $row = $stock->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $stocks->firstItem() + $loop->index }}</td>
                                <td>{{ $stock->product?->sku ?: '—' }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $stock->product?->name }}</button>
                                </td>
                                <td>{{ $stock->product?->category?->name ?? '—' }}</td>
                                <td class="font-semibold">{{ $row['quantity_label'] }}</td>
                                <td>{{ $row['reserved_label'] }}</td>
                                <td>
                                    <x-status :value="$stock->statusTone()">{{ $stock->statusLabel() }}</x-status>
                                </td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                        @can('inventory.manage')
                                            <button type="button" class="table-action" title="Sesuaikan" @click="openAdjust({{ Js::from($row) }})">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-16 text-center text-sm text-slate-400">Tidak ada stok yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($stocks->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $stocks->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.product_name || 'Detail Stok'"></h3>
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
                            <p class="stat-kicker">Stok</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.quantity_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Reserved</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.reserved_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Tersedia</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.available_label || '—'"></p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[12px] text-muted">Kategori</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.category_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Reorder</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.reorder_label || '—'"></dd>
                        </div>
                    </dl>
                </div>
                @can('inventory.manage')
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="viewOpen = false">Tutup</button>
                        <button type="button" class="btn-add" @click="openAdjust(viewing)">Sesuaikan stok</button>
                    </div>
                @endcan
            </div>
        </div>

        @can('inventory.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('inventory.adjust') }}">
                        @csrf
                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading">Sesuaikan Stok</h3>
                                    <p class="mt-1 text-[13px] text-muted">Masukkan angka positif untuk menambah, negatif untuk mengurangi.</p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4">
                                <div>
                                    <label class="label">Produk</label>
                                    <select name="product_id" class="input js-product-select" required x-model="form.product_id">
                                        <option value="">Pilih produk</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                        @endforeach
                                    </select>
                                    @error('product_id')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Kuantitas (+ / −)</label>
                                    <input class="input" type="number" step="1" name="quantity" required x-model="form.quantity" placeholder="5 atau -2">
                                    @error('quantity')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Alasan</label>
                                    <input class="input" name="reason" required maxlength="255" x-model="form.reason" placeholder="Contoh: Koreksi hitung fisik">
                                    @error('reason')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
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
        function inventoryPage() {
            const emptyForm = () => ({
                product_id: '',
                quantity: '',
                reason: '',
            });

            const formError = @json($formError);
            const formOld = @json($formOld);
            let form = emptyForm();
            if (formError) {
                form = { ...emptyForm(), ...formOld };
            }

            return {
                formOpen: formError,
                form,
                viewOpen: false,
                viewing: null,
                serverFormError: formError,
                openAdjust(row) {
                    this.form = emptyForm();
                    if (row?.product_id) {
                        this.form.product_id = String(row.product_id);
                    }
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
