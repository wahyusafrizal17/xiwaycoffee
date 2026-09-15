@extends('layouts.app')
@section('title', 'Stock Opname')
@section('breadcrumb', 'Inventory')
@section('content')
    @php
        $formError = $errors->has('category_id') || $errors->has('notes');
        $countError = $errors->any() && ! $formError;
        $requestedModal = request('modal');
        $storeUrl = route('opnames.store');
        $formOld = [
            'category_id' => (string) old('category_id', ''),
            'notes' => old('notes', ''),
        ];
    @endphp
    <div x-data="opnamePage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total opname</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua sesi hitung stok</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Draft</p>
                    <p class="stat-value">{{ number_format($stats['draft']) }}</p>
                    <p class="stat-hint">Belum dikunci</p>
                </div>
                <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Selesai</p>
                    <p class="stat-value">{{ number_format($stats['finalized']) }}</p>
                    <p class="stat-hint">Sudah difinalisasi</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar opname</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    @can('inventory.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Mulai opname
                        </button>
                    @endcan
                </div>
            </div>

            <form id="opname-filters" method="GET" action="{{ route('opnames.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Nomor</th>
                            <th>Kategori</th>
                            <th>Item</th>
                            <th>Selisih</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="opname-filters" class="col-filter" type="search" name="number" value="{{ $filters['number'] ?? '' }}" placeholder="Nomor..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="opname-filters" class="col-filter" name="category_id" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                            <th>
                                <select form="opname-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                                    <option value="finalized" @selected(($filters['status'] ?? '') === 'finalized')>Selesai</option>
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($opnames as $opname)
                            @php $row = $opname->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $opnames->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $opname->number }}</button>
                                </td>
                                <td>{{ $row['category_label'] }}</td>
                                <td class="font-semibold">{{ number_format($row['items_count']) }}</td>
                                <td class="font-semibold {{ $row['variance_count'] > 0 ? 'text-brand' : '' }}">{{ number_format($row['variance_count']) }}</td>
                                <td>
                                    <x-status :value="$opname->statusTone()">{{ $opname->statusLabel() }}</x-status>
                                </td>
                                <td>
                                    <p>{{ $opname->creator?->name ?? '—' }}</p>
                                    <p class="mt-0.5 text-[12px] text-muted">{{ $row['created_label'] }}</p>
                                </td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                        @can('inventory.manage')
                                            @unless ($opname->isLocked())
                                                <button type="button" class="table-action" title="Hitung" @click="openCount({{ Js::from($row) }})">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                                </button>
                                            @endunless
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-16 text-center text-sm text-slate-400">Tidak ada opname yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($opnames->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $opnames->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal !max-w-[880px]">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.number || 'Detail Opname'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.status_label || '—'"></span>
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
                            <p class="stat-kicker">Item</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.items_count ?? 0"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Ada selisih</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.variance_count ?? 0"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Kategori</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.category_label || '—'"></p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[12px] text-muted">Dibuat</dt>
                            <dd class="mt-0.5 font-medium"><span x-text="viewing?.creator_name || '—'"></span> · <span x-text="viewing?.created_label || '—'"></span></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Finalisasi</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.locked ? ((viewing?.approver_name || '—') + ' · ' + (viewing?.finalized_label || '—')) : 'Belum'"></dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[12px] text-muted">Catatan</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.notes || '—'"></dd>
                        </div>
                    </dl>

                    <div class="mt-5 overflow-hidden rounded-xl border border-line">
                        <table class="list-table">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Sistem</th>
                                    <th>Fisik</th>
                                    <th>Selisih</th>
                                    <th>Alasan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in (viewing?.items || [])" :key="item.id">
                                    <tr>
                                        <td>
                                            <p class="font-semibold" x-text="item.product_name"></p>
                                            <p class="mt-0.5 text-[12px] text-muted"><span x-text="item.sku"></span> · <span x-text="item.unit || '—'"></span></p>
                                        </td>
                                        <td x-text="item.system_label"></td>
                                        <td class="font-semibold" x-text="item.physical_label"></td>
                                        <td>
                                            <span class="font-semibold" :class="item.difference_tone === 'plus' ? 'text-[#28c76f]' : (item.difference_tone === 'minus' ? 'text-brand' : '')" x-text="item.difference_label"></span>
                                        </td>
                                        <td class="text-muted" x-text="item.reason || '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="crud-modal-footer">
                    <button type="button" class="btn-ghost" @click="viewOpen = false">Tutup</button>
                    @can('inventory.manage')
                        <button type="button" class="btn-add" x-show="viewing && !viewing.locked" x-cloak @click="openCount(viewing)">Hitung stok</button>
                    @endcan
                </div>
            </div>
        </div>

        @can('inventory.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ $storeUrl }}">
                        @csrf
                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading">Mulai Opname</h3>
                                    <p class="mt-1 text-[13px] text-muted">Snapshot stok sistem diambil saat opname dimulai.</p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4">
                                <div>
                                    <label class="label">Kategori (opsional)</label>
                                    <select name="category_id" class="input" x-model="form.category_id">
                                        <option value="">Semua produk stokable</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('category_id')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Catatan</label>
                                    <textarea class="input min-h-24" name="notes" x-model="form.notes" placeholder="Contoh: Opname harian outlet"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="crud-modal-footer">
                            <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                            <button class="btn-add" type="submit">Mulai opname</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="countOpen" x-cloak @click.self="countOpen = false">
                <div class="crud-modal !max-w-[880px]">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" :action="viewing?.update_url">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_opname_id" :value="viewing?.id || ''">
                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading" x-text="viewing?.number || 'Hitung Stok'"></h3>
                                    <p class="mt-1 text-[13px] text-muted">Simpan perhitungan fisik sebelum finalisasi.</p>
                                </div>
                                <button type="button" class="modal-close" @click="countOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            @error('items')<p class="mt-3 text-sm text-red-600" x-show="serverCountError" x-cloak>{{ $message }}</p>@enderror
                            @error('status')<p class="mt-3 text-sm text-red-600" x-show="serverCountError" x-cloak>{{ $message }}</p>@enderror

                            <div class="mt-5 overflow-hidden rounded-xl border border-line">
                                <table class="list-table">
                                    <thead>
                                        <tr>
                                            <th>Produk</th>
                                            <th>Sistem</th>
                                            <th>Fisik</th>
                                            <th>Selisih</th>
                                            <th>Alasan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="item in (viewing?.items || [])" :key="item.id">
                                            <tr>
                                                <td>
                                                    <input type="hidden" :name="'items[' + item.id + '][id]'" :value="item.id">
                                                    <p class="font-semibold" x-text="item.product_name"></p>
                                                    <p class="mt-0.5 text-[12px] text-muted"><span x-text="item.sku"></span> · <span x-text="item.unit || '—'"></span></p>
                                                </td>
                                                <td x-text="item.system_label"></td>
                                                <td>
                                                    <input class="input !w-28" type="number" step="0.001" required :name="'items[' + item.id + '][physical_qty]'" x-model="item.physical_qty">
                                                </td>
                                                <td>
                                                    <span class="font-semibold" :class="liveDiffTone(item)" x-text="liveDiffLabel(item)"></span>
                                                </td>
                                                <td>
                                                    <input class="input" :name="'items[' + item.id + '][reason]'" x-model="item.reason" placeholder="Opsional">
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="crud-modal-footer">
                            <button type="button" class="btn-ghost" @click="countOpen = false">Batal</button>
                            @can('inventory.approve')
                                <button type="button" class="btn-import" @click="confirmFinalize(viewing)">Finalisasi</button>
                            @endcan
                            <button class="btn-add" type="submit">Simpan hitungan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        @can('inventory.approve')
            <div class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 p-4" x-show="finalizeOpen" x-cloak @click.self="finalizeOpen = false">
                <div class="crud-modal-sm">
                    <h3 class="text-lg font-semibold text-heading">Finalisasi Opname</h3>
                    <p class="mt-2 text-sm text-muted">
                        Stok akan disesuaikan sesuai selisih pada
                        <span class="font-medium text-heading" x-text="pendingFinalize?.number"></span>.
                        Tindakan ini tidak bisa dibatalkan.
                    </p>
                    <form method="POST" class="mt-6 flex justify-end gap-2" :action="pendingFinalize?.finalize_url">
                        @csrf
                        <button type="button" class="btn-ghost" @click="finalizeOpen = false">Batal</button>
                        <button class="btn-add" type="submit">Finalisasi</button>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        function opnamePage() {
            const emptyForm = () => ({
                category_id: '',
                notes: '',
            });

            const focus = @json($focusPayload);
            const formError = @json($formError);
            const countError = @json($countError);
            const requestedModal = @json($requestedModal);
            const formOld = @json($formOld);

            let form = emptyForm();
            if (formError) {
                form = { ...emptyForm(), ...formOld };
            }

            return {
                formOpen: formError || requestedModal === 'create',
                form,
                viewOpen: ! formError && ! countError && requestedModal === 'view' && !! focus,
                countOpen: countError || (! formError && requestedModal === 'count' && !! focus),
                viewing: focus,
                finalizeOpen: false,
                pendingFinalize: null,
                serverFormError: formError,
                serverCountError: countError,
                openCreate() {
                    this.form = emptyForm();
                    this.viewOpen = false;
                    this.countOpen = false;
                    this.serverFormError = false;
                    this.formOpen = true;
                },
                openView(row) {
                    if (! row) return;
                    this.viewing = row;
                    this.formOpen = false;
                    this.countOpen = false;
                    this.viewOpen = true;
                },
                openCount(row) {
                    if (! row || row.locked) return;
                    this.viewing = JSON.parse(JSON.stringify(row));
                    this.formOpen = false;
                    this.viewOpen = false;
                    this.serverCountError = false;
                    this.countOpen = true;
                },
                confirmFinalize(row) {
                    if (! row) return;
                    this.pendingFinalize = row;
                    this.finalizeOpen = true;
                },
                liveDiff(item) {
                    return Number(item.physical_qty) - Number(item.system_qty);
                },
                liveDiffLabel(item) {
                    const diff = this.liveDiff(item);
                    const label = (diff > 0 ? '+' : '') + Number(diff).toFixed(2);
                    return item.unit ? label + ' ' + item.unit : label;
                },
                liveDiffTone(item) {
                    const diff = this.liveDiff(item);
                    if (diff > 0) return 'text-[#28c76f]';
                    if (diff < 0) return 'text-brand';
                    return '';
                },
                closeTop() {
                    if (this.finalizeOpen) this.finalizeOpen = false;
                    else if (this.formOpen) this.formOpen = false;
                    else if (this.countOpen) this.countOpen = false;
                    else if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
