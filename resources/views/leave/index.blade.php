@extends('layouts.app')
@section('title', 'Izin')
@section('content')
    @php
        $canApply = $shift?->starts_at && $backups->isNotEmpty();
        $dateObj = \Illuminate\Support\Carbon::parse($date)->locale('id');
        $openModal = $errors->any() || request()->boolean('open');
        $statusTone = [
            'pending' => ['Menunggu', 'bg-amber-50 text-amber-800 ring-amber-200'],
            'approved' => ['Disetujui', 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
            'rejected' => ['Ditolak', 'bg-rose-50 text-rose-800 ring-rose-200'],
        ];
    @endphp

    <div
        class="leave-page mx-auto max-w-2xl"
        x-data="{ open: {{ $openModal ? 'true' : 'false' }} }"
        @keydown.escape.window="open = false"
    >
        <div class="leave-hero mb-6 overflow-hidden rounded-2xl border border-line bg-white">
            <div class="leave-hero-glow px-5 py-6 sm:px-7 sm:py-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-brand">Izin kerja</p>
                        <h1 class="mt-2 font-serif text-[1.85rem] font-semibold leading-none text-heading">Ajukan izin</h1>
                        <p class="mt-3 max-w-md text-[13px] leading-relaxed text-muted">Pilih backup, tunggu mereka setujui. Shift digabung ke jam mereka. Tanpa approve dan tidak masuk: denda 50% gaji per hari.</p>
                    </div>
                    <button
                        type="button"
                        class="btn-primary shrink-0 !rounded-xl !px-5"
                        @click="open = true"
                        @if (! $canApply && ! $shift?->starts_at) disabled @endif
                    >
                        Ajukan izin
                    </button>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-white/80 px-4 py-3 ring-1 ring-line">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted">Tanggal</p>
                        <p class="mt-1 text-sm font-medium text-heading">{{ $dateObj->translatedFormat('l, j F Y') }}</p>
                    </div>
                    <div class="rounded-xl bg-white/80 px-4 py-3 ring-1 ring-line">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted">Shift kamu</p>
                        <p class="mt-1 text-sm font-medium text-heading">
                            @if (! $shift)
                                Tidak ada jadwal
                            @elseif (! $shift->starts_at)
                                OFF
                            @else
                                {{ $shift->label() }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-emerald-100">{{ session('success') }}</div>
        @endif

        @if ($incoming->isNotEmpty())
            <section class="mb-5">
                <div class="mb-3 flex items-end justify-between gap-2">
                    <h2 class="font-serif text-lg font-semibold text-heading">Menunggu kamu</h2>
                    <span class="rounded-full bg-brand/10 px-2.5 py-0.5 text-[12px] font-semibold text-brand">{{ $incoming->count() }}</span>
                </div>
                <div class="space-y-3">
                    @foreach ($incoming as $row)
                        @php($req = $row['request'])
                        <article class="card overflow-hidden !rounded-2xl">
                            <div class="border-l-4 border-brand px-4 py-4 sm:px-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-base font-semibold text-heading">{{ $req->employee?->user?->name }}</p>
                                        <p class="mt-1 text-[13px] text-muted">{{ $req->work_date->locale('id')->translatedFormat('l, j M Y') }}</p>
                                        <p class="mt-2 text-[13px] text-heading">{{ $req->reason ?: 'Tanpa keterangan' }}</p>
                                        @if ($row['merged_label'])
                                            <p class="mt-2 inline-flex items-center rounded-lg bg-slate-50 px-2.5 py-1 text-[12px] font-medium text-heading ring-1 ring-line">
                                                Shift kamu jadi {{ $row['merged_label'] }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="flex w-full gap-2 sm:w-auto">
                                        <form class="flex-1 sm:flex-none" method="POST" action="{{ route('leave.decide', $req) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="rejected">
                                            <button class="btn-secondary w-full !rounded-xl" type="submit">Tolak</button>
                                        </form>
                                        <form class="flex-1 sm:flex-none" method="POST" action="{{ route('leave.decide', $req) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="approved">
                                            <button class="btn-primary w-full !rounded-xl" type="submit">Setujui</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <div class="mb-3 flex items-end justify-between gap-2">
                <h2 class="font-serif text-lg font-semibold text-heading">Pengajuan saya</h2>
            </div>
            <div class="card divide-y divide-line !rounded-2xl overflow-hidden">
                @forelse ($mine as $req)
                    @php($tone = $statusTone[$req->status] ?? $statusTone['pending'])
                    <div class="flex items-center justify-between gap-3 px-4 py-3.5 sm:px-5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-heading">{{ $req->work_date->locale('id')->translatedFormat('j M Y') }} · {{ $req->backup?->user?->name }}</p>
                            @if ($req->reason)
                                <p class="mt-0.5 truncate text-[13px] text-muted">{{ $req->reason }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $tone[1] }}">{{ $tone[0] }}</span>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm text-muted">Belum ada pengajuan. Tekan <span class="font-medium text-heading">Ajukan izin</span> untuk mulai.</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Modal ajukan --}}
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/45 p-0 sm:items-center sm:p-4"
            x-show="open"
            x-cloak
            x-transition.opacity
            @click.self="open = false"
        >
            <div
                class="crud-modal leave-modal !max-w-lg !rounded-t-2xl sm:!rounded-2xl"
                x-show="open"
                x-transition
                @click.stop
            >
                <form method="POST" action="{{ route('leave.store') }}">
                    @csrf
                    <div class="crud-modal-body !max-h-[85vh] overflow-y-auto">
                        <div class="mb-5 flex items-start justify-between gap-3">
                            <div>
                                <h5 class="card-header-title">Ajukan izin</h5>
                                <p class="card-header-subtitle">Pilih tanggal dan siapa yang backup.</p>
                            </div>
                            <button type="button" class="modal-close" @click="open = false" aria-label="Tutup">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="grid gap-4">
                            <div>
                                <label class="label">Tanggal</label>
                                <input
                                    class="input"
                                    type="date"
                                    name="work_date"
                                    value="{{ old('work_date', $date) }}"
                                    min="{{ $today }}"
                                    required
                                    onchange="window.location='{{ route('leave.index') }}?date='+this.value+'&open=1'"
                                >
                                @error('work_date') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                            </div>

                            @if (! $shift)
                                <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-muted ring-1 ring-line">Tidak ada jadwal di tanggal ini.</div>
                            @elseif (! $shift->starts_at)
                                <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-muted ring-1 ring-line">Kamu OFF di tanggal ini. Tidak perlu izin.</div>
                            @elseif ($backups->isEmpty())
                                <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-muted ring-1 ring-line">Tidak ada karyawan lain di jadwal tanggal ini.</div>
                            @else
                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <label class="label !mb-0">Shift kamu</label>
                                        <span class="text-[13px] font-medium text-heading">{{ $shift->label() }}</span>
                                    </div>
                                    <label class="label">Pilih backup</label>
                                    <div class="mt-1 grid gap-2">
                                        @foreach ($backups as $row)
                                            <label class="leave-backup group relative cursor-pointer">
                                                <input
                                                    class="peer sr-only"
                                                    type="radio"
                                                    name="backup_employee_id"
                                                    value="{{ $row['employee']->id }}"
                                                    @checked((string) old('backup_employee_id') === (string) $row['employee']->id)
                                                    required
                                                >
                                                <div class="rounded-xl px-4 py-3 ring-1 ring-line transition peer-checked:bg-brand/[0.04] peer-checked:ring-2 peer-checked:ring-brand">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-semibold text-heading">{{ $row['employee']->user?->name }}</p>
                                                            <p class="mt-0.5 text-[12px] text-muted">Sekarang {{ $row['label'] }}</p>
                                                        </div>
                                                        <div class="text-right">
                                                            <p class="text-[11px] uppercase tracking-wide text-muted">Jadi</p>
                                                            <p class="text-[13px] font-semibold text-brand">{{ $row['merged'] }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('backup_employee_id') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="label">Alasan <span class="font-normal text-muted">(opsional)</span></label>
                                    <input class="input" type="text" name="reason" value="{{ old('reason') }}" maxlength="255" placeholder="Misal: urusan keluarga">
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-secondary !rounded-xl" @click="open = false">Batal</button>
                        @if ($canApply)
                            <button class="btn-primary !rounded-xl" type="submit">Kirim pengajuan</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .leave-hero-glow {
            background:
                radial-gradient(120% 80% at 100% 0%, color-mix(in srgb, var(--color-brand, #e8192c) 14%, transparent), transparent 55%),
                linear-gradient(180deg, #fff 0%, #fafafa 100%);
        }
        .leave-page { padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px)); }
        @media (min-width: 1024px) {
            .leave-page { padding-bottom: 1.5rem; }
        }
    </style>
@endsection
