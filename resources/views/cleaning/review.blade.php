@extends('layouts.app')
@section('title', 'Report Harian')
@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Report Harian</h1>
            <p class="mt-2 text-[13px] text-muted">Tugas yang harus dikerjakan tiap orang, statusnya, dan foto buktinya.</p>
        </div>
        <form method="GET">
            <input class="input" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
        </form>
    </div>

    @forelse ($boards as $board)
        <section class="card mb-4 p-4">
            <h2 class="font-medium text-heading">{{ $board['name'] }}</h2>
            <p class="mt-1 text-[13px] text-muted">{{ $board['shift'] }}</p>
            @if ($board['off'])
                <p class="mt-3 text-sm text-muted">Hari ini OFF. Tidak ada tugas.</p>
            @else
                @foreach ($board['slots'] as $slot)
                    <h3 class="mt-4 text-sm font-semibold text-heading">{{ $slot['slot'] }}</h3>
                    @foreach ($slot['areas'] as $area)
                        <div class="mt-3 rounded-xl border border-line p-3">
                            <p class="text-sm font-semibold text-heading">{{ $area['label'] }}</p>
                            @if ($area['legacy_photo'])
                                <a class="mt-3 block" href="{{ evidence_url($area['legacy_photo']) }}" target="_blank" rel="noopener">
                                    <img class="h-36 w-full rounded-lg object-cover" src="{{ evidence_url($area['legacy_photo']) }}" alt="{{ $area['label'] }}">
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
                                <div class="mt-2 space-y-3">
                                    @foreach ($area['items'] as $item)
                                        <div>
                                            <p class="text-sm text-heading">{{ $item['label'] }}</p>
                                            <p class="text-[13px] {{ $item['done'] ? 'text-muted' : 'text-brand' }}">{{ $item['status'] }}</p>
                                            @if ($item['photo'])
                                                <a class="mt-2 block" href="{{ evidence_url($item['photo']) }}" target="_blank" rel="noopener">
                                                    <img class="h-36 w-full rounded-lg object-cover" src="{{ evidence_url($item['photo']) }}" alt="{{ $item['label'] }}">
                                                </a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            @endif
        </section>
    @empty
        @if ($orphans->isEmpty())
            <div class="card p-6 text-sm text-muted">Belum ada jadwal pada tanggal ini.</div>
        @endif
    @endforelse

    @foreach ($orphans as $rows)
        <section class="card mb-4 p-4">
            <h2 class="font-medium text-heading">{{ $rows->first()->employee?->user?->name }}</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach ($rows as $report)
                    @php
                        $area = $areas[$report->area] ?? null;
                        $task = $report->item !== null ? ($area['items'][$report->item] ?? null) : null;
                    @endphp
                    <figure>
                        <a href="{{ evidence_url($report->photo) }}" target="_blank">
                            <img class="h-56 w-full rounded-lg object-cover" src="{{ evidence_url($report->photo) }}" alt="{{ $task ?? ($area['label'] ?? $report->area) }}">
                        </a>
                        <figcaption class="mt-2 text-[13px] text-muted">
                            {{ $report->slot }} · {{ $area['label'] ?? $report->area }}{{ $task ? ' · '.$task : '' }}
                            @if ($report->item === null && $report->unchecked)
                                · {{ $report->unchecked }} tidak dicentang
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
