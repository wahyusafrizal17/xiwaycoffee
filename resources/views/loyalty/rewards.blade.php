@extends('layouts.app')
@section('title', 'Rewards')
@section('breadcrumb', 'Loyalty')
@section('content')
    @php
        $formError = $errors->any();
    @endphp
    <div x-data="rewardPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total reward</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua hadiah terdaftar</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 12v10H4V12M2 7h20v5H2V7zM12 22V7M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Aktif</p>
                    <p class="stat-value">{{ number_format($stats['active']) }}</p>
                    <p class="stat-hint">Bisa ditukar member</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Nonaktif</p>
                    <p class="stat-value">{{ number_format($stats['inactive']) }}</p>
                    <p class="stat-hint">Tidak tampil untuk ditukar</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar reward</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    <a href="{{ route('loyalty.index') }}" class="btn-ghost">Kembali</a>
                    @can('loyalty.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Tambah reward
                        </button>
                    @endcan
                </div>
            </div>

            <form id="reward-filters" method="GET" action="{{ route('loyalty.rewards') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Nama</th>
                            <th>Poin</th>
                            <th>Nilai</th>
                            <th>Status</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="reward-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th></th>
                            <th></th>
                            <th>
                                <select form="reward-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                                </select>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rewards as $reward)
                            @php $row = $reward->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $rewards->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $reward->name }}</button>
                                    @if ($reward->description)
                                        <p class="mt-0.5 text-[12px] text-muted">{{ $reward->description }}</p>
                                    @endif
                                </td>
                                <td class="font-semibold">{{ number_format($reward->points_required) }}</td>
                                <td>{{ (float) $reward->value > 0 ? money($reward->value) : '—' }}</td>
                                <td>
                                    <x-status :value="$reward->is_active ? 'green' : 'gray'">{{ $reward->is_active ? 'Aktif' : 'Nonaktif' }}</x-status>
                                </td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                        @can('loyalty.manage')
                                            <form method="POST" action="{{ route('loyalty.rewards.toggle', $reward) }}">
                                                @csrf
                                                <button type="submit" class="table-action {{ $reward->is_active ? '' : 'table-action-danger' }}" title="{{ $reward->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                    @if ($reward->is_active)
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    @else
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    @endif
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-16 text-center text-sm text-slate-400">Tidak ada reward yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rewards->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $rewards->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.name || 'Detail Reward'"></h3>
                            <p class="mt-1 text-[13px] text-muted" x-text="viewing?.status_label || '—'"></p>
                        </div>
                        <button type="button" class="modal-close" @click="viewOpen = false">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Poin dibutuhkan</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.points_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Nilai</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.value_label || '—'"></p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <p class="text-[12px] text-muted">Deskripsi</p>
                        <p class="mt-1 text-sm font-medium" x-text="viewing?.description || '—'"></p>
                    </div>
                </div>
                @can('loyalty.manage')
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="viewOpen = false">Tutup</button>
                        <form method="POST" :action="viewing?.toggle_url">
                            @csrf
                            <button class="btn-add" type="submit" x-text="viewing?.is_active ? 'Nonaktifkan' : 'Aktifkan'"></button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        @can('loyalty.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('loyalty.rewards.store') }}">
                        @csrf
                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading">Tambah Reward</h3>
                                    <p class="mt-1 text-[13px] text-muted">Hadiah yang aktif bisa ditukar dari halaman loyalty.</p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4">
                                <div>
                                    <label class="label">Nama</label>
                                    <input class="input" name="name" required maxlength="120" value="{{ old('name') }}" placeholder="Contoh: Kopi gratis">
                                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Deskripsi</label>
                                    <textarea class="input min-h-24" name="description" placeholder="Opsional">{{ old('description') }}</textarea>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="label">Poin dibutuhkan</label>
                                        <input class="input" type="number" min="1" name="points_required" required value="{{ old('points_required') }}" placeholder="100">
                                        @error('points_required')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="label">Nilai</label>
                                        <input class="input" type="number" step="0.01" min="0" name="value" value="{{ old('value') }}" placeholder="Opsional">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="crud-modal-footer">
                            <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                            <button class="btn-add" type="submit">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        function rewardPage() {
            const formError = @json($formError);

            return {
                formOpen: formError,
                viewOpen: false,
                viewing: null,
                openCreate() {
                    this.viewOpen = false;
                    this.formOpen = true;
                },
                openView(row) {
                    if (! row) return;
                    this.viewing = row;
                    this.formOpen = false;
                    this.viewOpen = true;
                },
                closeTop() {
                    if (this.formOpen) this.formOpen = false;
                    else if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
