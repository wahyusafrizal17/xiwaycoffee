@extends('layouts.app')
@section('title', 'Report Harian')
@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Report Harian</h1>
            <p class="mt-2 text-[13px] text-muted">Evidence kebersihan karyawan pada tanggal ini.</p>
        </div>
        <form method="GET">
            <input class="input" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
        </form>
    </div>

    @forelse ($reports as $rows)
        <section class="card mb-4 p-4">
            <h2 class="font-medium text-heading">{{ $rows->first()->employee?->user?->name }}</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach ($rows as $report)
                    <figure>
                        <a href="{{ asset($report->photo) }}" target="_blank">
                            <img class="h-56 w-full rounded-lg object-cover" src="{{ asset($report->photo) }}" alt="{{ $areas[$report->area]['label'] ?? $report->area }}">
                        </a>
                        <figcaption class="mt-2 text-[13px] text-muted">
                            {{ $report->slot }} · {{ $areas[$report->area]['label'] ?? $report->area }}
                            @if ($report->unchecked)
                                · {{ $report->unchecked }} tidak dicentang
                            @else
                                · lengkap
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @empty
        <div class="card p-6 text-sm text-muted">Belum ada report pada tanggal ini.</div>
    @endforelse
@endsection
