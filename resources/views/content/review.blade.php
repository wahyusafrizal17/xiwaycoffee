@extends('layouts.app')
@section('title', 'Konten')
@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Konten</h1>
            <p class="mt-2 max-w-xl text-[13px] text-muted">Tepat 3 link video sehari, ditanggung bersama. Minimal dan maksimalnya 3. Tiap video yang kurang dipotong Rp 10.000, dibagi rata, sejak 16 Okt 2026.</p>
        </div>
        <form method="GET">
            <input class="input" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
        </form>
    </div>

    <div class="card mb-4 px-4 py-3">
        <p class="text-sm text-heading">{{ $links->count() }}/{{ $limit }} video</p>
        @if ($date < \App\Models\ContentLink::START)
            <p class="mt-1 text-[13px] text-muted">Denda belum berlaku. Mulai 16 Okt 2026.</p>
        @elseif (! $closed)
            <p class="mt-1 text-[13px] text-muted">Hari ini belum ditutup. Belum ada denda.</p>
        @elseif ($missed === 0)
            <p class="mt-1 text-[13px] text-muted">Lengkap. Tidak ada denda.</p>
        @else
            <p class="mt-1 text-[13px] text-brand">Kurang {{ $missed }} video. Denda {{ money($fine) }}, masing-masing {{ money($share) }}.</p>
        @endif
    </div>

    <div class="card divide-y divide-line">
        @forelse ($links as $link)
            <a class="block px-4 py-3" href="{{ $link->url }}" target="_blank" rel="noopener">
                <p class="text-sm font-medium text-heading">{{ $link->employee?->user?->name ?? 'Karyawan' }}</p>
                <p class="mt-1 break-all text-[13px] text-muted">{{ $link->url }}</p>
            </a>
        @empty
            <p class="p-4 text-sm text-muted">Belum ada link pada tanggal ini.</p>
        @endforelse
    </div>
@endsection
