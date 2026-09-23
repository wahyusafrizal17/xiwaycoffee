@extends('layouts.app')
@section('title', 'Laba Rugi')
@section('breadcrumb', 'Keuangan')
@section('content')
    @php
        $s = $summary;
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Keuangan</p>
            <h1 class="mt-1 font-serif text-[1.75rem] font-semibold leading-none text-heading">Laba Rugi</h1>
            <p class="mt-2 max-w-xl text-[13px] text-muted">Ringkasan kinerja keuangan outlet · {{ $s['label'] }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.laba-rugi') }}" class="mb-5 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Bulan</label>
            <input class="input !h-10 !rounded-xl" type="month" name="month" value="{{ $filters['month'] }}" required>
        </div>
        @if ($canSwitchOutlet)
            <div>
                <label class="label">Outlet</label>
                <select class="input !h-10 !min-w-[180px] !rounded-xl" name="outlet_id">
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected((int) $outletId === $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <input type="hidden" name="outlet_id" value="{{ $outletId }}">
        @endif
        <button type="submit" class="btn-primary !h-10 !rounded-xl !px-5 text-[13px]">Terapkan</button>
    </form>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="stat-card !items-start">
            <div class="min-w-0 flex-1">
                <p class="stat-kicker">Penjualan</p>
                <p class="stat-value">{{ money($s['sales']) }}</p>
            </div>
        </div>
        <div class="stat-card !items-start">
            <div class="min-w-0 flex-1">
                <p class="stat-kicker">HPP</p>
                <p class="stat-value">{{ money($s['hpp']) }}</p>
                <p class="stat-hint">Dari cost produk × qty terjual</p>
            </div>
        </div>
        <div class="stat-card !items-start">
            <div class="min-w-0 flex-1">
                <p class="stat-kicker">Laba kotor</p>
                <p class="stat-value">{{ money($s['gross']) }}</p>
                <p class="stat-hint">Margin {{ number_format($s['gross_margin'], 1, ',', '.') }}%</p>
            </div>
        </div>
        <div class="stat-card !items-start">
            <div class="min-w-0 flex-1">
                <p class="stat-kicker">BOP</p>
                <p class="stat-value">{{ money($s['bop']) }}</p>
            </div>
        </div>
        <div class="stat-card !items-start">
            <div class="min-w-0 flex-1">
                <p class="stat-kicker">Laba bersih</p>
                <p class="stat-value {{ $s['net'] < 0 ? 'text-brand' : '' }}">{{ money($s['net']) }}</p>
                <p class="stat-hint">{{ $s['status'] }} · Net margin {{ number_format($s['net_margin'], 1, ',', '.') }}%</p>
            </div>
        </div>
    </div>

    <div class="mb-5 grid gap-4 xl:grid-cols-[1.1fr_1fr]">
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Breakdown</h5>
                    <p class="card-header-subtitle">{{ $s['label'] }}</p>
                </div>
            </div>
            <div class="divide-y divide-[#f0ebe4] px-5 py-1 text-sm">
                <div class="py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">Pendapatan</p>
                    <div class="mt-2 flex justify-between gap-4 py-1">
                        <span>Penjualan</span>
                        <span class="tabular-nums font-medium">{{ money($s['sales']) }}</span>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-[#f0ebe4] py-2 font-semibold text-heading">
                        <span>Total pendapatan</span>
                        <span class="tabular-nums">{{ money($s['sales']) }}</span>
                    </div>
                </div>

                <div class="py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">HPP</p>
                    <div class="mt-2 flex justify-between gap-4 py-1">
                        <span>Bahan Baku (COGS)</span>
                        <span class="tabular-nums font-medium">{{ money($s['hpp']) }}</span>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-[#f0ebe4] py-2 font-semibold text-heading">
                        <span>Total HPP</span>
                        <span class="tabular-nums">{{ money($s['hpp']) }}</span>
                    </div>
                </div>

                <div class="flex justify-between gap-4 py-3.5 font-semibold text-heading">
                    <span>Laba kotor</span>
                    <span class="tabular-nums">{{ money($s['gross']) }}</span>
                </div>

                <div class="py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">Biaya operasional</p>
                    <div class="mt-2 space-y-1">
                        @forelse ($s['bop_rows'] as $row)
                            <div class="flex justify-between gap-4 py-1">
                                <span>{{ $row['label'] }}</span>
                                <span class="tabular-nums font-medium">{{ money($row['total']) }}</span>
                            </div>
                        @empty
                            <p class="py-2 text-muted">Belum ada BOP pada periode ini.</p>
                        @endforelse
                    </div>
                    <div class="mt-1 flex justify-between gap-4 border-t border-[#f0ebe4] py-2 font-semibold text-heading">
                        <span>Total BOP</span>
                        <span class="tabular-nums">{{ money($s['bop']) }}</span>
                    </div>
                </div>

                <div class="flex justify-between gap-4 py-3.5 font-semibold text-heading">
                    <span>Laba bersih <span class="ml-2 rounded-full bg-[#f3f3f3] px-2 py-0.5 text-[11px] font-medium text-muted">{{ $s['status'] }}</span></span>
                    <span class="tabular-nums {{ $s['net'] < 0 ? 'text-brand' : 'text-[#166534]' }}">{{ money($s['net']) }}</span>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden p-5">
            <div class="mb-4">
                <h2 class="font-serif text-xl font-semibold text-heading">Ringkasan visual</h2>
                <p class="mt-1 text-[12px] text-muted">Penjualan, HPP, BOP, laba bersih</p>
            </div>
            <div class="h-72"><canvas id="labaRugiChart"></canvas></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('labaRugiChart');
        if (!el || !window.Chart) return;
        new window.Chart(el, {
            type: 'bar',
            data: {
                labels: ['Penjualan', 'HPP', 'BOP', 'Laba bersih'],
                datasets: [{
                    data: [
                        {{ (float) $s['sales'] }},
                        {{ (float) $s['hpp'] }},
                        {{ (float) $s['bop'] }},
                        {{ (float) $s['net'] }},
                    ],
                    backgroundColor: ['#166534', '#c2410c', '#a16207', '#6f1715'],
                    borderRadius: 6,
                    borderWidth: 0,
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
