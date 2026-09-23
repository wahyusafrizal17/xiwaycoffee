@extends('layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')
@section('content')
    @php
        $outletName = current_outlet()?->name ?? 'Semua outlet';
        $showFinance = auth()->user()->hasPermission('reports.view');
        $periodLabel = $range['label'] ?? $metrics['label'] ?? '';
        $filterPeriod = $period;
    @endphp

    <form
        method="GET"
        action="{{ route('dashboard') }}"
        class="mb-5 flex flex-wrap items-end gap-3"
        x-data="dashboardFilter(@js([
            'period' => $filterPeriod,
            'date' => $range['date'] ?? now()->toDateString(),
            'month' => $range['month'] ?? now()->format('Y-m'),
            'year' => $range['year'] ?? now()->format('Y'),
            'from' => $range['from'] ?? now()->startOfMonth()->toDateString(),
            'to' => $range['to'] ?? now()->toDateString(),
            'outlet_id' => $outletId,
        ]))"
    >
        @if ($outletId)
            <input type="hidden" name="outlet_id" :value="outlet_id">
        @endif

        <div>
            <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Filter</label>
            <div class="relative min-w-[160px]">
                <button type="button" class="input flex !h-10 w-full items-center justify-between gap-2 !rounded-xl !pr-3 text-left text-[13px] font-medium" @click="open = !open" @click.outside="open = false">
                    <span x-text="typeLabel"></span>
                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="absolute left-0 z-20 mt-1 w-full overflow-hidden rounded-xl bg-[#3a3a3a] py-1 text-[13px] text-white shadow-lg" x-show="open" x-cloak>
                    <template x-for="opt in options" :key="opt.value">
                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-white/10" @click="period = opt.value; open = false">
                            <span class="w-4 text-center" x-text="period === opt.value ? '✓' : ''"></span>
                            <span x-text="opt.label"></span>
                        </button>
                    </template>
                </div>
                <input type="hidden" name="period" :value="period">
            </div>
        </div>

        <div class="min-w-[200px] flex-1 sm:flex-none">
            <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">&nbsp;</label>
            <div class="relative" x-show="period === 'day'">
                <input class="input !h-10 !rounded-xl !pr-10 text-[13px]" type="date" name="date" x-model="date" :disabled="period !== 'day'">
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-heading">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <div class="relative" x-show="period === 'month'" x-cloak>
                <input class="input !h-10 !rounded-xl !pr-10 text-[13px]" type="month" name="month" x-model="month" :disabled="period !== 'month'">
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-heading">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <div class="relative" x-show="period === 'year'" x-cloak>
                <input class="input !h-10 !rounded-xl !pr-10 text-[13px]" type="number" name="year" min="2020" max="2100" x-model="year" :disabled="period !== 'year'">
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-heading">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <div class="flex flex-wrap gap-2" x-show="period === 'range'" x-cloak>
                <input class="input !h-10 !min-w-[140px] !rounded-xl text-[13px]" type="date" name="from" x-model="from" :disabled="period !== 'range'">
                <input class="input !h-10 !min-w-[140px] !rounded-xl text-[13px]" type="date" name="to" x-model="to" :disabled="period !== 'range'">
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">&nbsp;</label>
            <button type="submit" class="btn-primary !h-10 !rounded-xl !px-5 text-[13px]">Terapkan</button>
        </div>
    </form>

    @if ($showFinance)
        @php $target = $metrics['target']; @endphp
        <div class="mb-5 card overflow-hidden p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Target omzet minuman</p>
                    <h2 class="mt-1 font-serif text-xl font-semibold text-heading">{{ $target['label'] }}</h2>
                    <p class="mt-1 text-[13px] text-muted">Minimal menutupi BOP {{ money($target['amount']) }}/bulan</p>
                </div>
                <div class="text-right">
                    <p class="font-serif text-3xl font-semibold tabular-nums text-heading">{{ number_format($target['progress'], 1, ',', '.') }}%</p>
                    <p class="mt-1 text-[12px] text-muted">{{ money($target['actual']) }} / {{ money($target['amount']) }}</p>
                </div>
            </div>
            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-[#f0ebe4]">
                <div class="h-full rounded-full bg-brand transition-all" style="width: {{ $target['progress'] }}%"></div>
            </div>
        </div>
    @endif

    <div class="mb-5 grid gap-4 sm:grid-cols-2 {{ $showFinance ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
        <div class="stat-card !items-start">
            <div class="min-w-0 flex-1">
                <p class="stat-kicker">Pendapatan kotor</p>
                <p class="stat-value">{{ money($metrics['gross']) }}</p>
                <p class="stat-hint">{{ number_format($metrics['orders']) }} order · AOV {{ money($metrics['aov']) }}</p>
            </div>
            <span class="stat-icon bg-[#eef6f0] text-[#166534]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 17l6-6 4 4 8-8M14 7h7v7"/></svg>
            </span>
        </div>
        @if ($showFinance)
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">BOP</p>
                    <p class="stat-value">{{ money($metrics['bop']) }}</p>
                    <p class="stat-hint">Biaya operasional tercatat</p>
                </div>
                <span class="stat-icon bg-[#fff3e8] text-[#c2410c]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-12V5m0 14v-2"/></svg>
                </span>
            </div>
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Pendapatan bersih</p>
                    <p class="stat-value {{ $metrics['net'] < 0 ? 'text-brand' : '' }}">{{ money($metrics['net']) }}</p>
                    <p class="stat-hint">Omzet cafe − BOP (siap bagi hasil)</p>
                </div>
                <span class="stat-icon {{ $metrics['net'] < 0 ? 'bg-brand-soft text-brand' : 'bg-[#e8f1ff] text-[#2563eb]' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 7h6M9 11h6M9 15h4M5 5h14a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
                </span>
            </div>
        @else
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Meja tersedia</p>
                    <p class="stat-value">{{ $metrics['available_tables'] }}</p>
                    <p class="stat-hint">Isi {{ $metrics['occupied_tables'] }}</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#2563eb]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 10h16M4 14h10M4 18h6"/></svg>
                </span>
            </div>
        @endif
        <div class="stat-card !items-start">
            <div class="min-w-0 flex-1">
                <p class="stat-kicker">Pesanan berjalan</p>
                <p class="stat-value">{{ $metrics['pending_kitchen'] }}</p>
                <p class="stat-hint">Pickup {{ $metrics['pending_pickup'] }} · Meja isi {{ $metrics['occupied_tables'] }}</p>
            </div>
            <span class="stat-icon bg-[#f3e6e4] text-brand">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3M12 3a9 9 0 100 18 9 9 0 000-18z"/></svg>
            </span>
        </div>
    </div>

    @if ($showFinance)
    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Breakdown penjualan</h5>
                    <p class="card-header-subtitle">Minuman vs makanan {{ strtolower($periodLabel) }}.</p>
                </div>
            </div>
            <div class="divide-y divide-[#f0ebe4] px-5 py-1 text-sm">
                <div class="flex items-center justify-between gap-4 py-3.5">
                    <div>
                        <p class="font-medium text-heading">Pendapatan minuman</p>
                        <p class="text-[12px] text-muted">Coffee, non-coffee, fit tea, Xiway Main</p>
                    </div>
                    <p class="tabular-nums font-semibold text-heading">{{ money($metrics['drinks']) }}</p>
                </div>
                <div class="flex items-center justify-between gap-4 py-3.5">
                    <div>
                        <p class="font-medium text-heading">Pendapatan makanan</p>
                        <p class="text-[12px] text-muted">Total penjualan makanan mitra</p>
                    </div>
                    <p class="tabular-nums font-semibold text-heading">{{ money($metrics['food_sales']) }}</p>
                </div>
                <div class="flex items-center justify-between gap-4 py-3.5">
                    <div>
                        <p class="font-medium text-heading">Pendapatan cafe dari makanan</p>
                        <p class="text-[12px] text-muted">Komisi cafe</p>
                    </div>
                    <p class="tabular-nums font-semibold text-[#166534]">{{ money($metrics['food_cafe']) }}</p>
                </div>
                <div class="flex items-center justify-between gap-4 py-3.5">
                    <div>
                        <p class="font-medium text-heading">Setoran makanan</p>
                        <p class="text-[12px] text-muted">Bagagian mitra (dipotong dari omzet)</p>
                    </div>
                    <p class="tabular-nums font-semibold text-[#c2410c]">{{ money($metrics['food_setoran']) }}</p>
                </div>
                <div class="flex items-center justify-between gap-4 py-3.5">
                    <div>
                        <p class="font-medium text-heading">Omzet cafe</p>
                        <p class="text-[12px] text-muted">Kotor − setoran makanan</p>
                    </div>
                    <p class="tabular-nums font-semibold text-heading">{{ money($metrics['sales']) }}</p>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Bagi hasil</h5>
                    <p class="card-header-subtitle">Dari pendapatan bersih {{ money($metrics['net']) }}.</p>
                </div>
                <a href="{{ route('reports.profit', ['period' => $period]) }}" class="text-[12px] font-medium text-brand hover:underline">Detail</a>
            </div>
            <div class="divide-y divide-[#f0ebe4] px-5 py-1 text-sm">
                @forelse ($metrics['shares'] as $share)
                    <div class="flex items-center justify-between gap-4 py-3.5">
                        <div>
                            <p class="font-medium text-heading">{{ $share['name'] }}</p>
                            <p class="text-[12px] text-muted">{{ number_format($share['percent'], 1, ',', '.') }}% · modal {{ money($share['capital']) }}</p>
                        </div>
                        <p class="tabular-nums font-semibold text-heading">{{ money($share['amount']) }}</p>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm text-muted">Belum ada data investor.</p>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    <div class="mb-5 grid gap-4 xl:grid-cols-[1.4fr_1fr]">
        <div class="card overflow-hidden p-5">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 class="font-serif text-xl font-semibold text-heading">Tren penjualan</h2>
                    <p class="mt-1 text-[12px] text-muted">14 hari terakhir</p>
                </div>
            </div>
            <div class="h-72"><canvas id="salesTrend"></canvas></div>
        </div>
        <div class="card overflow-hidden p-5">
            <div class="mb-4">
                <h2 class="font-serif text-xl font-semibold text-heading">Kategori</h2>
                <p class="mt-1 text-[12px] text-muted">Omzet {{ strtolower($periodLabel) }}</p>
            </div>
            <div class="h-72"><canvas id="salesCategory"></canvas></div>
        </div>
    </div>

    <div class="mb-5 grid gap-4 xl:grid-cols-[1.1fr_1fr]">
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Produk terlaris</h5>
                    <p class="card-header-subtitle">Berdasarkan qty terjual {{ strtolower($periodLabel) }}.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Produk</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($charts['top_products'] as $i => $row)
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td class="font-medium text-heading">{{ $row->name }}</td>
                                <td class="text-right tabular-nums">{{ number_format((float) $row->qty) }}</td>
                                <td class="text-right tabular-nums">{{ money($row->total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-14 text-center text-sm text-muted">Belum ada penjualan di periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden p-5">
            <div class="mb-4">
                <h2 class="font-serif text-xl font-semibold text-heading">Metode bayar</h2>
                <p class="mt-1 text-[12px] text-muted">{{ $periodLabel }}</p>
            </div>
            <div class="h-56"><canvas id="payments"></canvas></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function dashboardFilter(initial = {}) {
        return {
            open: false,
            period: initial.period || 'day',
            date: initial.date,
            month: initial.month,
            year: initial.year,
            from: initial.from,
            to: initial.to,
            outlet_id: initial.outlet_id || '',
            options: [
                { value: 'day', label: 'Harian' },
                { value: 'month', label: 'Bulanan' },
                { value: 'year', label: 'Tahunan' },
                { value: 'range', label: 'Range tanggal' },
            ],
            get typeLabel() {
                return this.options.find((o) => o.value === this.period)?.label || 'Harian';
            },
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        const palette = ['#6f1715', '#1f2937', '#c2410c', '#166534', '#2563eb', '#78716c', '#a16207', '#0f766e'];
        const soft = 'rgba(111,23,21,.10)';
        const make = (id, type, labels, data, extra = {}) => {
            const el = document.getElementById(id);
            if (!el) return;
            new window.Chart(el, {
                type,
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: type === 'line' ? soft : palette,
                        borderColor: '#6f1715',
                        fill: type === 'line',
                        tension: .35,
                        borderWidth: type === 'line' ? 2.5 : 0,
                        borderRadius: 6,
                        pointBackgroundColor: '#6f1715',
                        pointRadius: type === 'line' ? 3 : 0,
                        pointHoverRadius: 5,
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: type === 'doughnut',
                            position: 'bottom',
                            labels: { boxWidth: 8, usePointStyle: true, padding: 16, font: { size: 11 } },
                        },
                        tooltip: {
                            backgroundColor: '#171717',
                            padding: 10,
                            cornerRadius: 8,
                        },
                    },
                    maintainAspectRatio: false,
                    scales: type === 'doughnut' ? {} : {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 11 } }, beginAtZero: true },
                    },
                    ...extra,
                },
            });
        };

        make('salesTrend', 'line', @json($charts['sales_trend']->pluck('d')), @json($charts['sales_trend']->pluck('total')->map(fn ($v) => (float) $v)));
        make('salesCategory', 'doughnut', @json($charts['by_category']->pluck('name')), @json($charts['by_category']->pluck('total')->map(fn ($v) => (float) $v)));
        make('payments', 'doughnut', @json($charts['payments']->pluck('method')), @json($charts['payments']->pluck('total')->map(fn ($v) => (float) $v)));
    });
</script>
@endpush
