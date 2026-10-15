@extends('layouts.app')
@section('title', 'Konten')
@section('content')
    <div class="mb-5">
        <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Konten</h1>
        <p class="mt-2 max-w-xl text-[13px] text-muted">Sehari tepat 3 link video untuk semua karyawan. Minimal dan maksimalnya 3. Upload dulu ke Instagram, lalu tempel linknya di sini. Sejak 16 Okt 2026, tiap video yang kurang dipotong Rp 10.000 dan dibagi rata.</p>
    </div>

    <div class="card mb-4 p-4">
        <p class="text-sm text-heading">Hari ini {{ $links->count() }}/{{ $limit }}</p>
        @if ($links->count() < $limit)
            <form class="mt-3 flex flex-col gap-2 sm:flex-row" method="POST" action="{{ route('content.store') }}">
                @csrf
                <input class="input min-w-0 flex-1" type="url" name="url" placeholder="https://instagram.com/reel/..." required>
                <button class="btn-add shrink-0" type="submit">Kirim link</button>
            </form>
        @else
            <p class="mt-2 text-sm text-muted">Sudah 3 video. Itu batas maksimal hari ini.</p>
        @endif
    </div>

    <div class="card divide-y divide-line">
        @forelse ($links as $link)
            <a class="block px-4 py-3" href="{{ $link->url }}" target="_blank" rel="noopener">
                <p class="text-sm font-medium text-heading">{{ $link->employee?->user?->name ?? 'Karyawan' }}</p>
                <p class="mt-1 break-all text-[13px] text-muted">{{ $link->url }}</p>
            </a>
        @empty
            <p class="p-4 text-sm text-muted">Belum ada link hari ini. Ingatkan yang lain kalau belum ada yang kirim.</p>
        @endforelse
    </div>
@endsection
