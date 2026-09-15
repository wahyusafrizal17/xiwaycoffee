@extends('layouts.app')
@section('title', 'Loyalty')
@section('breadcrumb', 'Marketing')
@section('content')
    <div x-data="loyaltyPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total member</p>
                    <p class="stat-value">{{ number_format($stats['members']) }}</p>
                    <p class="stat-hint">Pelanggan terdaftar</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M11 11a4 4 0 100-8 4 4 0 000 8M21 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Poin beredar</p>
                    <p class="stat-value">{{ number_format($stats['points']) }}</p>
                    <p class="stat-hint">Saldo poin seluruh member</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Reward aktif</p>
                    <p class="stat-value">{{ number_format($stats['rewards']) }}</p>
                    <p class="stat-hint">Bisa ditukar dengan poin</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 12v10H4V12M2 7h20v5H2V7zM12 22V7M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar member</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    <a href="{{ route('loyalty.rewards') }}" class="btn-ghost">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 12v10H4V12M2 7h20v5H2V7zM12 22V7M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>
                        Kelola rewards
                    </a>
                </div>
            </div>

            <form id="loyalty-filters" method="GET" action="{{ route('loyalty.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Nama</th>
                            <th>No. HP</th>
                            <th>Level</th>
                            <th>Poin</th>
                            <th>Total belanja</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="loyalty-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="loyalty-filters" class="col-filter" type="search" name="phone" value="{{ $filters['phone'] ?? '' }}" placeholder="No. HP..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="loyalty-filters" class="col-filter" name="membership_level" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach (\App\Enums\MembershipLevel::cases() as $level)
                                        <option value="{{ $level->value }}" @selected(($filters['membership_level'] ?? '') === $level->value)>{{ $level->label() }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            @php $row = $customer->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $customers->firstItem() + $loop->index }}</td>
                                <td>
                                    <button type="button" class="font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $customer->name }}</button>
                                </td>
                                <td>{{ $customer->phone ?: '—' }}</td>
                                <td>
                                    <x-status :value="$customer->membership_level?->color() ?? 'gray'">{{ $customer->membership_level?->label() ?? '—' }}</x-status>
                                </td>
                                <td class="font-semibold">{{ number_format((int) $customer->points) }}</td>
                                <td>{{ money($customer->total_transaction) }}</td>
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
                                <td colspan="7" class="py-16 text-center text-sm text-slate-400">Tidak ada member yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $customers->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-heading" x-text="viewing?.name || 'Detail Member'"></h3>
                            <p class="mt-1 text-[13px] text-muted">
                                <span x-text="viewing?.code || '—'"></span>
                                ·
                                <span x-text="viewing?.membership_label || '—'"></span>
                            </p>
                        </div>
                        <button type="button" class="modal-close" @click="viewOpen = false">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Poin</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.points_label || '0'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Total belanja</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.total_transaction_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Transaksi terakhir</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.last_transaction_label || '—'"></p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[12px] text-muted">No. HP</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.phone || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Email</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.email || '—'"></dd>
                        </div>
                    </dl>

                    @can('loyalty.manage')
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <form method="POST" class="rounded-xl border border-line p-4" :action="viewing?.points_url">
                                @csrf
                                <p class="text-sm font-semibold text-heading">Sesuaikan poin</p>
                                <p class="mt-1 text-[12px] text-muted">Gunakan angka negatif untuk mengurangi.</p>
                                <input class="input mt-3" type="number" name="points" required placeholder="+10 atau -10">
                                <input class="input mt-2" name="reason" required maxlength="255" placeholder="Alasan">
                                <button class="btn-ghost mt-3 w-full" type="submit">Simpan poin</button>
                            </form>
                            <form method="POST" class="rounded-xl border border-line p-4" :action="viewing?.rewards_url">
                                @csrf
                                <p class="text-sm font-semibold text-heading">Tukar reward</p>
                                <p class="mt-1 text-[12px] text-muted">Poin member akan dipotong sesuai syarat.</p>
                                <select class="input mt-3" name="reward_id" required>
                                    <option value="">Pilih reward</option>
                                    @foreach ($rewards as $reward)
                                        <option value="{{ $reward->id }}">{{ $reward->name }} · {{ number_format($reward->points_required) }} poin</option>
                                    @endforeach
                                </select>
                                <button class="btn-add mt-3 w-full" type="submit" @disabled($rewards->isEmpty())>Tukarkan</button>
                            </form>
                        </div>
                    @endcan
                </div>
                <div class="crud-modal-footer">
                    <button type="button" class="btn-ghost" @click="viewOpen = false">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function loyaltyPage() {
            return {
                viewOpen: false,
                viewing: null,
                openView(row) {
                    if (! row) return;
                    this.viewing = row;
                    this.viewOpen = true;
                },
                closeTop() {
                    if (this.viewOpen) this.viewOpen = false;
                },
            };
        }
    </script>
@endpush
