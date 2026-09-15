@extends('layouts.app')
@section('title', 'Batches')
@section('breadcrumb', 'Production')
@section('content')
    @php
        $requestedModal = request('modal');
    @endphp
    <div x-data="batchPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total batch</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua hasil produksi</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h10"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Tersedia</p>
                    <p class="stat-value">{{ number_format($stats['available']) }}</p>
                    <p class="stat-hint">Masih ada sisa</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Kadaluarsa</p>
                    <p class="stat-value">{{ number_format($stats['expired']) }}</p>
                    <p class="stat-hint">Sudah lewat tanggal</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar batch</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    <a href="{{ route('production.index') }}" class="btn-import">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>
                        Daftar produksi
                    </a>
                </div>
            </div>

            <form id="batch-filters" method="GET" action="{{ route('batches.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Batch</th>
                            <th>Produk</th>
                            <th>Outlet</th>
                            <th>Sisa</th>
                            <th>Status</th>
                            <th>Expired</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="batch-filters" class="col-filter" type="search" name="number" value="{{ $filters['number'] ?? '' }}" placeholder="Nomor..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="batch-filters" class="col-filter" type="search" name="product" value="{{ $filters['product'] ?? '' }}" placeholder="Nama / SKU..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="batch-filters" class="col-filter" name="outlet_id" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th>
                                <select form="batch-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="available" @selected(($filters['status'] ?? '') === 'available')>Tersedia</option>
                                    <option value="expired" @selected(($filters['status'] ?? '') === 'expired')>Kadaluarsa</option>
                                    <option value="depleted" @selected(($filters['status'] ?? '') === 'depleted')>Habis</option>
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            @php $row = $batch->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $batches->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $batch->batch_number }}</button>
                                </td>
                                <td>{{ $batch->product?->name ?? '—' }}</td>
                                <td>{{ $batch->outlet?->name ?? '—' }}</td>
                                <td class="font-semibold">{{ $row['remaining_label'] }}</td>
                                <td>
                                    <x-status :value="$row['status_color']">{{ $row['status_label'] }}</x-status>
                                </td>
                                <td class="{{ $row['status'] === 'expired' ? 'text-brand' : '' }}">{{ $row['expires_label'] }}</td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-16 text-center text-sm text-slate-400">Tidak ada batch yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($batches->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $batches->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal !max-w-[880px]">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.batch_number || 'Detail Batch'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.product_name || '—'"></span>
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
                            <p class="stat-kicker">Qty</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.quantity_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Yield</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.yield_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Sisa</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.remaining_label || '—'"></p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[12px] text-muted">SKU</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.sku || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Outlet</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.outlet_name || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Tujuan</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.destination_name || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Diproduksi</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.produced_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Expired</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.expires_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Production order</dt>
                            <dd class="mt-0.5 font-medium">
                                <a class="hover:underline" x-show="viewing?.production_url" x-cloak :href="viewing?.production_url || '#'" x-text="viewing?.production_number || '—'"></a>
                                <span x-show="! viewing?.production_url" x-text="viewing?.production_number || '—'"></span>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 overflow-hidden rounded-xl border border-line">
                        <p class="px-4 pt-4 text-sm font-medium">Bahan pada produksi</p>
                        <table class="list-table mt-2">
                            <thead>
                                <tr>
                                    <th>Bahan</th>
                                    <th>Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in (viewing?.materials || [])" :key="item.id">
                                    <tr>
                                        <td>
                                            <p class="font-semibold" x-text="item.product_name"></p>
                                            <p class="mt-0.5 text-[12px] text-muted"><span x-text="item.sku"></span> · <span x-text="item.unit || '—'"></span></p>
                                        </td>
                                        <td class="font-semibold" x-text="item.quantity_label"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p class="px-4 py-10 text-center text-sm text-slate-400" x-show="! (viewing?.materials || []).length">Tidak ada rincian bahan.</p>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-xl border border-line">
                        <p class="px-4 pt-4 text-sm font-medium">Terpakai di penjualan</p>
                        <table class="list-table mt-2">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Item</th>
                                    <th>Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in (viewing?.sales || [])" :key="item.id">
                                    <tr>
                                        <td>
                                            <a class="font-semibold hover:underline" x-show="item.order_url" x-cloak :href="item.order_url" x-text="item.order_number"></a>
                                            <span x-show="! item.order_url" x-text="item.order_number"></span>
                                        </td>
                                        <td x-text="item.name"></td>
                                        <td class="font-semibold" x-text="item.quantity_label"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p class="px-4 py-10 text-center text-sm text-slate-400" x-show="! (viewing?.sales || []).length">Batch ini belum terpakai di penjualan.</p>
                    </div>
                </div>
                <div class="crud-modal-footer">
                    <button type="button" class="btn-ghost" @click="viewOpen = false">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function batchPage() {
            const focus = @json($focusPayload);
            const requestedModal = @json($requestedModal);

            return {
                viewOpen: requestedModal === 'view' && !! focus,
                viewing: focus ? JSON.parse(JSON.stringify(focus)) : null,
                openView(row) {
                    if (! row) return;
                    this.viewing = JSON.parse(JSON.stringify(row));
                    this.viewOpen = true;
                },
                closeTop() {
                    if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
