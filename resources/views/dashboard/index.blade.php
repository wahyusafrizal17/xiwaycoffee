@extends('layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')
@section('content')
    <div class="mb-5 grid gap-4 xl:grid-cols-[1.4fr_1fr]">
        <div class="card overflow-hidden p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-[13px] font-medium text-muted">Selamat datang kembali 👋</p>
                    <h2 class="mt-1 text-2xl font-semibold text-heading">{{ auth()->user()->name }}</h2>
                    <p class="mt-2 max-w-lg text-sm text-ink">Ringkasan operasional {{ current_outlet()?->name ?? 'semua outlet' }} hari ini.</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('pos.index') }}" class="btn-primary">Buka POS</a>
                        <a href="{{ route('orders.index') }}" class="btn-ghost">Lihat orders</a>
                    </div>
                </div>
                @if (auth()->user()->canSwitchOutlet())
                    <form method="GET" class="min-w-[180px]">
                        <label class="label">Filter outlet</label>
                        <select name="outlet_id" class="input" onchange="this.form.submit()">
                            <option value="">Current outlet</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}" @selected((int) $outletId === $outlet->id)>{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>
        </div>
        <div class="card p-6">
            <p class="text-sm font-semibold text-heading">Pesanan berjalan</p>
            <div class="mt-4 flex items-end justify-between">
                <div>
                    <p class="text-3xl font-semibold text-heading">{{ $metrics['pending_kitchen'] }}</p>
                    <p class="mt-1 text-sm text-muted">Belum selesai hari ini</p>
                </div>
                <a href="{{ route('orders.index') }}" class="text-sm font-medium text-brand hover:underline">Lihat orders</a>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ([
            ['Today\'s Sales', money($metrics['sales']), 'Pendapatan terbayar', 'bg-brand-soft text-brand', 'M3 3v18h18M7 14l4-4 4 3 6-7'],
            ['Today\'s Orders', number_format($metrics['orders']), 'Transaksi selesai', 'bg-[#e8fadf] text-[#28c76f]', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['Average Order', money($metrics['aov']), 'Nilai rata-rata', 'bg-[#fff3e8] text-[#ff9f43]', 'M12 8c-2.2 0-4 1.34-4 3s1.8 3 4 3 4 1.34 4 3-1.8 3-4 3m0-12V5m0 14v-2'],
        ] as $card)
            <div class="stat-card">
                <div>
                    <p class="text-[13px] text-muted">{{ $card[0] }}</p>
                    <p class="mt-2 text-[1.65rem] font-semibold leading-none tracking-tight text-heading">{{ $card[1] }}</p>
                    <p class="mt-2 text-xs text-muted">{{ $card[2] }}</p>
                </div>
                <div class="icon-tile {{ $card[3] }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $card[4] }}"/></svg>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <p class="font-semibold text-heading">Operational</p>
                <span class="badge bg-brand-soft text-brand">Live</span>
            </div>
            <div class="mt-4 space-y-3.5 text-sm">
                @foreach ([
                    ['Pesanan berjalan', $metrics['pending_kitchen'], 'bg-orange-400'],
                    ['Pending pickup', $metrics['pending_pickup'], 'bg-brand'],
                ] as $row)
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-ink"><span class="h-2 w-2 rounded-full {{ $row[2] }}"></span>{{ $row[0] }}</span>
                        <span class="font-medium text-heading">{{ $row[1] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card p-5">
            <p class="font-semibold text-heading">Inventory</p>
            <div class="mt-4 space-y-3.5 text-sm">
                @foreach ([
                    ['Low stock', $metrics['low_stock'], 'bg-orange-400'],
                    ['Out of stock', $metrics['out_of_stock'], 'bg-red-500'],
                    ['Today\'s waste', number_format($metrics['today_waste'], 1), 'bg-slate-400'],
                    ['Transfer pending', $metrics['pending_transfers'], 'bg-brand'],
                ] as $row)
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-ink"><span class="h-2 w-2 rounded-full {{ $row[2] }}"></span>{{ $row[0] }}</span>
                        <span class="font-medium text-heading">{{ $row[1] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card p-5">
            <p class="font-semibold text-heading">Production</p>
            <div class="mt-4 space-y-3.5 text-sm">
                @foreach ([
                    ['Today\'s production', number_format($metrics['today_production'], 1), 'bg-emerald-500'],
                    ['Pending', $metrics['pending_production'], 'bg-orange-400'],
                    ['Semi finished SKU', $metrics['semi_finished'], 'bg-sky-500'],
                    ['Yield', number_format($metrics['production_yield'], 1).'%', 'bg-brand'],
                ] as $row)
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-ink"><span class="h-2 w-2 rounded-full {{ $row[2] }}"></span>{{ $row[0] }}</span>
                        <span class="font-medium text-heading">{{ $row[1] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="card p-5">
            <p class="font-semibold text-heading">Sales trend</p>
            <p class="mt-1 text-xs text-muted">14 hari terakhir</p>
            <div class="mt-4 h-64"><canvas id="salesTrend"></canvas></div>
        </div>
        <div class="card p-5">
            <p class="font-semibold text-heading">Sales by outlet</p>
            <p class="mt-1 text-xs text-muted">Perbandingan pendapatan</p>
            <div class="mt-4 h-64"><canvas id="salesOutlet"></canvas></div>
        </div>
        <div class="card p-5">
            <p class="font-semibold text-heading">Sales by category</p>
            <div class="mt-4 h-64"><canvas id="salesCategory"></canvas></div>
        </div>
        <div class="card p-5">
            <p class="font-semibold text-heading">Top products</p>
            <div class="mt-4 h-64"><canvas id="topProducts"></canvas></div>
        </div>
        <div class="card p-5">
            <p class="font-semibold text-heading">Order channel</p>
            <div class="mt-4 h-64"><canvas id="channels"></canvas></div>
        </div>
        <div class="card p-5">
            <p class="font-semibold text-heading">Payment method</p>
            <div class="mt-4 h-64"><canvas id="payments"></canvas></div>
        </div>
    </div>

    @if ($metrics['low_stock_items']->count())
        <div class="card mt-4 p-5">
            <div class="flex items-center justify-between">
                <p class="font-semibold text-heading">Low stock alerts</p>
                <a href="{{ route('inventory.index') }}" class="text-sm font-medium text-brand">Lihat inventory</a>
            </div>
            <div class="mt-4 divide-y divide-slate-100">
                @foreach ($metrics['low_stock_items'] as $product)
                    <div class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <p class="font-medium text-heading">{{ $product->name }}</p>
                            <p class="text-muted">Reorder {{ $product->reorder_level }} {{ $product->unit?->code }}</p>
                        </div>
                        <span class="badge bg-[#fff3e8] text-[#ff9f43]">{{ qty($product->inventories->first()?->quantity ?? 0) }} {{ $product->unit?->code }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const palette = ['#111111', '#6f1715', '#525252', '#8a1c1a', '#A3A3A3', '#591211', '#171717', '#efe7de'];
        const grid = { color: 'rgba(17,17,17,.08)' };
        const make = (id, type, labels, data, extra = {}) => new window.Chart(document.getElementById(id), {
            type,
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: type === 'line' ? 'rgba(111,23,21,.12)' : palette,
                    borderColor: '#6f1715',
                    fill: type === 'line',
                    tension: .4,
                    borderWidth: type === 'line' ? 3 : 0,
                    borderRadius: 8,
                    pointBackgroundColor: '#6f1715',
                    pointRadius: type === 'line' ? 3 : 0,
                }]
            },
            options: {
                plugins: { legend: { display: type !== 'bar' && type !== 'line', labels: { boxWidth: 10, usePointStyle: true } } },
                maintainAspectRatio: false,
                scales: type === 'doughnut' ? {} : { x: { grid: { display: false } }, y: { grid: { color: grid.color }, ticks: { color: '#a5a3ae' } } },
                ...extra
            }
        });
        make('salesTrend', 'line', @json($charts['sales_trend']->pluck('d')), @json($charts['sales_trend']->pluck('total')));
        make('salesOutlet', 'bar', @json($charts['by_outlet']->pluck('name')), @json($charts['by_outlet']->pluck('total')));
        make('salesCategory', 'doughnut', @json($charts['by_category']->pluck('name')), @json($charts['by_category']->pluck('total')));
        make('topProducts', 'bar', @json($charts['top_products']->pluck('name')), @json($charts['top_products']->pluck('qty')), { indexAxis: 'y' });
        make('channels', 'doughnut', @json($charts['channels']->pluck('channel')), @json($charts['channels']->pluck('qty')));
        make('payments', 'doughnut', @json($charts['payments']->pluck('method')), @json($charts['payments']->pluck('total')));
    });
</script>
@endpush
