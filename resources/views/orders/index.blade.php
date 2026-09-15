@extends('layouts.app')
@section('title', 'Orders')
@section('breadcrumb', 'Front of house')
@section('content')
    @php
        $hasFilters = collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty();
    @endphp
    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Total order</p>
                <p class="stat-value">{{ number_format($stats['total']) }}</p>
                <p class="stat-hint">Semua transaksi tercatat</p>
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
                <p class="stat-kicker">Antrian dapur</p>
                <p class="stat-value">{{ number_format($stats['kitchen']) }}</p>
                <p class="stat-hint">New, proses, dan preparing</p>
            </div>
            <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/></svg>
            </span>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h5 class="card-header-title">Daftar order</h5>
                <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
            </div>
            @if ($hasFilters)
                <div class="card-header-actions">
                    <a href="{{ route('orders.index') }}" class="btn-ghost">Reset filter</a>
                </div>
            @endif
        </div>

        <form id="order-filters" method="GET" action="{{ route('orders.index') }}"></form>
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th class="col-no">No.</th>
                        <th>Order</th>
                        <th>Tanggal</th>
                        <th>Outlet</th>
                        <th>Pelanggan</th>
                        <th>Meja</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Bayar</th>
                        <th>Total</th>
                        <th>Kasir</th>
                        <th class="col-actions"></th>
                    </tr>
                    <tr class="filter-row">
                        <th></th>
                        <th>
                            <input form="order-filters" class="col-filter" type="search" name="order_number" value="{{ $filters['order_number'] ?? '' }}" placeholder="No. order..." onchange="this.form.submit()">
                        </th>
                        <th>
                            <input form="order-filters" class="col-filter" type="date" name="date" value="{{ $filters['date'] ?? '' }}" onchange="this.form.submit()">
                        </th>
                        <th>
                            <select form="order-filters" class="col-filter" name="outlet_id" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <input form="order-filters" class="col-filter" type="search" name="customer" value="{{ $filters['customer'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                        </th>
                        <th>
                            <input form="order-filters" class="col-filter" type="search" name="table" value="{{ $filters['table'] ?? '' }}" placeholder="Kode..." onchange="this.form.submit()">
                        </th>
                        <th>
                            <select form="order-filters" class="col-filter" name="order_type" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                @foreach (\App\Enums\OrderType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(($filters['order_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <select form="order-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                @foreach (['draft', 'held', 'new', 'processing', 'preparing', 'ready', 'completed', 'cancelled'] as $status)
                                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Enums\OrderStatus::from($status)->label() }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <select form="order-filters" class="col-filter" name="payment_status" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                @foreach (\App\Enums\PaymentStatus::cases() as $pay)
                                    <option value="{{ $pay->value }}" @selected(($filters['payment_status'] ?? '') === $pay->value)>{{ $pay->label() }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th></th>
                        <th>
                            <input form="order-filters" class="col-filter" type="search" name="cashier" value="{{ $filters['cashier'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="col-no">{{ $orders->firstItem() + $loop->index }}</td>
                            <td>
                                <a href="{{ route('orders.show', $order) }}" class="font-semibold hover:underline">{{ $order->order_number }}</a>
                            </td>
                            <td class="text-muted">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $order->outlet?->name ?? '—' }}</td>
                            <td>{{ $order->customer?->name ?? 'Walk-in' }}</td>
                            <td>{{ $order->table?->code ?? '—' }}</td>
                            <td>
                                <span class="badge-soft">{{ $order->order_type?->label() ?? '—' }}</span>
                            </td>
                            <td><x-status :value="$order->status->color()">{{ $order->status->label() }}</x-status></td>
                            <td><x-status :value="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-status></td>
                            <td class="font-semibold">{{ money($order->grand_total) }}</td>
                            <td>{{ $order->user?->name ?? '—' }}</td>
                            <td class="col-actions">
                                <a href="{{ route('orders.show', $order) }}" class="table-action" title="Lihat">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="py-16 text-center text-sm text-slate-400">Tidak ada order yang cocok dengan filter kolom ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection
