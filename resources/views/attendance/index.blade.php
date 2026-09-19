@extends('layouts.app')
@section('title', 'Absensi')
@section('breadcrumb', 'Karyawan')
@section('content')
    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Batas tepat waktu</p>
                <p class="stat-value">{{ $lateAfter }}</p>
                <p class="stat-hint">Lewat jam ini dihitung telat</p>
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
                        @if (! $today?->clock_in_at)
                            Belum absen
                        @elseif (! $today?->clock_out_at)
                            {{ $today->is_late ? 'Masuk (telat)' : 'Masuk' }}
                        @else
                            Selesai
                        @endif
                    </p>
                    <p class="stat-hint">{{ $outlet?->name }}</p>
                </div>
            </div>
        @endif
    </div>

    @if (! $employee && ! auth()->user()->hasPermission('attendance.manage'))
        <div class="card p-6 text-sm text-muted">Akun ini belum terdaftar sebagai karyawan.</div>
    @endif

    @if ($employee)
        <div class="mb-5 grid gap-4 lg:grid-cols-2" x-data="attendanceCam()">
            <div class="card p-5">
                <h5 class="card-header-title mb-1">Absen masuk</h5>
                <p class="mb-4 text-sm text-muted">Wajib GPS di area ruko + selfie. Pastikan izinkan kamera & lokasi.</p>

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
                <p class="mb-4 text-sm text-muted">GPS harus di area ruko. Selfie tidak wajib saat pulang.</p>

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
                            <p class="mt-2 text-xs font-medium text-amber-600">Tercatat telat (melewati {{ $lateAfter }}).</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <h5 class="card-header-title">Riwayat 14 hari</h5>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Masuk</th>
                            <th>Pulang</th>
                            <th>Status</th>
                            <th>Jarak</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $row)
                            <tr>
                                <td>{{ $row->work_date?->format('d/m/Y') }}</td>
                                <td>{{ $row->clock_in_at?->format('H:i') ?? '—' }}</td>
                                <td>{{ $row->clock_out_at?->format('H:i') ?? '—' }}</td>
                                <td>
                                    @if ($row->is_late)
                                        <span class="text-amber-600">Telat</span>
                                    @else
                                        <span class="text-emerald-600">Tepat waktu</span>
                                    @endif
                                </td>
                                <td>{{ $row->clock_in_distance_m !== null ? $row->clock_in_distance_m.' m' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-sm text-slate-400">Belum ada riwayat.</td></tr>
                        @endforelse
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
