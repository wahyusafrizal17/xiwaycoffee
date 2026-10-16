@extends('layouts.app')
@section('title', 'Izin')
@section('content')
    <div class="mb-5">
        <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Izin tidak masuk</h1>
        <p class="mt-2 max-w-xl text-[13px] text-muted">Pilih backup (OFF atau shift lain). Setelah approve, kamu jadi OFF dan backup menutupi shift kamu — kalau dia shift siang, jamnya digabung dari shift kamu sampai akhir shift dia. Kalau ditolak / tidak ada yang mau dan kamu tidak masuk: denda 50% gaji per hari.</p>
    </div>

    @if (session('success'))
        <p class="mb-4 text-sm text-heading">{{ session('success') }}</p>
    @endif

    @if ($incoming->isNotEmpty())
        <div class="card mb-4 divide-y divide-line">
            <div class="px-4 py-3">
                <p class="text-sm font-medium text-heading">Perlu approve kamu ({{ $incoming->count() }})</p>
            </div>
            @foreach ($incoming as $row)
                @php($req = $row['request'])
                <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-heading">{{ $req->employee?->user?->name }} · {{ $req->work_date->format('d/m/Y') }}</p>
                        <p class="mt-1 text-[13px] text-muted">{{ $req->reason ?: 'Tanpa keterangan' }}</p>
                        @if ($row['merged_label'])
                            <p class="mt-1 text-[13px] text-muted">Kalau setujui, shift kamu jadi {{ $row['merged_label'] }}</p>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('leave.decide', $req) }}">
                            @csrf
                            <input type="hidden" name="decision" value="rejected">
                            <button class="btn-secondary" type="submit">Tolak</button>
                        </form>
                        <form method="POST" action="{{ route('leave.decide', $req) }}">
                            @csrf
                            <input type="hidden" name="decision" value="approved">
                            <button class="btn-primary" type="submit">Setujui</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card mb-4 p-4">
        <form method="GET" class="mb-4">
            <label class="label">Tanggal izin</label>
            <input class="input max-w-xs" type="date" name="date" value="{{ $date }}" min="{{ $today }}" onchange="this.form.submit()">
        </form>

        @if (! $shift)
            <p class="text-sm text-muted">Tidak ada jadwal di tanggal ini.</p>
        @elseif (! $shift->starts_at)
            <p class="text-sm text-muted">Kamu OFF di tanggal ini. Tidak perlu izin.</p>
        @else
            <p class="mb-3 text-sm text-heading">Shift kamu: {{ $shift->label() }}</p>
            @if ($backups->isEmpty())
                <p class="text-sm text-muted">Tidak ada karyawan lain di jadwal tanggal ini.</p>
            @else
                <form method="POST" action="{{ route('leave.store') }}" class="grid gap-3">
                    @csrf
                    <input type="hidden" name="work_date" value="{{ $date }}">
                    <div>
                        <label class="label">Backup</label>
                        <select class="input" name="backup_employee_id" required>
                            <option value="">Pilih backup</option>
                            @foreach ($backups as $row)
                                <option value="{{ $row['employee']->id }}" @selected(old('backup_employee_id') == $row['employee']->id)>
                                    {{ $row['employee']->user?->name }} · {{ $row['label'] }} → jadi {{ $row['merged'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('backup_employee_id') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                        @error('work_date') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Alasan (opsional)</label>
                        <input class="input" type="text" name="reason" value="{{ old('reason') }}" maxlength="255" placeholder="Misal: urusan keluarga">
                    </div>
                    <button class="btn-primary w-fit" type="submit">Ajukan izin</button>
                </form>
            @endif
        @endif
    </div>

    <div class="card divide-y divide-line">
        <div class="px-4 py-3">
            <p class="text-sm font-medium text-heading">Pengajuan saya</p>
        </div>
        @forelse ($mine as $req)
            <div class="px-4 py-3">
                <p class="text-sm font-medium text-heading">{{ $req->work_date->format('d/m/Y') }} · {{ $req->backup?->user?->name }}</p>
                <p class="mt-1 text-[13px] text-muted">
                    @if ($req->status === 'pending') Menunggu approve
                    @elseif ($req->status === 'approved') Disetujui
                    @else Ditolak
                    @endif
                    @if ($req->reason) · {{ $req->reason }} @endif
                </p>
            </div>
        @empty
            <p class="p-4 text-sm text-muted">Belum ada pengajuan.</p>
        @endforelse
    </div>
@endsection
