@extends('layouts.app')
@section('title', 'Inventory Report')
@section('breadcrumb', 'Reports')
@section('actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn-ghost">Export XLSX</a>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn-ghost">Export PDF</a>
@endsection
@section('content')
    @unless ($exporting ?? false)
        <form method="GET" action="{{ route('reports.inventory') }}" class="card mb-6 p-5">
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
                        <option value="">Current / semua</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Cari</label>
                    <input class="input" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama produk">
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
                        <th>Produk</th>
                        <th>Outlet</th>
                        <th>Qty</th>
                        <th>Cost</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-medium">{{ $row->product?->name }}</td>
                            <td>{{ $row->outlet?->name }}</td>
                            <td>{{ qty($row->quantity) }} {{ $row->product?->unit?->code }}</td>
                            <td>{{ money($row->product?->cost) }}</td>
                            <td>{{ money((float) $row->quantity * (float) ($row->product?->cost ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center text-sm text-slate-400">Tidak ada data inventory.</td>
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
