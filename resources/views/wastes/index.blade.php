@extends('layouts.app')
@section('title', 'Waste')
@section('breadcrumb', 'Inventory')
@section('content')
    @php
        $formError = $errors->any();
        $formOld = [
            'product_id' => (string) old('product_id', ''),
            'quantity' => old('quantity', ''),
            'reason' => (string) old('reason', ''),
            'notes' => old('notes', ''),
        ];
    @endphp
    <div x-data="wastePage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total waste</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua catatan outlet ini</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M9 7V5h6v2m-7 0v12a1 1 0 001 1h6a1 1 0 001-1V7"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Hari ini</p>
                    <p class="stat-value">{{ number_format($stats['today']) }}</p>
                    <p class="stat-hint">Dicatat hari ini</p>
                </div>
                <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M6 5h12a1 1 0 011 1v14a1 1 0 01-1 1H6a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Kadaluarsa</p>
                    <p class="stat-value">{{ number_format($stats['expired']) }}</p>
                    <p class="stat-hint">Alasan kadaluarsa</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.3 4.2L2.6 17.5A2 2 0 004.3 20.5h15.4a2 2 0 001.7-3L13.7 4.2a2 2 0 00-3.4 0z"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar waste</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    @can('inventory.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Catat waste
                        </button>
                    @endcan
                </div>
            </div>

            <form id="waste-filters" method="GET" action="{{ route('wastes.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Nomor</th>
                            <th>Produk</th>
                            <th>Qty</th>
                            <th>Alasan</th>
                            <th>Waktu</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="waste-filters" class="col-filter" type="search" name="number" value="{{ $filters['number'] ?? '' }}" placeholder="Nomor..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="waste-filters" class="col-filter" type="search" name="product" value="{{ $filters['product'] ?? '' }}" placeholder="Nama / SKU..." onchange="this.form.submit()">
                            </th>
                            <th></th>
                            <th>
                                <select form="waste-filters" class="col-filter" name="reason" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach (\App\Enums\WasteReason::cases() as $reason)
                                        <option value="{{ $reason->value }}" @selected(($filters['reason'] ?? '') === $reason->value)>{{ $reason->label() }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($wastes as $waste)
                            @php $row = $waste->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $wastes->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $waste->number }}</button>
                                </td>
                                <td>{{ $waste->product?->name ?? '—' }}</td>
                                <td class="font-semibold">{{ $row['quantity_label'] }}</td>
                                <td>
                                    <x-status :value="$waste->reason?->color() ?? 'gray'">{{ $waste->reason?->label() ?? '—' }}</x-status>
                                </td>
                                <td>
                                    <p>{{ $waste->user?->name ?? '—' }}</p>
                                    <p class="mt-0.5 text-[12px] text-muted">{{ $row['created_label'] }}</p>
                                </td>
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
                                <td colspan="7" class="py-16 text-center text-sm text-slate-400">Tidak ada waste yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($wastes->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $wastes->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.product_name || 'Detail Waste'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.number || '—'"></span>
                                ·
                                <span x-text="viewing?.reason_label || '—'"></span>
                            </p>
                        </div>
                        <button type="button" class="modal-close" @click="viewOpen = false">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Qty</p>
                            <p class="mt-1 text-lg font-semibold text-brand" x-text="viewing?.quantity_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Alasan</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.reason_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">SKU</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.sku || '—'"></p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
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
                            <dt class="text-[12px] text-muted">Catatan</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.notes || '—'"></dd>
                        </div>
                    </dl>
                </div>
                <div class="crud-modal-footer">
                    <button type="button" class="btn-ghost" @click="viewOpen = false">Tutup</button>
                </div>
            </div>
        </div>

        @can('inventory.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('wastes.store') }}">
                        @csrf
                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading">Catat Waste</h3>
                                    <p class="mt-1 text-[13px] text-muted">Stok produk akan berkurang sesuai kuantitas yang dicatat.</p>
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
                                    <label class="label">Kuantitas</label>
                                    <input class="input" type="number" step="1" min="1" name="quantity" required x-model="form.quantity" placeholder="1">
                                    @error('quantity')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Alasan</label>
                                    <select name="reason" class="input" required x-model="form.reason">
                                        <option value="">Pilih alasan</option>
                                        @foreach (\App\Enums\WasteReason::cases() as $reason)
                                            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('reason')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Catatan</label>
                                    <textarea class="input min-h-24" name="notes" x-model="form.notes" placeholder="Opsional"></textarea>
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
        function wastePage() {
            const emptyForm = () => ({
                product_id: '',
                quantity: '',
                reason: '',
                notes: '',
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
                openCreate() {
                    this.form = emptyForm();
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
