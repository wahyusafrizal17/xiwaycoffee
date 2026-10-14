@extends('layouts.app')
@section('title', 'Report Harian')
@section('content')
    @php
        $labels = ['done' => 'Selesai', 'wait' => 'Dalam toleransi', 'miss' => 'Tidak dikerjakan', 'off' => 'Libur'];
        $summary = ['done' => 0, 'wait' => 0, 'miss' => 0];
        foreach ($boards as $board) {
            foreach ($board['slots'] as $slot) {
                $summary[$slot['status']]++;
            }
        }
    @endphp
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Report Harian</h1>
            <p class="mt-2 max-w-xl text-[13px] text-muted">Hijau sudah lengkap, kuning masih dalam toleransi 1 jam, merah tidak dikerjakan. Buka sesi untuk melihat tugas dan fotonya.</p>
        </div>
        <form method="GET">
            <input class="input" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
        </form>
    </div>

    <style>
        .monitor-done { background: #e7f6ec; }
        .monitor-wait { background: #fff6d8; }
        .monitor-miss { background: #fde8e8; }
        .monitor-session > summary { cursor: pointer; list-style: none; }
        .monitor-session > summary::-webkit-details-marker { display: none; }
        .monitor-session .chev { transition: transform .15s; }
        .monitor-session[open] > summary .chev { transform: rotate(180deg); }
    </style>

    @if ($boards->isNotEmpty())
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            @foreach (['done' => $summary['done'], 'wait' => $summary['wait'], 'miss' => $summary['miss']] as $tone => $count)
                <div class="card monitor-{{ $tone }} px-4 py-3">
                    <p class="text-[12px] text-muted">{{ $labels[$tone] }}</p>
                    <p class="mt-1 font-serif text-[1.6rem] font-semibold leading-none text-heading">{{ $count }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="space-y-4">
        @forelse ($boards as $board)
            <section class="card overflow-hidden">
                <div class="flex items-center gap-3 px-4 py-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft font-serif text-sm font-semibold text-brand">{{ mb_strtoupper(mb_substr($board['name'], 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate font-medium text-heading">{{ $board['name'] }}</h2>
                        <p class="text-[13px] text-muted">{{ $board['shift'] }}</p>
                    </div>
                    <span class="monitor-{{ $board['tone'] }} shrink-0 rounded-full px-2.5 py-1 text-[12px] font-medium text-heading">{{ $labels[$board['tone']] }}</span>
                </div>
                @if ($board['off'])
                    <p class="border-t border-line px-4 py-3 text-sm text-muted">Hari ini OFF. Tidak ada tugas.</p>
                @else
                    <div class="space-y-2 border-t border-line p-3">
                        @foreach ($board['slots'] as $slot)
                            <details class="monitor-session overflow-hidden rounded-xl">
                                <summary class="monitor-{{ $slot['status'] }} flex items-center gap-3 px-3 py-2.5">
                                    <span class="font-medium tabular-nums text-heading">{{ $slot['slot'] }}</span>
                                    <span class="text-[13px] text-muted">{{ $labels[$slot['status']] }}</span>
                                    <span class="ml-auto text-[13px] tabular-nums text-muted">{{ $slot['done'] }}/{{ $slot['total'] }}</span>
                                    <span class="chev text-muted" aria-hidden="true">▾</span>
                                </summary>
                                <div class="space-y-3 bg-white px-3 py-3">
                                    @foreach ($slot['areas'] as $area)
                                        <div>
                                            <p class="text-[13px] font-semibold text-heading">{{ $area['label'] }}</p>
                                            @if ($area['legacy_photo'])
                                                <a class="mt-2 flex items-center gap-3" href="{{ evidence_url($area['legacy_photo']) }}" target="_blank" rel="noopener">
                                                    <img class="h-14 w-14 rounded-lg object-cover" src="{{ evidence_url($area['legacy_photo']) }}" alt="{{ $area['label'] }}">
                                                    <span class="text-[13px] text-muted">{{ $area['label'] }}</span>
                                                </a>
                                                @if ($area['legacy_unchecked'])
                                                    <p class="mt-2 text-[13px] text-brand">{{ $area['legacy_unchecked'] }} tidak dicentang</p>
                                                @endif
                                                <ul class="mt-2 space-y-1 text-sm text-muted">
                                                    @foreach ($area['legacy_items'] as $label)
                                                        <li>{{ $label }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <div class="mt-1 divide-y divide-line">
                                                    @foreach ($area['items'] as $item)
                                                        <div class="flex items-center gap-3 py-2">
                                                            <div class="min-w-0 flex-1">
                                                                <p class="text-sm text-heading">{{ $item['label'] }}</p>
                                                                <p class="text-[12px] {{ $item['done'] ? 'text-muted' : 'text-brand' }}">{{ $item['status'] }}</p>
                                                            </div>
                                                            @if ($item['photo'])
                                                                <a href="{{ evidence_url($item['photo']) }}" target="_blank" rel="noopener">
                                                                    <img class="h-14 w-14 rounded-lg object-cover" src="{{ evidence_url($item['photo']) }}" alt="{{ $item['label'] }}">
                                                                </a>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                @endif
            </section>
        @empty
            @if ($orphans->isEmpty())
                <div class="card p-6 text-sm text-muted">Belum ada jadwal pada tanggal ini.</div>
            @endif
        @endforelse

        @foreach ($orphans as $rows)
            <section class="card p-4">
                <h2 class="font-medium text-heading">{{ $rows->first()->employee?->user?->name }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($rows as $report)
                        @php
                            $area = $areas[$report->area] ?? null;
                            $task = $report->item !== null ? ($area['items'][$report->item] ?? null) : null;
                        @endphp
                        <a class="flex items-center gap-3 rounded-xl border border-line p-2" href="{{ evidence_url($report->photo) }}" target="_blank" rel="noopener">
                            <img class="h-14 w-14 rounded-lg object-cover" src="{{ evidence_url($report->photo) }}" alt="{{ $task ?? ($area['label'] ?? $report->area) }}">
                            <span class="text-[13px] text-muted">
                                {{ $report->slot }} · {{ $area['label'] ?? $report->area }}{{ $task ? ' · '.$task : '' }}
                                @if ($report->item === null && $report->unchecked)
                                    · {{ $report->unchecked }} tidak dicentang
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
