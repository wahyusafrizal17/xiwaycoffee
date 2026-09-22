@extends('layouts.app')
@section('title', 'Setoran makanan')
@section('breadcrumb', 'Reports')
@section('content')
    @php
        $period = $filters['period'] ?? null;
        $fromLabel = filled($filters['from'] ?? null) ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') : '—';
        $toLabel = filled($filters['to'] ?? null) ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d/m/Y') : '—';
        $periodKeep = collect($filters)->only(['outlet_id'])->filter(fn ($value) => filled($value))->all();
        $chip = 'inline-flex items-center rounded-lg px-3 py-1.5 text-[13px] font-medium';
        $chipOn = 'bg-brand text-white';
        $chipOff = 'bg-[#f5f5f5] text-muted hover:bg-[#ececec]';
        $formError = $errors->any();
    @endphp

    <div x-data="{ formOpen: {{ $formError ? 'true' : 'false' }} }" @keydown.escape.window="formOpen = false">
        @include('reports._nav')

        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Mitra makanan</p>
                <h1 class="mt-1 font-serif text-[1.75rem] font-semibold leading-none text-heading">Setoran makanan</h1>
                <p class="mt-2 max-w-xl text-[13px] text-muted">Hitung bagian mitra, lalu catat saat cafe menyetor sekaligus.</p>
            </div>
            <button type="button" class="btn-add" @click="formOpen = true" @if ($outstanding <= 0) disabled @endif>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                Catat setoran
            </button>
        </div>

        <div class="mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Penjualan makanan</p>
                    <p class="stat-value">{{ money($sales) }}</p>
                    <p class="stat-hint">{{ $fromLabel }} – {{ $toLabel }}</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Komisi cafe</p>
                    <p class="stat-value">{{ money($commission) }}</p>
                    <p class="stat-hint">Periode terpilih</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Setoran periode</p>
                    <p class="stat-value">{{ money($setoran) }}</p>
                    <p class="stat-hint">Penjualan − komisi</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Belum disetor</p>
                    <p class="stat-value {{ $outstanding > 0 ? 'text-[#c2410c]' : '' }}">{{ money($outstanding) }}</p>
                    <p class="stat-hint">Akumulasi − sudah disetor</p>
                </div>
            </div>
        </div>

        <form id="setoran-filters" method="GET" action="{{ route('reports.setoran') }}">
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="hidden" name="outlet_id" value="{{ $filters['outlet_id'] ?? '' }}">
        </form>
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <a href="{{ route('reports.setoran', $periodKeep + ['period' => 'today']) }}" class="{{ $chip }} {{ $period === 'today' ? $chipOn : $chipOff }}">Hari ini</a>
            <a href="{{ route('reports.setoran', $periodKeep + ['period' => 'week']) }}" class="{{ $chip }} {{ $period === 'week' ? $chipOn : $chipOff }}">Mingguan</a>
            <a href="{{ route('reports.setoran', $periodKeep + ['period' => 'month']) }}" class="{{ $chip }} {{ $period === 'month' ? $chipOn : $chipOff }}">Bulanan</a>
            <input form="setoran-filters" class="input !w-40" type="date" name="from" value="{{ $filters['from'] ?? '' }}" onchange="this.form.period.value=''; this.form.submit()">
            <input form="setoran-filters" class="input !w-40" type="date" name="to" value="{{ $filters['to'] ?? '' }}" onchange="this.form.period.value=''; this.form.submit()">
        </div>

        <div class="mb-5 card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Rincian setoran</h5>
                    <p class="card-header-subtitle">Makanan milik mitra pada periode terpilih.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Qty</th>
                            <th>Penjualan</th>
                            <th>Komisi cafe</th>
                            <th>Setoran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td>{{ rtrim(rtrim(number_format((float) $row->qty, 3, ',', '.'), '0'), ',') }}</td>
                                <td>{{ money($row->sales) }}</td>
                                <td>{{ money($row->commission) }}</td>
                                <td>{{ money($row->setoran) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-16 text-center text-sm text-slate-400">Belum ada penjualan makanan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Riwayat setoran</h5>
                    <p class="card-header-subtitle">Pencatatan saat cafe menyetor ke mitra. Bukan BOP.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-right">Nominal</th>
                            <th>Keterangan</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settlements as $settlement)
                            <tr>
                                <td class="whitespace-nowrap text-[13px] text-muted">{{ $settlement->settled_on?->format('d M Y') }}</td>
                                <td class="text-right font-medium tabular-nums">{{ money($settlement->amount) }}</td>
                                <td class="max-w-[280px] truncate text-[13px] text-muted">{{ $settlement->notes ?: '—' }}</td>
                                <td class="text-[13px]">{{ $settlement->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-16 text-center text-sm text-slate-400">Belum ada setoran tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
            <div class="crud-modal" @click.stop>
                <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('reports.setoran.store') }}">
                    @csrf
                    <div class="crud-modal-body">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-serif text-lg font-semibold text-heading">Catat setoran</h3>
                                <p class="mt-1 text-[13px] text-muted">Menyetor seluruh sisa {{ money($outstanding) }} sekaligus.</p>
                            </div>
                            <button type="button" class="modal-close" @click="formOpen = false">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>

                        <div class="mt-5 grid gap-4">
                            <div>
                                <label class="label">Tanggal setor</label>
                                <input class="input" type="date" name="settled_on" required value="{{ old('settled_on', now()->toDateString()) }}">
                                @error('settled_on')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Nominal</label>
                                <input class="input bg-[#fafafa]" type="text" value="{{ money($outstanding) }}" readonly>
                                <p class="mt-1 text-[12px] text-muted">Otomatis seluruh yang belum disetor.</p>
                                @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Keterangan</label>
                                <input class="input" type="text" name="notes" value="{{ old('notes') }}" placeholder="Opsional" maxlength="255">
                                @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                        <button class="btn-add" type="submit" @if ($outstanding <= 0) disabled @endif>Simpan setoran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
