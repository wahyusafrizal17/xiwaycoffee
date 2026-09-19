@extends('layouts.app')
@section('title', 'BOP')
@section('breadcrumb', 'Operations')
@section('content')
    @php
        $formError = $errors->any();
        $formOld = [
            'spent_on' => old('spent_on', now()->toDateString()),
            'category' => (string) old('category', \App\Enums\ExpenseCategory::Ingredients->value),
            'amount' => old('amount', ''),
            'notes' => old('notes', ''),
        ];
        $pct = $bopMonthly > 0 ? min(100, (int) round(($total / $bopMonthly) * 100)) : 0;
        $badge = fn (string $value) => match ($value) {
            'bahan' => 'bg-[#fff3e8] text-[#c2410c]',
            'sewa' => 'bg-[#e8f1ff] text-[#2563eb]',
            'gaji' => 'bg-[#eef6f0] text-[#166534]',
            'listrik' => 'bg-[#fff8e6] text-[#a16207]',
            'wifi' => 'bg-[#e8f7f8] text-[#0f766e]',
            'iuran' => 'bg-[#f3f3f3] text-[#525252]',
            default => 'bg-brand-soft text-brand',
        };
    @endphp

    <div x-data="bopPage()" @keydown.escape.window="formOpen = false">
        @if (auth()->user()->hasPermission('reports.view'))
            @include('reports._nav')
        @endif

        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Biaya operasional</p>
                <h1 class="mt-1 font-serif text-[1.75rem] font-semibold leading-none text-heading">BOP</h1>
                <p class="mt-2 max-w-xl text-[13px] text-muted">Pantau rencana bulanan dan catat belanja harian tanpa meninggalkan halaman ini.</p>
            </div>
            <button type="button" class="btn-add" @click="openCreate()">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                Catat BOP
            </button>
        </div>

        <div class="mb-5 grid gap-4 lg:grid-cols-2">
            <div class="stat-card">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Total tercatat</p>
                    <p class="stat-value">{{ money($total) }}</p>
                    <p class="stat-hint">{{ $pct }}% dari rencana bulan ini</p>
                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-[#f0ebe4]">
                        <div class="h-full rounded-full bg-brand transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-12V5m0 14v-2"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Rencana / bulan</p>
                    <p class="stat-value">{{ money($bopMonthly) }}</p>
                    <p class="stat-hint">Target omzet minuman minimal sebesar ini</p>
                </div>
                <span class="stat-icon bg-[#fff3e8] text-[#c2410c]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 13h4l3 8 4-16 3 8h4"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Riwayat pengeluaran</h5>
                    <p class="card-header-subtitle">Catatan BOP outlet yang sedang dipilih.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th class="text-right">Nominal</th>
                            <th>Keterangan</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            @php $cat = $expense->category?->value ?? (string) $expense->category; @endphp
                            <tr>
                                <td class="whitespace-nowrap text-[13px] text-muted">{{ $expense->spent_on?->format('d M Y') }}</td>
                                <td>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge($cat) }}">
                                        {{ $expense->category?->label() ?? $expense->category }}
                                    </span>
                                </td>
                                <td class="text-right font-medium tabular-nums">{{ money($expense->amount) }}</td>
                                <td class="max-w-[280px] truncate text-[13px] text-muted">{{ $expense->notes ?: '—' }}</td>
                                <td class="text-[13px]">{{ $expense->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="!border-0">
                                    <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                                        <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#f6f3ef] text-muted">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-12V5m0 14v-2"/></svg>
                                        </span>
                                        <p class="text-sm font-medium text-heading">Belum ada BOP</p>
                                        <p class="mt-1 text-[13px] text-muted">Catat belanja atau biaya operasional pertama.</p>
                                        <button type="button" class="btn-add mt-5" @click="openCreate()">
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                                            Catat BOP
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($expenses->hasPages())
                <div class="border-t border-line px-4 py-4">{{ $expenses->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
            <div class="crud-modal" @click.stop>
                <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('reports.expenses.store') }}">
                    @csrf
                    <div class="crud-modal-body">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-serif text-lg font-semibold text-heading">Catat BOP</h3>
                                <p class="mt-1 text-[13px] text-muted">Simpan belanja atau biaya operasional outlet.</p>
                            </div>
                            <button type="button" class="modal-close" @click="formOpen = false">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label">Tanggal</label>
                                <input class="input" type="date" name="spent_on" required x-model="form.spent_on">
                                @error('spent_on')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Kategori</label>
                                <select class="input" name="category" required x-model="form.category">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->value }}">{{ $category->label() }}</option>
                                    @endforeach
                                </select>
                                @error('category')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label">Nominal</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-muted">Rp</span>
                                    <input class="input pl-10" type="number" name="amount" min="0.01" step="0.01" required x-model="form.amount" placeholder="0">
                                </div>
                                @error('amount')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label">Keterangan</label>
                                <input class="input" type="text" name="notes" x-model="form.notes" placeholder="Sewa, belanja bahan, …" maxlength="255">
                                @error('notes')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                        <button class="btn-add" type="submit">Simpan BOP</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function bopPage() {
            const emptyForm = () => ({
                spent_on: @json(now()->toDateString()),
                category: @json(\App\Enums\ExpenseCategory::Ingredients->value),
                amount: '',
                notes: '',
            });

            const formError = @json($formError);
            const formOld = @json($formOld);
            let form = emptyForm();
            if (formError) {
                form = { ...emptyForm(), ...formOld };
            }

            return {
                formOpen: formError,
                form,
                serverFormError: formError,
                openCreate() {
                    this.form = emptyForm();
                    this.serverFormError = false;
                    this.formOpen = true;
                },
            };
        }
    </script>
@endpush
