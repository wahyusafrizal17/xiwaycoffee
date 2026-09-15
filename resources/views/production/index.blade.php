@extends('layouts.app')
@section('title', 'Production')
@section('breadcrumb', 'Production')
@section('content')
    @php
        $formError = collect($errors->keys())->contains(fn ($key) => in_array($key, ['outlet_id', 'product_id', 'bom_id', 'quantity_planned', 'production_date', 'notes'], true));
        $viewError = $errors->has('status') || $errors->has('quantity_produced') || $errors->has('reason');
        $completeError = $errors->has('quantity_produced');
        $cancelError = $errors->has('reason');
        $requestedModal = request('modal');
        $currentOutletId = (string) (current_outlet_id() ?: '');
        $today = now()->toDateString();
        $formOld = [
            'outlet_id' => (string) old('outlet_id', $currentOutletId),
            'product_id' => (string) old('product_id', ''),
            'bom_id' => (string) old('bom_id', ''),
            'quantity_planned' => old('quantity_planned', ''),
            'production_date' => old('production_date', $today),
            'notes' => old('notes', ''),
        ];
        $oldProduced = old('quantity_produced', '');
        $oldReason = old('reason', '');
    @endphp
    <div x-data="productionPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total produksi</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua outlet</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>
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
                    <p class="stat-hint">Sudah selesai</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar produksi</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    @can('production.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Buat produksi
                        </button>
                    @endcan
                </div>
            </div>

            <form id="production-filters" method="GET" action="{{ route('production.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Nomor</th>
                            <th>Produk</th>
                            <th>Outlet</th>
                            <th>Planned</th>
                            <th>Produced</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="production-filters" class="col-filter" type="search" name="number" value="{{ $filters['number'] ?? '' }}" placeholder="Nomor..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="production-filters" class="col-filter" type="search" name="product" value="{{ $filters['product'] ?? '' }}" placeholder="Nama / SKU..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="production-filters" class="col-filter" name="outlet_id" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                            <th>
                                <select form="production-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach (\App\Enums\ProductionStatus::cases() as $status)
                                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php $row = $order->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $orders->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $order->number }}</button>
                                </td>
                                <td>{{ $order->product?->name ?? '—' }}</td>
                                <td>{{ $order->outlet?->name ?? '—' }}</td>
                                <td class="font-semibold">{{ $row['planned_label'] }}</td>
                                <td>{{ $row['produced_label'] }}</td>
                                <td>
                                    <x-status :value="$order->status->color()">{{ $order->status->label() }}</x-status>
                                </td>
                                <td>{{ $row['production_date_label'] }}</td>
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
                                <td colspan="9" class="py-16 text-center text-sm text-slate-400">Tidak ada produksi yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $orders->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal !max-w-[880px]">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.number || 'Detail Produksi'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.status_label || '—'"></span>
                                ·
                                <span x-text="viewing?.product_name || '—'"></span>
                                ·
                                <span x-text="viewing?.outlet_name || '—'"></span>
                            </p>
                        </div>
                        <button type="button" class="modal-close" @click="viewOpen = false">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Planned</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.planned_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Produced</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.produced_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Yield</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.yield_label || '—'"></p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[12px] text-muted">BOM</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.bom_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Batch</dt>
                            <dd class="mt-0.5 font-medium">
                                <a class="hover:underline" x-show="viewing?.batch_url" x-cloak :href="viewing?.batch_url || '#'" x-text="viewing?.batch_number || '—'"></a>
                                <span x-show="! viewing?.batch_url" x-text="viewing?.batch_number || '—'"></span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Tanggal produksi</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.production_date_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">User</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.user_name || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Mulai</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.started_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Selesai</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.completed_label || '—'"></dd>
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
                                    <th>Bahan</th>
                                    <th>Required</th>
                                    <th>Used</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in (viewing?.items || [])" :key="item.id">
                                    <tr>
                                        <td>
                                            <p class="font-semibold" x-text="item.product_name"></p>
                                            <p class="mt-0.5 text-[12px] text-muted"><span x-text="item.sku"></span> · <span x-text="item.unit || '—'"></span></p>
                                        </td>
                                        <td class="font-semibold" x-text="item.required_label"></td>
                                        <td x-text="item.used_label"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p class="px-4 py-10 text-center text-sm text-slate-400" x-show="! (viewing?.items || []).length">Belum ada item bahan.</p>
                    </div>
                </div>
                <div class="crud-modal-footer">
                    <button type="button" class="btn-ghost" @click="viewOpen = false">Tutup</button>
                    @can('production.manage')
                        <form method="POST" :action="viewing?.start_url" x-show="viewing?.status === 'draft' || viewing?.status === 'planned'" x-cloak>
                            @csrf
                            <input type="hidden" name="_production_id" :value="viewing?.id || ''">
                            <button class="btn-add" type="submit">Mulai</button>
                        </form>
                        <button type="button" class="btn-add" x-show="viewing?.status === 'in_production'" x-cloak @click="confirmComplete(viewing)">Selesai</button>
                        <button type="button" class="btn-ghost !text-red-600" x-show="viewing && viewing.status !== 'completed' && viewing.status !== 'cancelled'" x-cloak @click="confirmCancel(viewing)">Batalkan</button>
                    @endcan
                </div>
            </div>
        </div>

        @can('production.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal !max-w-[640px]">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('production.store') }}">
                        @csrf
                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading">Buat Produksi</h3>
                                    <p class="mt-1 text-[13px] text-muted">Order dibuat sebagai draft. Stok baru berubah saat produksi diselesaikan.</p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label">Outlet</label>
                                    <select name="outlet_id" class="input" required x-model="form.outlet_id">
                                        <option value="">Pilih outlet</option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('outlet_id')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Produk</label>
                                    <select name="product_id" class="input js-product-select" required x-model="form.product_id" @change="onProductChange()">
                                        <option value="">Pilih produk</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                        @endforeach
                                    </select>
                                    @error('product_id')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">BOM</label>
                                    <select name="bom_id" class="input" x-model="form.bom_id">
                                        <option value="">Otomatis / tanpa BOM</option>
                                        <template x-for="bom in filteredBoms()" :key="bom.id">
                                            <option :value="bom.id" x-text="bom.label"></option>
                                        </template>
                                    </select>
                                    @error('bom_id')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Qty planned</label>
                                    <input class="input" type="number" step="0.001" min="0.001" name="quantity_planned" required x-model="form.quantity_planned">
                                    @error('quantity_planned')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Tanggal produksi</label>
                                    <input class="input" type="date" name="production_date" x-model="form.production_date">
                                    @error('production_date')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Catatan</label>
                                    <input class="input" name="notes" x-model="form.notes" placeholder="Opsional">
                                    @error('notes')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="crud-modal-footer">
                            <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                            <button class="btn-add" type="submit">Buat produksi</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 p-4" x-show="completeOpen" x-cloak @click.self="completeOpen = false">
                <div class="crud-modal-sm">
                    <h3 class="text-lg font-semibold text-heading">Selesaikan Produksi</h3>
                    <p class="mt-2 text-sm text-muted">
                        Bahan akan dipotong dan hasil produksi masuk stok untuk
                        <span class="font-medium text-heading" x-text="pendingComplete?.number"></span>.
                    </p>
                    <form method="POST" class="mt-5 space-y-4" :action="pendingComplete?.complete_url">
                        @csrf
                        <input type="hidden" name="_production_id" :value="pendingComplete?.id || ''">
                        <div>
                            <label class="label">Qty hasil</label>
                            <input class="input" type="number" step="0.001" min="0.001" name="quantity_produced" required x-model="completeQty">
                            @error('quantity_produced')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-ghost" @click="completeOpen = false">Batal</button>
                            <button class="btn-add" type="submit">Selesai</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 p-4" x-show="cancelOpen" x-cloak @click.self="cancelOpen = false">
                <div class="crud-modal-sm">
                    <h3 class="text-lg font-semibold text-heading">Batalkan Produksi</h3>
                    <p class="mt-2 text-sm text-muted">
                        Produksi
                        <span class="font-medium text-heading" x-text="pendingCancel?.number"></span>
                        akan dibatalkan. Stok tidak berubah.
                    </p>
                    <form method="POST" class="mt-5 space-y-4" :action="pendingCancel?.cancel_url">
                        @csrf
                        <input type="hidden" name="_production_id" :value="pendingCancel?.id || ''">
                        <div>
                            <label class="label">Alasan</label>
                            <input class="input" name="reason" required maxlength="255" x-model="cancelReason" placeholder="Alasan pembatalan">
                            @error('reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-ghost" @click="cancelOpen = false">Kembali</button>
                            <button class="btn-ghost !text-red-600" type="submit">Batalkan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        function productionPage() {
            const currentOutletId = @json($currentOutletId);
            const today = @json($today);
            const boms = @json($boms);
            const emptyForm = () => ({
                outlet_id: currentOutletId,
                product_id: '',
                bom_id: '',
                quantity_planned: '',
                production_date: today,
                notes: '',
            });

            const focus = @json($focusPayload);
            const formError = @json($formError);
            const viewError = @json($viewError);
            const completeError = @json($completeError);
            const cancelError = @json($cancelError);
            const requestedModal = @json($requestedModal);
            const formOld = @json($formOld);
            const oldProduced = @json($oldProduced);
            const oldReason = @json($oldReason);

            if (focus && oldProduced) {
                focus.produced_input = String(oldProduced);
            }

            let form = emptyForm();
            if (formError) {
                form = { ...emptyForm(), ...formOld };
            }

            const viewing = focus ? JSON.parse(JSON.stringify(focus)) : null;

            return {
                formOpen: formError || requestedModal === 'create',
                form,
                boms,
                viewOpen: ! formError && (viewError || requestedModal === 'view') && !! focus,
                viewing,
                completeOpen: ! formError && completeError && !! focus,
                cancelOpen: ! formError && cancelError && ! completeError && !! focus,
                pendingComplete: completeError && viewing ? viewing : null,
                pendingCancel: cancelError && viewing ? viewing : null,
                completeQty: oldProduced || (focus ? String(focus.produced_input || '') : ''),
                cancelReason: oldReason || '',
                serverFormError: formError,
                serverViewError: viewError,
                filteredBoms() {
                    const pid = String(this.form.product_id || '');
                    if (! pid) return this.boms;
                    return this.boms.filter((bom) => String(bom.product_id) === pid);
                },
                onProductChange() {
                    const ids = this.filteredBoms().map((bom) => String(bom.id));
                    if (this.form.bom_id && ! ids.includes(String(this.form.bom_id))) {
                        this.form.bom_id = '';
                    }
                },
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
                confirmComplete(row) {
                    if (! row) return;
                    this.pendingComplete = row;
                    this.completeQty = row.produced_input || String(row.planned || '');
                    this.completeOpen = true;
                },
                confirmCancel(row) {
                    if (! row) return;
                    this.pendingCancel = row;
                    this.cancelReason = '';
                    this.cancelOpen = true;
                },
                closeTop() {
                    if (this.cancelOpen) this.cancelOpen = false;
                    else if (this.completeOpen) this.completeOpen = false;
                    else if (this.formOpen) this.formOpen = false;
                    else if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
