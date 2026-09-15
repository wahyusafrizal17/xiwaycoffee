@extends('layouts.app')
@section('title', 'Promo Performance')
@section('breadcrumb', 'Reports')
@section('content')
    @php
        $period = $filters['period'] ?? null;
        $fromLabel = filled($filters['from'] ?? null) ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') : '—';
        $toLabel = filled($filters['to'] ?? null) ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d/m/Y') : '—';
        $periodKeep = collect($filters)->only(['outlet_id', 'discount', 'bundle'])->filter(fn ($value) => filled($value))->all();
        $chip = 'inline-flex items-center rounded-lg px-3 py-1.5 text-[13px] font-medium';
        $chipOn = 'bg-brand text-white';
        $chipOff = 'bg-[#f5f5f5] text-muted hover:bg-[#ececec]';
        $tableClass = ($exporting ?? false) ? 'data-table' : 'list-table';
    @endphp
    @unless ($exporting ?? false)
        @include('reports._nav')
    @endunless

    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Pemakaian diskon</p>
                <p class="stat-value">{{ number_format($stats['discount_usage'] ?? 0) }}</p>
                <p class="stat-hint">{{ $fromLabel }} – {{ $toLabel }}</p>
            </div>
            <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A2 2 0 013 9V4a1 1 0 011-1h3z"/></svg>
            </span>
        </div>
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Nilai diskon</p>
                <p class="stat-value">{{ money($stats['discount_total'] ?? 0) }}</p>
                <p class="stat-hint">Total potongan order dibayar</p>
            </div>
            <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-12V5m0 14v-2"/></svg>
            </span>
        </div>
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Penjualan bundle</p>
                <p class="stat-value">{{ money($stats['bundle_sales'] ?? 0) }}</p>
                <p class="stat-hint">Omzet item paket</p>
            </div>
            <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 12v10H4V12M2 7h20v5H2V7zM12 22V7M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>
            </span>
        </div>
    </div>

    @unless ($exporting ?? false)
        <div class="card mb-5 overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Laporan promo</h5>
                    <p class="card-header-subtitle">Performa diskon dan bundle pada {{ $fromLabel }} – {{ $toLabel }}.</p>
                </div>
                <div class="card-header-actions">
                    @can('reports.export')
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn-excel">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM8 6v12M4 10h16M4 14h16"/></svg>
                            Export Excel
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn-pdf">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg>
                            Export PDF
                        </a>
                    @endcan
                </div>
            </div>

            <form id="promo-filters" method="GET" action="{{ route('reports.promo') }}">
                <input type="hidden" name="period" value="{{ $period }}">
            </form>
            <div class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3">
                <a href="{{ route('reports.promo', $periodKeep + ['period' => 'today']) }}" class="{{ $chip }} {{ $period === 'today' ? $chipOn : $chipOff }}">Hari ini</a>
                <a href="{{ route('reports.promo', $periodKeep + ['period' => 'week']) }}" class="{{ $chip }} {{ $period === 'week' ? $chipOn : $chipOff }}">Mingguan</a>
                <a href="{{ route('reports.promo', $periodKeep + ['period' => 'month']) }}" class="{{ $chip }} {{ $period === 'month' ? $chipOn : $chipOff }}">Bulanan</a>
                <input form="promo-filters" class="input !w-40" type="date" name="from" value="{{ $filters['from'] ?? '' }}" onchange="this.form.period.value=''; this.form.submit()">
                <input form="promo-filters" class="input !w-40" type="date" name="to" value="{{ $filters['to'] ?? '' }}" onchange="this.form.period.value=''; this.form.submit()">
                <select form="promo-filters" class="input !w-44" name="outlet_id" onchange="this.form.submit()">
                    <option value="">Semua outlet</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endunless

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="{{ ($exporting ?? false) ? '' : 'card overflow-hidden' }}">
            @unless ($exporting ?? false)
                <div class="card-header !border-b-0 !pb-0">
                    <div>
                        <h5 class="card-header-title">Diskon</h5>
                        <p class="card-header-subtitle">Program diskon yang dipakai pada order dibayar.</p>
                    </div>
                </div>
            @else
                <p class="mb-3 text-sm font-semibold">Diskon</p>
            @endunless
            <div class="table-wrap">
                <table class="{{ $tableClass }}">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Program</th>
                            <th>Pakai</th>
                            <th>Diskon</th>
                            <th>Penjualan</th>
                        </tr>
                        @unless ($exporting ?? false)
                            <tr class="filter-row">
                                <th></th>
                                <th>
                                    <input form="promo-filters" class="col-filter" type="search" name="discount" value="{{ $filters['discount'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                                </th>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                        @endunless
                    </thead>
                    <tbody>
                        @forelse ($discounts as $row)
                            <tr>
                                <td class="col-no">{{ $loop->iteration }}</td>
                                <td>
                                    @unless ($exporting ?? false)
                                        <a href="{{ route('marketing.discounts', ['name' => $row->name]) }}" class="font-semibold hover:underline">{{ $row->name }}</a>
                                    @else
                                        <span class="font-medium">{{ $row->name }}</span>
                                    @endunless
                                </td>
                                <td>{{ number_format($row->usage_count) }}</td>
                                <td class="font-semibold">{{ money($row->discount_total) }}</td>
                                <td>{{ money($row->sales_total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-16 text-center text-sm text-slate-400">Belum ada pemakaian diskon yang cocok dengan filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="{{ ($exporting ?? false) ? '' : 'card overflow-hidden' }}">
            @unless ($exporting ?? false)
                <div class="card-header !border-b-0 !pb-0">
                    <div>
                        <h5 class="card-header-title">Bundle</h5>
                        <p class="card-header-subtitle">Paket bundle yang terjual pada order dibayar.</p>
                    </div>
                </div>
            @else
                <p class="mb-3 text-sm font-semibold">Bundle</p>
            @endunless
            <div class="table-wrap">
                <table class="{{ $tableClass }}">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Paket</th>
                            <th>Qty</th>
                            <th>Penjualan</th>
                        </tr>
                        @unless ($exporting ?? false)
                            <tr class="filter-row">
                                <th></th>
                                <th>
                                    <input form="promo-filters" class="col-filter" type="search" name="bundle" value="{{ $filters['bundle'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                                </th>
                                <th></th>
                                <th></th>
                            </tr>
                        @endunless
                    </thead>
                    <tbody>
                        @forelse ($bundles as $row)
                            <tr>
                                <td class="col-no">{{ $loop->iteration }}</td>
                                <td>
                                    @unless ($exporting ?? false)
                                        <a href="{{ route('marketing.bundles', ['name' => $row->name]) }}" class="font-semibold hover:underline">{{ $row->name }}</a>
                                    @else
                                        <span class="font-medium">{{ $row->name }}</span>
                                    @endunless
                                </td>
                                <td>{{ number_format($row->qty, 0) }}</td>
                                <td class="font-semibold">{{ money($row->sales_total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-16 text-center text-sm text-slate-400">Belum ada penjualan bundle yang cocok dengan filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
