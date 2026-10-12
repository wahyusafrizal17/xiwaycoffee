@extends('layouts.app')
@section('title', 'Absensi')
@section('breadcrumb', 'Karyawan')
@section('content')
    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Batas tepat waktu</p>
                <p class="stat-value">{{ $offToday ? 'OFF' : $clockInBy }}</p>
                <p class="stat-hint">{{ $offToday ? 'Hari ini OFF. Tidak perlu absen.' : 'Telat jika absen lewat jam ini.' }}</p>
            </div>
        </div>
        @if ($employee)
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Posisi</p>
                    <p class="stat-value !text-2xl">{{ $employee->position }}</p>
                    <p class="stat-hint">{{ money($employee->salary) }} / bulan</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Status hari ini</p>
                    <p class="stat-value !text-2xl">
                        @if ($offToday && ! $today?->clock_in_at)
                            OFF
                        @elseif (! $today?->clock_in_at)
                            Belum absen
                        @elseif (! $today?->clock_out_at)
                            {{ $today->is_late ? 'Masuk (telat)' : 'Masuk' }}
                        @else
                            Selesai
                        @endif
                    </p>
                    <p class="stat-hint">{{ $outlet?->name }}</p>
                    @if ($todayShift)
                        <p class="mt-1 text-[12px] text-muted">Jadwal hari ini: <span class="font-medium text-heading">{{ $todayShift->label($employee->position) }}</span></p>
                    @endif
                    <a href="{{ route('schedules.index') }}" class="mt-2 inline-block text-[12px] font-medium text-brand">Lihat jadwal</a>
                </div>
            </div>
        @endif
    </div>

    @if (! $employee && ! auth()->user()->hasPermission('attendance.manage'))
        <div class="card p-6 text-sm text-muted">Akun ini belum terdaftar sebagai karyawan.</div>
    @endif

    @if ($employee && $offToday && ! $today?->clock_in_at)
        <div class="card p-6 text-sm text-muted">Hari ini OFF. Tidak perlu absen.</div>
    @elseif ($employee)
        <div class="mb-5 grid gap-4 lg:grid-cols-2" x-data="attendanceCam()">
            <div class="card p-5">
                <h5 class="card-header-title mb-1">Absen masuk</h5>
                <p class="mb-4 text-sm text-muted">Wajib di titik kafe{{ $outlet?->geo_radius_m ? ' (radius '.$outlet->geo_radius_m.' m)' : '' }} + selfie. Titik diset admin di outlet.</p>

                <div class="mb-3 overflow-hidden rounded-xl bg-slate-900">
                    <video x-ref="video" class="aspect-[4/3] w-full object-cover" autoplay playsinline muted x-show="!selfie"></video>
                    <img :src="selfie" class="aspect-[4/3] w-full object-cover" x-show="selfie" x-cloak alt="Selfie">
                    <canvas x-ref="canvas" class="hidden"></canvas>
                </div>

                <p class="mb-3 text-xs" :class="gpsOk ? 'text-emerald-600' : 'text-brand'" x-text="gpsLabel"></p>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary" @click="startCamera()" x-show="!selfie">Buka kamera</button>
                    <button type="button" class="btn-secondary" @click="capture()" x-show="stream && !selfie">Ambil selfie</button>
                    <button type="button" class="btn-ghost" @click="retake()" x-show="selfie">Ulangi selfie</button>
                </div>

                <form method="POST" action="{{ route('attendance.clock-in') }}" class="mt-4" @submit="prepareSubmit($event)">
                    @csrf
                    <input type="hidden" name="latitude" x-model="lat">
                    <input type="hidden" name="longitude" x-model="lng">
                    <input type="hidden" name="selfie" x-model="selfie">
                    <div class="mb-3">
                        <label class="label">Alasan terlambat</label>
                        <input class="input" type="text" name="late_reason" value="{{ old('late_reason') }}" maxlength="255" placeholder="Wajib jika lewat batas" @if ($reasonRequired) required @endif>
                        @error('late_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button
                        type="submit"
                        class="btn-primary"
                        @disabled($today?->clock_in_at)
                        :disabled="!canClockIn"
                    >
                        Absen masuk
                    </button>
                </form>
                @error('gps') <p class="mt-2 text-xs text-brand">{{ $message }}</p> @enderror
                @error('selfie') <p class="mt-2 text-xs text-brand">{{ $message }}</p> @enderror
                @error('clock_in') <p class="mt-2 text-xs text-brand">{{ $message }}</p> @enderror
            </div>

            <div class="card p-5">
                <h5 class="card-header-title mb-1">Absen pulang</h5>
                <p class="mb-4 text-sm text-muted">GPS harus di titik kafe. Selfie tidak wajib saat pulang.</p>

                <form method="POST" action="{{ route('attendance.clock-out') }}" @submit="prepareOut($event)">
                    @csrf
                    <input type="hidden" name="latitude" x-model="lat">
                    <input type="hidden" name="longitude" x-model="lng">
                    <button
                        type="submit"
                        class="btn-primary"
                        @disabled(! $today?->clock_in_at || $today?->clock_out_at)
                        :disabled="!gpsOk"
                    >
                        Absen pulang
                    </button>
                </form>
                @error('clock_out') <p class="mt-2 text-xs text-brand">{{ $message }}</p> @enderror

                @if ($today?->selfieUrl())
                    <div class="mt-6">
                        <p class="label mb-2">Selfie hari ini</p>
                        <img src="{{ $today->selfieUrl() }}" alt="Selfie" class="max-h-48 rounded-xl border border-line object-cover">
                        @if ($today->is_late)
                            <p class="mt-2 text-xs font-medium text-amber-600">Tercatat telat{{ $today->late_reason ? ': '.$today->late_reason : '' }}.</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Kalender absensi</h5>
                    <p class="card-header-subtitle">{{ $calendar['label'] }} · jam WIB</p>
                </div>
            </div>
            <style>
                .recap-grid th, .recap-grid td { padding: 0.45rem 0.25rem; text-align: center; vertical-align: middle; }
                .list-table.recap-grid tbody tr td.recap-ok { background: #e7f6ec; }
                .list-table.recap-grid tbody tr td.recap-late { background: #fff1e0; }
                .list-table.recap-grid tbody tr td.recap-off { background: #fff3bf; }
                .list-table.recap-grid tbody tr td.recap-miss { background: #f8b4b4; }
                .recap-time { display: block; font-size: 11px; line-height: 1.3; font-variant-numeric: tabular-nums; }
                .recap-flag { display: block; font-size: 10px; font-weight: 700; color: #c2410c; }
                .recap-dow { display: block; font-size: 10px; font-weight: 500; }
                .recap-weekend { color: #e8192c; }
            </style>
            <div class="table-wrap">
                <table class="list-table recap-grid">
                    <thead>
                        <tr>
                            @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $name)
                                <th class="{{ in_array($name, ['Min', 'Sab'], true) ? 'recap-weekend' : '' }}">{{ $name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($calendar['weeks'] as $week)
                            <tr>
                                @foreach ($week as $day)
                                    @php
                                        $class = '';
                                        if ($day) {
                                            $class = $day['cell']
                                                ? ($day['cell']['late'] ? 'recap-late' : 'recap-ok')
                                                : ($day['off'] ? 'recap-off' : ($day['miss'] ? 'recap-miss' : ''));
                                        }
                                    @endphp
                                    <td class="{{ $class }}" @if ($day['cell']['reason'] ?? null) title="{{ $day['cell']['reason'] }}" @endif>
                                        @if ($day)
                                            <span class="recap-dow {{ $day['weekend'] ? 'recap-weekend' : '' }}">{{ $day['day'] }}</span>
                                            @if ($day['cell'])
                                                <span class="recap-time">{{ $day['cell']['in'] }}</span>
                                                <span class="recap-time">{{ $day['cell']['out'] ?: '—' }}</span>
                                                @if ($day['cell']['late'])<span class="recap-flag">Telat</span>@endif
                                            @elseif ($day['off'])
                                                <span class="recap-time">L</span>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($team)
        <div class="mt-5 card overflow-hidden">
            <div class="card-header">
                <h5 class="card-header-title">Absensi tim hari ini</h5>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Posisi</th>
                            <th>Masuk</th>
                            <th>Pulang</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($team as $row)
                            <tr>
                                <td>{{ $row->employee?->user?->name }}</td>
                                <td>{{ $row->employee?->position }}</td>
                                <td>{{ $row->clock_in_at?->format('H:i') ?? '—' }}</td>
                                <td>{{ $row->clock_out_at?->format('H:i') ?? '—' }}</td>
                                <td>{{ $row->is_late ? 'Telat' : 'Tepat waktu' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-sm text-slate-400">Belum ada yang absen.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
function attendanceCam() {
    return {
        stream: null,
        selfie: '',
        lat: '',
        lng: '',
        gpsOk: false,
        gpsLabel: 'Mengambil lokasi…',
        get canClockIn() {
            return this.gpsOk && !!this.selfie && !!this.lat && !!this.lng;
        },
        init() {
            this.refreshGps();
        },
        refreshGps() {
            if (!navigator.geolocation) {
                this.gpsLabel = 'Browser tidak mendukung GPS.';
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.lat = pos.coords.latitude.toFixed(7);
                    this.lng = pos.coords.longitude.toFixed(7);
                    this.gpsOk = true;
                    this.gpsLabel = `GPS OK · ${this.lat}, ${this.lng}`;
                },
                () => {
                    this.gpsOk = false;
                    this.gpsLabel = 'Gagal ambil GPS. Izinkan lokasi di browser.';
                },
                { enableHighAccuracy: true, timeout: 15000 }
            );
        },
        async startCamera() {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' },
                    audio: false,
                });
                this.$refs.video.srcObject = this.stream;
            } catch (e) {
                alert('Tidak bisa membuka kamera. Izinkan akses kamera.');
            }
        },
        capture() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            canvas.getContext('2d').drawImage(video, 0, 0);
            this.selfie = canvas.toDataURL('image/jpeg', 0.85);
            this.stopCamera();
        },
        retake() {
            this.selfie = '';
            this.startCamera();
        },
        stopCamera() {
            this.stream?.getTracks()?.forEach((t) => t.stop());
            this.stream = null;
        },
        prepareSubmit(e) {
            this.refreshGps();
            if (!this.canClockIn) {
                e.preventDefault();
                alert('GPS dan selfie wajib diisi.');
            }
        },
        prepareOut(e) {
            this.refreshGps();
            if (!this.gpsOk) {
                e.preventDefault();
                alert('GPS wajib aktif untuk absen pulang.');
            }
        },
    }
}
</script>
@endpush
