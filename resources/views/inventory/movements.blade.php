@extends('layouts.app')
@section('title', 'Movements')
@section('breadcrumb', 'Inventory')
@section('content')
    <div x-data="movementPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total mutasi</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua pergerakan stok</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h10M7 12h10M7 17h6"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Masuk</p>
                    <p class="stat-value">{{ number_format($stats['in']) }}</p>
                    <p class="stat-hint">Kuantitas bertambah</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 19V5m0 0l-5 5m5-5l5 5"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Keluar</p>
                    <p class="stat-value">{{ number_format($stats['out']) }}</p>
                    <p class="stat-hint">Kuantitas berkurang</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m0 0l-5-5m5 5l5-5"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Riwayat mutasi</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    <a href="{{ route('inventory.index') }}" class="btn-import">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7l9-4 9 4-9 4-9-4zM3 12l9 4 9-4M3 17l9 4 9-4"/></svg>
                        Daftar stok
                    </a>
                </div>
            </div>

            <form id="movement-filters" method="GET" action="{{ route('inventory.movements') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Ref</th>
                            <th>Produk</th>
                            <th>Tipe</th>
                            <th>Qty</th>
                            <th>Sesudah</th>
                            <th>Waktu</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="movement-filters" class="col-filter" type="search" name="reference" value="{{ $filters['reference'] ?? '' }}" placeholder="Ref..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="movement-filters" class="col-filter" type="search" name="product" value="{{ $filters['product'] ?? '' }}" placeholder="Nama / SKU..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="movement-filters" class="col-filter" name="type" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach (\App\Enums\StockMovementType::cases() as $type)
                                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            @php $row = $movement->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $movements->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $movement->reference_number }}</button>
                                </td>
                                <td>{{ $movement->product?->name ?? '—' }}</td>
                                <td>
                                    <x-status :value="$movement->type?->color() ?? 'gray'">{{ $movement->type?->label() ?? '—' }}</x-status>
                                </td>
                                <td class="font-semibold {{ $movement->quantity > 0 ? 'text-[#28c76f]' : ($movement->quantity < 0 ? 'text-brand' : '') }}">
                                    {{ $row['quantity_label'] }}
                                </td>
                                <td>{{ $row['after_label'] }}</td>
                                <td class="text-slate-500">{{ $row['created_label'] }}</td>
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
                                <td colspan="8" class="py-16 text-center text-sm text-slate-400">Tidak ada mutasi yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($movements->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $movements->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.product_name || 'Detail Mutasi'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.reference_number || '—'"></span>
                                ·
                                <span x-text="viewing?.type_label || '—'"></span>
                            </p>
                        </div>
                        <button type="button" class="modal-close" @click="viewOpen = false">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Qty</p>
                            <p class="mt-1 text-lg font-semibold" :class="viewing?.quantity_tone === 'plus' ? 'text-[#28c76f]' : (viewing?.quantity_tone === 'minus' ? 'text-brand' : '')" x-text="viewing?.quantity_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Sebelum</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.before_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Sesudah</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.after_label || '—'"></p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[12px] text-muted">SKU</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.sku || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Pengguna</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.user_name || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Waktu</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.created_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Outlet</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.outlet_name || '—'"></dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[12px] text-muted">Alasan</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.reason || '—'"></dd>
                        </div>
                    </dl>
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
        function movementPage() {
            return {
                viewOpen: false,
                viewing: null,
                openView(row) {
                    if (! row) return;
                    this.viewing = row;
                    this.viewOpen = true;
                },
                closeTop() {
                    if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
