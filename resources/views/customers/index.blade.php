@extends('layouts.app')
@section('title', 'Customers')
@section('breadcrumb', 'CRM')
@section('content')
    @php
        $formError = $errors->any() && ! $errors->has('file');
        $importError = $errors->has('file');
        $requestedModal = request('modal');
        $storeUrl = route('customers.store');
        $formOld = [
            'name' => old('name', data_get($focusPayload, 'name', '')),
            'phone' => old('phone', data_get($focusPayload, 'phone', '')),
            'email' => old('email', data_get($focusPayload, 'email', '')),
            'birthday' => old('birthday', data_get($focusPayload, 'birthday', '')),
            'gender' => old('gender', data_get($focusPayload, 'gender', '')),
            'address' => old('address', data_get($focusPayload, 'address', '')),
            'membership_level' => old('membership_level', data_get($focusPayload, 'membership_level', 'regular')),
            'is_active' => old('is_active', data_get($focusPayload, 'is_active', true) ? '1' : '0') !== '0',
            'id' => old('_customer_id', data_get($focusPayload, 'id')),
            'mode' => old('_form_mode', 'create'),
        ];
    @endphp
    <div x-data="customerPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total pelanggan</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua data master</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h8M8 12h8M8 17h5M5 5h14a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Dengan telepon</p>
                    <p class="stat-value">{{ number_format($stats['phone']) }}</p>
                    <p class="stat-hint">Kontak tersedia</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5a2 2 0 012-2h2.3a1 1 0 01.95.68l1.2 3.4a1 1 0 01-.27 1.1L8 10a12 12 0 006 6l1.82-1.18a1 1 0 011.1-.27l3.4 1.2a1 1 0 01.68.95V19a2 2 0 01-2 2A16 16 0 013 5z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Dengan email</p>
                    <p class="stat-value">{{ number_format($stats['email']) }}</p>
                    <p class="stat-hint">Alamat email tercatat</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM4 8l8 6 8-6"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar pelanggan</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn-excel">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM8 6v12M4 10h16M4 14h16"/></svg>
                        Export Excel
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn-pdf">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg>
                        Export PDF
                    </a>
                    @can('customers.manage')
                        <button type="button" class="btn-import" @click="importOpen = true">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                            Import Excel
                        </button>
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Tambah pelanggan
                        </button>
                    @endcan
                </div>
            </div>

            <form id="customer-filters" method="GET" action="{{ route('customers.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Kode</th>
                            <th>Nama pelanggan</th>
                            <th>No. HP</th>
                            <th>Email</th>
                            <th>Level</th>
                            <th>Alamat</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="customer-filters" class="col-filter" type="search" name="code" value="{{ $filters['code'] ?? '' }}" placeholder="Kode..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="customer-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="customer-filters" class="col-filter" type="search" name="phone" value="{{ $filters['phone'] ?? '' }}" placeholder="No. HP..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="customer-filters" class="col-filter" type="search" name="email" value="{{ $filters['email'] ?? '' }}" placeholder="Email..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="customer-filters" class="col-filter" name="membership_level" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach (\App\Enums\MembershipLevel::cases() as $level)
                                        <option value="{{ $level->value }}" @selected(($filters['membership_level'] ?? '') === $level->value)>{{ $level->label() }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th>
                                <input form="customer-filters" class="col-filter" type="search" name="address" value="{{ $filters['address'] ?? '' }}" placeholder="Alamat..." onchange="this.form.submit()">
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            @php $row = $customer->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $customers->firstItem() + $loop->index }}</td>
                                <td>{{ $customer->code ?: '—' }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $customer->name }}</button>
                                </td>
                                <td>{{ $customer->phone ?: '—' }}</td>
                                <td>{{ $customer->email ?: '—' }}</td>
                                <td>
                                    @if ($customer->membership_level)
                                        <span class="badge-soft">{{ $customer->membership_level->label() }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $customer->address ?: '—' }}</td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                        @can('customers.manage')
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
                                <td colspan="8" class="py-16 text-center text-sm text-slate-400">Tidak ada pelanggan yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $customers->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.name || 'Detail Pelanggan'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.code || '—'"></span>
                                ·
                                <span x-text="viewing?.membership_label || '—'"></span>
                            </p>
                        </div>
                        <button type="button" class="modal-close" @click="viewOpen = false">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Poin</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.points_label || '0'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Total transaksi</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.total_transaction_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Transaksi terakhir</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.last_transaction_label || '—'"></p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[12px] text-muted">No. HP</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.phone || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Email</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.email || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Tanggal lahir</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.birthday_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Gender</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.gender_label || '—'"></dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[12px] text-muted">Alamat</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.address || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Status</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.is_active ? 'Aktif' : 'Nonaktif'"></dd>
                        </div>
                    </dl>
                </div>
                @can('customers.manage')
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="confirmDelete(viewing)">Hapus</button>
                        <button type="button" class="btn-add" @click="openEdit(viewing)">Edit</button>
                    </div>
                @endcan
            </div>
        </div>

        @can('customers.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" :action="formMode === 'edit' ? form.update_url : storeUrl">
                        @csrf
                        <input type="hidden" name="_form_mode" :value="formMode">
                        <input type="hidden" name="_customer_id" :value="form.id || ''">
                        <template x-if="formMode === 'edit'">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading" x-text="formMode === 'edit' ? 'Edit Pelanggan' : 'Tambah Pelanggan'"></h3>
                                    <p class="mt-1 text-[13px] text-muted" x-text="formMode === 'edit' ? (form.code ? 'Kode ' + form.code : '') : 'Kode pelanggan dibuat otomatis setelah disimpan.'"></p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="label">Nama</label>
                                <input class="input" name="name" required maxlength="150" x-model="form.name">
                                @error('name')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">No. HP</label>
                                <input class="input" name="phone" maxlength="30" x-model="form.phone">
                                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Email</label>
                                <input class="input" type="email" name="email" x-model="form.email">
                                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Tanggal lahir</label>
                                <input class="input" type="date" name="birthday" x-model="form.birthday">
                                @error('birthday')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Gender</label>
                                <select name="gender" class="input" x-model="form.gender">
                                    <option value="">—</option>
                                    <option value="male">Laki-laki</option>
                                    <option value="female">Perempuan</option>
                                    <option value="other">Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Level</label>
                                <select name="membership_level" class="input" x-model="form.membership_level">
                                    @foreach (\App\Enums\MembershipLevel::cases() as $level)
                                        <option value="{{ $level->value }}">{{ $level->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label">Alamat</label>
                                <textarea class="input min-h-24" name="address" x-model="form.address"></textarea>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="flex items-center gap-3 text-sm">
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
                    <h3 class="text-lg font-semibold text-heading">Hapus Pelanggan</h3>
                    <p class="mt-2 text-sm text-muted">
                        Hapus <span class="font-medium text-heading" x-text="pendingDelete?.name"></span>? Data akan diarsipkan dan bisa dipulihkan dari database.
                    </p>
                    <form method="POST" class="mt-6 flex justify-end gap-2" :action="pendingDelete?.delete_url">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn-ghost" @click="deleteOpen = false">Batal</button>
                        <button class="btn-danger" type="submit">Hapus</button>
                    </form>
                </div>
            </div>

            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="importOpen" x-cloak @click.self="closeImport()">
                <div class="import-modal">
                    <h3 class="text-lg font-semibold text-heading">Import Pelanggan</h3>
                    <p class="mt-1 text-[13px] text-muted">Upload file .xlsx dari template atau dari Export Excel.</p>

                    <form method="POST" action="{{ route('customers.import') }}" enctype="multipart/form-data" class="mt-5">
                        @csrf
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted">Upload file excel</p>
                        <input class="sr-only" type="file" name="file" x-ref="file" accept=".xlsx,.xls,.csv" @change="onFile($event)">
                        <button
                            type="button"
                            class="import-dropzone w-full"
                            :class="dragging && 'import-dropzone-active'"
                            @click="$refs.file.click()"
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="onDrop($event)"
                        >
                            <svg class="h-10 w-10 text-[#b4b4b4]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6h.1a5 5 0 011 9.9M16 16l-4-4m0 0l-4 4m4-4v9"/>
                            </svg>
                            <span class="mt-3 text-sm text-muted" x-text="fileName || 'Klik untuk memilih file .xlsx atau .xls'"></span>
                        </button>
                        @error('file')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
                            <a href="{{ route('customers.template') }}" class="btn-ghost">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4"/>
                                </svg>
                                Download Template
                            </a>
                            <button class="btn-add" type="submit" :disabled="!fileName">Import</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        function customerPage() {
            const emptyForm = () => ({
                id: null,
                code: '',
                name: '',
                phone: '',
                email: '',
                birthday: '',
                birthday_label: '—',
                gender: '',
                gender_label: '—',
                address: '',
                membership_level: 'regular',
                membership_label: 'Regular',
                is_active: true,
                points_label: '0',
                total_transaction_label: '',
                last_transaction_label: '—',
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
                importOpen: @json($importError),
                fileName: '',
                dragging: false,
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
                closeImport() {
                    this.importOpen = false;
                    this.fileName = '';
                    this.dragging = false;
                    if (this.$refs.file) this.$refs.file.value = '';
                },
                closeTop() {
                    if (this.deleteOpen) this.deleteOpen = false;
                    else if (this.formOpen) this.formOpen = false;
                    else if (this.viewOpen) this.viewOpen = false;
                    else if (this.importOpen) this.closeImport();
                },
                onFile(event) {
                    const file = event.target.files[0];
                    this.fileName = file ? file.name : '';
                },
                onDrop(event) {
                    this.dragging = false;
                    const file = event.dataTransfer.files[0];
                    if (! file) return;
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    this.$refs.file.files = transfer.files;
                    this.fileName = file.name;
                },
            };
        }
    </script>
@endpush
