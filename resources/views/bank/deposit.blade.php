@extends('layouts.app')
@section('title', 'Setoran kas')
@section('breadcrumb', 'Keuangan')
@section('content')
    @php
        $formError = $errors->any() && old('_form') === 'setoran';
    @endphp

    <div x-data="{ formOpen: {{ $formError ? 'true' : 'false' }} }" @keydown.escape.window="formOpen = false">
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Riwayat setoran kas</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis.</p>
                </div>
                <div class="card-header-actions">
                    @if ($hasFilters)
                        <a href="{{ route('bank.deposits.create') }}" class="btn-ghost">Reset filter</a>
                    @endif
                    <button type="button" class="btn-add" @click="formOpen = true">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                        Catat setoran
                    </button>
                </div>
            </div>
            <form id="deposit-filters" method="GET" action="{{ route('bank.deposits.create') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-right">Nominal</th>
                            <th>Keterangan</th>
                            <th>Oleh</th>
                        </tr>
                        <tr class="filter-row">
                            <th>
                                <input form="deposit-filters" class="col-filter" type="date" name="date" value="{{ $filters['date'] ?? '' }}" onchange="this.form.submit()">
                            </th>
                            <th></th>
                            <th>
                                <input form="deposit-filters" class="col-filter" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Keterangan..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="deposit-filters" class="col-filter" type="search" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deposits as $row)
                            <tr>
                                <td class="whitespace-nowrap text-[13px] text-muted">{{ $row->occurred_on?->format('d M Y') }}</td>
                                <td class="text-right tabular-nums font-medium text-[#166534]">+{{ money($row->amount) }}</td>
                                <td class="max-w-[280px] truncate text-[13px] text-muted">{{ $row->notes ?: '—' }}</td>
                                <td class="text-[13px]">{{ $row->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="!border-0">
                                    <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                                        <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#f6f3ef] text-muted">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                                        </span>
                                        <p class="text-sm font-medium text-heading">Belum ada setoran kas</p>
                                        <p class="mt-1 text-[13px] text-muted">Catat setelah cash ditransfer ke rekening bank.</p>
                                        <button type="button" class="btn-add mt-5" @click="formOpen = true">
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                                            Catat setoran
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($deposits->hasPages())
                <div class="border-t border-line px-4 py-4">{{ $deposits->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
            <div class="crud-modal" @click.stop>
                <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('bank.deposits.store') }}">
                    @csrf
                    <input type="hidden" name="_form" value="setoran">
                    <div class="crud-modal-body">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-serif text-lg font-semibold text-heading">Catat setoran kas</h3>
                                <p class="mt-1 text-[13px] text-muted">Setelah cash ditransfer ke rekening bank.</p>
                            </div>
                            <button type="button" class="modal-close" @click="formOpen = false">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                        <div class="mt-5 grid gap-4">
                            <div>
                                <label class="label">Tanggal transfer</label>
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
                        <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                        <button class="btn-add" type="submit">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
