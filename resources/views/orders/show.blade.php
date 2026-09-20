@extends('layouts.app')
@section('title', $order->order_number)
@section('breadcrumb', 'Orders')
@section('content')
    @php
        $itemCount = $order->items->count();
        $canCancel = auth()->user()?->can('orders.cancel') || auth()->user()?->can('orders.manage');
        $canSendInvoice = auth()->user()?->can('orders.checkout') || auth()->user()?->can('orders.manage');
        $isPaid = $order->payment_status === \App\Enums\PaymentStatus::Paid;
    @endphp

    <div class="grid gap-4 lg:grid-cols-3" x-data="orderInvoice()">
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
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($order->items as $item)
                                <tr>
                                    <td class="col-no">{{ $loop->iteration }}</td>
                                    <td>
                                        <p class="font-semibold">{{ $item->name }}</p>
                                        @if ($item->notes)
                                            <p class="text-xs text-muted">{{ $item->notes }}</p>
                                        @endif
                                        <p class="text-xs text-muted">{{ $item->product?->sku }}@if ($item->station) · {{ $item->station }}@endif</p>
                                    </td>
                                    <td>{{ number_format($item->quantity, 0) }}</td>
                                    <td>{{ money($item->unit_price) }}</td>
                                    <td class="{{ $item->discount_amount > 0 ? 'font-medium text-[#ff9f43]' : 'text-muted' }}">{{ money($item->discount_amount) }}</td>
                                    <td class="font-semibold">{{ money($item->total) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-16 text-center text-sm text-muted">Tidak ada item pada order ini.</td>
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
                    <div class="flex justify-between text-muted"><span>Charge</span><span class="font-medium text-heading">{{ money($order->tax_amount) }}</span></div>
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

            @if ($isPaid && $canSendInvoice)
                <div class="card overflow-hidden">
                    <div class="card-header">
                        <div>
                            <h5 class="card-header-title">Invoice</h5>
                            <p class="card-header-subtitle">Kirim invoice ke WhatsApp pelanggan</p>
                        </div>
                    </div>
                    <div class="space-y-3 px-6 pb-6">
                        <div>
                            <label class="label">Nomor WhatsApp</label>
                            <div class="flex gap-2">
                                <input class="input" type="tel" inputmode="tel" x-model="phone" placeholder="08xxxxxxxxxx" @keydown.enter.prevent="send()">
                                <button type="button" class="btn-add shrink-0" @click="send()" :disabled="busy || !phone">
                                    <span x-show="!busy">Kirim</span>
                                    <span x-show="busy" x-cloak>…</span>
                                </button>
                            </div>
                            <p class="mt-2 text-[12px] text-muted" x-show="notice" x-text="notice" x-cloak></p>
                            <p class="mt-2 text-[12px] text-brand" x-show="error" x-text="error" x-cloak></p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($order->status->isOpen() && $canCancel)
                <form id="cancel-order-form" method="POST" action="{{ route('pos.cancel', $order) }}">
                    @csrf
                    <input type="hidden" name="reason">
                    <button id="cancel-order-btn" class="btn-ghost w-full text-brand hover:bg-brand-soft" style="border-color: #6f1715" type="button">Cancel order</button>
                </form>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function orderInvoice() {
            return {
                phone: @json($order->customer?->phone ?: ''),
                busy: false,
                notice: '',
                error: '',
                async send() {
                    if (! this.phone || this.busy) return;
                    this.busy = true;
                    this.notice = '';
                    this.error = '';
                    try {
                        const res = await fetch(@json(route('pos.invoice.whatsapp', $order)), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ phone: this.phone }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok) throw new Error(data.message || 'Gagal mengirim invoice.');
                        this.notice = data.notice || (data.via === 'pdf' ? 'Invoice PDF terkirim via WhatsApp.' : 'Invoice teks terkirim via WhatsApp.');
                        await Swal.fire({ icon: 'success', title: 'Terkirim', text: this.notice, timer: 2200, showConfirmButton: false });
                    } catch (e) {
                        this.error = e.message || 'Gagal mengirim invoice.';
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }

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
