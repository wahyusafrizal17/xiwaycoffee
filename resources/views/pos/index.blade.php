@extends('layouts.pos')
@section('content')
<div class="flex h-full min-h-0" x-data="posApp()">
    <div class="pos-shell">
        <section class="pos-catalog">
            <div class="flex items-center justify-between gap-3 border-b border-[#f0ece7] px-3 py-2 text-xs" x-show="!online" x-cloak>
                <span class="font-medium text-[#d97706]">Mode offline — order akan dikirim saat koneksi kembali.</span>
            </div>
            @if ($lowStock->isNotEmpty())
                <div class="border-b border-brand-soft bg-brand-soft px-3 py-2 text-xs text-brand">
                    Stok menipis: {{ $lowStockNames }}@if ($lowStockExtra > 0) +{{ $lowStockExtra }} lagi @endif
                </div>
            @endif
            <div class="flex flex-col gap-3 border-b border-[#f0ece7] px-3 py-3 sm:flex-row sm:items-center">
                <div class="flex min-w-0 flex-1 gap-2 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <button type="button" class="pos-chip" :class="!category ? 'pos-chip-active' : 'pos-chip-idle'" @click="category = null">Semua</button>
                    @foreach ($categories as $category)
                        <button type="button" class="pos-chip" :class="category == {{ $category->id }} ? 'pos-chip-active' : 'pos-chip-idle'" @click="category = {{ $category->id }}">{{ $category->name }}</button>
                    @endforeach
                </div>
                <div class="relative w-full shrink-0 sm:w-56">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/></svg>
                    <input class="input !rounded-xl !border-[#ebe7e2] !bg-[#faf9f7] !py-2 !pl-9 !text-[13px]" placeholder="Cari menu..." x-model="search">
                </div>
            </div>
            <div class="grid flex-1 auto-rows-min grid-cols-2 gap-2.5 overflow-y-auto p-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                @foreach ($products as $product)
                    <article
                        class="menu-card"
                        x-show="(!category || category == {{ $product->category_id }}) && productMatch('{{ strtolower($product->name) }}')"
                        @click="beginAdd({{ $product->id }}, null, null)"
                    >
                        <div class="menu-card-visual">
                            <span class="menu-card-badge">{{ $product->is_recommended ? '★ ' : '' }}{{ $product->category?->name }}</span>
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="menu-card-photo" loading="lazy">
                        </div>
                        <div class="menu-card-body">
                            <div>
                                <h3 class="line-clamp-2 text-[14px] font-semibold leading-snug text-heading">{{ $product->name }}</h3>
                                <p class="mt-1.5 text-[14px] font-semibold tracking-tight text-heading">{{ money($product->price) }}</p>
                                @if ($product->variants->count())
                                    <div class="mt-2 flex flex-wrap gap-1" @click.stop>
                                        @foreach ($product->variants as $variant)
                                            <button type="button" class="rounded-full bg-[#f3f0ec] px-2 py-0.5 text-[11px] font-medium text-heading transition hover:bg-[#ebe7e2]" @click="beginAdd({{ $product->id }}, {{ $variant->id }}, null)">{{ $variant->name }}</button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <button type="button" class="menu-card-add" @click.stop="beginAdd({{ $product->id }}, null, null)">Tambah</button>
                        </div>
                    </article>
                @endforeach
                @foreach ($bundles as $bundle)
                    <article class="menu-card" x-show="!category || category == {{ $bundle->product?->category_id ?? 0 }}" @click="beginAdd({{ $bundle->product_id ?? $bundle->items->first()?->product_id }}, null, {{ $bundle->id }})">
                        <div class="menu-card-visual">
                            <span class="menu-card-badge">Package</span>
                            <img src="{{ $bundle->product?->imageUrl() ?? asset('images/menu/placeholder.svg') }}" alt="{{ $bundle->name }}" class="menu-card-photo" loading="lazy">
                        </div>
                        <div class="menu-card-body">
                            <div>
                                <h3 class="line-clamp-2 text-[14px] font-semibold leading-snug text-heading">{{ $bundle->name }}</h3>
                                <p class="mt-1.5 text-[14px] font-semibold tracking-tight text-heading">{{ money($bundle->price) }}</p>
                            </div>
                            <button type="button" class="menu-card-add" @click.stop="beginAdd({{ $bundle->product_id ?? $bundle->items->first()?->product_id }}, null, {{ $bundle->id }})">Tambah</button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <button type="button" class="pos-cart-fab" @click="cartOpen = true" x-show="!cartOpen">
            <span>Order</span>
            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-white/15 px-1.5 text-[11px]" x-text="(order?.items || []).reduce((n, i) => n + Number(i.quantity || 0), 0) || 0"></span>
        </button>

        <div class="pos-cart-backdrop" x-show="cartOpen" x-cloak @click="cartOpen = false"></div>

        <aside
            class="order-panel pos-cart-sheet"
            :class="cartOpen ? 'flex' : 'hidden lg:flex'"
        >
            <div class="flex items-center justify-between px-4 py-3 lg:px-5 lg:py-4">
                <div class="min-w-0">
                    <div class="mx-auto mb-2 h-1 w-10 rounded-full bg-[#e5e5e5] lg:hidden"></div>
                    <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-muted">Current order</p>
                    <p class="mt-1 truncate text-[15px] font-semibold text-heading" x-text="order?.order_number || 'Draft baru'"></p>
                    <p class="mt-0.5 text-[11px] font-medium text-brand" x-show="order?.status === 'held'">Hold — siap dilanjutkan</p>
                    <p class="mt-0.5 text-[11px] text-muted" x-show="order?.estimated_ready_at && order?.status !== 'held'" x-text="etaLabel()"></p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="rounded-lg border border-[#ebe7e2] px-3 py-1.5 text-xs font-medium text-heading transition hover:bg-[#faf9f7]" @click="hold()" x-show="order">Hold</button>
                    <button type="button" class="rounded-lg border border-[#ebe7e2] px-2.5 py-1.5 text-xs font-medium text-muted lg:hidden" @click="cartOpen = false">Tutup</button>
                </div>
            </div>
            <div class="mx-4 space-y-2 border-t border-[#f0ece7] pt-3 lg:mx-5">
                <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-muted">Tipe order</p>
                <select class="input !min-w-0 !rounded-xl !border-[#ebe7e2] !bg-[#faf9f7] !py-2 !text-xs" x-model="order_type">
                    <option value="pickup">Pickup</option>
                    <option value="dine_in">Dine-in</option>
                    <option value="online">Online</option>
                </select>
            </div>
            <div class="mx-4 mt-3 border-t border-[#f0ece7] lg:mx-5"></div>
            <div class="flex-1 space-y-2.5 overflow-y-auto px-4 py-3 lg:px-5 lg:py-4">
                <template x-if="!order || !order.items?.length">
                    <div class="flex h-full min-h-36 flex-col items-center justify-center rounded-2xl border border-dashed border-[#e8e4df] bg-[#faf9f7] px-4 text-center">
                        <p class="text-sm font-medium text-heading">Belum ada item</p>
                        <p class="mt-1 text-xs text-muted">Pilih menu, lalu tekan Tambah.</p>
                    </div>
                </template>
                <template x-for="item in (order?.items || [])" :key="item.id">
                    <div class="order-item">
                        <div class="flex items-start gap-3">
                            <img :src="itemImage(item)" :alt="item.name" class="h-14 w-14 shrink-0 rounded-xl bg-white object-cover">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-heading" x-text="item.name"></p>
                                        <p class="mt-0.5 text-xs text-muted" x-text="formatMoney(item.unit_price)"></p>
                                    </div>
                                    <button type="button" class="shrink-0 text-[11px] font-medium text-brand hover:underline" @click="removeItem(item.id)">Hapus</button>
                                </div>
                                <div class="mt-2.5 flex items-center gap-2">
                                    <div class="order-qty">
                                        <button type="button" @click="changeQty(item, -1)">−</button>
                                        <span x-text="Number(item.quantity)"></span>
                                        <button type="button" @click="changeQty(item, 1)">+</button>
                                    </div>
                                    <input class="order-note" placeholder="Notes" x-model="item.notes" @change="updateItem(item)">
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="space-y-3 border-t border-[#f0ece7] px-4 py-4 lg:px-5">
                <div class="order-row text-muted">
                    <span>Subtotal</span>
                    <span class="font-medium text-heading" x-text="formatMoney(order?.subtotal || 0)"></span>
                </div>
                <div class="space-y-1.5">
                    <div class="order-row text-muted">
                        <span>Diskon</span>
                        <span class="font-medium" :class="Number(order?.discount_amount || 0) > 0 ? 'text-brand' : 'text-heading'" x-text="discountLine()"></span>
                    </div>
                    <select class="h-9 w-full rounded-xl border border-[#ebe7e2] bg-[#faf9f7] px-2.5 text-xs text-heading outline-none" x-model="discount_id" @change="applyDiscount()">
                        <option value="">Tanpa diskon</option>
                        @foreach ($discounts as $discount)
                            <option value="{{ $discount->id }}">{{ $discount->name }} · {{ $discount->valueLabel() }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-[#c2410c]" x-show="discountHint()" x-text="discountHint()" x-cloak></p>
                </div>
                <div class="order-row text-muted">
                    <span>Tax</span>
                    <span class="font-medium text-heading" x-text="formatMoney(order?.tax_amount || 0)"></span>
                </div>
                <div class="flex items-center justify-between rounded-2xl bg-heading px-3.5 py-3.5 text-white">
                    <span class="text-sm font-medium">Total</span>
                    <span class="text-lg font-semibold tracking-tight" x-text="formatMoney(order?.grand_total || 0)"></span>
                </div>
                <p class="text-xs font-medium text-brand" x-show="notice" x-text="notice" x-cloak></p>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#ebe7e2] bg-white px-4 py-2.5 text-sm font-medium text-heading transition hover:bg-[#faf9f7]" @click="openHeldList()">
                        Hold
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-heading px-1.5 text-[10px] font-bold leading-none text-white" x-show="heldCount() > 0" x-text="heldCount()" x-cloak></span>
                    </button>
                    <button type="button" class="rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-40" @click="openPay()" :disabled="!canPay()">Bayar</button>
                </div>
            </div>
        </aside>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4" x-show="optionOpen" x-cloak @click.self="optionOpen = false">
        <div class="pay-modal !max-w-[480px]">
            <div class="pay-modal-hero">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-white/45">Pilih opsi</p>
                        <p class="mt-0.5 truncate text-[15px] font-semibold text-white" x-text="optionProduct?.name || ''"></p>
                    </div>
                    <p class="shrink-0 text-[18px] font-semibold text-white" x-text="formatMoney(optionPreviewTotal())"></p>
                </div>
            </div>
            <div class="max-h-[55vh] space-y-4 overflow-y-auto px-6 py-5">
                <template x-for="group in (optionProduct?.option_groups || [])" :key="group.id">
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="text-[12px] font-semibold text-heading" x-text="group.name"></p>
                            <span class="text-[11px] text-muted" x-text="group.is_required ? 'Wajib' : 'Opsional'"></span>
                        </div>
                        <div class="space-y-1.5">
                            <template x-for="opt in (group.options || []).filter(o => o.is_active)" :key="opt.id">
                                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-[#ebe7e2] bg-[#faf9f7] px-3 py-2.5 text-sm">
                                    <span class="flex min-w-0 items-center gap-2.5">
                                        <input
                                            :type="Number(group.max_select) <= 1 ? 'radio' : 'checkbox'"
                                            :name="'opt-group-' + group.id"
                                            :value="opt.id"
                                            :checked="optionSelected.includes(Number(opt.id))"
                                            @change="toggleOption(group, opt.id)"
                                        >
                                        <span class="truncate font-medium text-heading" x-text="opt.name"></span>
                                    </span>
                                    <span class="shrink-0 text-xs text-muted" x-text="Number(opt.price_adjustment) > 0 ? ('+ ' + formatMoney(opt.price_adjustment)) : '—'"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </template>
                <p class="text-xs font-medium text-brand" x-show="optionNotice" x-text="optionNotice"></p>
            </div>
            <div class="grid grid-cols-2 gap-2 border-t border-[#f0ece7] px-6 py-4">
                <button type="button" class="btn-ghost !rounded-xl" @click="optionOpen = false">Batal</button>
                <button type="button" class="btn-brand !rounded-xl" @click="confirmOptions()" :disabled="busy">Tambah</button>
            </div>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4" x-show="payOpen" x-cloak @click.self="payOpen = false">
        <div class="pay-modal">
            <div class="pay-modal-hero">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-white/45">Pembayaran</p>
                        <p class="mt-0.5 truncate text-[13px] text-white/70" x-text="order?.order_number || 'Draft'"></p>
                    </div>
                    <p class="shrink-0 text-[20px] font-semibold leading-none tracking-tight text-white" x-text="formatMoney(grandTotal())"></p>
                </div>
                <p class="mt-2 text-[12px] text-white/50" x-show="Number(order?.discount_amount || 0) > 0">
                    Diskon <span x-text="discountLine()"></span>
                </p>
            </div>
            <div class="px-6 py-5">
                <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-muted">Metode bayar</p>
                <div class="mt-2.5 grid grid-cols-2 gap-2">
                    <template x-for="m in paymentMethods" :key="m.id">
                        <button type="button" class="pay-method" :class="method === m.id ? 'pay-method-active' : ''" @click="setMethod(m.id)">
                            <span class="block text-[13px] font-semibold" x-text="m.label"></span>
                            <span class="mt-0.5 block text-[11px] opacity-60" x-text="m.hint"></span>
                        </button>
                    </template>
                </div>

                <div class="mt-5 space-y-3" x-show="method === 'cash'">
                    <div>
                        <label class="label">Uang diterima</label>
                        <input class="input !text-lg !font-semibold" type="text" inputmode="numeric" autocomplete="off" :value="formatRupiah(tendered)" @input="onTenderedInput($event)" placeholder="Rp 0">
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <template x-for="preset in cashPresets()" :key="preset">
                                <button type="button" class="rounded-full border border-[#e8e8e8] bg-[#fafafa] px-2.5 py-1 text-[11px] font-medium text-heading transition hover:border-heading hover:bg-white" @click="tendered = preset" x-text="preset === grandTotal() ? 'Pas' : formatMoney(preset)"></button>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-center justify-between rounded-2xl bg-[#f6f6f6] px-4 py-3.5">
                        <span class="text-[13px] text-muted">Kembalian</span>
                        <span class="text-[22px] font-semibold tracking-tight text-heading" x-text="formatMoney(changeDue())"></span>
                    </div>
                    <p class="text-xs font-medium text-brand" x-show="cashShort()">Uang diterima masih kurang dari total.</p>
                </div>

                <p class="mt-4 text-xs text-muted" x-show="method !== 'cash'">Nominal akan dicatat sesuai total order.</p>
                <p class="mt-3 text-xs font-medium text-brand" x-show="notice" x-text="notice"></p>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button type="button" class="btn-ghost !rounded-xl" @click="payOpen = false">Batal</button>
                    <button type="button" class="btn-brand !rounded-xl" @click="checkout()" :disabled="!canCompletePay()">Selesaikan</button>
                </div>
            </div>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4" x-show="invoiceOpen" x-cloak @click.self="skipInvoice()">
        <div class="pay-modal">
            <div class="pay-modal-hero">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-white/45">Invoice WhatsApp</p>
                        <p class="mt-0.5 truncate text-[13px] text-white/70" x-text="invoiceOrder?.order_number || ''"></p>
                    </div>
                    <p class="shrink-0 text-[20px] font-semibold leading-none tracking-tight text-white" x-text="formatMoney(invoiceOrder?.grand_total || 0)"></p>
                </div>
            </div>
            <div class="px-6 py-5">
                <p class="text-[13px] text-heading">Kirim invoice ke WhatsApp customer?</p>
                <p class="mt-1 text-xs text-muted">Minta nomor WA customer, lalu kirim. Atau lewati jika tidak perlu.</p>
                <div class="mt-4">
                    <label class="label">Nomor WhatsApp</label>
                    <input class="input" type="tel" inputmode="tel" x-model="invoicePhone" placeholder="08xxxxxxxxxx atau 628…" @keydown.enter.prevent="sendInvoice()">
                </div>
                <p class="mt-3 text-xs font-medium text-brand" x-show="invoiceNotice" x-text="invoiceNotice"></p>
                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button type="button" class="btn-ghost !rounded-xl" @click="skipInvoice()" :disabled="invoiceBusy">Lewati</button>
                    <button type="button" class="btn-brand !rounded-xl inline-flex items-center justify-center gap-2" @click="sendInvoice()" :disabled="invoiceBusy || !invoicePhone">
                        <svg x-show="invoiceBusy" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                        </svg>
                        <span x-text="invoiceBusy ? 'Mengirim…' : 'Kirim WA'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="openHeld" x-cloak>
        <div class="card w-full max-w-lg p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold">Order Hold</h3>
                    <p class="mt-1 text-sm text-muted">Pilih order untuk dilanjutkan ke keranjang.</p>
                </div>
                <span class="rounded-full bg-brand-soft px-2.5 py-1 text-xs font-semibold text-brand" x-text="heldCount()"></span>
            </div>
            <div class="mt-4 max-h-80 space-y-2 overflow-y-auto">
                <template x-for="held in visibleHeld()" :key="held.id">
                    <button type="button" class="flex w-full items-center justify-between gap-3 rounded-xl border border-line px-4 py-3 text-left transition hover:border-brand hover:bg-brand-soft" @click="recall(held.id)">
                        <span>
                            <span class="block text-sm font-semibold text-heading" x-text="held.order_number"></span>
                            <span class="mt-0.5 block text-xs text-muted" x-text="heldMeta(held)"></span>
                        </span>
                        <span class="text-right">
                            <span class="block text-sm font-semibold text-heading" x-text="formatMoney(held.grand_total)"></span>
                            <span class="mt-0.5 block text-xs font-medium text-brand">Lanjutkan</span>
                        </span>
                    </button>
                </template>
                <p class="py-8 text-center text-sm text-muted" x-show="heldCount() === 0">Tidak ada order yang sedang di-hold.</p>
            </div>
            <div class="mt-4 flex justify-end">
                <button class="btn-ghost" @click="openHeld = false">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function posApp() {
    return {
        order: null, search: '', category: null, order_type: 'pickup', table_id: '',
        discount_id: '', method: 'cash', tendered: 0, payOpen: false, openHeld: false, busy: false, notice: '',
        cartOpen: false,
        optionOpen: false,
        optionProduct: null,
        optionVariantId: null,
        optionBundleId: null,
        optionSelected: [],
        optionNotice: '',
        invoiceOpen: false, invoiceOrder: null, invoicePhone: '', invoiceBusy: false, invoiceNotice: '',
        paymentMethods: [
            { id: 'cash', label: 'Tunai', hint: 'Hitung kembalian' },
            { id: 'card', label: 'Kartu', hint: 'Debit / kredit' },
            { id: 'qris', label: 'QRIS', hint: 'Scan QR' },
            { id: 'transfer', label: 'Transfer', hint: 'Bank transfer' },
        ],
        heldOrders: @json($heldOrders),
        online: navigator.onLine,
        images: @json($productImages),
        placeholder: @json(asset('images/menu/placeholder.svg')),
        discountCatalog: @json($discountCatalog),
        productCatalog: @json($productCatalog),
        init() {
            window.addEventListener('online', () => { this.online = true; this.flushQueue(); });
            window.addEventListener('offline', () => { this.online = false; });
            this.restoreDraft();
            this.loadHeld();
        },
        visibleHeld() {
            return (this.heldOrders || []).filter((held) => held.id !== this.order?.id);
        },
        heldCount() {
            return this.visibleHeld().length;
        },
        heldMeta(held) {
            const parts = [held.order_type_label || held.order_type, held.table ? `Meja ${held.table}` : null, held.customer, `${held.items_count || 0} item`].filter(Boolean);
            return parts.join(' · ');
        },
        async openHeldList() {
            await this.loadHeld();
            this.openHeld = true;
        },
        async loadHeld() {
            try {
                this.heldOrders = await this.request('{{ route('pos.held', absolute: false) }}', { headers: await this.csrf() });
            } catch (e) {}
        },
        channel() {
            return this.order_type === 'pickup' ? 'pickup' : (this.order_type === 'online' ? 'online' : 'pos');
        },
        etaLabel() {
            if (!this.order?.estimated_ready_at) return '';
            const date = new Date(this.order.estimated_ready_at);
            return 'ETA ' + date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        },
        productMatch(name) { return !this.search || name.includes(this.search.toLowerCase()); },
        itemImage(item) { return item.image_url || this.images[item.product_id] || this.placeholder; },
        formatMoney(v) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v || 0); },
        selectedDiscount() {
            return (this.discountCatalog || []).find((d) => String(d.id) === String(this.discount_id)) || null;
        },
        discountLine() {
            const amount = Number(this.order?.discount_amount || 0);
            return amount > 0 ? '- ' + this.formatMoney(amount) : this.formatMoney(0);
        },
        discountHint() {
            const promo = this.selectedDiscount();
            if (!promo || !this.order) return '';
            const amount = Number(this.order.discount_amount || 0);
            const subtotal = Number(this.order.subtotal || 0);
            if (promo.minimum > 0 && subtotal < promo.minimum) {
                return 'Belum dapat diskon. Min. transaksi ' + this.formatMoney(promo.minimum);
            }
            if (amount > 0 && promo.maximum !== null && amount >= promo.maximum) {
                return promo.value_label + ' · dipotong maks ' + this.formatMoney(promo.maximum);
            }
            return amount > 0 ? 'Potongan ' + promo.value_label : '';
        },
        parseRupiah(value) {
            return Number(String(value ?? '').replace(/[^\d]/g, '')) || 0;
        },
        formatRupiah(value) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(this.parseRupiah(value));
        },
        onTenderedInput(event) {
            this.tendered = this.parseRupiah(event.target.value);
            event.target.value = this.formatRupiah(this.tendered);
        },
        grandTotal() { return Number(this.order?.grand_total || 0); },
        tenderedAmount() { return Number(this.tendered || 0); },
        changeDue() { return Math.max(0, this.tenderedAmount() - this.grandTotal()); },
        cashShort() { return this.method === 'cash' && this.tenderedAmount() < this.grandTotal(); },
        canPay() {
            return !!this.order?.items?.length && !this.busy;
        },
        canCompletePay() {
            if (!this.canPay()) return false;
            if (this.method === 'cash') return this.tenderedAmount() >= this.grandTotal();
            return true;
        },
        cashPresets() {
            const total = this.grandTotal();
            const steps = [50000, 100000, 150000, 200000, 300000, 500000];
            return [total, ...steps.filter((amount) => amount > total).slice(0, 3)];
        },
        openPay() {
            if (!this.canPay()) {
                this.notice = 'Tambah item dulu.';
                return;
            }
            this.notice = '';
            this.method = 'cash';
            this.tendered = this.grandTotal();
            this.payOpen = true;
        },
        setMethod(id) {
            this.method = id;
            this.notice = '';
            if (id !== 'cash') this.tendered = this.grandTotal();
        },
        persistDraft() {
            const payload = {
                order_type: this.order_type, table_id: this.table_id,
                items: (this.order?.items || []).map((item) => ({
                    product_id: item.product_id, product_variant_id: item.product_variant_id, bundle_id: item.bundle_id,
                    quantity: item.quantity, notes: item.notes,
                })),
            };
            localStorage.setItem('pos_offline_draft', JSON.stringify(payload));
        },
        restoreDraft() {
            const raw = localStorage.getItem('pos_offline_draft');
            if (!raw || this.order) return;
            try {
                const draft = JSON.parse(raw);
                this.order_type = draft.order_type || this.order_type;
                this.table_id = draft.table_id || '';
            } catch (e) {}
        },
        queue(action) {
            const items = JSON.parse(localStorage.getItem('pos_offline_queue') || '[]');
            items.push(action);
            localStorage.setItem('pos_offline_queue', JSON.stringify(items));
        },
        async flushQueue() {
            const items = JSON.parse(localStorage.getItem('pos_offline_queue') || '[]');
            if (!items.length) return;
            localStorage.removeItem('pos_offline_queue');
            for (const action of items) {
                if (action.type === 'add') {
                    await this.addProduct(action.product_id, action.variant_id, action.bundle_id, true, action.option_ids || []);
                }
            }
        },
        async csrf() { return { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' }; },
        appUrl(url) {
            if (!url.startsWith('/') || url.startsWith('//')) return url;
            const fromLaravel = @json(rtrim((string) request()->getBasePath(), '/'));
            const path = window.location.pathname;
            const posAt = path.indexOf('/pos');
            const fromWindow = posAt > 0 ? path.slice(0, posAt) : '';
            return (fromLaravel || fromWindow) + url;
        },
        async request(url, options) {
            if (!this.online) throw new Error('offline');
            const res = await fetch(this.appUrl(url), options);
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Request gagal');
            }
            return res.json();
        },
        async ensureOrder() {
            if (this.order) return this.order;
            this.order = await this.request('{{ route('pos.draft', absolute: false) }}', { method: 'POST', headers: await this.csrf(), body: JSON.stringify({
                order_type: this.order_type, table_id: this.table_id || null, channel: this.channel()
            })});
            return this.order;
        },
        beginAdd(productId, variantId, bundleId) {
            if (bundleId) {
                return this.addProduct(productId, variantId, bundleId);
            }
            const product = this.productCatalog[productId] || this.productCatalog[String(productId)];
            const groups = (product?.option_groups || []).filter((g) => (g.options || []).some((o) => o.is_active));
            if (!groups.length) {
                return this.addProduct(productId, variantId, bundleId);
            }
            this.optionProduct = { ...product, option_groups: groups };
            this.optionVariantId = variantId;
            this.optionBundleId = null;
            this.optionSelected = [];
            this.optionNotice = '';
            this.optionOpen = true;
        },
        toggleOption(group, optionId) {
            const id = Number(optionId);
            const max = Number(group.max_select) || 1;
            if (max <= 1) {
                const groupIds = (group.options || []).map((o) => Number(o.id));
                this.optionSelected = this.optionSelected.filter((x) => !groupIds.includes(Number(x)));
                this.optionSelected.push(id);
                return;
            }
            if (this.optionSelected.includes(id)) {
                this.optionSelected = this.optionSelected.filter((x) => Number(x) !== id);
                return;
            }
            const groupIds = (group.options || []).map((o) => Number(o.id));
            const current = this.optionSelected.filter((x) => groupIds.includes(Number(x)));
            if (current.length >= max) {
                this.optionNotice = `${group.name}: maksimal ${max} pilihan.`;
                return;
            }
            this.optionNotice = '';
            this.optionSelected.push(id);
        },
        optionPreviewTotal() {
            if (!this.optionProduct) return 0;
            let total = Number(this.optionProduct.price || 0);
            (this.optionProduct.option_groups || []).forEach((group) => {
                (group.options || []).forEach((opt) => {
                    if (this.optionSelected.includes(Number(opt.id))) {
                        total += Number(opt.price_adjustment || 0);
                    }
                });
            });
            return total;
        },
        confirmOptions() {
            const groups = this.optionProduct?.option_groups || [];
            for (const group of groups) {
                const groupIds = (group.options || []).map((o) => Number(o.id));
                const count = this.optionSelected.filter((x) => groupIds.includes(Number(x))).length;
                const min = group.is_required ? Math.max(1, Number(group.min_select) || 1) : Number(group.min_select) || 0;
                if (count < min) {
                    this.optionNotice = `Pilih ${group.name} terlebih dahulu.`;
                    return;
                }
            }
            const productId = this.optionProduct.id;
            const variantId = this.optionVariantId;
            const optionIds = [...this.optionSelected];
            this.optionOpen = false;
            this.addProduct(productId, variantId, null, false, optionIds);
        },
        async addProduct(productId, variantId, bundleId, fromQueue = false, optionIds = []) {
            try {
                await this.ensureOrder();
                this.order = await this.request(`/pos/${this.order.id}/items`, { method: 'POST', headers: await this.csrf(), body: JSON.stringify({
                    product_id: productId, product_variant_id: variantId, bundle_id: bundleId, quantity: 1, option_ids: optionIds || []
                })});
                this.persistDraft();
            } catch (e) {
                if (!fromQueue) this.queue({ type: 'add', product_id: productId, variant_id: variantId, bundle_id: bundleId, option_ids: optionIds || [] });
                this.notice = e.message || 'Gagal menambah item.';
            }
        },
        async changeQty(item, delta) {
            const qty = Number(item.quantity) + delta;
            if (qty <= 0) return this.removeItem(item.id);
            item.quantity = qty;
            await this.updateItem(item);
        },
        async updateItem(item) {
            this.order = await this.request(`/pos/${this.order.id}/items/${item.id}`, { method: 'PUT', headers: await this.csrf(), body: JSON.stringify({ quantity: item.quantity, notes: item.notes })});
            this.persistDraft();
        },
        async removeItem(id) {
            this.order = await this.request(`/pos/${this.order.id}/items/${id}`, { method: 'DELETE', headers: await this.csrf() });
            this.persistDraft();
        },
        async applyDiscount() {
            if (!this.order) return;
            this.order = await this.request(`/pos/${this.order.id}/discount`, { method: 'POST', headers: await this.csrf(), body: JSON.stringify({ discount_id: this.discount_id || null })});
        },
        async hold() {
            if (!this.order) return;
            const held = await this.request(`/pos/${this.order.id}/hold`, { method: 'POST', headers: await this.csrf() });
            this.heldOrders = [held, ...this.heldOrders.filter((item) => item.id !== held.id)];
            this.order = null;
            this.discount_id = '';
            localStorage.removeItem('pos_offline_draft');
        },
        async recall(id) {
            if (this.order && this.order.id !== id && this.order.items?.length) {
                await this.hold();
            }
            this.order = await this.request(`/pos/${id}/recall`, { headers: await this.csrf() });
            this.table_id = this.order.table_id || '';
            this.order_type = this.order.order_type || this.order_type;
            this.discount_id = this.order.discount_id || '';
            this.heldOrders = this.heldOrders.filter((item) => item.id !== id);
            this.openHeld = false;
        },
        async checkout() {
            if (!this.canCompletePay()) {
                this.notice = this.cashShort() ? 'Uang diterima masih kurang dari total.' : 'Tidak bisa menyelesaikan pembayaran.';
                return;
            }
            this.busy = true;
            this.notice = '';
            try {
                const paid = this.method === 'cash' ? this.tenderedAmount() : this.grandTotal();
                const data = await this.request(`/pos/${this.order.id}/checkout`, { method: 'POST', headers: await this.csrf(), body: JSON.stringify({
                    method: this.method, amount: this.grandTotal(), tendered: paid,
                    order_type: this.order_type, table_id: this.table_id || null,
                })});
                this.payOpen = false;
                if (data.order) {
                    if (data.print_jobs?.length && window.RasaQz?.printTickets) {
                        await window.RasaQz.printTickets(data);
                    }
                    this.invoiceOrder = data.order;
                    this.invoicePhone = data.order.customer?.phone || '';
                    this.invoiceNotice = data.kitchen_whatsapp_sent ? 'Pesanan dapur sudah dikirim via WhatsApp.' : '';
                    this.invoiceOpen = true;
                    this.order = null;
                    this.tendered = 0;
                    localStorage.removeItem('pos_offline_draft');
                }
            } catch (e) {
                this.notice = e.message || 'Pembayaran gagal.';
            } finally {
                this.busy = false;
            }
        },
        skipInvoice() {
            this.invoiceOpen = false;
            this.invoiceOrder = null;
            this.invoicePhone = '';
            this.invoiceNotice = '';
            this.notice = 'Pembayaran berhasil.';
        },
        async sendInvoice() {
            if (!this.invoiceOrder || !this.invoicePhone) return;
            this.invoiceBusy = true;
            this.invoiceNotice = '';
            try {
                const data = await this.request(`/pos/${this.invoiceOrder.id}/invoice-whatsapp`, {
                    method: 'POST',
                    headers: await this.csrf(),
                    body: JSON.stringify({ phone: this.invoicePhone }),
                });
                this.invoiceOpen = false;
                this.invoiceOrder = null;
                this.invoicePhone = '';
                this.notice = 'Pembayaran berhasil. Invoice terkirim ke WhatsApp.';
                if (window.Swal) {
                    const asPdf = data?.via === 'pdf';
                    await Swal.fire({
                        icon: 'success',
                        title: 'Terkirim',
                        text: asPdf
                            ? 'Invoice PDF sudah dikirim ke WhatsApp customer.'
                            : (data?.notice || 'Invoice sudah dikirim ke WhatsApp (teks).'),
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#7a1f1f',
                    });
                }
            } catch (e) {
                this.invoiceNotice = e.message || 'Gagal kirim WhatsApp.';
                if (window.Swal) {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Gagal kirim',
                        text: e.message || 'Gagal kirim WhatsApp.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#7a1f1f',
                    });
                }
            } finally {
                this.invoiceBusy = false;
            }
        },
    }
}
</script>
@endpush
