@extends('layouts.app')
@section('title', 'Orders')
@section('breadcrumb', 'Front of house')
@section('content')
    @php
        $columnFilters = collect($filters)->only([
            'order_number', 'outlet_id', 'customer', 'table', 'order_type', 'status', 'payment_status', 'cashier',
        ])->filter(fn ($value) => filled($value));
        $hasFilters = $columnFilters->isNotEmpty();
        $periodKeep = collect($filters)->only(['period', 'date', 'month', 'year', 'from', 'to'])->filter(fn ($v) => filled($v));
    @endphp

    <form
        method="GET"
        action="{{ route('orders.index') }}"
        class="mb-5 flex flex-wrap items-end gap-3"
        x-data="ordersPeriodFilter(@js([
            'period' => $period,
            'date' => $range['date'] ?? now()->toDateString(),
            'month' => $range['month'] ?? now()->format('Y-m'),
            'year' => $range['year'] ?? now()->format('Y'),
            'from' => $range['from'] ?? now()->startOfMonth()->toDateString(),
            'to' => $range['to'] ?? now()->toDateString(),
        ]))"
    >
        @foreach ($columnFilters as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach

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

    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Total order</p>
                <p class="stat-value">{{ number_format($stats['total']) }}</p>
                <p class="stat-hint">{{ $range['label'] }}</p>
            </div>
            <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5h6M9 9h6M5 5h.01M5 9h.01M5 13h14M5 17h14M5 21h14"/></svg>
            </span>
        </div>
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Hari ini</p>
                <p class="stat-value">{{ number_format($stats['today']) }}</p>
                <p class="stat-hint">{{ now()->format('d/m/Y') }}</p>
            </div>
            <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
        </div>
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Antrian dapur</p>
                <p class="stat-value">{{ number_format($stats['kitchen']) }}</p>
                <p class="stat-hint">New, proses, dan preparing</p>
            </div>
            <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/></svg>
            </span>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h5 class="card-header-title">Daftar order</h5>
                <p class="card-header-subtitle">{{ $range['label'] }} · filter kolom memuat ulang otomatis.</p>
            </div>
            @if ($hasFilters)
                <div class="card-header-actions">
                    <a href="{{ route('orders.index', $periodKeep->all()) }}" class="btn-ghost">Reset filter</a>
                </div>
            @endif
        </div>

        <form id="order-filters" method="GET" action="{{ route('orders.index') }}">
            @foreach ($periodKeep as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
        </form>
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th class="col-no">No.</th>
                        <th>Order</th>
                        <th>Tanggal</th>
                        <th>Meja</th>
                        <th>Tipe</th>
                        <th>Bayar</th>
                        <th class="text-right">Subtotal</th>
                        <th class="text-right">Diskon</th>
                        <th class="text-right">Total</th>
                        <th class="col-actions"></th>
                    </tr>
                    <tr class="filter-row">
                        <th></th>
                        <th>
                            <input form="order-filters" class="col-filter" type="search" name="order_number" value="{{ $filters['order_number'] ?? '' }}" placeholder="No. order..." onchange="this.form.submit()">
                        </th>
                        <th></th>
                        <th>
                            <input form="order-filters" class="col-filter" type="search" name="table" value="{{ $filters['table'] ?? '' }}" placeholder="Kode..." onchange="this.form.submit()">
                        </th>
                        <th>
                            <select form="order-filters" class="col-filter" name="order_type" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                @foreach (\App\Enums\OrderType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(($filters['order_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <select form="order-filters" class="col-filter" name="payment_status" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                @foreach (\App\Enums\PaymentStatus::cases() as $pay)
                                    <option value="{{ $pay->value }}" @selected(($filters['payment_status'] ?? '') === $pay->value)>{{ $pay->label() }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="col-no">{{ $orders->firstItem() + $loop->index }}</td>
                            <td>
                                <a href="{{ route('orders.show', $order) }}" class="font-semibold hover:underline">{{ $order->order_number }}</a>
                            </td>
                            <td class="text-muted">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $order->table?->code ?? '—' }}</td>
                            <td>
                                <span class="badge-soft">{{ $order->order_type?->label() ?? '—' }}</span>
                            </td>
                            <td><x-status :value="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-status></td>
                            <td class="text-right tabular-nums">{{ money($order->subtotal) }}</td>
                            <td class="text-right tabular-nums {{ $order->discount_amount > 0 ? 'font-medium text-[#ff9f43]' : 'text-muted' }}">{{ money($order->discount_amount) }}</td>
                            <td class="text-right tabular-nums font-semibold">{{ money($order->grand_total) }}</td>
                            <td class="col-actions">
                                <a href="{{ route('orders.show', $order) }}" class="table-action" title="Lihat">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-16 text-center text-sm text-slate-400">Tidak ada order yang cocok dengan filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    function ordersPeriodFilter(initial = {}) {
        return {
            open: false,
            period: initial.period || 'day',
            date: initial.date,
            month: initial.month,
            year: initial.year,
            from: initial.from,
            to: initial.to,
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
</script>
@endpush
