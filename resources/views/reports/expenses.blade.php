@extends('layouts.app')
@section('title', 'BOP')
@section('breadcrumb', 'Reports')
@section('content')
    @include('reports._nav')

    <div class="mb-5 grid gap-4 md:grid-cols-2">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Total BOP outlet ini</p>
                <p class="stat-value">{{ money($total) }}</p>
                <p class="stat-hint">Semua pengeluaran yang tercatat</p>
            </div>
            <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-12V5m0 14v-2"/></svg>
            </span>
        </div>
    </div>

    <div class="mb-5 card p-5">
        <h5 class="card-header-title mb-4">Catat biaya operasional</h5>
        <form method="POST" action="{{ route('reports.expenses.store') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            @csrf
            <div>
                <label class="label">Tanggal</label>
                <input class="input" type="date" name="spent_on" value="{{ old('spent_on', now()->toDateString()) }}" required>
            </div>
            <div>
                <label class="label">Kategori</label>
                <select class="input" name="category" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Nominal</label>
                <input class="input" type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required>
            </div>
            <div class="xl:col-span-2">
                <label class="label">Keterangan</label>
                <input class="input" type="text" name="notes" value="{{ old('notes') }}" placeholder="Sewa, belanja bahan, …">
            </div>
            <div class="xl:col-span-5">
                <button class="btn-primary" type="submit">Simpan BOP</button>
            </div>
        </form>
        @error('amount') <p class="mt-2 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h5 class="card-header-title">Daftar BOP</h5>
                <p class="card-header-subtitle">Pengeluaran outlet yang sedang dipilih.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Nominal</th>
                        <th>Keterangan</th>
                        <th>Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->spent_on?->format('d/m/Y') }}</td>
                            <td>{{ $expense->category?->label() ?? $expense->category }}</td>
                            <td>{{ money($expense->amount) }}</td>
                            <td>{{ $expense->notes ?: '—' }}</td>
                            <td>{{ $expense->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center text-sm text-slate-400">Belum ada BOP.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($expenses->hasPages())
            <div class="border-t border-line px-4 py-4">{{ $expenses->links() }}</div>
        @endif
    </div>
@endsection
