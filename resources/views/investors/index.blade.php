@extends('layouts.app')
@section('title', 'Investor')
@section('breadcrumb', 'System')
@section('content')
    @php
        $formError = $errors->any();
        $requestedModal = old('_form_mode', request('modal'));
        $prefillInvestorId = old('investor_id', request('investor'));
    @endphp

    <div
        x-data="investorPage()"
        @keydown.escape.window="closeAll()"
    >
        @php
            $monthLabel = \Carbon\Carbon::create($targetYear, $targetMonth, 1)->translatedFormat('F Y');
        @endphp

        <div class="mb-5 grid gap-4 lg:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total modal investor</p>
                    <p class="stat-value">{{ money($totalCapital) }}</p>
                    <p class="stat-hint">Dipakai untuk hitung bagi hasil</p>
                </div>
                <span class="stat-icon bg-[#eef2ff] text-[#6366f1]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-12V5m0 14v-2"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Jumlah investor aktif</p>
                    <p class="stat-value">{{ $investors->count() }}</p>
                    <p class="stat-hint">Persentase dihitung dari modal</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="w-full">
                    <div class="mb-2 flex items-start justify-between gap-3">
                        <div>
                            <p class="stat-kicker">Target omzet minuman {{ $monthLabel }}</p>
                            <p class="stat-value">{{ $targetAmount > 0 ? money($targetAmount) : '—' }}</p>
                            <p class="stat-hint">Minimal menutupi BOP {{ money($bopMonthly) }}/bln</p>
                        </div>
                        <button type="button" class="btn-secondary !px-3 !py-1.5 text-sm shrink-0" @click="targetOpen = true">
                            Set target
                        </button>
                    </div>
                    <div class="mb-1.5 flex items-center justify-between text-xs text-slate-500">
                        <span>Omzet minuman {{ money($actualSales) }}</span>
                        <span>{{ $targetAmount > 0 ? number_format($targetProgress, 1, ',', '.') . '%' : 'Belum diset' }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-brand transition-all" style="width: {{ $targetAmount > 0 ? $targetProgress : 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-5 card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">BOP bulanan (rencana)</h5>
                    <p class="card-header-subtitle">Biaya tetap yang harus ditutup omzet minuman. {{ money($bopMonthly) }}/bln.</p>
                </div>
                <button type="button" class="btn-add" @click="openBop()">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                    Tambah
                </button>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Biaya</th>
                            <th>Periode</th>
                            <th class="text-right">Nominal</th>
                            <th class="text-right">Per bulan</th>
                            <th class="col-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bopItems as $index => $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['period'] === 'year' ? 'Tahunan' : 'Bulanan' }}</td>
                                <td class="text-right">{{ money($item['amount']) }}{{ $item['period'] === 'year' ? '/thn' : '/bln' }}</td>
                                <td class="text-right">{{ money($item['monthly']) }}</td>
                                <td class="col-actions">
                                    <button
                                        type="button"
                                        class="btn-secondary !px-3 !py-1.5 text-sm"
                                        @click="openBop({{ $index }}, {{ Js::from($item['name']) }}, {{ Js::from($item['period']) }}, {{ Js::from($item['amount']) }})"
                                    >
                                        Ubah
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-secondary !px-3 !py-1.5 text-sm"
                                        @click="confirmDeleteBop({{ $index }}, {{ Js::from($item['name']) }})"
                                    >
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center text-sm text-slate-400">Belum ada biaya rencana.</td>
                            </tr>
                        @endforelse
                        <tr class="font-semibold">
                            <td colspan="3">Total BOP / bulan</td>
                            <td class="text-right">{{ money($bopMonthly) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar investor</h5>
                    <p class="card-header-subtitle">Klik Topup untuk menambah modal investor.</p>
                </div>
                <div class="card-header-actions flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary" @click="historyOpen = true">Riwayat topup</button>
                    <button type="button" class="btn-add" @click="openCreate()">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                        Tambah investor
                    </button>
                </div>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Modal</th>
                            <th>Saham</th>
                            <th class="col-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($investors as $investor)
                            @php
                                $percent = $totalCapital > 0 ? ((float) $investor->capital / $totalCapital) * 100 : 0;
                            @endphp
                            <tr>
                                <td class="font-semibold">{{ $investor->name }}</td>
                                <td>{{ money($investor->capital) }}</td>
                                <td>{{ number_format($percent, 2, ',', '.') }}%</td>
                                <td class="col-actions">
                                    <button
                                        type="button"
                                        class="btn-primary !px-3 !py-1.5 text-sm"
                                        @click="openTopup({{ $investor->id }}, {{ Js::from($investor->name) }})"
                                    >
                                        Topup
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-16 text-center text-sm text-slate-400">Belum ada investor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Topup modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="topupOpen" x-cloak @click.self="topupOpen = false">
            <div class="crud-modal">
                <form method="POST" action="{{ route('investors.topup') }}">
                    @csrf
                    <input type="hidden" name="_form_mode" value="topup">
                    <div class="crud-modal-body">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h5 class="card-header-title">Topup modal</h5>
                                <p class="card-header-subtitle" x-text="topupName ? ('Untuk ' + topupName) : 'Pilih investor'"></p>
                            </div>
                            <button type="button" class="modal-close" @click="topupOpen = false">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="grid gap-3">
                            <div>
                                <label class="label">Investor</label>
                                <select class="input" name="investor_id" x-model="topupInvestorId" required>
                                    <option value="">Pilih investor</option>
                                    @foreach ($investors as $investor)
                                        <option value="{{ $investor->id }}">{{ $investor->name }} · {{ money($investor->capital) }}</option>
                                    @endforeach
                                </select>
                                @error('investor_id') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="label">Tanggal</label>
                                    <input class="input" type="date" name="topped_up_on" value="{{ old('topped_up_on', now()->toDateString()) }}" required>
                                </div>
                                <div>
                                    <label class="label">Nominal topup</label>
                                    <input class="input" type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                                    @error('amount') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div>
                                <label class="label">Keterangan</label>
                                <input class="input" type="text" name="notes" value="{{ old('notes') }}" placeholder="Topup modal …">
                            </div>
                        </div>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-secondary" @click="topupOpen = false">Batal</button>
                        <button class="btn-primary" type="submit">Simpan topup</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Create investor modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="createOpen" x-cloak @click.self="createOpen = false">
            <div class="crud-modal">
                <form method="POST" action="{{ route('investors.store') }}">
                    @csrf
                    <input type="hidden" name="_form_mode" value="create">
                    <div class="crud-modal-body">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h5 class="card-header-title">Tambah investor</h5>
                                <p class="card-header-subtitle">Isi nama dan modal awal.</p>
                            </div>
                            <button type="button" class="modal-close" @click="createOpen = false">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="grid gap-3">
                            <div>
                                <label class="label">Nama</label>
                                <input class="input" type="text" name="name" value="{{ old('name') }}" required>
                                @error('name') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Modal awal</label>
                                <input class="input" type="number" name="capital" min="0" step="0.01" value="{{ old('capital', 0) }}" required>
                                @error('capital') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-secondary" @click="createOpen = false">Batal</button>
                        <button class="btn-primary" type="submit">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Set target modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="targetOpen" x-cloak @click.self="targetOpen = false">
            <div class="crud-modal">
                <form method="POST" action="{{ route('investors.target') }}">
                    @csrf
                    <input type="hidden" name="_form_mode" value="target">
                    <input type="hidden" name="year" value="{{ $targetYear }}">
                    <input type="hidden" name="month" value="{{ $targetMonth }}">
                    <div class="crud-modal-body">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h5 class="card-header-title">Set target omzet minuman</h5>
                                <p class="card-header-subtitle">{{ $monthLabel }} · minimal BOP {{ money($bopMonthly) }}</p>
                            </div>
                            <button type="button" class="modal-close" @click="targetOpen = false">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div>
                            <label class="label">Target omzet minuman (Rp)</label>
                            <input
                                class="input"
                                type="number"
                                name="amount"
                                min="0.01"
                                step="0.01"
                                value="{{ old('amount', $targetAmount > 0 ? (int) $targetAmount : (int) $bopMonthly) }}"
                                required
                            >
                            <p class="mt-1 text-xs text-muted">Hanya omzet kategori Coffee / Non Coffee / Fit Tea / Xiway Main yang dihitung.</p>
                            @error('amount') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-secondary" @click="targetOpen = false">Batal</button>
                        <button class="btn-primary" type="submit">Simpan target</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- BOP plan modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="bopOpen" x-cloak @click.self="bopOpen = false">
            <div class="crud-modal">
                <form method="POST" action="{{ route('investors.bop.store') }}">
                    @csrf
                    <input type="hidden" name="_form_mode" value="bop">
                    <input type="hidden" name="index" x-model="bopIndex">
                    <div class="crud-modal-body">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h5 class="card-header-title" x-text="bopIndex === '' ? 'Tambah biaya' : 'Ubah biaya'"></h5>
                                <p class="card-header-subtitle">Nominal tahunan dibagi 12 untuk hitungan per bulan.</p>
                            </div>
                            <button type="button" class="modal-close" @click="bopOpen = false">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="grid gap-3">
                            <div>
                                <label class="label">Nama</label>
                                <input class="input" type="text" name="name" x-model="bopName" required maxlength="120">
                                @error('name') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Periode</label>
                                <select class="input" name="period" x-model="bopPeriod" required>
                                    <option value="month">Bulanan</option>
                                    <option value="year">Tahunan</option>
                                </select>
                                @error('period') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Nominal</label>
                                <input class="input" type="number" name="amount" min="0" step="0.01" x-model="bopAmount" required>
                                @error('amount') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                                @error('index') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-secondary" @click="bopOpen = false">Batal</button>
                        <button class="btn-primary" type="submit">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="bopDeleteOpen" x-cloak @click.self="bopDeleteOpen = false">
            <div class="crud-modal !max-w-[420px]">
                <form method="POST" action="{{ route('investors.bop.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="index" x-model="bopDeleteIndex">
                    <div class="crud-modal-body">
                        <h5 class="card-header-title">Hapus biaya</h5>
                        <p class="card-header-subtitle mt-2">Hapus <span class="font-medium text-heading" x-text="bopDeleteName"></span> dari rencana BOP?</p>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-secondary" @click="bopDeleteOpen = false">Batal</button>
                        <button class="btn-primary" type="submit">Hapus</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- History modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="historyOpen" x-cloak @click.self="historyOpen = false">
            <div class="crud-modal !max-w-[760px]">
                <div class="crud-modal-body">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h5 class="card-header-title">Riwayat topup</h5>
                            <p class="card-header-subtitle">Setiap topup menambah modal investor.</p>
                        </div>
                        <button type="button" class="modal-close" @click="historyOpen = false">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="table-wrap max-h-[60vh] overflow-auto rounded-lg border border-line">
                        <table class="list-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Investor</th>
                                    <th>Nominal</th>
                                    <th>Keterangan</th>
                                    <th>Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topups as $topup)
                                    <tr>
                                        <td>{{ $topup->topped_up_on?->format('d/m/Y') }}</td>
                                        <td>{{ $topup->investor?->name ?? '—' }}</td>
                                        <td>{{ money($topup->amount) }}</td>
                                        <td>{{ $topup->notes ?: '—' }}</td>
                                        <td>{{ $topup->user?->name ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-12 text-center text-sm text-slate-400">Belum ada topup.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($topups->hasPages())
                        <div class="mt-4">{{ $topups->links() }}</div>
                    @endif
                </div>
                <div class="crud-modal-footer">
                    <button type="button" class="btn-secondary" @click="historyOpen = false">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function investorPage() {
            const formError = @json($formError);
            const requestedModal = @json($requestedModal);
            const prefillInvestorId = @json($prefillInvestorId ? (string) $prefillInvestorId : '');
            const investors = @json($investors->map(fn ($i) => ['id' => (string) $i->id, 'name' => $i->name])->values());

            const bopError = formError && requestedModal === 'bop';

            return {
                topupOpen: formError && requestedModal === 'topup',
                createOpen: formError && requestedModal === 'create',
                targetOpen: formError && requestedModal === 'target',
                historyOpen: false,
                bopOpen: bopError,
                bopDeleteOpen: false,
                bopIndex: bopError ? @json((string) old('index', '')) : '',
                bopName: bopError ? @json((string) old('name', '')) : '',
                bopPeriod: bopError ? @json((string) old('period', 'month')) : 'month',
                bopAmount: bopError ? @json((string) old('amount', '')) : '',
                bopDeleteIndex: '',
                bopDeleteName: '',
                topupInvestorId: prefillInvestorId,
                topupName: investors.find(i => i.id === prefillInvestorId)?.name || '',
                init() {
                    this.$watch('topupInvestorId', (id) => {
                        this.topupName = investors.find(i => i.id === String(id))?.name || '';
                    });
                },
                openTopup(id, name) {
                    this.topupInvestorId = String(id);
                    this.topupName = name;
                    this.topupOpen = true;
                },
                openCreate() {
                    this.createOpen = true;
                },
                openBop(index = '', name = '', period = 'month', amount = '') {
                    this.bopIndex = index === '' ? '' : String(index);
                    this.bopName = name;
                    this.bopPeriod = period || 'month';
                    this.bopAmount = amount === '' ? '' : String(amount);
                    this.bopOpen = true;
                },
                confirmDeleteBop(index, name) {
                    this.bopDeleteIndex = String(index);
                    this.bopDeleteName = name;
                    this.bopDeleteOpen = true;
                },
                closeAll() {
                    this.topupOpen = false;
                    this.createOpen = false;
                    this.targetOpen = false;
                    this.historyOpen = false;
                    this.bopOpen = false;
                    this.bopDeleteOpen = false;
                },
            };
        }
    </script>
@endsection
