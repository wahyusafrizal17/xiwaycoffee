@extends('layouts.app')
@section('title', 'Customer Report')
@section('breadcrumb', 'Reports')
@section('actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn-ghost">Export XLSX</a>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn-ghost">Export PDF</a>
@endsection
@section('content')
    @unless ($exporting ?? false)
        <form method="GET" action="{{ route('reports.customers') }}" class="card mb-6 p-5">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label class="label">Dari</label>
                    <input class="input" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div>
                    <label class="label">Sampai</label>
                    <input class="input" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div>
                    <label class="label">Outlet</label>
                    <select name="outlet_id" class="input">
                        <option value="">Semua</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Cari</label>
                    <input class="input" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama atau telepon">
                </div>
                <div class="flex items-end">
                    <button class="btn-primary w-full" type="submit">Filter</button>
                </div>
            </div>
        </form>
    @endunless

    <div class="{{ ($exporting ?? false) ? '' : 'card overflow-hidden' }}">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Phone</th>
                        <th>Level</th>
                        <th>Poin</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-medium">{{ $row->name }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>{{ $row->membership_level?->label() ?? $row->membership_level?->value }}</td>
                            <td>{{ number_format($row->points) }}</td>
                            <td>{{ money($row->total_transaction) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center text-sm text-slate-400">Tidak ada data pelanggan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($exporting ?? false)
            @if ($rows->hasPages())
                <div class="border-t border-line px-4 py-4">{{ $rows->links() }}</div>
            @endif
        @endunless
    </div>
@endsection
