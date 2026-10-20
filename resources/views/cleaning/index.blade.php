@extends('layouts.app')
@section('title', 'Report Harian')
@section('content')
    <div class="report-page mb-5">
        <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Report Harian</h1>
        @if ($shiftLabel && ! $off)
            <p class="mt-2 text-[13px] text-muted">{{ $shiftLabel }}. Tiap sesi bisa dikirim sampai 1 jam setelah jamnya. Foto yang sudah terkirim tidak perlu diulang.</p>
        @endif
    </div>

    @if (! $shiftLabel)
        <div class="card p-6 text-sm text-muted">Tidak ada jadwal hari ini. Report mengikuti jadwal shift.</div>
    @elseif ($off)
        <div class="card p-6 text-sm text-muted">Hari ini OFF. Tidak perlu report.</div>
    @else
        <style>
            .report-page { padding-bottom: calc(6.5rem + env(safe-area-inset-bottom, 0px)); }
            @media (min-width: 1024px) { .report-page { padding-bottom: 0; } }
            .report-session > summary { cursor: pointer; list-style: none; border-top-left-radius: inherit; border-top-right-radius: inherit; }
            .report-session > summary::-webkit-details-marker { display: none; }
            .report-session .chev { transition: transform .15s; }
            .report-session[open] > summary .chev { transform: rotate(180deg); }
            .report-session.report-done > summary { background: #e7f6ec; }
            .report-session.report-wait > summary { background: #fff6d8; }
            .report-session.report-miss > summary { background: #fde8e8; }
            .report-check { width: 1.35rem; height: 1.35rem; margin-top: 0.1rem; flex: none; }
            .report-shot { display: flex; align-items: center; justify-content: center; min-height: 3rem; margin-top: 0.75rem; border: 1px dashed #e4e4e7; border-radius: 0.85rem; background: #fff; padding: 0.5rem 0.75rem; width: 100%; font-size: 0.875rem; font-weight: 500; color: #18181b; }
            .report-photos.has-photo .report-shot { display: none; }
            .report-retake { display: none; margin-top: 0.5rem; width: 100%; text-align: center; font-size: 0.75rem; font-weight: 500; color: #71717a; }
            .report-photos.has-photo .report-retake { display: block; }
            .report-file { position: absolute; width: 1px; height: 1px; overflow: hidden; opacity: 0; pointer-events: none; }
            .report-preview { display: none; width: 100%; height: 9rem; margin-top: 0.75rem; border-radius: 0.85rem; object-fit: cover; }
            .report-photos.has-photo .report-preview { display: block; }
            .report-submit { position: sticky; bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px)); z-index: 40; }
            @media (min-width: 1024px) { .report-submit { position: static; } }
            .report-cam { position: fixed; inset: 0; z-index: 60; display: none; flex-direction: column; background: #0a0a0a; }
            .report-cam.open { display: flex; }
            .report-cam video { flex: 1; width: 100%; object-fit: cover; background: #000; }
            .report-cam-bar { display: flex; gap: 0.75rem; padding: 1rem; padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px)); background: #111; }
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
                if ($openSlot === null && $plan['status'] === 'wait') {
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
                    $complete = $plan['status'] !== 'wait';
                    $mine = collect($plan['roster'])->firstWhere('mine', true);
                @endphp
                <details class="card report-session report-{{ $plan['status'] }}" @if ($slot === $openSlot) open @endif>
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
                        @if ($plan['status'] === 'miss')
                            <p class="px-4 pt-3 text-sm text-muted">Toleransi 1 jam sudah lewat. Sisa tugas tidak bisa dikirim.</p>
                        @endif
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
                        <form class="report-form space-y-3 bg-white p-3" method="POST" action="{{ route('cleaning.store') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="slot" value="{{ $slot }}">
                            <p class="report-error hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600"></p>
                            @foreach ($areas as $key => $area)
                                <section class="rounded-xl border border-line p-3">
                                    <h6 class="text-sm font-semibold text-heading">{{ $area['label'] }}</h6>
                                    <div class="mt-2 divide-y divide-line">
                                        @foreach ($area['items'] as $index => $item)
                                            @php
                                                $saved = $rows->first(fn ($row) => $row->area === $key && $row->item !== null && (int) $row->item === (int) $index);
                                            @endphp
                                            <div class="py-3" @if ($saved) data-sent="1" @endif>
                                                @if ($saved)
                                                    <p class="text-sm text-heading">{{ $item }}</p>
                                                    <p class="mt-1 text-[12px] text-muted">Terkirim</p>
                                                @else
                                                    <label class="flex items-start gap-3 text-sm text-heading">
                                                        <input class="report-check" type="checkbox" name="tasks[{{ $key }}][]" value="{{ $index }}">
                                                        <span>{{ $item }}</span>
                                                    </label>
                                                    <div class="report-photos relative">
                                                        <input class="report-file" type="file" accept="image/jpeg" data-field="photo[{{ $key }}][{{ $index }}]">
                                                        <img class="report-preview" alt="">
                                                        <p class="report-shot-status mt-2 text-[12px] text-muted">Belum ada foto. Wajib dari kamera.</p>
                                                        <button class="report-shot" type="button" onclick="reportOpenCam(this)">Buka kamera</button>
                                                        <button class="report-retake" type="button" onclick="reportClearPhoto(this)">Ulangi foto</button>
                                                    </div>
                                                @endif
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
        <div id="report-cam" class="report-cam" aria-hidden="true">
            <video id="report-cam-video" autoplay playsinline muted></video>
            <canvas id="report-cam-canvas" class="hidden"></canvas>
            <div class="report-cam-bar">
                <button id="report-cam-close" class="btn-secondary flex-1" type="button">Batal</button>
                <button id="report-cam-shot" class="btn-primary flex-1" type="button">Ambil foto</button>
            </div>
        </div>
        <script>
            let reportCamStream = null;
            let reportCamBox = null;

            function reportStopCam() {
                reportCamStream?.getTracks()?.forEach((track) => track.stop());
                reportCamStream = null;
                const shell = document.getElementById('report-cam');
                const video = document.getElementById('report-cam-video');
                if (video) video.srcObject = null;
                if (shell) {
                    shell.classList.remove('open');
                    shell.setAttribute('aria-hidden', 'true');
                }
                reportCamBox = null;
            }

            async function reportOpenCam(button) {
                reportCamBox = button.closest('.report-photos');
                const shell = document.getElementById('report-cam');
                const video = document.getElementById('report-cam-video');
                const status = reportCamBox.querySelector('.report-shot-status');
                try {
                    reportCamStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false,
                    });
                    video.srcObject = reportCamStream;
                    shell.classList.add('open');
                    shell.setAttribute('aria-hidden', 'false');
                } catch (e) {
                    reportCamBox = null;
                    status.textContent = 'Tidak bisa membuka kamera. Izinkan akses kamera di browser.';
                }
            }

            function reportClearPhoto(button) {
                const box = button.closest('.report-photos');
                const input = box.querySelector('input[type=file]');
                input.removeAttribute('name');
                input.value = '';
                box.classList.remove('has-photo');
                box.querySelector('img').removeAttribute('src');
                box.querySelector('.report-shot-status').textContent = 'Belum ada foto. Wajib dari kamera.';
                const check = box.closest('.py-3').querySelector('.report-check');
                if (check) check.checked = false;
            }

            function reportApplyFile(box, file) {
                const input = box.querySelector('input[type=file]');
                const list = new DataTransfer();
                list.items.add(file);
                input.files = list.files;
                input.name = input.dataset.field;
                const check = box.closest('.py-3').querySelector('.report-check');
                if (check) check.checked = true;
                box.querySelector('img').src = URL.createObjectURL(file);
                box.classList.add('has-photo');
                box.querySelector('.report-shot-status').textContent = 'Foto dari kamera';
            }

            async function reportCapture() {
                if (!reportCamBox || !reportCamStream) return;
                const box = reportCamBox;
                const status = box.querySelector('.report-shot-status');
                const video = document.getElementById('report-cam-video');
                const canvas = document.getElementById('report-cam-canvas');
                const width = video.videoWidth || 1280;
                const height = video.videoHeight || 720;
                const scale = Math.min(1, 1600 / Math.max(width, height, 1));
                canvas.width = Math.max(1, Math.round(width * scale));
                canvas.height = Math.max(1, Math.round(height * scale));
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                status.textContent = 'Memproses foto…';
                reportStopCam();
                try {
                    const blob = await new Promise((resolve, reject) => {
                        canvas.toBlob((next) => next ? resolve(next) : reject(new Error('blob')), 'image/jpeg', 0.72);
                    });
                    reportApplyFile(box, new File([blob], 'bukti.jpg', { type: 'image/jpeg' }));
                } catch (e) {
                    status.textContent = 'Foto gagal diproses. Ambil ulang dari kamera.';
                }
            }

            document.getElementById('report-cam-close')?.addEventListener('click', reportStopCam);
            document.getElementById('report-cam-shot')?.addEventListener('click', reportCapture);

            function reportError(form, message) {
                const box = form.querySelector('.report-error');
                box.textContent = message;
                box.classList.remove('hidden');
            }

            async function sendReport(form) {
                if (form.dataset.sending === '1') return;
                const button = form.querySelector('button[type=submit]');
                const label = button.textContent;
                const jobs = [];
                let missing = '';
                form.querySelectorAll('.py-3').forEach((row) => {
                    if (row.dataset.sent === '1') return;
                    const check = row.querySelector('.report-check');
                    if (!check || !check.checked) return;
                    const input = row.querySelector('input[type=file][name]');
                    if (!input || !input.files || !input.files[0]) {
                        missing = missing || row.querySelector('span').textContent.trim();
                        return;
                    }
                    jobs.push({ row, check, input });
                });
                if (missing) {
                    reportError(form, 'Foto kamera belum ada untuk: ' + missing);
                    return;
                }
                if (!jobs.length) {
                    reportError(form, 'Centang tugas yang sudah dikerjakan, lalu ambil fotonya dari kamera.');
                    return;
                }
                form.dataset.sending = '1';
                button.disabled = true;
                form.querySelector('.report-error').classList.add('hidden');
                const token = form.querySelector('[name=_token]').value;
                const slot = form.querySelector('[name=slot]').value;
                for (let index = 0; index < jobs.length; index++) {
                    const job = jobs[index];
                    button.textContent = 'Mengirim ' + (index + 1) + '/' + jobs.length + '…';
                    const body = new FormData();
                    body.append('_token', token);
                    body.append('slot', slot);
                    body.append(job.check.name, job.check.value);
                    body.append(job.input.name, job.input.files[0], 'bukti.jpg');
                    let response;
                    try {
                        response = await fetch(form.action, {
                            method: 'POST',
                            body,
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                    } catch (e) {
                        reportError(form, 'Koneksi putus. Foto yang belum terkirim masih di sini, coba lagi.');
                        button.disabled = false;
                        button.textContent = label;
                        delete form.dataset.sending;
                        return;
                    }
                    if (response.ok) {
                        job.row.dataset.sent = '1';
                        job.check.disabled = true;
                        const sent = job.row.querySelector('.report-shot-status');
                        if (sent) sent.textContent = 'Terkirim';
                        continue;
                    }
                    if (response.status === 419) {
                        reportError(form, 'Halaman kedaluwarsa. Muat ulang, lalu kirim lagi. Yang sudah terkirim tidak perlu diulang.');
                        button.disabled = false;
                        button.textContent = label;
                        delete form.dataset.sending;
                        return;
                    }
                    const data = await response.json().catch(() => ({}));
                    const first = data.errors ? Object.values(data.errors)[0] : null;
                    const message = (Array.isArray(first) ? first[0] : first) || data.message || 'Gagal mengirim. Coba lagi.';
                    if (response.status === 422 && String(message).includes('sudah dikirim')) {
                        job.row.dataset.sent = '1';
                        continue;
                    }
                    reportError(form, message);
                    button.disabled = false;
                    button.textContent = label;
                    delete form.dataset.sending;
                    return;
                }
                window.location.reload();
            }

            document.querySelectorAll('.report-form').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    sendReport(form);
                });
                form.querySelectorAll('.report-check').forEach((check) => {
                    check.addEventListener('change', () => {
                        if (check.checked) return;
                        const box = check.closest('.py-3').querySelector('.report-photos');
                        if (!box) return;
                        reportClearPhoto(box.querySelector('.report-retake') || box.querySelector('.report-shot'));
                    });
                });
            });
        </script>
    @endif
@endsection
