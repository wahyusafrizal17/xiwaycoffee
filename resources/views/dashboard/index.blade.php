@extends('layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')
@section('content')
    @php
        $chip = 'inline-flex items-center rounded-full px-3.5 py-1.5 text-[12px] font-medium transition';
        $chipOn = 'bg-heading text-white';
        $chipOff = 'bg-white text-muted ring-1 ring-[#e8e4de] hover:text-heading';
        $periodKeep = array_filter(['outlet_id' => $outletId ?: null]);
        $periodLabel = $period === 'month' ? 'Bulan ini' : 'Hari ini';
        $outletName = current_outlet()?->name ?? 'Semua outlet';
        $showFinance = auth()->user()->hasPermission('reports.view');
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">{{ $showFinance ? 'Ringkasan keuangan' : 'Ringkasan operasional' }}</p>
            <h1 class="mt-1 font-serif text-[1.85rem] font-semibold leading-none text-heading">Dashboard</h1>
            <p class="mt-2 text-[13px] text-muted">{{ $outletName }} · {{ $periodLabel }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('dashboard', $periodKeep + ['period' => 'today']) }}" class="{{ $chip }} {{ $period === 'today' ? $chipOn : $chipOff }}">Hari ini</a>
            <a href="{{ route('dashboard', $periodKeep + ['period' => 'month']) }}" class="{{ $chip }} {{ $period === 'month' ? $chipOn : $chipOff }}">Bulan ini</a>
            @if (auth()->user()->canSwitchOutlet())
                <form method="GET" class="ml-1">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <select name="outlet_id" class="input !h-9 !min-w-[160px] !rounded-full !py-1.5 text-[12px]" onchange="this.form.submit()">
                        <option value="">Outlet aktif</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected((int) $outletId === $outlet->id)>{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a href="{{ route('pos.index') }}" class="btn-primary !rounded-full !px-4 !py-2 text-[12px]">Buka POS</a>
        </div>
    </div>

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
