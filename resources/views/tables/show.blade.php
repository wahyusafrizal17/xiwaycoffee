@extends('layouts.app')
@section('title', $table->code)
@section('breadcrumb', 'Tables')
@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <x-status :value="$table->status->color()">{{ $table->status->label() }}</x-status>
                <span class="badge bg-slate-100 text-slate-600">{{ $table->capacity }} pax</span>
                @if ($table->zone)
                    <span class="badge bg-slate-100 text-slate-600">{{ $table->zone }}</span>
                @endif
            </div>
            <p class="mt-2 text-sm text-slate-500">{{ $table->name }} · {{ $table->outlet?->name }} · {{ $table->shape }}</p>
        </div>
        <a href="{{ route('tables.index') }}" class="btn-ghost">Kembali ke floor plan</a>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="card p-5">
            <p class="text-sm font-medium">Order aktif</p>
            @if ($order)
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('orders.show', $order) }}" class="font-medium hover:underline">{{ $order->order_number }}</a>
                            <p class="text-xs text-slate-400">{{ $order->created_at?->format('d/m/Y H:i') }}</p>
                        </div>
                        <x-status :value="$order->status->color()">{{ $order->status->label() }}</x-status>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($order->items as $item)
                            <div class="flex items-center justify-between py-3 text-sm">
                                <span>{{ $item->name }} × {{ number_format($item->quantity, 0) }}</span>
                                <span class="font-medium">{{ money($item->total) }}</span>
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-slate-400">Belum ada item.</p>
                        @endforelse
                    </div>
                    <div class="flex justify-between border-t border-line pt-3 text-sm font-semibold">
                        <span>Total</span>
                        <span>{{ money($order->grand_total) }}</span>
                    </div>
                </div>
            @else
                <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-12 text-center text-sm text-slate-400">Tidak ada order aktif di meja ini.</div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <p class="text-sm font-medium">Sesi meja</p>
                <div class="mt-4 space-y-3">
                    @forelse ($table->sessions->sortByDesc('opened_at')->take(8) as $session)
                        <div class="flex items-center justify-between rounded-2xl border border-line px-4 py-3 text-sm">
                            <div>
                                <p class="font-medium">{{ $session->order?->order_number ?? 'Tanpa order' }}</p>
                                <p class="text-xs text-slate-400">{{ $session->opened_at?->format('d/m/Y H:i') }} · {{ $session->guest_count }} tamu</p>
                            </div>
                            <span class="text-xs text-slate-500">{{ $session->durationMinutes() }} menit</span>
                        </div>
                    @empty
                        <div class="rounded-2xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-400">Belum ada sesi.</div>
                    @endforelse
                </div>
            </div>

            <div class="card p-5">
                <p class="text-sm font-medium">Reservasi</p>
                <div class="mt-4 space-y-3">
                    @forelse ($table->reservations->sortByDesc('reserved_at')->take(8) as $reservation)
                        <div class="rounded-2xl border border-line px-4 py-3 text-sm">
                            <p class="font-medium">{{ $reservation->guest_name }}</p>
                            <p class="text-xs text-slate-400">{{ $reservation->reserved_at?->format('d/m/Y H:i') }} · {{ $reservation->guest_count }} tamu · {{ $reservation->status }}</p>
                        </div>
                    @empty
                        <div class="rounded-2xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-400">Tidak ada reservasi.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
