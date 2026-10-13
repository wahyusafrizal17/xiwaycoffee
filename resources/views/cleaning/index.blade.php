@extends('layouts.app')
@section('title', 'Report Harian')
@section('content')
    <div class="mb-5">
        <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Report Harian</h1>
    </div>

    @if (! $shiftLabel)
        <div class="card p-6 text-sm text-muted">Tidak ada jadwal hari ini. Report mengikuti jadwal shift.</div>
    @elseif ($off)
        <div class="card p-6 text-sm text-muted">Hari ini OFF. Tidak perlu report.</div>
    @else
        <p class="mb-4 text-sm text-muted">Jadwal hari ini {{ $shiftLabel }}. Report pertama 30 menit sebelum shift. Dua area berganti tiap 2 jam. Satu tugas yang tidak dicentang dipotong Rp 100, terhitung mulai 15 Okt 2026.</p>
        <div class="space-y-4">
            @foreach ($plans as $plan)
                @php
                    $slot = $plan['slot'];
                    $areas = $plan['areas'];
                    $rows = $done->get($slot, collect());
                    $complete = $areas !== [] && $rows->count() >= count($areas);
                    $mine = collect($plan['roster'])->firstWhere('mine', true);
                @endphp
                <section class="card overflow-hidden">
                    <div class="card-header">
                        <div>
                            <h5 class="card-header-title">{{ $slot }}</h5>
                            <p class="card-header-subtitle">
                                Cek: {{ $mine ? implode(', ', $mine['labels']) : '—' }}
                                @if ($complete)
                                    · Sudah dikirim
                                @endif
                            </p>
                        </div>
                    </div>
                    @if (count($plan['roster']) > 1)
                        <p class="border-b border-line px-4 py-3 text-[13px] text-muted">
                            @foreach ($plan['roster'] as $person)
                                {{ $person['name'] }}{{ $person['mine'] ? ' (kamu)' : '' }}: {{ implode(', ', $person['labels']) }}@if (! $loop->last) · @endif
                            @endforeach
                        </p>
                    @endif
                    @if ($complete)
                        <div class="grid gap-3 p-4 sm:grid-cols-2">
                            @foreach ($areas as $key => $area)
                                @php $photo = $rows->firstWhere('area', $key); @endphp
                                <div>
                                    <p class="mb-2 text-sm font-medium text-heading">{{ $area['label'] }}</p>
                                    @if ($photo)
                                        <a href="{{ asset($photo->photo) }}" target="_blank" rel="noopener">
                                            <img src="{{ asset($photo->photo) }}" alt="{{ $area['label'] }}" class="h-36 w-full rounded-xl object-cover">
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <form class="space-y-5 p-4" method="POST" action="{{ route('cleaning.store') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="slot" value="{{ $slot }}">
                            @foreach ($areas as $key => $area)
                                <fieldset class="rounded-xl border border-line p-3">
                                    <legend class="px-1 text-sm font-semibold text-heading">{{ $area['label'] }}</legend>
                                    <div class="mt-2 space-y-2">
                                        @foreach ($area['items'] as $index => $item)
                                            <label class="flex items-start gap-2 text-sm text-heading">
                                                <input class="mt-1" type="checkbox" name="tasks[{{ $key }}][]" value="{{ $index }}">
                                                <span>{{ $item }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="mt-3">
                                        <label class="label">Foto {{ $area['label'] }}</label>
                                        <input class="input" type="file" name="photo[{{ $key }}]" accept="image/jpeg,image/png,image/webp" capture="environment" required>
                                    </div>
                                </fieldset>
                            @endforeach
                            <button class="btn-add" type="submit">Kirim report {{ $slot }}</button>
                        </form>
                    @endif
                </section>
            @endforeach
        </div>
    @endif
@endsection
