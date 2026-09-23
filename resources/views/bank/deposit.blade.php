@extends('layouts.app')
@section('title', 'Setoran kas')
@section('breadcrumb', 'Keuangan')
@section('content')
    <div class="mb-6">
        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Keuangan</p>
        <h1 class="mt-1 font-serif text-[1.75rem] font-semibold leading-none text-heading">Setoran kas</h1>
        <p class="mt-2 max-w-xl text-[13px] text-muted">Catat setelah cash ditransfer ke rekening bank. Saldo rekening bertambah.</p>
    </div>

    <div class="mb-5 grid gap-4 md:grid-cols-2">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Saldo rekening</p>
                <p class="stat-value">{{ money($balance) }}</p>
            </div>
        </div>
    </div>

    <div class="card max-w-lg p-5">
        <form method="POST" action="{{ route('bank.deposits.store') }}" class="space-y-4">
            @csrf
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
            <button class="btn-add" type="submit">Simpan setoran kas</button>
        </form>
    </div>
@endsection
