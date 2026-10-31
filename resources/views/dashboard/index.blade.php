@extends('layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')
@section('content')
    @php
        $outletName = current_outlet()?->name ?? 'Semua outlet';
        $showFinance = auth()->user()->hasPermission('reports.view');
        $showFoodShare = $showFinance || auth()->user()->hasPermission('reports.food');
        $periodLabel = $range['label'] ?? $metrics['label'] ?? '';
        $filterPeriod = $period;
        $statGrid = $showFoodShare ? 'sm:grid-cols-2 xl:grid-cols-4' : 'sm:grid-cols-2';
        $tabs = ['cafe' => 'Cafe', 'food' => 'Makanan'];
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
        @if ($tab !== 'cafe')
            <input type="hidden" name="tab" value="{{ $tab }}">
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

        @unless ($foodOnly)
        <div class="ml-auto inline-flex !h-10 items-center gap-1 rounded-xl bg-brand-soft p-1">
            @foreach ($tabs as $key => $name)
                <a
                    href="{{ route('dashboard', array_merge(request()->except('tab'), $key === 'cafe' ? [] : ['tab' => $key])) }}"
                    class="rounded-lg px-4 py-1.5 text-[13px] font-medium {{ $tab === $key ? 'bg-brand text-white' : 'text-muted' }}"
                    @if ($tab === $key) aria-current="page" @endif
                >{{ $name }}</a>
            @endforeach
        </div>
        @endunless
    </form>

    @if ($tab === 'cafe')
        <div class="mb-3">
            <h2 class="font-serif text-xl font-semibold text-heading">Pendapatan</h2>
            <p class="mt-1 text-[12px] text-muted">{{ $periodLabel }}.</p>
        </div>
    @endif
    <div class="mb-5 grid gap-4 {{ $statGrid }}">
        @if ($tab !== 'food')
        <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Pendapatan minuman</p>
                    <p class="stat-value">{{ money($metrics['drinks']) }}</p>
                    <p class="stat-hint">Setelah diskon pada periode ini</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#2563eb]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 10h16M4 14h10M4 18h6"/></svg>
                </span>
            </div>
        @endif
        @if ($tab === 'food')
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Pendapatan makanan</p>
                    <p class="stat-value">{{ money($metrics['food_sales']) }}</p>
                    <p class="stat-hint">Setelah diskon pada periode ini</p>
                </div>
                <span class="stat-icon bg-[#fff3e8] text-[#c2410c]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 10h16M4 14h10M4 18h6"/></svg>
                </span>
            </div>
        @endif
        @if ($tab === 'food' && ! $showFoodShare)
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Makanan terjual</p>
                    <p class="stat-value">{{ qty($charts['top_food']->sum('qty')) }}</p>
                    <p class="stat-hint">Porsi pada periode ini</p>
                </div>
            </div>
        @endif
        @if ($tab === 'food' && $showFoodShare)
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Pendapatan cafe dari makanan</p>
                    <p class="stat-value">{{ money($metrics['food_cafe']) }}</p>
                    <p class="stat-hint">Komisi cafe</p>
                </div>
            </div>
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Setoran makanan</p>
                    <p class="stat-value">{{ money($metrics['food_setoran']) }}</p>
                    <p class="stat-hint">Sudah disetor {{ money($metrics['food_setoran_paid']) }} · sisa {{ money($metrics['food_setoran_remaining']) }}</p>
                </div>
            </div>
        @endif
        @if ($tab === 'cafe' && $showFinance)
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Komisi makanan</p>
                    <p class="stat-value">{{ money($metrics['food_cafe']) }}</p>
                    <p class="stat-hint">Bagian cafe dari penjualan makanan</p>
                </div>
            </div>
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Omzet cafe</p>
                    <p class="stat-value">{{ money($metrics['sales']) }}</p>
                    <p class="stat-hint">Semua pendapatan − setoran makanan</p>
                </div>
            </div>
        @endif
        @if ($tab !== 'food')
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Target minuman</p>
                    <p class="stat-value {{ $metrics['drink_target_percent'] >= 100 ? 'text-[#166534]' : '' }}">{{ $metrics['drink_target_percent'] }}%</p>
                    <p class="stat-hint">{{ qty($metrics['drink_cups']) }} dari {{ qty($metrics['drink_target']) }} cup · target {{ $metrics['drink_daily_target'] }} cup/hari</p>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-brand-soft">
                        <div class="h-full rounded-full {{ $metrics['drink_target_percent'] >= 100 ? 'bg-[#166534]' : 'bg-brand' }}" style="width: {{ min(100, (int) $metrics['drink_target_percent']) }}%"></div>
                    </div>
                </div>
                <span class="stat-icon {{ $metrics['drink_target_percent'] >= 100 ? 'bg-[#eef6f0] text-[#166534]' : 'bg-brand-soft text-brand' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
        @endif
    </div>

    @if ($tab === 'cafe' && $showFinance)
    <div class="mb-3">
        <h2 class="font-serif text-xl font-semibold text-heading">Operasional cafe</h2>
        <p class="mt-1 text-[12px] text-muted">{{ $periodLabel }}.</p>
    </div>
        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">BOP</p>
                    <p class="stat-value">{{ money($metrics['bop_other']) }}</p>
                    <p class="stat-hint">Bahan, sewa, listrik, dan lainnya, tanpa gaji</p>
                </div>
            </div>
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Gaji karyawan</p>
                    <p class="stat-value">{{ money($metrics['salary']) }}</p>
                    <p class="stat-hint">Gaji tetap karyawan aktif</p>
                </div>
            </div>
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Total operasional</p>
                    <p class="stat-value">{{ money($metrics['operational']) }}</p>
                    <p class="stat-hint">BOP + gaji karyawan</p>
                </div>
            </div>
            <div class="stat-card !items-start">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Omzet bersih</p>
                    <p class="stat-value {{ $metrics['net'] < 0 ? 'text-brand' : '' }}">{{ money($metrics['net']) }}</p>
                    <p class="stat-hint">Omzet cafe − total operasional</p>
                </div>
            </div>
        </div>

    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Rincian operasional</h5>
                    <p class="card-header-subtitle">Per kategori, terbesar di atas.</p>
                </div>
            </div>
            <div class="divide-y divide-[#f0ebe4] px-5 py-1 text-sm">
                @forelse ($metrics['expense_groups'] as $group)
                    <div class="py-3.5">
                        <div class="flex items-center justify-between gap-4">
                            <p class="font-medium text-heading">{{ $group['label'] }}</p>
                            <p class="tabular-nums font-semibold text-heading">{{ money($group['amount']) }}</p>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-brand-soft">
                            <div class="h-full rounded-full bg-brand" style="width: {{ $group['percent'] }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm text-muted">Belum ada biaya di periode ini.</p>
                @endforelse
            </div>
        </div>
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Bagi hasil</h5>
                    <p class="card-header-subtitle">Dari omzet cafe − charge − BOP {{ money($metrics['share_base']) }}.</p>
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

    <div class="mb-5 card overflow-hidden p-5">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h2 class="font-serif text-xl font-semibold text-heading">Tren penjualan {{ $tab === 'food' ? 'makanan' : 'minuman' }}</h2>
                <p class="mt-1 text-[12px] text-muted">14 hari terakhir</p>
            </div>
        </div>
        <div class="h-72"><canvas id="salesTrend"></canvas></div>
    </div>

    <div class="mb-5">
        @foreach ([$tab === 'food'
            ? ['title' => 'Makanan terlaris', 'rows' => $charts['top_food']]
            : ['title' => 'Minuman terlaris', 'rows' => $charts['top_drinks']]] as $block)
            <div class="card overflow-hidden">
                <div class="card-header">
                    <div>
                        <h5 class="card-header-title">{{ $block['title'] }}</h5>
                        <p class="card-header-subtitle">Semua terjual {{ strtolower($periodLabel) }}. Omzet setelah diskon.@if ($showFoodShare) Profit = omzet − HPP.@endif</p>
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
                                @if ($showFoodShare)
                                    <th class="text-right">Profit</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($block['rows'] as $i => $row)
                                <tr>
                                    <td class="text-muted">{{ $i + 1 }}</td>
                                    <td class="font-medium text-heading">{{ $row->name }}</td>
                                    <td class="text-right tabular-nums">{{ number_format((float) $row->qty) }}</td>
                                    <td class="text-right tabular-nums">{{ money($row->total) }}</td>
                                    @if ($showFoodShare)
                                        <td class="text-right tabular-nums {{ (float) $row->profit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ money($row->profit) }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $showFoodShare ? 5 : 4 }}" class="py-14 text-center text-sm text-muted">Belum ada penjualan di periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    @if ($tab === 'cafe')
    <div class="mb-3">
        <h2 class="font-serif text-xl font-semibold text-heading">Pembayaran</h2>
        <p class="mt-1 text-[12px] text-muted">{{ $periodLabel }}.</p>
    </div>
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="stat-card !items-start">
            <div class="min-w-0 flex-1">
                <p class="stat-kicker">Cash</p>
                <p class="stat-value">{{ money($metrics['cash']) }}</p>
                <p class="stat-hint">Pembayaran tunai {{ strtolower($periodLabel) }}</p>
            </div>
            <span class="stat-icon bg-[#eef6f0] text-[#166534]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </span>
        </div>
        <div class="stat-card !items-start">
            <div class="min-w-0 flex-1">
                <p class="stat-kicker">QRIS</p>
                <p class="stat-value">{{ money($metrics['qris']) }}</p>
                <p class="stat-hint">Pembayaran QRIS {{ strtolower($periodLabel) }}</p>
            </div>
            <span class="stat-icon bg-[#e8f1ff] text-[#2563eb]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 3h2v3h-2v-3zm4-3h2v2h-2v-2zm-4 0h2v2h-2v-2zm4 3h2v3h-2v-3z"/></svg>
            </span>
        </div>
        <div class="stat-card !items-start">
            <div class="min-w-0 flex-1">
                <p class="stat-kicker">Total pembayaran</p>
                <p class="stat-value">{{ money($metrics['payment_total']) }}</p>
                <p class="stat-hint">Dibanding pendapatan kotor {{ money($metrics['gross']) }}</p>
            </div>
        </div>
    </div>

    @if (! $metrics['payments_match'])
        <div class="mb-5 rounded-xl border border-[#f5d0a9] bg-[#fff7ed] px-4 py-3 text-[13px] text-[#9a3412]">
            Perlu rekonsiliasi. Total pembayaran {{ money($metrics['payment_total']) }}, pendapatan kotor {{ money($metrics['gross']) }}, selisih {{ money($metrics['payment_gap']) }}.
        </div>
    @endif


    <div class="mb-5 card overflow-hidden">
        <div class="card-header">
            <div>
                <h5 class="card-header-title">Sumber kedatangan</h5>
                <p class="card-header-subtitle">Order lunas {{ strtolower($periodLabel) }}. Yang paling banyak di atas.</p>
            </div>
        </div>
        <div class="divide-y divide-[#f0ebe4] px-5 py-1 text-sm">
            @forelse ($charts['arrival_sources'] as $row)
                <div class="py-3.5">
                    <div class="flex items-center justify-between gap-4">
                        <p class="font-medium text-heading">{{ $row['label'] }}</p>
                        <p class="tabular-nums text-[13px] text-muted">{{ $row['qty'] }} order · {{ $row['percent'] }}%</p>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-brand-soft">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $row['percent'] }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-muted">Belum ada data sumber kedatangan.</p>
            @endforelse
        </div>
    </div>

    @endif
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
        const el = document.getElementById('salesTrend');
        if (!el || !window.Chart) return;

        new window.Chart(el, {
            type: 'line',
            data: {
                labels: @json($charts['sales_trend']->pluck('d')),
                datasets: [{
                    data: @json($charts['sales_trend']->pluck('total')->map(fn ($v) => (float) $v)),
                    backgroundColor: 'rgba(111,23,21,.10)',
                    borderColor: '#e8192c',
                    fill: true,
                    tension: .35,
                    borderWidth: 2.5,
                    pointBackgroundColor: '#e8192c',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                }],
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#171717', padding: 10, cornerRadius: 8 },
                },
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    y: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 11 } }, beginAtZero: true },
                },
            },
        });
    });
</script>
@endpush
