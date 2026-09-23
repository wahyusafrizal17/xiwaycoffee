@extends('layouts.app')
@section('title', 'Bagi hasil')
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
    @endphp

    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Omzet cafe</p>
                <p class="stat-value">{{ money($sales) }}</p>
                <p class="stat-hint">Penjualan dikurangi setoran makanan</p>
            </div>
            <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 17l6-6 4 4 8-8M14 7h7v7"/></svg>
            </span>
        </div>
        <div class="stat-card">
            <div>
                <p class="stat-kicker">BOP</p>
                <p class="stat-value">{{ money($bop) }}</p>
                <p class="stat-hint">Biaya operasional periode ini</p>
            </div>
            <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-12V5m0 14v-2"/></svg>
            </span>
        </div>
        <div class="stat-card">
            <div>
                <p class="stat-kicker">{{ $remainder < 0 ? 'Defisit' : 'Sisa bagi hasil' }}</p>
                <p class="stat-value">{{ money($remainder) }}</p>
                <p class="stat-hint">Omzet dikurangi BOP</p>
            </div>
            <span class="stat-icon {{ $remainder < 0 ? 'bg-brand-soft text-brand' : 'bg-[#e8f1ff] text-[#3b82f6]' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-3.3 0-6 1.8-6 4s2.7 4 6 4 6 1.8 6 4-2.7 4-6 4"/></svg>
            </span>
        </div>
    </div>

    <form id="profit-filters" method="GET" action="{{ route('reports.profit') }}">
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="outlet_id" value="{{ $filters['outlet_id'] ?? '' }}">
    </form>
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('reports.profit', $periodKeep + ['period' => 'today']) }}" class="{{ $chip }} {{ $period === 'today' ? $chipOn : $chipOff }}">Hari ini</a>
        <a href="{{ route('reports.profit', $periodKeep + ['period' => 'week']) }}" class="{{ $chip }} {{ $period === 'week' ? $chipOn : $chipOff }}">Mingguan</a>
        <a href="{{ route('reports.profit', $periodKeep + ['period' => 'month']) }}" class="{{ $chip }} {{ $period === 'month' ? $chipOn : $chipOff }}">Bulanan</a>
        <input form="profit-filters" class="input !w-40" type="date" name="from" value="{{ $filters['from'] ?? '' }}" onchange="this.form.period.value=''; this.form.submit()">
        <input form="profit-filters" class="input !w-40" type="date" name="to" value="{{ $filters['to'] ?? '' }}" onchange="this.form.period.value=''; this.form.submit()">
    </div>

    <div class="mb-5 grid gap-4 md:grid-cols-3">
        @foreach ($shares as $share)
            <div class="card p-5">
                <p class="stat-kicker">{{ $share['name'] }}</p>
                <p class="stat-value">{{ money($share['amount']) }}</p>
                <p class="stat-hint">{{ number_format($share['percent'], 1, ',', '.') }}% · modal {{ money($share['capital']) }}</p>
            </div>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h5 class="card-header-title">BOP periode ini</h5>
                <p class="card-header-subtitle">Pengeluaran yang dipotong sebelum bagi hasil.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Nominal</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->spent_on?->format('d/m/Y') }}</td>
                            <td>{{ $expense->category?->label() ?? $expense->category }}</td>
                            <td>{{ money($expense->amount) }}</td>
                            <td>{{ $expense->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-16 text-center text-sm text-slate-400">Tidak ada BOP pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
