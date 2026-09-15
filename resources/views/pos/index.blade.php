@extends('layouts.pos')
@section('content')
<div class="flex h-full min-h-0" x-data="posApp()">
    <div class="grid min-h-0 flex-1 gap-3 p-3 lg:grid-cols-[minmax(0,1fr)_400px]">
        <section class="card flex min-h-0 flex-col overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-line px-3 py-2 text-xs" x-show="!online" x-cloak>
                <span class="font-medium text-[#d97706]">Mode offline — order akan dikirim saat koneksi kembali.</span>
            </div>
            @if ($lowStock->isNotEmpty())
                <div class="border-b border-brand-soft bg-brand-soft px-3 py-2 text-xs text-brand">
                    Stok menipis: {{ $lowStockNames }}@if ($lowStockExtra > 0) +{{ $lowStockExtra }} lagi @endif
                </div>
            @endif
            <div class="flex items-center gap-3 border-b border-line px-3 py-2.5">
                <div class="flex min-w-0 flex-1 gap-2 overflow-x-auto">
                    <button class="shrink-0 rounded-full px-3 py-1.5 text-sm" :class="!category ? 'bg-brand text-white' : 'bg-slate-100 text-heading'" @click="category = null">Semua</button>
                    @foreach ($categories as $category)
                        <button class="shrink-0 rounded-full px-3 py-1.5 text-sm" :class="category == {{ $category->id }} ? 'bg-brand text-white' : 'bg-slate-100 text-heading'" @click="category = {{ $category->id }}">{{ $category->name }}</button>
                    @endforeach
                </div>
                <input class="input !w-56 shrink-0 !py-2" placeholder="Cari menu..." x-model="search">
            </div>
            <div class="grid flex-1 auto-rows-min grid-cols-3 gap-3 overflow-y-auto p-3">
                @foreach ($products as $product)
                    <article
                        class="menu-card min-h-[188px]"
                        x-show="(!category || category == {{ $product->category_id }}) && productMatch('{{ strtolower($product->name) }}')"
                    >
                        <div class="menu-card-visual">
                            <span class="menu-card-badge">{{ $product->is_recommended ? '⭐ ' : '' }}{{ $product->category?->name }}</span>
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="menu-card-photo" loading="lazy">
                        </div>
                        <div class="menu-card-body">
                            <div>
                                <h3 class="text-[17px] font-bold leading-tight text-heading">{{ $product->name }}</h3>
                                <p class="mt-1.5 line-clamp-2 text-[13px] leading-snug text-neutral-700">{{ $product->menuDescription() }}</p>
                                <p class="mt-2.5 text-[16px] font-bold text-heading">{{ money($product->price) }}</p>
                                @if ($product->variants->count())
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($product->variants as $variant)
                                            <button type="button" class="rounded-full bg-white px-2 py-0.5 text-[11px] text-heading shadow-sm" @click="addProduct({{ $product->id }}, {{ $variant->id }}, null)">{{ $variant->name }}</button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <button type="button" class="menu-card-add" @click="addProduct({{ $product->id }}, null, null)">Tambah</button>
                        </div>
                    </article>
                @endforeach
                @foreach ($bundles as $bundle)
                    <article class="menu-card min-h-[188px]" x-show="!category || category == {{ $bundle->product?->category_id ?? 0 }}">
                        <div class="menu-card-visual">
                            <span class="menu-card-badge">Package</span>
                            <img src="{{ $bundle->product?->imageUrl() ?? asset('images/menu/placeholder.svg') }}" alt="{{ $bundle->name }}" class="menu-card-photo" loading="lazy">
                        </div>
                        <div class="menu-card-body">
                            <div>
                                <h3 class="text-[17px] font-bold leading-tight text-heading">{{ $bundle->name }}</h3>
                                <p class="mt-1.5 line-clamp-2 text-[13px] leading-snug text-neutral-700">Paket hemat berisi makanan utama dan minuman pilihan</p>
                                <p class="mt-2.5 text-[16px] font-bold text-heading">{{ money($bundle->price) }}</p>
                            </div>
                            <button type="button" class="menu-card-add" @click="addProduct({{ $bundle->product_id ?? $bundle->items->first()?->product_id }}, null, {{ $bundle->id }})">Tambah</button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <aside class="order-panel">
            <div class="flex items-center justify-between px-5 py-4">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-muted">Current order</p>
                    <p class="mt-1 text-[15px] font-semibold text-heading" x-text="order?.order_number || 'Draft baru'"></p>
                    <p class="mt-0.5 text-[11px] font-medium text-brand" x-show="order?.status === 'held'">Hold — siap dilanjutkan</p>
                    <p class="mt-0.5 text-[11px] text-muted" x-show="order?.estimated_ready_at && order?.status !== 'held'" x-text="etaLabel()"></p>
                </div>
                <button class="rounded-lg border border-[#e5e5e5] px-3 py-1.5 text-xs font-medium text-heading transition hover:bg-neutral-50" @click="hold()" x-show="order">Hold</button>
            </div>
            <div class="mx-5 space-y-3 border-t border-[#eee] pt-3">
                <div class="space-y-2">
                    <p class="text-[11px] font-medium uppercase tracking-[0.16em] text-muted">Tipe order</p>
                    <div class="flex items-center gap-2">
                        <select class="input !min-w-0 !flex-1 !py-2 !text-xs" x-model="order_type">
                            <option value="pickup">Pickup</option>
                            <option value="dine_in">Dine-in</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="mx-5 mt-3 border-t border-[#eee]"></div>
            <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                <template x-if="!order || !order.items?.length">
                    <div class="flex h-full min-h-40 flex-col items-center justify-center rounded-2xl border border-dashed border-[#e5e5e5] bg-[#fafafa] px-4 text-center">
                        <p class="text-sm font-medium text-heading">Belum ada item</p>
                        <p class="mt-1 text-xs text-muted">Pilih menu di kiri, lalu tekan Tambah.</p>
                    </div>
                </template>
                <template x-for="item in (order?.items || [])" :key="item.id">
                    <div class="order-item">
                        <div class="flex items-start gap-3">
                            <img :src="itemImage(item)" :alt="item.name" class="h-16 w-16 shrink-0 rounded-xl bg-white object-cover shadow-sm">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-heading" x-text="item.name"></p>
                                        <p class="mt-0.5 text-xs text-muted" x-text="formatMoney(item.unit_price)"></p>
                                    </div>
                                    <button class="shrink-0 text-[11px] font-medium text-brand hover:underline" @click="removeItem(item.id)">Hapus</button>
                                </div>
                                <div class="mt-3 flex items-center gap-2">
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
            <div class="space-y-3 border-t border-[#eee] px-5 py-4">
                <div class="order-row text-muted">
                    <span>Subtotal</span>
                    <span class="font-medium text-heading" x-text="formatMoney(order?.subtotal || 0)"></span>
                </div>
                <div class="space-y-1.5">
                    <div class="order-row text-muted">
                        <span>Diskon</span>
                        <span class="font-medium" :class="Number(order?.discount_amount || 0) > 0 ? 'text-brand' : 'text-heading'" x-text="discountLine()"></span>
                    </div>
                    <select class="h-8 w-full rounded-lg border border-[#e5e5e5] bg-white px-2 text-xs text-heading outline-none" x-model="discount_id" @change="applyDiscount()">
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
                <div class="flex items-center justify-between rounded-xl bg-[#111] px-3.5 py-3 text-white">
                    <span class="text-sm font-medium">Total</span>
                    <span class="text-lg font-semibold" x-text="formatMoney(order?.grand_total || 0)"></span>
                </div>
                <p class="text-xs font-medium text-brand" x-show="notice" x-text="notice" x-cloak></p>
                <div class="grid grid-cols-2 gap-2">
                    <button class="inline-flex items-center justify-center gap-2 rounded-full border border-[#e5e5e5] bg-white px-4 py-2.5 text-sm font-medium text-heading transition hover:bg-neutral-50" @click="openHeldList()">
                        Order Hold
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand px-1.5 text-[10px] font-bold leading-none text-white" x-show="heldCount() > 0" x-text="heldCount()" x-cloak></span>
                    </button>
                    <button class="rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-40" @click="openPay()" :disabled="!canPay()">Bayar</button>
                </div>
            </div>
        </aside>
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
<script>
function posApp() {
    return {
        order: null, search: '', category: null, order_type: 'pickup', table_id: '',
        discount_id: '', method: 'cash', tendered: 0, payOpen: false, openHeld: false, busy: false, notice: '',
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
                    await this.addProduct(action.product_id, action.variant_id, action.bundle_id, true);
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
        async addProduct(productId, variantId, bundleId, fromQueue = false) {
            try {
                await this.ensureOrder();
                this.order = await this.request(`/pos/${this.order.id}/items`, { method: 'POST', headers: await this.csrf(), body: JSON.stringify({
                    product_id: productId, product_variant_id: variantId, bundle_id: bundleId, quantity: 1
                })});
                this.persistDraft();
            } catch (e) {
                if (!fromQueue) this.queue({ type: 'add', product_id: productId, variant_id: variantId, bundle_id: bundleId });
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
                    const qzOk = window.RasaQz?.printCheckout
                        ? await window.RasaQz.printCheckout(data)
                        : false;
                    this.notice = qzOk
                        ? 'Pembayaran berhasil. Invoice customer dan tiket dapur/bar dicetak.'
                        : 'Pembayaran berhasil. QZ Tray belum cetak — jalankan QZ Tray. Jangan print dari Chrome.';
                    this.order = null;
                    this.tendered = 0;
                    localStorage.removeItem('pos_offline_draft');
                }
            } catch (e) {
                this.notice = e.message || 'Pembayaran gagal.';
            } finally {
                this.busy = false;
            }
        }
    }
}
</script>
@endpush
