@extends('layouts.app')
@section('title', $order->order_number)
@section('breadcrumb', 'Orders')
@section('content')
    @php
        $flow = [
            ['label' => 'Pesanan Baru', 'statuses' => ['new'], 'next' => 'processing'],
            ['label' => 'Proses', 'statuses' => ['processing', 'preparing'], 'next' => 'ready'],
            ['label' => 'Siap', 'statuses' => ['ready'], 'next' => 'completed'],
            ['label' => 'Selesai', 'statuses' => ['completed'], 'next' => null],
        ];
        $current = $order->status->value;
        $flowIndex = collect($flow)->search(fn ($step) => in_array($current, $step['statuses'], true));
        $flowIndex = $flowIndex === false ? -1 : $flowIndex;
        $nextStatus = $flowIndex === -1 ? 'new' : $flow[$flowIndex]['next'];
        $nextLabel = match ($nextStatus) {
            'new' => 'Pesanan Baru',
            'processing' => 'Proses',
            'ready' => 'Siap',
            'completed' => 'Selesai',
            default => null,
        };
        $linePercent = $flowIndex < 1 ? 0 : min(100, (int) round(($flowIndex / (count($flow) - 1)) * 100));
        $itemCount = $order->items->count();
        $allItemsReady = $order->allItemsReady();
        $canCheckItems = auth()->user()?->can('orders.check') || auth()->user()?->can('orders.manage');
    @endphp

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="card overflow-hidden">
                <div class="card-header">
                    <div>
                        <h5 class="card-header-title">Informasi order</h5>
                        <p class="card-header-subtitle">{{ $order->outlet?->name }} · {{ $order->created_at?->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="card-header-actions">
                        <x-status :value="$order->status->color()">{{ $order->status->label() }}</x-status>
                        <x-status :value="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-status>
                    </div>
                </div>
                <div class="space-y-4 px-6 pb-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <p class="stat-kicker mb-1.5">Pelanggan</p>
                            <div class="rounded-lg bg-[#f4f4f4] px-3.5 py-2.5 text-sm font-medium">{{ $order->customer?->name ?? 'Walk-in' }}</div>
                        </div>
                        <div>
                            <p class="stat-kicker mb-1.5">No HP</p>
                            <div class="rounded-lg bg-[#f4f4f4] px-3.5 py-2.5 text-sm font-medium">{{ $order->customer?->phone ?: '—' }}</div>
                        </div>
                        <div>
                            <p class="stat-kicker mb-1.5">Tipe</p>
                            <div class="rounded-lg bg-[#f4f4f4] px-3.5 py-2.5 text-sm font-medium">{{ $order->order_type?->label() ?? '—' }}</div>
                        </div>
                        <div>
                            <p class="stat-kicker mb-1.5">Channel</p>
                            <div class="rounded-lg bg-[#f4f4f4] px-3.5 py-2.5 text-sm font-medium">{{ $order->channel?->label() ?? '—' }}</div>
                        </div>
                        <div>
                            <p class="stat-kicker mb-1.5">Kasir</p>
                            <div class="rounded-lg bg-[#f4f4f4] px-3.5 py-2.5 text-sm font-medium">{{ $order->user?->name ?? '—' }}</div>
                        </div>
                        <div>
                            <p class="stat-kicker mb-1.5">ETA</p>
                            <div class="rounded-lg bg-[#f4f4f4] px-3.5 py-2.5 text-sm font-medium">{{ $order->estimated_ready_at?->format('H:i') ?? '—' }}</div>
                        </div>
                    </div>
                    @if ($order->customer?->address)
                        <div>
                            <p class="stat-kicker mb-1.5">Alamat</p>
                            <div class="rounded-lg bg-[#f4f4f4] px-3.5 py-2.5 text-sm font-medium">{{ $order->customer->address }}</div>
                        </div>
                    @endif

                    <div class="flex gap-4 rounded-xl bg-[#f6f6f6] p-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 10h16M6 10V7a2 2 0 012-2h8a2 2 0 012 2v3M5 10v8h14v-8M9 14h6"/></svg>
                        </span>
                        <div class="grid min-w-0 flex-1 gap-4 sm:grid-cols-3">
                            <div>
                                <p class="stat-kicker">Outlet</p>
                                <p class="mt-1 text-sm font-semibold">{{ $order->outlet?->name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="stat-kicker">Meja</p>
                                <p class="mt-1 text-sm font-semibold">{{ $order->table?->code ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="stat-kicker">Tamu</p>
                                <p class="mt-1 text-sm font-semibold">{{ $order->guest_count ?: '—' }}</p>
                            </div>
                        </div>
                    </div>
                    @if ($order->notes)
                        <p class="rounded-xl bg-[#fff8f0] px-4 py-3 text-sm text-[#9a6b2f]">{{ $order->notes }}</p>
                    @endif
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="card-header">
                    <div>
                        <h5 class="card-header-title">Daftar item</h5>
                        <p class="card-header-subtitle">Produk yang dipesan pada order ini</p>
                    </div>
                    <span class="badge-soft">{{ $itemCount }} item</span>
                </div>
                <div class="table-wrap">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th class="col-no">No.</th>
                                <th>Produk</th>
                                <th>Qty</th>
                                <th>Harga</th>
                                <th>Diskon</th>
                                <th>Total</th>
                                <th class="col-actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($order->items as $item)
                                @php
                                    $itemDone = in_array($item->status, ['ready', 'served'], true);
                                    $canToggleItem = $canCheckItems;
                                @endphp
                                <tr class="{{ $itemDone ? 'item-ready' : 'item-wait' }}">
                                    <td class="col-no">{{ $loop->iteration }}</td>
                                    <td>
                                        <p class="font-semibold">{{ $item->name }}</p>
                                        @if ($item->notes)
                                            <p class="text-xs text-muted">{{ $item->notes }}</p>
                                        @endif
                                        <p class="text-xs text-muted">{{ $item->product?->sku }} · {{ $item->station }} · {{ $item->status }}</p>
                                        @if ($item->batch)
                                            <p class="text-xs text-muted">Batch {{ $item->batch->batch_number }}</p>
                                        @endif
                                    </td>
                                    <td>{{ number_format($item->quantity, 0) }}</td>
                                    <td>{{ money($item->unit_price) }}</td>
                                    <td class="{{ $item->discount_amount > 0 ? 'font-medium text-[#ff9f43]' : 'text-muted' }}">{{ money($item->discount_amount) }}</td>
                                    <td class="font-semibold">{{ money($item->total) }}</td>
                                    <td class="col-actions">
                                        @if ($itemDone)
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[#1f9d57] text-white" title="Siap">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 12l5 5L19 7"/></svg>
                                            </span>
                                        @elseif ($canToggleItem)
                                            <form method="POST" action="{{ route('order-items.status', $item) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="ready">
                                                <button class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-[#d4d4d4] bg-white text-muted transition hover:border-[#1f9d57] hover:text-[#1f9d57]" type="submit" title="Tandai siap">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 12l5 5L19 7"/></svg>
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-[#e8e8e8] text-[#c0c0c0]" title="Belum siap">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 12l5 5L19 7"/></svg>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-16 text-center text-sm text-muted">Tidak ada item pada order ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="card-header">
                    <div>
                        <h5 class="card-header-title">Pembayaran</h5>
                        <p class="card-header-subtitle">Metode dan nominal yang tercatat</p>
                    </div>
                    <span class="badge-soft">{{ $order->payments->count() }} transaksi</span>
                </div>
                <div class="space-y-3 px-6 pb-6">
                    @forelse ($order->payments as $payment)
                        <div class="flex items-center justify-between rounded-xl bg-[#f6f6f6] px-4 py-3 text-sm">
                            <div>
                                <p class="font-semibold">{{ $payment->method?->label() ?? $payment->method }}</p>
                                <p class="text-xs text-muted">
                                    {{ $payment->created_at?->format('d/m/Y H:i') }}
                                    @if ($payment->reference) · Ref {{ $payment->reference }} @endif
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">{{ money($payment->amount) }}</p>
                                <p class="text-xs text-muted">Tendered {{ money($payment->tendered) }} · Change {{ money($payment->change_amount) }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl bg-[#f6f6f6] px-4 py-10 text-center text-sm text-muted">Belum ada pembayaran tercatat.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card overflow-hidden">
                <div class="card-header">
                    <div>
                        <h5 class="card-header-title">Ringkasan</h5>
                        <p class="card-header-subtitle">Perhitungan total order</p>
                    </div>
                </div>
                <div class="space-y-3 px-6 pb-6 text-sm">
                    <div class="flex justify-between text-muted"><span>Subtotal</span><span class="font-medium text-heading">{{ money($order->subtotal) }}</span></div>
                    <div class="flex justify-between text-muted"><span>Diskon {{ $order->discount?->name }}</span><span class="font-medium {{ $order->discount_amount > 0 ? 'text-[#ff9f43]' : 'text-heading' }}">{{ money($order->discount_amount) }}</span></div>
                    <div class="flex justify-between text-muted"><span>Pajak</span><span class="font-medium text-heading">{{ money($order->tax_amount) }}</span></div>
                    <div class="flex justify-between text-muted"><span>Service</span><span class="font-medium text-heading">{{ money($order->service_charge) }}</span></div>
                    <div class="flex justify-between text-muted"><span>Poin</span><span class="font-medium text-heading">- {{ money($order->points_value) }}</span></div>
                    <div class="flex justify-between text-muted"><span>Terbayar</span><span class="font-medium text-heading">{{ money($order->paidTotal()) }}</span></div>
                    <div class="flex justify-between text-muted"><span>Sisa</span><span class="font-medium text-heading">{{ money($order->balanceDue()) }}</span></div>
                    <div class="flex items-center justify-between rounded-lg bg-heading px-4 py-3 text-white">
                        <span class="text-sm font-medium">Total bayar</span>
                        <span class="text-lg font-semibold">{{ money($order->grand_total) }}</span>
                    </div>
                </div>
            </div>

            @if ($order->status !== \App\Enums\OrderStatus::Cancelled)
                <div class="card p-6">
                    <p class="stat-kicker">Alur pengerjaan</p>
                    <div class="relative mt-6">
                        <div class="absolute left-[12%] right-[12%] top-4 h-[2px] bg-[#e8e8e8]"></div>
                        <div class="absolute left-[12%] top-4 h-[2px] bg-[#1f9d57]" style="width: calc((100% - 24%) * {{ $linePercent }} / 100)"></div>
                        <div class="relative flex justify-between">
                            @foreach ($flow as $index => $step)
                                @php $reached = $flowIndex >= $index; @endphp
                                <div class="flex w-1/4 flex-col items-center">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full {{ $reached ? 'bg-[#1f9d57] text-white' : 'bg-[#e8e8e8] text-[#b0b0b0]' }}">
                                        @if ($reached)
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 12l5 5L19 7"/></svg>
                                        @endif
                                    </span>
                                    <p class="mt-2 text-center text-[11px] font-semibold {{ $reached ? 'text-[#1f9d57]' : 'text-muted' }}">{{ $step['label'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($order->status->isOpen())
                        @php
                            $canAdvance = auth()->user()?->can('orders.manage') || auth()->user()?->can('orders.check');
                            $canCancel = auth()->user()?->can('orders.cancel') || auth()->user()?->can('orders.manage');
                        @endphp
                        @if ($canAdvance || $canCancel)
                            @php $needItemsReady = $nextStatus === 'ready' && ! $allItemsReady; @endphp
                            <div class="mt-6 flex gap-2">
                                @if ($canAdvance && $nextStatus)
                                    <form method="POST" action="{{ route('orders.status', $order) }}" class="min-w-0 flex-1">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                                        <button class="btn-add w-full" type="submit" @disabled($needItemsReady)>Lanjut: {{ $nextLabel }}</button>
                                    </form>
                                @endif
                                @if ($canCancel)
                                    <form id="cancel-order-form" method="POST" action="{{ route('pos.cancel', $order) }}" class="min-w-0 flex-1">
                                        @csrf
                                        <input type="hidden" name="reason">
                                        <button id="cancel-order-btn" class="btn-ghost w-full text-brand hover:bg-brand-soft" style="border-color: #6f1715" type="button">Cancel order</button>
                                    </form>
                                @endif
                            </div>
                            @if ($needItemsReady)
                                <p class="mt-2 text-center text-[12px] text-muted">Tandai semua item siap di Daftar item dulu.</p>
                            @endif
                        @endif
                    @endif
                </div>
            @endif

            <div class="space-y-2">
                <p class="stat-kicker px-1">Invoice</p>
                @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                    <a href="{{ route('pos.receipt', $order) }}" class="btn-add w-full">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg>
                        Invoice customer
                    </a>
                    <a href="{{ route('pos.ticket', [$order, 'prep']) }}?reprint=1" target="_blank" class="btn-ghost w-full">Tiket dapur / bar</a>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <style>
        .list-table tr.item-wait td { background-color: #fff8e1 !important; }
        .list-table tr.item-ready td { background-color: #e8f8ee !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('cancel-order-btn')?.addEventListener('click', async () => {
            const { value, isConfirmed } = await Swal.fire({
                title: 'Batalkan order?',
                text: 'Masukkan alasan pembatalan.',
                input: 'text',
                inputPlaceholder: 'Alasan pembatalan',
                inputAttributes: { maxlength: 255 },
                showCancelButton: true,
                confirmButtonText: 'Ya, batalkan',
                cancelButtonText: 'Tutup',
                confirmButtonColor: '#ef4444',
                inputValidator: (v) => !String(v || '').trim() && 'Alasan wajib diisi.',
            });
            if (! isConfirmed) return;
            const form = document.getElementById('cancel-order-form');
            form.reason.value = String(value).trim();
            form.submit();
        });
    </script>
@endpush
