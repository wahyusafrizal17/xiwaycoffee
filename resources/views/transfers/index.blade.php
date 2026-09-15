@extends('layouts.app')
@section('title', 'Transfers')
@section('breadcrumb', 'Inventory')
@section('content')
    @php
        $formError = collect($errors->keys())->contains(fn ($key) => $key !== 'status' && ! str_starts_with($key, 'received'));
        $viewError = $errors->has('status') || $errors->has('received') || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'received.'));
        $requestedModal = request('modal');
        $currentOutletId = (string) (current_outlet_id() ?: '');
        $today = now()->toDateString();
        $oldItems = old('items');
        if (! is_array($oldItems) || $oldItems === []) {
            $oldItems = [['product_id' => '', 'quantity' => 1]];
        } else {
            $oldItems = array_values(array_map(fn ($item) => [
                'product_id' => (string) ($item['product_id'] ?? ''),
                'quantity' => $item['quantity'] ?? 1,
            ], $oldItems));
        }
        $formOld = [
            'source_outlet_id' => (string) old('source_outlet_id', $currentOutletId),
            'destination_outlet_id' => (string) old('destination_outlet_id', ''),
            'transfer_date' => old('transfer_date', $today),
            'notes' => old('notes', ''),
            'items' => $oldItems,
        ];
    @endphp
    <div x-data="transferPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total transfer</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Terkait outlet ini</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Proses</p>
                    <p class="stat-value">{{ number_format($stats['open']) }}</p>
                    <p class="stat-hint">Belum selesai</p>
                </div>
                <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Selesai</p>
                    <p class="stat-value">{{ number_format($stats['completed']) }}</p>
                    <p class="stat-hint">Sudah diterima</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar transfer</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    @can('inventory.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Buat transfer
                        </button>
                    @endcan
                </div>
            </div>

            <form id="transfer-filters" method="GET" action="{{ route('transfers.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Nomor</th>
                            <th>Dari</th>
                            <th>Ke</th>
                            <th>Item</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="transfer-filters" class="col-filter" type="search" name="number" value="{{ $filters['number'] ?? '' }}" placeholder="Nomor..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="transfer-filters" class="col-filter" name="source_outlet_id" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" @selected((string) ($filters['source_outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th>
                                <select form="transfer-filters" class="col-filter" name="destination_outlet_id" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" @selected((string) ($filters['destination_outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th>
                                <select form="transfer-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach (\App\Enums\TransferStatus::cases() as $status)
                                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transfers as $transfer)
                            @php $row = $transfer->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $transfers->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $transfer->number }}</button>
                                </td>
                                <td>{{ $transfer->sourceOutlet?->name ?? '—' }}</td>
                                <td>{{ $transfer->destinationOutlet?->name ?? '—' }}</td>
                                <td class="font-semibold">{{ number_format($row['items_count']) }}</td>
                                <td>
                                    <x-status :value="$transfer->status->color()">{{ $transfer->status->label() }}</x-status>
                                </td>
                                <td>{{ $row['transfer_date_label'] }}</td>
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
                                <td colspan="8" class="py-16 text-center text-sm text-slate-400">Tidak ada transfer yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($transfers->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $transfers->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal !max-w-[880px]">
                <form id="receive-form" class="flex min-h-0 flex-1 flex-col" method="POST" :action="viewing?.receive_url">
                    @csrf
                    <input type="hidden" name="_transfer_id" :value="viewing?.id || ''">
                    <div class="crud-modal-body">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-heading" x-text="viewing?.number || 'Detail Transfer'"></h3>
                                <p class="mt-1 text-[13px] text-muted">
                                    <span x-text="viewing?.status_label || '—'"></span>
                                    ·
                                    <span x-text="viewing?.route_label || '—'"></span>
                                </p>
                            </div>
                            <button type="button" class="modal-close" @click="viewOpen = false">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                                <p class="stat-kicker">Item</p>
                                <p class="mt-1 text-lg font-semibold" x-text="viewing?.items_count ?? 0"></p>
                            </div>
                            <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                                <p class="stat-kicker">Tanggal</p>
                                <p class="mt-1 text-lg font-semibold" x-text="viewing?.transfer_date_label || '—'"></p>
                            </div>
                            <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                                <p class="stat-kicker">Status</p>
                                <p class="mt-1 text-lg font-semibold" x-text="viewing?.status_label || '—'"></p>
                            </div>
                        </div>

                        <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-[12px] text-muted">Pengaju</dt>
                                <dd class="mt-0.5 font-medium" x-text="viewing?.requester_name || '—'"></dd>
                            </div>
                            <div>
                                <dt class="text-[12px] text-muted">Penyetuju</dt>
                                <dd class="mt-0.5 font-medium" x-text="viewing?.approver_name || '—'"></dd>
                            </div>
                            <div>
                                <dt class="text-[12px] text-muted">Dikirim</dt>
                                <dd class="mt-0.5 font-medium" x-text="viewing?.shipped_label || '—'"></dd>
                            </div>
                            <div>
                                <dt class="text-[12px] text-muted">Diterima</dt>
                                <dd class="mt-0.5 font-medium">
                                    <span x-text="viewing?.receiver_name && viewing.receiver_name !== '—' ? viewing.receiver_name + ' · ' : ''"></span>
                                    <span x-text="viewing?.received_label || '—'"></span>
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-[12px] text-muted">Catatan</dt>
                                <dd class="mt-0.5 font-medium" x-text="viewing?.notes || '—'"></dd>
                            </div>
                        </dl>

                        @error('status')<p class="mt-3 text-sm text-red-600" x-show="serverViewError" x-cloak>{{ $message }}</p>@enderror

                        <div class="mt-5 overflow-hidden rounded-xl border border-line">
                            <table class="list-table">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Qty dikirim</th>
                                        <th>Qty diterima</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="item in (viewing?.items || [])" :key="item.id">
                                        <tr>
                                            <td>
                                                <p class="font-semibold" x-text="item.product_name"></p>
                                                <p class="mt-0.5 text-[12px] text-muted"><span x-text="item.sku"></span> · <span x-text="item.unit || '—'"></span></p>
                                            </td>
                                            <td class="font-semibold" x-text="item.quantity_label"></td>
                                            <td>
                                                <template x-if="viewing?.status === 'shipped'">
                                                    <input class="input !w-28" type="number" step="0.001" min="0" :name="'received[' + item.id + ']'" x-model="item.received_input">
                                                </template>
                                                <template x-if="viewing?.status !== 'shipped'">
                                                    <span x-text="viewing?.status === 'completed' ? item.received_label : '—'"></span>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
                <div class="crud-modal-footer">
                    <button type="button" class="btn-ghost" @click="viewOpen = false">Tutup</button>
                    @can('inventory.manage')
                        <form method="POST" :action="viewing?.request_url" x-show="viewing?.status === 'draft'" x-cloak>
                            @csrf
                            <button class="btn-add" type="submit">Ajukan</button>
                        </form>
                    @endcan
                    @can('inventory.approve')
                        <form method="POST" :action="viewing?.approve_url" x-show="viewing?.status === 'requested'" x-cloak>
                            @csrf
                            <button class="btn-add" type="submit">Setujui</button>
                        </form>
                    @endcan
                    @can('inventory.manage')
                        <button type="button" class="btn-add" x-show="viewing?.status === 'approved'" x-cloak @click="confirmShip(viewing)">Kirim</button>
                        <button form="receive-form" class="btn-add" type="submit" x-show="viewing?.status === 'shipped'" x-cloak>Terima</button>
                    @endcan
                </div>
            </div>
        </div>

        @can('inventory.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal !max-w-[880px]">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('transfers.store') }}">
                        @csrf
                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading">Buat Transfer</h3>
                                    <p class="mt-1 text-[13px] text-muted">Stok baru berkurang saat transfer dikirim, dan bertambah saat diterima.</p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label">Outlet sumber</label>
                                    <select name="source_outlet_id" class="input" required x-model="form.source_outlet_id">
                                        <option value="">Pilih outlet</option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('source_outlet_id')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Outlet tujuan</label>
                                    <select name="destination_outlet_id" class="input" required x-model="form.destination_outlet_id">
                                        <option value="">Pilih outlet</option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('destination_outlet_id')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Tanggal transfer</label>
                                    <input class="input" type="date" name="transfer_date" x-model="form.transfer_date">
                                </div>
                                <div>
                                    <label class="label">Catatan</label>
                                    <input class="input" name="notes" x-model="form.notes" placeholder="Opsional">
                                </div>
                            </div>

                            @error('items')<p class="mt-4 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror

                            <div class="mt-6">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-sm font-medium">Item</p>
                                    <button type="button" class="btn-import !px-3 !py-1.5 text-xs" @click="form.items.push({ product_id: '', quantity: 1 })">Tambah item</button>
                                </div>
                                <div class="space-y-3">
                                    <template x-for="(item, index) in form.items" :key="index">
                                        <div class="grid gap-3 rounded-xl border border-line p-4 sm:grid-cols-12">
                                            <div class="sm:col-span-8">
                                                <label class="label">Produk</label>
                                                <select class="input js-product-select" :name="'items[' + index + '][product_id]'" x-model="item.product_id" required>
                                                    <option value="">Pilih produk</option>
                                                    @foreach ($products as $product)
                                                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="sm:col-span-3">
                                                <label class="label">Qty</label>
                                                <input class="input" type="number" step="0.001" min="0.001" :name="'items[' + index + '][quantity]'" x-model="item.quantity" required>
                                            </div>
                                            <div class="flex items-end sm:col-span-1">
                                                <button type="button" class="btn-ghost w-full !px-3" @click="form.items.length > 1 && form.items.splice(index, 1)">×</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="crud-modal-footer">
                            <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                            <button class="btn-add" type="submit">Buat transfer</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 p-4" x-show="shipOpen" x-cloak @click.self="shipOpen = false">
                <div class="crud-modal-sm">
                    <h3 class="text-lg font-semibold text-heading">Kirim Transfer</h3>
                    <p class="mt-2 text-sm text-muted">
                        Stok outlet sumber akan berkurang sesuai item pada
                        <span class="font-medium text-heading" x-text="pendingShip?.number"></span>.
                    </p>
                    <form method="POST" class="mt-6 flex justify-end gap-2" :action="pendingShip?.ship_url">
                        @csrf
                        <button type="button" class="btn-ghost" @click="shipOpen = false">Batal</button>
                        <button class="btn-add" type="submit">Kirim</button>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        function transferPage() {
            const currentOutletId = @json($currentOutletId);
            const today = @json($today);
            const emptyForm = () => ({
                source_outlet_id: currentOutletId,
                destination_outlet_id: '',
                transfer_date: today,
                notes: '',
                items: [{ product_id: '', quantity: 1 }],
            });

            const focus = @json($focusPayload);
            const formError = @json($formError);
            const viewError = @json($viewError);
            const requestedModal = @json($requestedModal);
            const formOld = @json($formOld);

            let form = emptyForm();
            if (formError) {
                form = { ...emptyForm(), ...formOld };
            }

            return {
                formOpen: formError || requestedModal === 'create',
                form,
                viewOpen: ! formError && (viewError || requestedModal === 'view') && !! focus,
                viewing: focus ? JSON.parse(JSON.stringify(focus)) : null,
                shipOpen: false,
                pendingShip: null,
                serverFormError: formError,
                serverViewError: viewError,
                openCreate() {
                    this.form = emptyForm();
                    this.viewOpen = false;
                    this.serverFormError = false;
                    this.formOpen = true;
                },
                openView(row) {
                    if (! row) return;
                    this.viewing = JSON.parse(JSON.stringify(row));
                    this.formOpen = false;
                    this.viewOpen = true;
                },
                confirmShip(row) {
                    if (! row) return;
                    this.pendingShip = row;
                    this.shipOpen = true;
                },
                closeTop() {
                    if (this.shipOpen) this.shipOpen = false;
                    else if (this.formOpen) this.formOpen = false;
                    else if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
