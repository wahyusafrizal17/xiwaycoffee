@extends('layouts.app')
@section('title', 'Category Sales')
@section('breadcrumb', 'Reports')
@section('content')
    @php
        $period = $filters['period'] ?? null;
        $fromLabel = filled($filters['from'] ?? null) ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') : '—';
        $toLabel = filled($filters['to'] ?? null) ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d/m/Y') : '—';
        $periodKeep = collect($filters)->only(['outlet_id', 'name', 'search'])->filter(fn ($value) => filled($value))->all();
        $chip = 'inline-flex items-center rounded-lg px-3 py-1.5 text-[13px] font-medium';
        $chipOn = 'bg-brand text-white';
        $chipOff = 'bg-[#f5f5f5] text-muted hover:bg-[#ececec]';
    @endphp
    @unless ($exporting ?? false)
        @include('reports._nav')
    @endunless

    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Total omzet</p>
                <p class="stat-value">{{ money($stats['total'] ?? 0) }}</p>
                <p class="stat-hint">{{ $fromLabel }} – {{ $toLabel }}</p>
            </div>
            <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 17l6-6 4 4 8-8M14 7h7v7"/></svg>
            </span>
        </div>
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Qty terjual</p>
                <p class="stat-value">{{ number_format($stats['qty'] ?? 0, 0) }}</p>
                <p class="stat-hint">Semua item order dibayar</p>
            </div>
            <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h7v5H4zM13 6h7v5h-7zM4 13h7v5H4zM13 13h7v5h-7z"/></svg>
            </span>
        </div>
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Kategori terjual</p>
                <p class="stat-value">{{ number_format($stats['categories'] ?? 0) }}</p>
                <p class="stat-hint">Grup unik</p>
            </div>
            <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM8 6v12M4 10h16M4 14h16"/></svg>
            </span>
        </div>
    </div>

    <div class="{{ ($exporting ?? false) ? '' : 'card overflow-hidden' }}">
        @unless ($exporting ?? false)
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Laporan penjualan kategori</h5>
                    <p class="card-header-subtitle">Agregasi item order dibayar pada {{ $fromLabel }} – {{ $toLabel }}.</p>
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

            <form id="category-sales-filters" method="GET" action="{{ route('reports.categories') }}">
                <input type="hidden" name="period" value="{{ $period }}">
            </form>
            <div class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3">
                <a href="{{ route('reports.categories', $periodKeep + ['period' => 'today']) }}" class="{{ $chip }} {{ $period === 'today' ? $chipOn : $chipOff }}">Hari ini</a>
                <a href="{{ route('reports.categories', $periodKeep + ['period' => 'week']) }}" class="{{ $chip }} {{ $period === 'week' ? $chipOn : $chipOff }}">Mingguan</a>
                <a href="{{ route('reports.categories', $periodKeep + ['period' => 'month']) }}" class="{{ $chip }} {{ $period === 'month' ? $chipOn : $chipOff }}">Bulanan</a>
                <input form="category-sales-filters" class="input !w-40" type="date" name="from" value="{{ $filters['from'] ?? '' }}" onchange="this.form.period.value=''; this.form.submit()">
                <input form="category-sales-filters" class="input !w-40" type="date" name="to" value="{{ $filters['to'] ?? '' }}" onchange="this.form.period.value=''; this.form.submit()">
                <select form="category-sales-filters" class="input !w-44" name="outlet_id" onchange="this.form.submit()">
                    <option value="">Semua outlet</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
        @endunless

        <div class="table-wrap">
            <table class="{{ ($exporting ?? false) ? 'data-table' : 'list-table' }}">
                <thead>
                    <tr>
                        <th class="col-no">No.</th>
                        <th>Kategori</th>
                        <th>Qty</th>
                        <th>Total</th>
                        @unless ($exporting ?? false)
                            <th class="col-actions"></th>
                        @endunless
                    </tr>
                    @unless ($exporting ?? false)
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="category-sales-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? $filters['search'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    @endunless
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="col-no">{{ ($exporting ?? false) ? $loop->iteration : $rows->firstItem() + $loop->index }}</td>
                            <td>
                                @unless ($exporting ?? false)
                                    @if ($row->category_id)
                                        <a href="{{ route('categories.index', ['modal' => 'view', 'category' => $row->category_id]) }}" class="font-semibold hover:underline">{{ $row->name }}</a>
                                    @else
                                        <span class="font-semibold">{{ $row->name }}</span>
                                    @endif
                                @else
                                    <span class="font-medium">{{ $row->name }}</span>
                                @endunless
                            </td>
                            <td>{{ number_format($row->qty, 0) }}</td>
                            <td class="font-semibold">{{ money($row->total) }}</td>
                            @unless ($exporting ?? false)
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($row->category_id)
                                            <a href="{{ route('categories.index', ['modal' => 'view', 'category' => $row->category_id]) }}" class="table-action" title="Lihat">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            @endunless
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($exporting ?? false) ? 4 : 5 }}" class="py-16 text-center text-sm text-slate-400">Tidak ada penjualan kategori yang cocok dengan filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($exporting ?? false)
            @if ($rows->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $rows->links() }}</div>
            @endif
        @endunless
    </div>
@endsection
