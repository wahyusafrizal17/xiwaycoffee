@extends('layouts.app')
@section('title', 'Setoran makanan')
@section('breadcrumb', 'Reports')
@section('content')
    @php
        $period = $filters['period'] ?? null;
        $fromLabel = filled($filters['from'] ?? null) ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') : '—';
        $toLabel = filled($filters['to'] ?? null) ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d/m/Y') : '—';
        $periodKeep = collect($filters)->only(['outlet_id'])->filter(fn ($value) => filled($value))->all();
        $chip = 'inline-flex items-center rounded-lg px-3 py-1.5 text-[13px] font-medium';
        $chipOn = 'bg-brand text-white';
        $chipOff = 'bg-[#f5f5f5] text-muted hover:bg-[#ececec]';
    @endphp
    @include('reports._nav')

    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Penjualan makanan</p>
                <p class="stat-value">{{ money($sales) }}</p>
                <p class="stat-hint">{{ $fromLabel }} – {{ $toLabel }}</p>
            </div>
        </div>
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Komisi cafe</p>
                <p class="stat-value">{{ money($commission) }}</p>
                <p class="stat-hint">Rp {{ number_format((int) config('pos.food_commission'), 0, ',', '.') }} per porsi</p>
            </div>
        </div>
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Setoran mitra</p>
                <p class="stat-value">{{ money($setoran) }}</p>
                <p class="stat-hint">Penjualan dikurangi komisi</p>
            </div>
        </div>
    </div>

    <form id="setoran-filters" method="GET" action="{{ route('reports.setoran') }}">
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="outlet_id" value="{{ $filters['outlet_id'] ?? '' }}">
    </form>
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('reports.setoran', $periodKeep + ['period' => 'today']) }}" class="{{ $chip }} {{ $period === 'today' ? $chipOn : $chipOff }}">Hari ini</a>
        <a href="{{ route('reports.setoran', $periodKeep + ['period' => 'week']) }}" class="{{ $chip }} {{ $period === 'week' ? $chipOn : $chipOff }}">Mingguan</a>
        <a href="{{ route('reports.setoran', $periodKeep + ['period' => 'month']) }}" class="{{ $chip }} {{ $period === 'month' ? $chipOn : $chipOff }}">Bulanan</a>
        <input form="setoran-filters" class="input !w-40" type="date" name="from" value="{{ $filters['from'] ?? '' }}" onchange="this.form.period.value=''; this.form.submit()">
        <input form="setoran-filters" class="input !w-40" type="date" name="to" value="{{ $filters['to'] ?? '' }}" onchange="this.form.period.value=''; this.form.submit()">
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h5 class="card-header-title">Rincian setoran</h5>
                <p class="card-header-subtitle">Makanan milik mitra. Cafe hanya menahan komisi.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Menu</th>
                        <th>Qty</th>
                        <th>Penjualan</th>
                        <th>Komisi cafe</th>
                        <th>Setoran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $row->qty, 3, ',', '.'), '0'), ',') }}</td>
                            <td>{{ money($row->sales) }}</td>
                            <td>{{ money($row->commission) }}</td>
                            <td>{{ money($row->setoran) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center text-sm text-slate-400">Belum ada penjualan makanan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
