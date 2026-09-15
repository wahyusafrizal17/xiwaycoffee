@extends('layouts.app')
@section('title', 'Audit Logs')
@section('breadcrumb', 'Settings')
@section('content')
    @php
        $requestedModal = request('modal');
    @endphp
    <div x-data="auditPage()" @keydown.escape.window="viewOpen = false">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total log</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua aktivitas tercatat</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5h6M9 9h6M5 5h.01M5 9h.01M5 13h14M5 17h14M5 21h14"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Hari ini</p>
                    <p class="stat-value">{{ number_format($stats['today']) }}</p>
                    <p class="stat-hint">{{ now()->format('d/m/Y') }}</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Modul</p>
                    <p class="stat-value">{{ number_format($stats['modules']) }}</p>
                    <p class="stat-hint">Sumber aktivitas unik</p>
                </div>
                <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h7v5H4zM13 6h7v5h-7zM4 13h7v5H4zM13 13h7v5h-7z"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Log audit</h5>
                    <p class="card-header-subtitle">Riwayat aksi pengguna dan perubahan sistem.</p>
                </div>
            </div>

            <form id="audit-filters" method="GET" action="{{ route('audit.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Modul</th>
                            <th>Aksi</th>
                            <th>IP</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="audit-filters" class="col-filter" type="date" name="date" value="{{ $filters['date'] ?? '' }}" onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="audit-filters" class="col-filter" type="search" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="audit-filters" class="col-filter" name="module" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach ($modules as $module)
                                        <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th>
                                <input form="audit-filters" class="col-filter" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Aksi..." onchange="this.form.submit()">
                            </th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php $row = $log->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $logs->firstItem() + $loop->index }}</td>
                                <td class="text-[13px] text-muted">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ $log->user?->name ?? 'Sistem' }}</td>
                                <td><span class="badge-soft">{{ $log->module }}</span></td>
                                <td>
                                    <button type="button" class="text-left font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $log->action }}</button>
                                    <p class="mt-0.5 text-[12px] text-muted">{{ $log->auditableLabel() }}</p>
                                </td>
                                <td class="text-[13px] text-muted">{{ $log->ip_address ?: '—' }}</td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-16 text-center text-sm text-slate-400">Tidak ada log audit yang cocok dengan filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $logs->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.action || 'Detail Log'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.module || '—'"></span>
                                ·
                                <span x-text="viewing?.created_at || '—'"></span>
                            </p>
                        </div>
                        <button type="button" class="modal-close" @click="viewOpen = false">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">User</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.user_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Referensi</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.auditable_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">IP</p>
                            <p class="mt-1 text-sm font-semibold" x-text="viewing?.ip_address || '—'"></p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Nilai lama</p>
                            <pre class="max-h-48 overflow-auto rounded-xl bg-[#fafafa] p-4 text-[12px] leading-relaxed text-heading" x-text="viewing?.old_json || '{}'"></pre>
                        </div>
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Nilai baru</p>
                            <pre class="max-h-48 overflow-auto rounded-xl bg-[#fafafa] p-4 text-[12px] leading-relaxed text-heading" x-text="viewing?.new_json || '{}'"></pre>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl bg-[#fafafa] px-4 py-3">
                        <p class="stat-kicker">User agent</p>
                        <p class="mt-1 break-all text-[12px] font-medium text-heading" x-text="viewing?.user_agent || '—'"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function auditPage() {
            const focus = @json($focusPayload);
            const requestedModal = @json($requestedModal);

            return {
                viewOpen: requestedModal === 'view' && !!focus,
                viewing: focus,
                openView(row) {
                    if (! row) return;
                    this.viewing = row;
                    this.viewOpen = true;
                },
            };
        }
    </script>
@endpush
