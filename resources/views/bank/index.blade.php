@extends('layouts.app')
@section('title', 'Rekening')
@section('breadcrumb', 'Keuangan')
@section('content')
    @php
        $formError = $errors->any();
        $openDeposit = $formError && old('_form') === 'deposit';
        $openAdjust = $formError && old('_form') === 'adjust';
    @endphp

    <div x-data="{ depositOpen: {{ $openDeposit ? 'true' : 'false' }}, adjustOpen: {{ $openAdjust ? 'true' : 'false' }} }" @keydown.escape.window="depositOpen = false; adjustOpen = false">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Keuangan</p>
                <h1 class="mt-1 font-serif text-[1.75rem] font-semibold leading-none text-heading">Rekening</h1>
                <p class="mt-2 max-w-xl text-[13px] text-muted">Saldo bank di web. QRIS, setoran kas, BOP, dan setoran makanan masuk otomatis.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-ghost" @click="adjustOpen = true">Koreksi saldo</button>
                <button type="button" class="btn-add" @click="depositOpen = true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                    Catat setoran kas
                </button>
            </div>
        </div>

        <div class="mb-5 grid gap-4 md:grid-cols-2">
            <div class="stat-card">
                <div class="min-w-0 flex-1">
                    <p class="stat-kicker">Saldo</p>
                    <p class="stat-value {{ $balance < 0 ? 'text-brand' : '' }}">{{ money($balance) }}</p>
                    <p class="stat-hint">Outlet aktif</p>
                </div>
                <span class="stat-icon bg-[#eef6f0] text-[#166534]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Mutasi rekening</h5>
                    <p class="card-header-subtitle">Urutan terbaru di atas.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th class="text-right">Masuk / Keluar</th>
                            <th class="text-right">Saldo</th>
                            <th>Keterangan</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $row)
                            <tr>
                                <td class="whitespace-nowrap text-[13px] text-muted">{{ $row->occurred_on?->format('d M Y') }}</td>
                                <td class="text-[13px] font-medium">{{ $row->type?->label() ?? $row->type }}</td>
                                <td class="text-right tabular-nums font-medium {{ $row->amount >= 0 ? 'text-[#166534]' : 'text-[#c2410c]' }}">
                                    {{ $row->amount >= 0 ? '+' : '' }}{{ money($row->amount) }}
                                </td>
                                <td class="text-right tabular-nums">{{ money($row->balance_after) }}</td>
                                <td class="max-w-[260px] truncate text-[13px] text-muted">{{ $row->notes ?: '—' }}</td>
                                <td class="text-[13px]">{{ $row->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-16 text-center text-sm text-slate-400">Belum ada mutasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($movements->hasPages())
                <div class="border-t border-line px-4 py-4">{{ $movements->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="depositOpen" x-cloak @click.self="depositOpen = false">
            <div class="crud-modal" @click.stop>
                <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('bank.deposits.store') }}">
                    @csrf
                    <input type="hidden" name="_form" value="deposit">
                    <div class="crud-modal-body">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-serif text-lg font-semibold text-heading">Catat setoran kas</h3>
                                <p class="mt-1 text-[13px] text-muted">Setelah cash ditransfer ke rekening bank.</p>
                            </div>
                            <button type="button" class="modal-close" @click="depositOpen = false">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                        <div class="mt-5 grid gap-4">
                            <div>
                                <label class="label">Tanggal</label>
                                <input class="input" type="date" name="occurred_on" required value="{{ old('occurred_on', now()->toDateString()) }}">
                                @error('occurred_on')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Nominal</label>
                                <input class="input" type="number" name="amount" min="0.01" step="0.01" required value="{{ old('amount') }}" placeholder="0">
                                @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Keterangan</label>
                                <input class="input" type="text" name="notes" value="{{ old('notes') }}" maxlength="255" placeholder="Opsional">
                                @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="depositOpen = false">Batal</button>
                        <button class="btn-add" type="submit">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="adjustOpen" x-cloak @click.self="adjustOpen = false">
            <div class="crud-modal" @click.stop>
                <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('bank.adjustments.store') }}">
                    @csrf
                    <input type="hidden" name="_form" value="adjust">
                    <div class="crud-modal-body">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-serif text-lg font-semibold text-heading">Koreksi saldo</h3>
                                <p class="mt-1 text-[13px] text-muted">Positif = tambah, negatif = kurang. Samakan dengan bank asli.</p>
                            </div>
                            <button type="button" class="modal-close" @click="adjustOpen = false">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                        <div class="mt-5 grid gap-4">
                            <div>
                                <label class="label">Tanggal</label>
                                <input class="input" type="date" name="occurred_on" required value="{{ old('occurred_on', now()->toDateString()) }}">
                                @error('occurred_on')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Nominal (+ / −)</label>
                                <input class="input" type="number" name="amount" step="0.01" required value="{{ old('amount') }}" placeholder="0">
                                @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Keterangan</label>
                                <input class="input" type="text" name="notes" value="{{ old('notes') }}" maxlength="255" required placeholder="Alasan koreksi">
                                @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="adjustOpen = false">Batal</button>
                        <button class="btn-add" type="submit">Simpan koreksi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
