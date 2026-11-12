@extends('layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')
@section('content')
    @php
        $showFinance = auth()->user()->hasPermission('reports.view');
        $showFoodShare = $showFinance || auth()->user()->hasPermission('reports.food');
        $periodLabel = $range['label'] ?? $metrics['label'] ?? '';
        $filterPeriod = $period;
        $tabs = ['cafe' => 'Cafe', 'food' => 'Makanan'];
        $isCafe = $tab === 'cafe';
        $margin = $metrics['ops_sales'] > 0 ? (int) round($metrics['net'] / $metrics['ops_sales'] * 100) : null;
        $opsMonthly = $metrics['ops_label'] !== $periodLabel;
        $mixTotal = max(0.01, $metrics['drinks'] + $metrics['food_cafe']);
        $drinkShare = (int) round($metrics['drinks'] / $mixTotal * 100);
        $foodCups = $charts['top_food']->sum('qty');
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

    {{-- Ringkasan utama --}}
    <section class="mb-5 overflow-hidden rounded-2xl bg-[#161414] text-white shadow-[0_18px_40px_-24px_rgba(0,0,0,.55)]">
        <div class="grid lg:grid-cols-[1.35fr_1fr]">
            <div class="p-6 sm:p-8">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">
                    @if ($isCafe)
                        {{ $showFinance ? 'Omzet cafe' : 'Pendapatan minuman' }}
                    @else
                        Pendapatan makanan
                    @endif
                    · {{ $periodLabel }}
                </p>
                <p class="mt-3 font-sans text-[2.6rem] font-semibold leading-none tracking-tight tabular-nums sm:text-[3.25rem]">
                    {{ money($isCafe ? ($showFinance ? $metrics['sales'] : $metrics['drinks']) : $metrics['food_sales']) }}
                </p>

                @if ($isCafe && $showFinance)
                    <div class="mt-7">
                        <div class="flex h-2 gap-0.5 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-brand" style="width: {{ $drinkShare }}%"></div>
                            <div class="h-full flex-1 rounded-full bg-[#f4c27a]"></div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-4 text-[13px]">
                            <div>
                                <p class="flex items-center gap-2 text-white/60"><span class="h-2 w-2 rounded-full bg-brand"></span>Pendapatan minuman</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">{{ money($metrics['drinks']) }}</p>
                            </div>
                            <div>
                                <p class="flex items-center gap-2 text-white/60"><span class="h-2 w-2 rounded-full bg-[#f4c27a]"></span>Komisi makanan</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">{{ money($metrics['food_cafe']) }}</p>
                            </div>
                        </div>
                        <p class="mt-3 text-[12px] text-white/40">Omzet cafe = semua pendapatan − setoran makanan.</p>
                    </div>
                @else
                    <p class="mt-4 text-[13px] text-white/50">Setelah diskon pada periode ini</p>
                @endif
            </div>

            <div class="grid border-t border-white/10 lg:border-l lg:border-t-0">
                @if ($isCafe && $showFinance)
                    <div class="p-6 sm:p-8">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">Omzet bersih · {{ $metrics['ops_label'] }}</p>
                        <p class="mt-3 text-[2rem] font-semibold leading-none tracking-tight tabular-nums {{ $metrics['net'] < 0 ? 'text-[#ff8a8a]' : 'text-[#7ee2a8]' }}">{{ money($metrics['net']) }}</p>
                        @if ($margin !== null)
                            <span class="mt-3 inline-flex rounded-full px-2.5 py-1 text-[12px] font-semibold {{ $metrics['net'] < 0 ? 'bg-[#ff8a8a]/15 text-[#ff8a8a]' : 'bg-[#7ee2a8]/15 text-[#7ee2a8]' }}">Margin {{ $margin }}%</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 border-t border-white/10">
                    <div class="p-6 sm:px-8">
                        <p class="text-[13px] text-white/60">Total operasional</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums">{{ money($metrics['operational']) }}</p>
                        <p class="mt-1 text-[12px] text-white/40">BOP + gaji + sewa · {{ $metrics['ops_label'] }}</p>
                        @if ($opsMonthly)
                            <p class="mt-1 text-[12px] text-white/40">Omzet cafe {{ $metrics['ops_label'] }} {{ money($metrics['ops_sales']) }}</p>
                        @endif
                    </div>
                    <a href="{{ route('bank.index') }}" class="border-l border-white/10 p-6 transition hover:bg-white/5 sm:px-8">
                        <p class="text-[13px] text-white/60">Saldo rekening</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums {{ $metrics['bank_balance'] < 0 ? 'text-[#ff8a8a]' : 'text-[#7ee2a8]' }}">{{ money($metrics['bank_balance']) }}</p>
                        <p class="mt-1 text-[12px] text-white/40">Saldo saat ini · lihat rekening</p>
                    </a>
                    </div>
                @elseif ($isCafe)
                    <div class="p-6 sm:p-8">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">Target minuman</p>
                        <p class="mt-3 text-[2rem] font-semibold leading-none tabular-nums {{ $metrics['drink_target_percent'] >= 100 ? 'text-[#7ee2a8]' : '' }}">{{ $metrics['drink_target_percent'] }}%</p>
                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full {{ $metrics['drink_target_percent'] >= 100 ? 'bg-[#7ee2a8]' : 'bg-brand' }}" style="width: {{ min(100, (int) $metrics['drink_target_percent']) }}%"></div>
                        </div>
                        <p class="mt-3 text-[12px] text-white/50">{{ qty($metrics['drink_cups']) }} dari {{ qty($metrics['drink_target']) }} cup · target {{ $metrics['drink_daily_target'] }} cup/hari</p>
                    </div>
                @elseif ($showFoodShare)
                    <div class="p-6 sm:p-8">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">Pendapatan cafe dari makanan</p>
                        <p class="mt-3 text-[2rem] font-semibold leading-none tracking-tight tabular-nums text-[#f4c27a]">{{ money($metrics['food_cafe']) }}</p>
                        <p class="mt-3 text-[12px] text-white/50">Komisi cafe</p>
                    </div>
                @else
                    <div class="p-6 sm:p-8">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">Makanan terjual</p>
                        <p class="mt-3 text-[2rem] font-semibold leading-none tabular-nums">{{ qty($foodCups) }}</p>
                        <p class="mt-3 text-[12px] text-white/50">Porsi pada periode ini</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Angka pendukung --}}
    @if ($isCafe)
        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-5">
                <p class="text-[12px] font-medium text-muted">Order lunas</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-heading">{{ qty($metrics['orders']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-5">
                <p class="text-[12px] font-medium text-muted">Rata-rata per order</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-heading">{{ money($metrics['aov']) }}</p>
            </div>
            @if ($showFinance)
                <div class="rounded-2xl border border-[#ebe4dc] bg-white p-5">
                    <p class="text-[12px] font-medium text-muted">Target minuman</p>
                    <div class="mt-2 flex items-baseline justify-between gap-2">
                        <p class="text-2xl font-semibold tracking-tight tabular-nums {{ $metrics['drink_target_percent'] >= 100 ? 'text-[#166534]' : 'text-heading' }}">{{ $metrics['drink_target_percent'] }}%</p>
                        <p class="text-[12px] tabular-nums text-muted">{{ qty($metrics['drink_cups']) }}/{{ qty($metrics['drink_target']) }} cup</p>
                    </div>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-brand-soft">
                        <div class="h-full rounded-full {{ $metrics['drink_target_percent'] >= 100 ? 'bg-[#166534]' : 'bg-brand' }}" style="width: {{ min(100, (int) $metrics['drink_target_percent']) }}%"></div>
                    </div>
                    <p class="mt-2 text-[11px] text-muted">target {{ $metrics['drink_daily_target'] }} cup/hari</p>
                </div>
            @else
                <div class="rounded-2xl border border-[#ebe4dc] bg-white p-5">
                    <p class="text-[12px] font-medium text-muted">Cup terjual</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-heading">{{ qty($metrics['drink_cups']) }}</p>
                </div>
            @endif
            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-5">
                <p class="text-[12px] font-medium text-muted">{{ $showFinance ? 'Omzet cafe rata-rata/hari' : 'Pendapatan minuman rata-rata/hari' }}</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-heading">{{ money($showFinance ? $metrics['avg_sales'] : $metrics['avg_drinks']) }}</p>
                <p class="mt-1 text-[11px] text-muted">{{ $metrics['avg_days'] }} hari · {{ \Illuminate\Support\Carbon::parse($metrics['avg_from'])->locale('id')->translatedFormat('d M') }}–{{ \Illuminate\Support\Carbon::parse($metrics['avg_to'])->locale('id')->translatedFormat('d M Y') }}</p>
            </div>
            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-5">
                <p class="text-[12px] font-medium text-muted">Pendapatan kotor</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-heading">{{ money($metrics['gross']) }}</p>
                <p class="mt-1 text-[11px] text-muted">Semua order lunas, termasuk makanan</p>
            </div>
        </div>
    @elseif ($showFoodShare)
        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-5">
                <p class="text-[12px] font-medium text-muted">Setoran makanan</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-heading">{{ money($metrics['food_setoran']) }}</p>
                <p class="mt-1 text-[11px] text-muted">Sudah disetor {{ money($metrics['food_setoran_paid']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-5">
                <p class="text-[12px] font-medium text-muted">Sisa setoran</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums {{ $metrics['food_setoran_remaining'] > 0 ? 'text-brand' : 'text-heading' }}">{{ money($metrics['food_setoran_remaining']) }}</p>
                <p class="mt-1 text-[11px] text-muted">Belum disetor pada periode ini</p>
            </div>
            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-5">
                <p class="text-[12px] font-medium text-muted">Makanan terjual</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-heading">{{ qty($foodCups) }}</p>
                <p class="mt-1 text-[11px] text-muted">Porsi pada periode ini</p>
            </div>
        </div>
    @endif

    {{-- Tren + operasional --}}
    <div class="mb-5 grid items-start gap-5 {{ $isCafe && $showFinance ? 'xl:grid-cols-3' : '' }}">
        <div class="space-y-5 {{ $isCafe && $showFinance ? 'xl:col-span-2' : '' }}">
            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-6">
                <div class="mb-5">
                    <h2 class="font-serif text-xl font-semibold text-heading">Tren penjualan {{ $isCafe ? 'minuman' : 'makanan' }}</h2>
                    <p class="mt-1 text-[12px] text-muted">14 hari terakhir · total {{ money($charts['sales_trend']->sum('total')) }}</p>
                </div>
                <div class="h-72"><canvas id="salesTrend" role="img" aria-label="Tren penjualan 14 hari terakhir"></canvas></div>
            </div>

            {{-- Jam kedatangan --}}
            @if ($isCafe)
                @php $hours = $charts['arrival_hours']; @endphp
                <div class="rounded-2xl border border-[#ebe4dc] bg-white p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="font-serif text-xl font-semibold text-heading">Jam kedatangan</h2>
                            <p class="mt-1 text-[12px] text-muted">Rata-rata order lunas per jam per hari · {{ $hours['days'] }} hari, {{ strtolower($periodLabel) }}.</p>
                        </div>
                        @if ($hours['peak'])
                            <div class="flex flex-wrap gap-3">
                                <div class="rounded-xl bg-brand-soft px-4 py-2.5">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-brand">Paling ramai</p>
                                    <p class="mt-0.5 text-[15px] font-semibold tabular-nums text-heading">{{ $hours['peak']['label'] }}–{{ sprintf('%02d.00', $hours['peak']['hour'] + 1) }} <span class="text-[12px] font-normal text-muted">· {{ number_format($hours['peak']['avg'], 1, ',', '.') }} order/hari</span></p>
                                </div>
                                @if ($hours['quiet'])
                                    <div class="rounded-xl bg-[#f3eee8] px-4 py-2.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Paling sepi</p>
                                        <p class="mt-0.5 text-[15px] font-semibold tabular-nums text-heading">{{ $hours['quiet']['label'] }}–{{ sprintf('%02d.00', $hours['quiet']['hour'] + 1) }} <span class="text-[12px] font-normal text-muted">· {{ number_format($hours['quiet']['avg'], 1, ',', '.') }} order/hari</span></p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                    @if ($hours['peak'])
                        <div class="mt-5 h-60"><canvas id="arrivalHours" role="img" aria-label="Rata-rata order per jam"></canvas></div>
                    @else
                        <p class="py-12 text-center text-sm text-muted">Belum ada order di periode ini.</p>
                    @endif
                </div>
            @endif
        </div>

        @if ($isCafe && $showFinance)
            <div class="flex flex-col rounded-2xl border border-[#ebe4dc] bg-white p-6">
                <h2 class="font-serif text-xl font-semibold text-heading">Operasional cafe</h2>
                <p class="mt-1 text-[12px] text-muted">Rincian operasional {{ $metrics['ops_label'] }} per kategori, terbesar di atas.</p>
                <div class="mt-5 flex-1 space-y-4">
                    @forelse ($metrics['expense_groups'] as $group)
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-[13px]">
                                <p class="font-medium text-heading">{{ $group['label'] }}</p>
                                <p class="font-semibold tabular-nums text-heading">{{ money($group['amount']) }}</p>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[#f3eee8]">
                                <div class="h-full rounded-full bg-heading" style="width: {{ $group['percent'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-10 text-center text-sm text-muted">Belum ada biaya di periode ini.</p>
                    @endforelse
                </div>
                <div class="mt-5 flex items-baseline justify-between border-t border-[#f0ebe4] pt-4">
                    <p class="text-[13px] font-semibold text-heading">Total operasional</p>
                    <p class="text-lg font-semibold tabular-nums text-heading">{{ money($metrics['operational']) }}</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Terlaris + bagi hasil --}}
    <div class="mb-5 grid items-start gap-5 {{ $isCafe && $showFinance ? 'xl:grid-cols-3' : '' }}">
        <div class="overflow-hidden rounded-2xl border border-[#ebe4dc] bg-white {{ $isCafe && $showFinance ? 'xl:col-span-2' : '' }}">
            <div class="px-6 pt-6">
                <h2 class="font-serif text-xl font-semibold text-heading">{{ $isCafe ? 'Minuman terlaris' : 'Makanan terlaris' }}</h2>
                <p class="mt-1 text-[12px] text-muted">Semua terjual {{ strtolower($periodLabel) }}. Omzet setelah diskon.@if ($showFoodShare) Profit = omzet − HPP.@endif</p>
            </div>
            @php $rows = $isCafe ? $charts['top_drinks'] : $charts['top_food']; $topTotal = max(0.01, (float) $rows->max('total')); @endphp
            <div class="table-wrap mt-4 max-h-[520px] overflow-y-auto">
                <table class="w-full text-[13px]">
                    <thead class="sticky top-0 bg-white text-[11px] uppercase tracking-[0.08em] text-muted">
                        <tr class="border-b border-[#f0ebe4]">
                            <th class="w-10 px-6 py-3 text-left font-semibold">#</th>
                            <th class="py-3 text-left font-semibold">Produk</th>
                            <th class="py-3 pl-3 text-right font-semibold">Qty</th>
                            <th class="py-3 pl-3 text-right font-semibold {{ $showFoodShare ? '' : 'pr-6' }}">Omzet</th>
                            @if ($showFoodShare)
                                <th class="py-3 pl-3 pr-6 text-right font-semibold">Profit</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $i => $row)
                            <tr class="border-b border-[#f6f2ee] last:border-0">
                                <td class="px-6 py-3 tabular-nums text-muted">{{ $i + 1 }}</td>
                                <td class="py-3">
                                    <p class="font-medium text-heading">{{ $row->name }}</p>
                                    <div class="mt-1.5 h-1 max-w-[220px] overflow-hidden rounded-full bg-[#f3eee8]">
                                        <div class="h-full rounded-full bg-brand" style="width: {{ (int) round((float) $row->total / $topTotal * 100) }}%"></div>
                                    </div>
                                </td>
                                <td class="py-3 pl-3 text-right tabular-nums">{{ number_format((float) $row->qty) }}</td>
                                <td class="py-3 pl-3 text-right font-semibold tabular-nums text-heading {{ $showFoodShare ? '' : 'pr-6' }}">{{ money($row->total) }}</td>
                                @if ($showFoodShare)
                                    <td class="py-3 pl-3 pr-6 text-right tabular-nums {{ (float) $row->profit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ money($row->profit) }}</td>
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

        @if ($isCafe && $showFinance)
            <div class="flex flex-col rounded-2xl border border-[#ebe4dc] bg-white p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-serif text-xl font-semibold text-heading">Bagi hasil</h2>
                        <p class="mt-1 text-[12px] text-muted">Dari omzet cafe − charge − BOP {{ money($metrics['share_base']) }}.</p>
                    </div>
                    <a href="{{ route('reports.profit', ['period' => $period]) }}" class="text-[12px] font-medium text-brand hover:underline">Detail</a>
                </div>
                <div class="mt-4 divide-y divide-[#f0ebe4]">
                    @forelse ($metrics['shares'] as $share)
                        <div class="flex items-center justify-between gap-4 py-3.5">
                            <div class="min-w-0">
                                <p class="font-medium text-heading">{{ $share['name'] }}</p>
                                <p class="text-[12px] text-muted">{{ number_format($share['percent'], 1, ',', '.') }}% · modal {{ money($share['capital']) }}</p>
                            </div>
                            <p class="font-semibold tabular-nums text-heading">{{ money($share['amount']) }}</p>
                        </div>
                    @empty
                        <p class="py-10 text-center text-sm text-muted">Belum ada data investor.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    {{-- Pembayaran + sumber --}}
    @if ($isCafe)
        <div class="mb-5 grid gap-5 xl:grid-cols-2">
            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-6">
                <h2 class="font-serif text-xl font-semibold text-heading">Pembayaran</h2>
                <p class="mt-1 text-[12px] text-muted">{{ $periodLabel }}.</p>
                @php $payTotal = max(0.01, $metrics['payment_total']); @endphp
                <div class="mt-5 flex h-2 gap-0.5 overflow-hidden rounded-full bg-[#f3eee8]">
                    <div class="h-full rounded-full bg-heading" style="width: {{ (int) round($metrics['cash'] / $payTotal * 100) }}%"></div>
                    <div class="h-full rounded-full bg-brand" style="width: {{ (int) round($metrics['qris'] / $payTotal * 100) }}%"></div>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-4">
                    <div>
                        <p class="flex items-center gap-2 text-[12px] text-muted"><span class="h-2 w-2 rounded-full bg-heading"></span>Cash</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-heading">{{ money($metrics['cash']) }}</p>
                    </div>
                    <div>
                        <p class="flex items-center gap-2 text-[12px] text-muted"><span class="h-2 w-2 rounded-full bg-brand"></span>QRIS</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-heading">{{ money($metrics['qris']) }}</p>
                    </div>
                    <div>
                        <p class="text-[12px] text-muted">Total pembayaran</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-heading">{{ money($metrics['payment_total']) }}</p>
                    </div>
                </div>
                @if (! $metrics['payments_match'])
                    <div class="mt-5 rounded-xl border border-[#f5d0a9] bg-[#fff7ed] px-4 py-3 text-[13px] text-[#9a3412]">
                        Perlu rekonsiliasi. Total pembayaran {{ money($metrics['payment_total']) }}, pendapatan kotor {{ money($metrics['gross']) }}, selisih {{ money($metrics['payment_gap']) }}.
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-6">
                <h2 class="font-serif text-xl font-semibold text-heading">Sumber kedatangan</h2>
                <p class="mt-1 text-[12px] text-muted">Order lunas {{ strtolower($periodLabel) }}. Yang paling banyak di atas.</p>
                <div class="mt-5 space-y-4">
                    @forelse ($charts['arrival_sources'] as $row)
                        <div>
                            <div class="flex items-baseline justify-between gap-4 text-[13px]">
                                <p class="font-medium text-heading">{{ $row['label'] }}</p>
                                <p class="tabular-nums text-muted">{{ $row['qty'] }} order · {{ $row['percent'] }}%</p>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[#f3eee8]">
                                <div class="h-full rounded-full bg-brand" style="width: {{ $row['percent'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-10 text-center text-sm text-muted">Belum ada data sumber kedatangan.</p>
                    @endforelse
                </div>
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
        const hoursEl = document.getElementById('arrivalHours');
        if (hoursEl && window.Chart) {
            const hours = @json($charts['arrival_hours']);
            const peak = hours.peak?.hour;
            const quiet = hours.quiet?.hour;
            new window.Chart(hoursEl, {
                type: 'bar',
                data: {
                    labels: hours.rows.map((row) => row.label),
                    datasets: [{
                        data: hours.rows.map((row) => row.avg),
                        backgroundColor: hours.rows.map((row) => row.hour === peak ? '#e8192c' : (row.hour === quiet ? '#cfc6bc' : '#f1a0a8')),
                        borderRadius: 4,
                        maxBarThickness: 36,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#161414',
                            padding: 12,
                            cornerRadius: 10,
                            displayColors: false,
                            callbacks: {
                                title: (items) => items[0].label + '–' + String(hours.rows[items[0].dataIndex].hour + 1).padStart(2, '0') + '.00',
                                label: (item) => item.parsed.y.toLocaleString('id-ID') + ' order/hari',
                            },
                        },
                    },
                    scales: {
                        x: { grid: { display: false }, border: { display: false }, ticks: { color: '#8a8580', font: { size: 11 } } },
                        y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { color: '#8a8580', font: { size: 11 }, maxTicksLimit: 5 } },
                    },
                },
            });
        }

        const el = document.getElementById('salesTrend');
        if (!el || !window.Chart) return;

        const rupiah = (v) => 'Rp ' + Math.round(v).toLocaleString('id-ID');
        const compact = (v) => v >= 1e6 ? (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt'
            : v >= 1e3 ? Math.round(v / 1e3) + ' rb' : String(v);
        const ctx = el.getContext('2d');
        const fill = ctx.createLinearGradient(0, 0, 0, el.parentElement.clientHeight || 288);
        fill.addColorStop(0, 'rgba(232,25,44,.16)');
        fill.addColorStop(1, 'rgba(232,25,44,0)');

        new window.Chart(el, {
            type: 'line',
            data: {
                labels: @json($charts['sales_trend']->pluck('d')->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->locale('id')->translatedFormat('d M'))),
                datasets: [{
                    data: @json($charts['sales_trend']->pluck('total')->map(fn ($v) => (float) $v)),
                    backgroundColor: fill,
                    borderColor: '#e8192c',
                    fill: true,
                    tension: .35,
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#e8192c',
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                }],
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#161414',
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false,
                        titleFont: { weight: '500' },
                        bodyFont: { size: 14, weight: '600' },
                        callbacks: { label: (item) => rupiah(item.parsed.y) },
                    },
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#8a8580', font: { size: 11 } } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: 'rgba(0,0,0,.05)' },
                        ticks: { color: '#8a8580', font: { size: 11 }, maxTicksLimit: 5, callback: (v) => compact(v) },
                    },
                },
            },
        });
    });
</script>
@endpush
