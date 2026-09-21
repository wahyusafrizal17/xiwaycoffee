@extends('layouts.pos')
@section('content')
<div class="flex h-full min-h-0" x-data="posApp()">
    <div class="pos-shell">
        <section class="pos-catalog">
            <div class="flex items-center justify-between gap-3 border-b border-[#f0ece7] px-3 py-2 text-xs" x-show="!online" x-cloak>
                <span class="font-medium text-[#d97706]">Mode offline — order akan dikirim saat koneksi kembali.</span>
            </div>
            <div class="flex flex-col gap-2.5 border-b border-[#f0ece7] px-3 py-2.5 sm:flex-row sm:items-center">
                <div class="flex min-w-0 flex-1 gap-1.5 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <button type="button" class="pos-chip" :class="!category ? 'pos-chip-active' : 'pos-chip-idle'" @click="category = null">Semua</button>
                    @foreach ($categories as $category)
                        <button type="button" class="pos-chip" :class="category == {{ $category->id }} ? 'pos-chip-active' : 'pos-chip-idle'" @click="category = {{ $category->id }}">{{ $category->name }}</button>
                    @endforeach
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <div class="flex items-center gap-1 rounded-xl bg-[#f3f0ec] p-1" title="Kontrol layar menu TV">
                        <button type="button" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold transition" :class="displayFocus === 'drinks' ? 'bg-white text-heading shadow-sm' : 'text-muted'" @click="setDisplayFocus('drinks')">Minuman</button>
                        <button type="button" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold transition" :class="displayFocus === 'food' ? 'bg-white text-heading shadow-sm' : 'text-muted'" @click="setDisplayFocus('food')">Makanan</button>
                        <button type="button" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold transition" :class="displayFocus === 'auto' ? 'bg-white text-heading shadow-sm' : 'text-muted'" @click="setDisplayFocus('auto')">Auto</button>
                    </div>
                    <div class="relative w-full sm:w-44">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/></svg>
                        <input class="input !rounded-xl !border-[#ebe7e2] !bg-[#faf9f7] !py-2 !pl-9 !text-[13px]" placeholder="Cari menu..." x-model="search">
                    </div>
                </div>
            </div>
            <div class="grid flex-1 auto-rows-min grid-cols-1 gap-2 overflow-y-auto p-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($products as $product)
                    @php
                        $hasOptions = $product->optionGroups->contains(fn ($g) => $g->options->contains(fn ($o) => $o->is_active));
                    @endphp
                    <article
                        class="menu-card"
                        x-show="(!category || category == {{ $product->category_id }}) && productMatch('{{ strtolower($product->name) }}')"
                        @click="beginAdd({{ $product->id }}, null, null)"
                    >
                        <div class="menu-card-visual">
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="menu-card-photo" loading="lazy">
                        </div>
                        <div class="menu-card-body">
                            <div class="menu-card-meta">
                                <span class="menu-card-badge">{{ $product->is_recommended ? '★ ' : '' }}{{ $product->category?->name }}</span>
                                @if ($hasOptions)
                                    <span class="menu-card-opt">Opsi</span>
                                @endif
                            </div>
                            <h3 class="line-clamp-2 text-[14px] font-semibold leading-snug text-heading">{{ $product->name }}</h3>
                            <p class="text-[14px] font-semibold tracking-tight text-heading">{{ money($product->price) }}</p>
                            @if ($product->variants->count())
                                <div class="mt-0.5 flex flex-wrap gap-1" @click.stop>
                                    @foreach ($product->variants as $variant)
                                        <button type="button" class="rounded-full bg-[#f3f0ec] px-2 py-0.5 text-[11px] font-medium text-heading transition hover:bg-[#ebe7e2]" @click="beginAdd({{ $product->id }}, {{ $variant->id }}, null)">{{ $variant->name }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
                @foreach ($bundles as $bundle)
                    <article class="menu-card" x-show="!category || category == {{ $bundle->product?->category_id ?? 0 }}" @click="beginAdd({{ $bundle->product_id ?? $bundle->items->first()?->product_id }}, null, {{ $bundle->id }})">
                        <div class="menu-card-visual">
                            <img src="{{ $bundle->product?->imageUrl() ?? asset('images/menu/placeholder.svg') }}" alt="{{ $bundle->name }}" class="menu-card-photo" loading="lazy">
                        </div>
                        <div class="menu-card-body">
                            <div class="menu-card-meta">
                                <span class="menu-card-badge">Package</span>
                            </div>
                            <h3 class="line-clamp-2 text-[14px] font-semibold leading-snug text-heading">{{ $bundle->name }}</h3>
                            <p class="text-[14px] font-semibold tracking-tight text-heading">{{ money($bundle->price) }}</p>
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
            <div class="border-b border-[#f0ece7] px-4 py-3 lg:px-4">
                <div class="mx-auto mb-2.5 h-1 w-10 rounded-full bg-[#e5e5e5] lg:hidden"></div>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted">Current order</p>
                        <p class="mt-1 truncate text-[15px] font-semibold text-heading" x-text="order?.order_number || 'Draft baru'"></p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <span class="rounded-full bg-brand-soft px-2 py-0.5 text-[10px] font-semibold text-brand" x-show="order?.status === 'held'" x-cloak>Hold</span>
                        <span class="rounded-full bg-[#f3f0ec] px-2 py-0.5 text-[10px] font-medium text-muted" x-show="order?.estimated_ready_at && order?.status !== 'held'" x-text="etaLabel()" x-cloak></span>
                        <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-muted hover:bg-[#f3f0ec] lg:hidden" @click="cartOpen = false">Tutup</button>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-1 rounded-xl bg-[#f3f0ec] p-1">
                    <button type="button" class="rounded-lg px-2 py-1.5 text-[12px] font-semibold transition" :class="order_type === 'pickup' ? 'bg-white text-heading shadow-sm' : 'text-muted'" @click="order_type = 'pickup'">Pickup</button>
                    <button type="button" class="rounded-lg px-2 py-1.5 text-[12px] font-semibold transition" :class="order_type === 'dine_in' ? 'bg-white text-heading shadow-sm' : 'text-muted'" @click="order_type = 'dine_in'">Dine-in</button>
                    <button type="button" class="rounded-lg px-2 py-1.5 text-[12px] font-semibold transition" :class="order_type === 'online' ? 'bg-white text-heading shadow-sm' : 'text-muted'" @click="order_type = 'online'">Online</button>
                </div>
            </div>

            <div class="flex-1 space-y-1.5 overflow-y-auto px-3 py-3">
                <template x-if="!order || !order.items?.length">
                    <div class="flex h-full min-h-28 flex-col items-center justify-center rounded-xl border border-dashed border-[#e8e4df] bg-[#faf9f7] px-4 text-center">
                        <p class="text-sm font-medium text-heading">Belum ada item</p>
                        <p class="mt-1 text-xs text-muted">Ketuk menu untuk menambah.</p>
                    </div>
                </template>
                <template x-for="item in (order?.items || [])" :key="item.id">
                    <div class="order-item">
                        <div class="flex items-start gap-2.5">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="line-clamp-2 text-[13px] font-semibold leading-snug text-heading" x-text="item.name"></p>
                                        <p class="mt-0.5 text-[12px] text-muted" x-text="formatMoney(item.unit_price)"></p>
                                    </div>
                                    <button type="button" class="shrink-0 rounded-md p-1 text-muted transition hover:bg-white hover:text-brand" @click="removeItem(item.id)" title="Hapus">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                    </button>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <div class="order-qty">
                                        <button type="button" @click="changeQty(item, -1)">−</button>
                                        <span x-text="Number(item.quantity)"></span>
                                        <button type="button" @click="changeQty(item, 1)">+</button>
                                    </div>
                                    <input class="order-note" placeholder="Catatan" x-model="item.notes" @change="updateItem(item)">
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="space-y-2.5 border-t border-[#f0ece7] px-4 py-3.5">
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
                    <span>Charge</span>
                    <span class="font-medium text-heading" x-text="formatMoney(order?.tax_amount || 0)"></span>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-heading px-3.5 py-3 text-white">
                    <span class="text-sm font-medium">Total</span>
                    <span class="text-[17px] font-semibold tracking-tight" x-text="formatMoney(order?.grand_total || 0)"></span>
                </div>
                <p class="text-xs font-medium text-brand" x-show="notice" x-text="notice" x-cloak></p>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#ebe7e2] bg-white px-4 py-2.5 text-sm font-medium text-heading transition hover:bg-[#faf9f7] disabled:cursor-not-allowed disabled:opacity-40" @click="hold()" :disabled="!order?.items?.length">
                        Hold
                        <span
                            class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-heading px-1.5 text-[10px] font-bold leading-none text-white"
                            x-show="heldCount() > 0"
                            x-text="heldCount()"
                            x-cloak
                            @click.stop="openHeldList()"
                            title="Lihat order hold"
                        ></span>
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
            <div class="max-h-[55vh] space-y-5 overflow-y-auto px-6 py-5">
                <template x-for="group in (optionProduct?.option_groups || [])" :key="group.id">
                    <div>
                        <div class="mb-2.5 flex items-center justify-between gap-2">
                            <p class="text-[12px] font-semibold text-heading" x-text="group.name"></p>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="group.is_required ? 'bg-brand-soft text-brand' : 'bg-[#f3f0ec] text-muted'" x-text="group.is_required ? 'Wajib' : 'Opsional'"></span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="opt in (group.options || []).filter(o => o.is_active)" :key="opt.id">
                                <button
                                    type="button"
                                    class="pos-opt-chip min-w-[calc(50%-0.25rem)]"
                                    :class="optionSelected.includes(Number(opt.id)) ? 'pos-opt-chip-active' : 'pos-opt-chip-idle'"
                                    @click="toggleOption(group, opt.id)"
                                >
                                    <span class="truncate" x-text="opt.name"></span>
                                    <span class="shrink-0 text-[11px] opacity-70" x-text="Number(opt.price_adjustment) > 0 ? ('+ ' + formatMoney(opt.price_adjustment)) : ''"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="flex items-center justify-between gap-3 rounded-xl border border-[#ebe7e2] bg-[#faf9f7] px-3 py-2.5">
                    <p class="text-[12px] font-semibold text-heading">Jumlah</p>
                    <div class="order-qty">
                        <button type="button" @click="optionQty = Math.max(1, Number(optionQty) - 1)">−</button>
                        <span x-text="optionQty"></span>
                        <button type="button" @click="optionQty = Number(optionQty) + 1">+</button>
                    </div>
                </div>
                <p class="text-xs font-medium text-brand" x-show="optionNotice" x-text="optionNotice"></p>
            </div>
            <div class="grid grid-cols-2 gap-2 border-t border-[#f0ece7] px-6 py-4">
                <button type="button" class="btn-ghost !rounded-xl" @click="optionOpen = false">Batal</button>
                <button type="button" class="btn-brand !rounded-xl" @click="confirmOptions()" :disabled="busy">Tambah ke order</button>
            </div>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4" x-show="payOpen" x-cloak @click.self="!busy && (payOpen = false)">
        <div class="pay-modal relative">
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
                        <button type="button" class="pay-method" :class="method === m.id ? 'pay-method-active' : ''" @click="!busy && setMethod(m.id)" :disabled="busy">
                            <span class="block text-[13px] font-semibold" x-text="m.label"></span>
                            <span class="mt-0.5 block text-[11px] opacity-60" x-text="m.hint"></span>
                        </button>
                    </template>
                </div>

                <div class="mt-5 space-y-3" x-show="method === 'cash'">
                    <div>
                        <label class="label">Uang diterima</label>
                        <input class="input !text-lg !font-semibold" type="text" inputmode="numeric" autocomplete="off" :value="formatRupiah(tendered)" @input="onTenderedInput($event)" placeholder="Rp 0" :disabled="busy">
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <template x-for="preset in cashPresets()" :key="preset">
                                <button type="button" class="rounded-full border border-[#e8e8e8] bg-[#fafafa] px-2.5 py-1 text-[11px] font-medium text-heading transition hover:border-heading hover:bg-white disabled:opacity-50" @click="tendered = preset" :disabled="busy" x-text="preset === grandTotal() ? 'Pas' : formatMoney(preset)"></button>
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
                    <button type="button" class="btn-ghost !rounded-xl" @click="payOpen = false" :disabled="busy">Batal</button>
                    <button type="button" class="btn-brand !rounded-xl inline-flex items-center justify-center gap-2" @click="checkout()" :disabled="!canCompletePay() || busy">
                        <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                        </svg>
                        <span x-text="busy ? 'Memproses…' : 'Selesaikan'"></span>
                    </button>
                </div>
            </div>

            <div class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 rounded-2xl bg-white/80 backdrop-blur-[2px]" x-show="busy" x-cloak>
                <svg class="h-8 w-8 animate-spin text-brand" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                </svg>
                <p class="text-sm font-medium text-heading">Memproses pembayaran…</p>
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
        displayFocus: 'auto',
        optionOpen: false,
        optionProduct: null,
        optionVariantId: null,
        optionBundleId: null,
        optionSelected: [],
        optionQty: 1,
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
        async setDisplayFocus(mode) {
            this.displayFocus = mode;
            try {
                await this.request('{{ route('pos.display.focus', absolute: false) }}', {
                    method: 'POST',
                    headers: await this.csrf(),
                    body: JSON.stringify({ mode }),
                });
            } catch (e) {
                this.notice = e.message || 'Gagal update layar menu.';
            }
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
                    await this.addProduct(action.product_id, action.variant_id, action.bundle_id, true, action.option_ids || [], action.quantity || 1);
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
            this.optionQty = 1;
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
                this.optionNotice = '';
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
            let unit = Number(this.optionProduct.price || 0);
            (this.optionProduct.option_groups || []).forEach((group) => {
                (group.options || []).forEach((opt) => {
                    if (this.optionSelected.includes(Number(opt.id))) {
                        unit += Number(opt.price_adjustment || 0);
                    }
                });
            });
            return unit * Math.max(1, Number(this.optionQty) || 1);
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
            const quantity = Math.max(1, Number(this.optionQty) || 1);
            this.optionOpen = false;
            this.addProduct(productId, variantId, null, false, optionIds, quantity);
        },
        async addProduct(productId, variantId, bundleId, fromQueue = false, optionIds = [], quantity = 1) {
            try {
                await this.ensureOrder();
                this.order = await this.request(`/pos/${this.order.id}/items`, { method: 'POST', headers: await this.csrf(), body: JSON.stringify({
                    product_id: productId, product_variant_id: variantId, bundle_id: bundleId, quantity: quantity || 1, option_ids: optionIds || []
                })});
                this.persistDraft();
            } catch (e) {
                if (!fromQueue) this.queue({ type: 'add', product_id: productId, variant_id: variantId, bundle_id: bundleId, option_ids: optionIds || [], quantity: quantity || 1 });
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
