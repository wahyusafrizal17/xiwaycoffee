<form
    method="GET"
    action="{{ $action }}"
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

    @if ($showOutlet ?? false)
        <div class="min-w-[180px]">
            <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Outlet</label>
            <select class="input !h-10 !rounded-xl text-[13px]" name="outlet_id" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach ($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">&nbsp;</label>
        <button type="submit" class="btn-primary !h-10 !rounded-xl !px-5 text-[13px]">Terapkan</button>
    </div>
</form>

@once
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
@endonce
