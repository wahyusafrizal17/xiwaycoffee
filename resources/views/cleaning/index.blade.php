@extends('layouts.app')
@section('title', 'Report Harian')
@section('content')
    <div class="report-page mb-5">
        <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Report Harian</h1>
        @if ($shiftLabel && ! $off)
            <p class="mt-2 text-[13px] text-muted">{{ $shiftLabel }}. Tiap sesi bisa dikirim sampai 1 jam setelah jamnya.</p>
        @endif
    </div>

    @if (! $shiftLabel)
        <div class="card p-6 text-sm text-muted">Tidak ada jadwal hari ini. Report mengikuti jadwal shift.</div>
    @elseif ($off)
        <div class="card p-6 text-sm text-muted">Hari ini OFF. Tidak perlu report.</div>
    @else
        <style>
            .report-page { padding-bottom: 5.5rem; }
            @media (min-width: 1024px) { .report-page { padding-bottom: 0; } }
            .report-session > summary { cursor: pointer; list-style: none; }
            .report-session > summary::-webkit-details-marker { display: none; }
            .report-session .chev { transition: transform .15s; }
            .report-session[open] > summary .chev { transform: rotate(180deg); }
            .report-session.report-done > summary { background: #e7f6ec; }
            .report-session.report-wait > summary { background: #fff6d8; }
            .report-session.report-miss > summary { background: #fde8e8; }
            .report-check { width: 1.35rem; height: 1.35rem; margin-top: 0.1rem; flex: none; }
            .report-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.75rem; }
            .report-shot { position: relative; display: flex; align-items: center; justify-content: center; min-height: 3rem; border: 1px dashed #e4e4e7; border-radius: 0.85rem; background: #fff; padding: 0.5rem 0.75rem; }
            .report-photos.has-photo .report-shot { border-style: solid; border-color: #bbf7d0; background: #f0fdf4; }
            .report-file { position: absolute; width: 1px; height: 1px; overflow: hidden; opacity: 0; }
            .report-preview { display: none; width: 100%; height: 9rem; margin-top: 0.75rem; border-radius: 0.85rem; object-fit: cover; }
            .report-photos.has-photo .report-preview { display: block; }
            .report-submit { position: sticky; bottom: 4.75rem; z-index: 5; }
            @media (min-width: 1024px) { .report-submit { position: static; } }
        </style>
        @php
            $sessions = [];
            $openSlot = null;
            $today = now('Asia/Jakarta')->toDateString();
            foreach ($plans as $plan) {
                $rows = $done->get($plan['slot'], collect());
                $allDone = $plan['areas'] !== [];
                foreach ($plan['areas'] as $key => $area) {
                    $areaRows = $rows->where('area', $key);
                    foreach (array_keys($area['items']) as $index) {
                        $has = $areaRows->contains(fn ($row) => $row->item !== null && (int) $row->item === $index);
                        if (! $has) {
                            $allDone = false;
                            break 2;
                        }
                    }
                }
                $closed = ! \App\Models\CleaningReport::accepts($today, $plan['slot']);
                $plan['rows'] = $rows;
                $plan['status'] = $allDone ? 'done' : ($closed ? 'miss' : 'wait');
                $plan['until'] = \Illuminate\Support\Carbon::parse($today.' '.$plan['slot'], 'Asia/Jakarta')->addHour()->format('H:i');
                if ($openSlot === null && $plan['status'] === 'wait' && $rows->isEmpty()) {
                    $openSlot = $plan['slot'];
                }
                $sessions[] = $plan;
            }
            $statusLabel = ['done' => 'Sudah dikirim', 'wait' => 'Belum', 'miss' => 'Tidak dikerjakan'];
        @endphp
        <div class="report-page space-y-3">
            @foreach ($sessions as $plan)
                @php
                    $slot = $plan['slot'];
                    $areas = $plan['areas'];
                    $rows = $plan['rows'];
                    $complete = $plan['status'] === 'done' || $rows->isNotEmpty();
                    $mine = collect($plan['roster'])->firstWhere('mine', true);
                @endphp
                <details class="card report-session report-{{ $plan['status'] }} overflow-hidden" @if ($slot === $openSlot) open @endif>
                    <summary class="flex items-center gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <h5 class="text-base font-semibold text-heading">{{ $slot }}</h5>
                            <p class="mt-0.5 text-[13px] text-muted">
                                Cek: {{ $mine ? implode(', ', $mine['labels']) : '—' }}
                                · {{ $statusLabel[$plan['status']] }}
                                @if ($plan['status'] === 'wait')
                                    sampai {{ $plan['until'] }}
                                @endif
                            </p>
                        </div>
                        <span class="chev text-muted" aria-hidden="true">▾</span>
                    </summary>
                    @if (count($plan['roster']) > 1)
                        <p class="border-b border-line px-4 py-3 text-[13px] leading-5 text-muted">
                            @foreach ($plan['roster'] as $person)
                                {{ $person['name'] }}{{ $person['mine'] ? ' (kamu)' : '' }}: {{ implode(', ', $person['labels']) }}@if (! $loop->last) · @endif
                            @endforeach
                        </p>
                    @endif
                    @if ($plan['status'] === 'miss' && $rows->isEmpty())
                        <p class="p-4 text-sm text-muted">Toleransi 1 jam sudah lewat. Sesi ini terhitung tidak dikerjakan.</p>
                    @elseif ($complete)
                        <div class="space-y-2 bg-white p-3">
                            @foreach ($areas as $key => $area)
                                @foreach ($rows->where('area', $key) as $photo)
                                    @php $task = $photo->item !== null ? ($area['items'][$photo->item] ?? $area['label']) : $area['label']; @endphp
                                    <a class="flex items-center gap-3 rounded-xl border border-line p-2" href="{{ evidence_url($photo->photo) }}" target="_blank" rel="noopener">
                                        <img src="{{ evidence_url($photo->photo) }}" alt="{{ $task }}" class="h-14 w-14 shrink-0 rounded-lg object-cover">
                                        <span class="min-w-0 text-sm text-heading">{{ $area['label'] }} · {{ $task }}</span>
                                    </a>
                                @endforeach
                            @endforeach
                        </div>
                    @else
                        <form class="space-y-3 bg-white p-3" method="POST" action="{{ route('cleaning.store') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="slot" value="{{ $slot }}">
                            @foreach ($areas as $key => $area)
                                <section class="rounded-xl border border-line p-3">
                                    <h6 class="text-sm font-semibold text-heading">{{ $area['label'] }}</h6>
                                    <div class="mt-2 divide-y divide-line">
                                        @foreach ($area['items'] as $index => $item)
                                            <div class="py-3">
                                                <label class="flex items-start gap-3 text-sm text-heading">
                                                    <input class="report-check" type="checkbox" name="tasks[{{ $key }}][]" value="{{ $index }}">
                                                    <span>{{ $item }}</span>
                                                </label>
                                                <div class="report-photos">
                                                    <img class="report-preview" alt="">
                                                    <p class="report-shot-status mt-2 text-[12px] text-muted">Belum ada foto</p>
                                                    <div class="report-actions">
                                                        <label class="report-shot">
                                                            <input class="report-file" type="file" accept="image/*" capture="environment" data-field="photo[{{ $key }}][{{ $index }}]" onchange="reportPick(this)">
                                                            <span class="text-sm font-medium text-heading">Kamera</span>
                                                        </label>
                                                        <label class="report-shot">
                                                            <input class="report-file" type="file" name="photo[{{ $key }}][{{ $index }}]" accept="image/jpeg,image/png,image/webp" data-field="photo[{{ $key }}][{{ $index }}]" onchange="reportPick(this)">
                                                            <span class="text-sm font-medium text-heading">Galeri</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                            <div class="report-submit">
                                <button class="btn-add w-full" type="submit">Kirim report {{ $slot }}</button>
                            </div>
                        </form>
                    @endif
                </details>
            @endforeach
        </div>
        <script>
            function reportPick(source) {
                const box = source.closest('.report-photos');
                box.querySelectorAll('input[type=file]').forEach((input) => {
                    if (input === source) {
                        input.name = input.dataset.field;
                    } else {
                        input.removeAttribute('name');
                    }
                });
                const img = box.querySelector('img');
                const status = box.querySelector('.report-shot-status');
                if (! source.files[0]) {
                    return;
                }
                img.src = URL.createObjectURL(source.files[0]);
                box.classList.add('has-photo');
                status.textContent = source.hasAttribute('capture') ? 'Foto dari kamera' : 'Foto dari galeri';
            }
        </script>
    @endif
@endsection
