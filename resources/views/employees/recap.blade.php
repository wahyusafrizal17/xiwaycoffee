@extends('layouts.app')
@section('title', 'Rekap Absensi')
@section('content')
    @php
        $cursor = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $dates = collect(range(1, $cursor->daysInMonth))->map(fn (int $day) => $cursor->copy()->day($day));
        $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $today = now('Asia/Jakarta')->startOfDay();
    @endphp
    <style>
        .recap-grid th,
        .recap-grid td { padding: 0.35rem 0.25rem; text-align: center; vertical-align: middle; }
        .list-table.recap-grid th.recap-name,
        .list-table.recap-grid td.recap-name {
            position: sticky; left: 0; z-index: 1; min-width: 9.5rem; text-align: left; background: #fff;
        }
        .list-table.recap-grid thead th.recap-name { z-index: 2; background: #f6f6f6; }
        .list-table.recap-grid tbody tr:nth-child(even) td.recap-name { background: #fafafa; }
        .list-table.recap-grid tbody tr td.recap-ok { background: #e7f6ec; }
        .list-table.recap-grid tbody tr td.recap-late { background: #fff1e0; }
        .list-table.recap-grid tbody tr td.recap-off { background: #fff3bf; }
        .list-table.recap-grid tbody tr td.recap-miss { background: #f8b4b4; }
        .recap-time { display: block; font-size: 11px; line-height: 1.3; font-variant-numeric: tabular-nums; }
        .recap-flag { display: block; font-size: 10px; font-weight: 700; color: #c2410c; }
        .recap-dow { display: block; font-size: 10px; font-weight: 500; text-transform: none; }
        .recap-weekend { color: #e8192c; }
    </style>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Rekap absensi</h1>
            <p class="mt-1 text-sm text-muted">{{ $cursor->locale('id')->translatedFormat('F Y') }} · jam dalam WIB</p>
        </div>
        <form method="GET">
            <input class="input" type="month" name="month" value="{{ $month }}" onchange="this.form.submit()">
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="list-table recap-grid">
                <thead>
                    <tr>
                        <th class="recap-name">Nama</th>
                        @foreach ($dates as $date)
                            <th class="{{ $date->isWeekend() ? 'recap-weekend' : '' }}">
                                <span class="block">{{ $date->day }}</span>
                                <span class="recap-dow">{{ $dayNames[$date->dayOfWeek] }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="recap-name">
                                <span class="font-medium">{{ $row['employee']->user?->name }}</span>
                                <span class="mt-0.5 block text-[11px] font-normal text-muted">{{ $row['attended'] }} hadir · {{ $row['late'] }} telat</span>
                            </td>
                            @foreach ($dates as $date)
                                @php
                                    $cell = $row['cells'][$date->day] ?? null;
                                    $off = in_array($date->day, $row['off_days'], true);
                                    $miss = ! $cell && ! $off && in_array($date->day, $row['due_days'], true) && $date->lte($today);
                                @endphp
                                <td class="{{ $cell ? ($cell['late'] ? 'recap-late' : 'recap-ok') : ($off ? 'recap-off' : ($miss ? 'recap-miss' : '')) }}">
                                    @if ($cell)
                                        <span class="recap-time">{{ $cell['in'] }}</span>
                                        <span class="recap-time">{{ $cell['out'] ?: '—' }}</span>
                                        @if ($cell['late'])<span class="recap-flag">Telat</span>@endif
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td class="recap-name" colspan="{{ $dates->count() + 1 }}">Belum ada karyawan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
