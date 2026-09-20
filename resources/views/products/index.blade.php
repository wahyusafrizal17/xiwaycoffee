@extends('layouts.app')
@section('title', 'Products')
@section('breadcrumb', 'Catalog')
@section('content')
    @php
        $formError = $errors->any();
        $requestedModal = request('modal');
        $storeUrl = route('products.store');
        $formOld = [
            'sku' => old('sku', data_get($focusPayload, 'sku', '')),
            'name' => old('name', data_get($focusPayload, 'name', '')),
            'category_id' => (string) old('category_id', data_get($focusPayload, 'category_id', '')),
            'unit_id' => (string) old('unit_id', data_get($focusPayload, 'unit_id', '')),
            'type' => old('type', data_get($focusPayload, 'type', 'finished')),
            'bom_level' => old('bom_level', data_get($focusPayload, 'bom_level', 0)),
            'description' => old('description', data_get($focusPayload, 'description', '')),
            'image' => old('image', data_get($focusPayload, 'image', '')),
            'image_url' => data_get($focusPayload, 'image_url', ''),
            'price' => old('price', data_get($focusPayload, 'price', 0)),
            'cost' => old('cost', data_get($focusPayload, 'cost', 0)),
            'is_sellable' => old('is_sellable', data_get($focusPayload, 'is_sellable', true) ? '1' : '0') !== '0',
            'is_stockable' => old('is_stockable', data_get($focusPayload, 'is_stockable', true) ? '1' : '0') !== '0',
            'is_active' => old('is_active', data_get($focusPayload, 'is_active', true) ? '1' : '0') !== '0',
            'minimum_stock' => old('minimum_stock', data_get($focusPayload, 'minimum_stock', 0)),
            'reorder_level' => old('reorder_level', data_get($focusPayload, 'reorder_level', 0)),
            'maximum_stock' => old('maximum_stock', data_get($focusPayload, 'maximum_stock', 0)),
            'station' => old('station', data_get($focusPayload, 'station', '')),
            'prep_minutes' => old('prep_minutes', data_get($focusPayload, 'prep_minutes', 0)),
            'variants' => old('variants', data_get($focusPayload, 'variants', [])),
            'option_groups' => old('option_groups', data_get($focusPayload, 'option_groups', [])),
            'id' => old('_product_id', data_get($focusPayload, 'id')),
            'mode' => old('_form_mode', 'create'),
            'update_url' => data_get($focusPayload, 'update_url', ''),
            'options_url' => data_get($focusPayload, 'options_url', ''),
            'delete_url' => data_get($focusPayload, 'delete_url', ''),
        ];
    @endphp
    <div x-data="productPage()" @keydown.escape.window="closeTop()">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Total produk</p>
                    <p class="stat-value">{{ number_format($stats['total']) }}</p>
                    <p class="stat-hint">Semua item katalog</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Dapat dijual</p>
                    <p class="stat-value">{{ number_format($stats['sellable']) }}</p>
                    <p class="stat-hint">Aktif di kasir</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Nonaktif</p>
                    <p class="stat-value">{{ number_format($stats['inactive']) }}</p>
                    <p class="stat-hint">Tidak tampil di POS</p>
                </div>
                <span class="stat-icon bg-brand-soft text-brand">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Daftar produk</h5>
                    <p class="card-header-subtitle">Filter kolom memuat ulang otomatis saat nilai diubah.</p>
                </div>
                <div class="card-header-actions">
                    @can('products.manage')
                        <button type="button" class="btn-add" @click="openCreate()">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Tambah produk
                        </button>
                    @endcan
                </div>
            </div>

            <form id="product-filters" method="GET" action="{{ route('products.index') }}"></form>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>SKU</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Tipe</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th class="col-actions"></th>
                        </tr>
                        <tr class="filter-row">
                            <th></th>
                            <th>
                                <input form="product-filters" class="col-filter" type="search" name="sku" value="{{ $filters['sku'] ?? '' }}" placeholder="SKU..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <input form="product-filters" class="col-filter" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Nama..." onchange="this.form.submit()">
                            </th>
                            <th>
                                <select form="product-filters" class="col-filter" name="category_id" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th>
                                <select form="product-filters" class="col-filter" name="type" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    @foreach (\App\Enums\ProductType::cases() as $type)
                                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th></th>
                            <th>
                                <select form="product-filters" class="col-filter" name="status" onchange="this.form.submit()">
                                    <option value="">Semua</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                                </select>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php $row = $product->toModalArray(); @endphp
                            <tr>
                                <td class="col-no">{{ $products->firstItem() + $loop->index }}</td>
                                <td>{{ $product->sku }}</td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $product->imageUrl() }}" alt="" width="36" height="36" class="h-9 w-9 shrink-0 rounded-lg object-cover">
                                        <button type="button" class="text-left font-semibold hover:underline" @click="openView({{ Js::from($row) }})">{{ $product->name }}</button>
                                    </div>
                                </td>
                                <td>{{ $product->category?->name ?? '—' }}</td>
                                <td><span class="badge-soft">{{ $product->type?->label() }}</span></td>
                                <td class="font-semibold">{{ money($product->price) }}</td>
                                <td>
                                    <x-status :value="$product->is_active ? 'green' : 'gray'">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</x-status>
                                </td>
                                <td class="col-actions">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="table-action" title="Lihat" @click="openView({{ Js::from($row) }})">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.3 12S6 6 12 6s9.7 6 9.7 6-3.7 6-9.7 6S2.3 12 2.3 12z"/><circle cx="12" cy="12" r="2.5" stroke-width="1.8"/></svg>
                                        </button>
                                        @can('products.manage')
                                            <button type="button" class="table-action" title="Edit" @click="openEdit({{ Js::from($row) }})">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                            </button>
                                            <button type="button" class="table-action table-action-danger" title="Hapus" @click="confirmDelete({{ Js::from($row) }})">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M9 7V5h6v2m-7 0v12a1 1 0 001 1h6a1 1 0 001-1V7"/></svg>
                                            </button>
                                        @elsecan('products.options')
                                            <button type="button" class="table-action" title="Kelola opsi" @click="openOptions({{ Js::from($row) }})">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h10M4 17h16"/></svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-16 text-center text-sm text-slate-400">Tidak ada produk yang cocok dengan filter kolom ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $products->links() }}</div>
            @endif
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="viewOpen" x-cloak @click.self="viewOpen = false">
            <div class="crud-modal">
                <div class="crud-modal-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <img :src="viewing?.image_url" alt="" class="h-14 w-14 shrink-0 rounded-xl object-cover">
                            <div>
                                <h3 class="text-lg font-semibold text-heading" x-text="viewing?.name || 'Detail Produk'"></h3>
                                <p class="mt-1 text-[13px] text-muted">
                                    <span x-text="viewing?.sku || '—'"></span>
                                    ·
                                    <span x-text="viewing?.status_label || '—'"></span>
                                </p>
                            </div>
                        </div>
                        <button type="button" class="modal-close" @click="viewOpen = false">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Harga</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.price_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Tipe</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.type_label || '—'"></p>
                        </div>
                        <div class="rounded-xl bg-[#fafafa] px-4 py-3">
                            <p class="stat-kicker">Kategori</p>
                            <p class="mt-1 text-lg font-semibold" x-text="viewing?.category_label || '—'"></p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[12px] text-muted">Unit</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.unit_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">HPP</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.cost_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Station</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.station_label || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-[12px] text-muted">Prep</dt>
                            <dd class="mt-0.5 font-medium" x-text="(viewing?.prep_minutes || 0) + ' menit'"></dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[12px] text-muted">Deskripsi</dt>
                            <dd class="mt-0.5 font-medium" x-text="viewing?.description || '—'"></dd>
                        </div>
                    </dl>

                    <div class="mt-5" x-show="viewing?.recipe?.length" x-cloak>
                        <p class="stat-kicker">Resep HPP</p>
                        <p class="mt-1 text-[12px] text-muted">Bahan per 1 porsi. Tidak memotong stok saat penjualan.</p>
                        <div class="mt-2 divide-y divide-[#f0f0f0] overflow-hidden rounded-xl border border-line">
                            <template x-for="(line, index) in (viewing?.recipe || [])" :key="index">
                                <div class="flex items-center justify-between bg-white px-4 py-2.5 text-sm">
                                    <span class="font-medium" x-text="line.name"></span>
                                    <span class="text-muted"><span x-text="line.quantity_label"></span> · <span x-text="line.cost_label"></span></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="mt-5" x-show="viewing?.variants?.length">
                        <p class="stat-kicker">Varian</p>
                        <div class="mt-2 divide-y divide-[#f0f0f0] overflow-hidden rounded-xl border border-line">
                            <template x-for="(variant, index) in (viewing?.variants || [])" :key="index">
                                <div class="flex items-center justify-between bg-white px-4 py-2.5 text-sm">
                                    <span class="font-medium" x-text="variant.name"></span>
                                    <span class="text-muted" x-text="variant.price_adjustment_label"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                @can('products.manage')
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="confirmDelete(viewing)">Hapus</button>
                        <button type="button" class="btn-add" @click="openEdit(viewing)">Edit</button>
                    </div>
                @elsecan('products.options')
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-add" @click="openOptions(viewing)">Kelola opsi</button>
                    </div>
                @endcan
            </div>
        </div>

        @can('products.options')
            @cannot('products.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="optionsOpen" x-cloak @click.self="optionsOpen = false">
                <div class="crud-modal !max-w-[720px]">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" :action="form.options_url">
                        @csrf
                        @method('PUT')
                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading">Kelola opsi</h3>
                                    <p class="mt-1 text-[13px] text-muted">
                                        <span x-text="form.name || ''"></span>
                                        <span x-show="form.sku"> · SKU <span x-text="form.sku"></span></span>
                                    </p>
                                </div>
                                <button type="button" class="modal-close" @click="optionsOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Opsi / Add-ons</p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="applyDrinkPreset()">Preset minuman</button>
                                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="applyFoodPreset()">Preset makanan</button>
                                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="form.option_groups.push({ id: '', name: '', is_required: false, min_select: 0, max_select: 1, options: [{ id: '', name: '', price_adjustment: 0, is_active: true }] })">Tambah grup</button>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <template x-for="(group, gIndex) in form.option_groups" :key="gIndex">
                                        <div class="rounded-xl border border-line bg-[#fafafa] p-3">
                                            <input type="hidden" :name="`option_groups[${gIndex}][id]`" x-model="group.id">
                                            <div class="grid gap-2 sm:grid-cols-12">
                                                <div class="sm:col-span-4">
                                                    <input class="input" :name="`option_groups[${gIndex}][name]`" x-model="group.name" placeholder="Nama grup (Sugar / Level Pedas)">
                                                </div>
                                                <div class="sm:col-span-3">
                                                    <label class="flex h-full items-center gap-2 rounded-lg border border-line bg-white px-3 text-xs">
                                                        <input type="hidden" :name="`option_groups[${gIndex}][is_required]`" :value="group.is_required ? 1 : 0">
                                                        <input type="checkbox" x-model="group.is_required" @change="if (group.is_required && Number(group.min_select) < 1) group.min_select = 1">
                                                        Wajib pilih
                                                    </label>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <input class="input" type="number" min="0" max="20" :name="`option_groups[${gIndex}][min_select]`" x-model="group.min_select" placeholder="Min">
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <input class="input" type="number" min="1" max="20" :name="`option_groups[${gIndex}][max_select]`" x-model="group.max_select" placeholder="Max">
                                                </div>
                                                <button type="button" class="btn-ghost sm:col-span-1 !px-2" @click="form.option_groups.splice(gIndex, 1)">×</button>
                                            </div>
                                            <div class="mt-2 space-y-2">
                                                <template x-for="(option, oIndex) in group.options" :key="oIndex">
                                                    <div class="grid grid-cols-12 gap-2">
                                                        <input type="hidden" :name="`option_groups[${gIndex}][options][${oIndex}][id]`" x-model="option.id">
                                                        <div class="col-span-5">
                                                            <input class="input" :name="`option_groups[${gIndex}][options][${oIndex}][name]`" x-model="option.name" placeholder="Nama opsi">
                                                        </div>
                                                        <div class="col-span-4">
                                                            <input class="input" type="number" step="0.01" :name="`option_groups[${gIndex}][options][${oIndex}][price_adjustment]`" x-model="option.price_adjustment" placeholder="+ harga">
                                                        </div>
                                                        <div class="col-span-2">
                                                            <label class="flex h-full items-center gap-1.5 rounded-lg border border-line bg-white px-2 text-[11px]">
                                                                <input type="hidden" :name="`option_groups[${gIndex}][options][${oIndex}][is_active]`" :value="option.is_active ? 1 : 0">
                                                                <input type="checkbox" x-model="option.is_active">
                                                                Aktif
                                                            </label>
                                                        </div>
                                                        <button type="button" class="btn-ghost col-span-1 !px-2" @click="group.options.splice(oIndex, 1)">×</button>
                                                    </div>
                                                </template>
                                                <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="group.options.push({ id: '', name: '', price_adjustment: 0, is_active: true })">Tambah opsi</button>
                                            </div>
                                        </div>
                                    </template>
                                    <p class="text-[12px] text-muted" x-show="!form.option_groups.length">Pakai preset minuman/makanan, atau buat grup sendiri. Produk tanpa opsi tetap bisa dijual.</p>
                                </div>
                            </div>
                        </div>
                        <div class="crud-modal-footer">
                            <button type="button" class="btn-ghost" @click="optionsOpen = false">Batal</button>
                            <button class="btn-add" type="submit">Simpan opsi</button>
                        </div>
                    </form>
                </div>
            </div>
            @endcannot
        @endcan

        @can('products.manage')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
                <div class="crud-modal !max-w-[720px]">
                    <form class="flex min-h-0 flex-1 flex-col" method="POST" enctype="multipart/form-data" :action="formMode === 'edit' ? form.update_url : storeUrl">
                        @csrf
                        <input type="hidden" name="_form_mode" :value="formMode">
                        <input type="hidden" name="_product_id" :value="form.id || ''">
                        <template x-if="formMode === 'edit'">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="crud-modal-body">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-heading" x-text="formMode === 'edit' ? 'Edit Produk' : 'Tambah Produk'"></h3>
                                    <p class="mt-1 text-[13px] text-muted" x-text="formMode === 'edit' ? (form.sku ? 'SKU ' + form.sku : '') : 'Lengkapi data katalog untuk dijual di kasir.'"></p>
                                </div>
                                <button type="button" class="modal-close" @click="formOpen = false">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <p class="sm:col-span-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Identitas</p>
                                <div>
                                    <label class="label">SKU</label>
                                    <input class="input" name="sku" required maxlength="50" x-model="form.sku">
                                    @error('sku')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Nama</label>
                                    <input class="input" name="name" required maxlength="150" x-model="form.name">
                                    @error('name')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Kategori</label>
                                    <select name="category_id" class="input" x-model="form.category_id">
                                        <option value="">Tanpa kategori</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Unit</label>
                                    <select name="unit_id" class="input" required x-model="form.unit_id">
                                        <option value="">Pilih unit</option>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->code }})</option>
                                        @endforeach
                                    </select>
                                    @error('unit_id')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Tipe</label>
                                    <select name="type" class="input" required x-model="form.type">
                                        @foreach (\App\Enums\ProductType::cases() as $type)
                                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Level BOM</label>
                                    <input class="input" type="number" name="bom_level" min="0" max="4" x-model="form.bom_level">
                                </div>

                                <p class="sm:col-span-2 mt-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Harga &amp; stok</p>
                                <div>
                                    <label class="label">Harga jual</label>
                                    <input class="input" type="number" step="0.01" min="0" name="price" required x-model="form.price">
                                    @error('price')<p class="mt-1 text-sm text-red-600" x-show="serverFormError" x-cloak>{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">HPP</label>
                                    <input class="input" type="number" step="0.01" min="0" name="cost" x-model="form.cost" :readonly="(form.recipe || []).length > 0">
                                    <p class="mt-1 text-[11px] text-muted" x-show="(form.recipe || []).length" x-cloak>Dihitung otomatis dari resep di menu BOM.</p>
                                </div>
                                <div>
                                    <label class="label">Komisi cafe (makanan mitra)</label>
                                    <input class="input" type="number" step="0.01" min="0" name="consignment_commission" x-model="form.consignment_commission">
                                    <p class="mt-1 text-[11px] text-muted">Isi 0 untuk minuman cafe. Makanan mitra biasanya {{ money(config('pos.food_commission')) }} per porsi.</p>
                                </div>
                                <div>
                                    <label class="label">Minimum stok</label>
                                    <input class="input" type="number" step="0.001" min="0" name="minimum_stock" x-model="form.minimum_stock">
                                </div>
                                <div>
                                    <label class="label">Reorder</label>
                                    <input class="input" type="number" step="0.001" min="0" name="reorder_level" x-model="form.reorder_level">
                                </div>
                                <div>
                                    <label class="label">Maximum stok</label>
                                    <input class="input" type="number" step="0.001" min="0" name="maximum_stock" x-model="form.maximum_stock">
                                </div>
                                <div>
                                    <label class="label">Stasiun</label>
                                    <select name="station" class="input" x-model="form.station">
                                        <option value="">—</option>
                                        <option value="kitchen">Dapur</option>
                                        <option value="bar">Bar</option>
                                        <option value="cashier">Kasir</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Prep (menit)</label>
                                    <input class="input" type="number" min="0" name="prep_minutes" x-model="form.prep_minutes">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Deskripsi</label>
                                    <textarea class="input min-h-20" name="description" x-model="form.description"></textarea>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Gambar menu</label>
                                    <img x-show="form.image_url" :src="form.image_url" alt="" class="mb-3 h-20 w-28 rounded-lg object-cover" x-cloak>
                                    <input class="input" type="file" name="image_file" accept="image/*" @change="onImage($event)">
                                    <input class="input mt-2" name="image" x-model="form.image" placeholder="Atau tempel URL gambar">
                                </div>
                                <div class="sm:col-span-2 flex flex-wrap gap-5">
                                    <label class="flex items-center gap-2.5 text-sm">
                                        <input type="hidden" name="is_sellable" :value="form.is_sellable ? 1 : 0">
                                        <input type="checkbox" class="h-4 w-4 rounded border-line" x-model="form.is_sellable">
                                        Dapat dijual
                                    </label>
                                    <label class="flex items-center gap-2.5 text-sm">
                                        <input type="hidden" name="is_stockable" :value="form.is_stockable ? 1 : 0">
                                        <input type="checkbox" class="h-4 w-4 rounded border-line" x-model="form.is_stockable">
                                        Pantau stok
                                    </label>
                                    <label class="flex items-center gap-2.5 text-sm">
                                        <input type="hidden" name="is_recommended" :value="form.is_recommended ? 1 : 0">
                                        <input type="checkbox" class="h-4 w-4 rounded border-line" x-model="form.is_recommended">
                                        Rekomendasi
                                    </label>
                                    <label class="flex items-center gap-2.5 text-sm">
                                        <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                                        <input type="checkbox" class="h-4 w-4 rounded border-line" x-model="form.is_active">
                                        Aktif
                                    </label>
                                </div>

                                <p class="sm:col-span-2 mt-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Varian</p>
                                <div class="sm:col-span-2">
                                    <div class="mb-2 flex items-center justify-end">
                                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="form.variants.push({ id: '', name: '', sku: '', price_adjustment: 0 })">Tambah varian</button>
                                    </div>
                                    <div class="space-y-2">
                                        <template x-for="(variant, index) in form.variants" :key="index">
                                            <div class="grid grid-cols-12 items-end gap-2">
                                                <input type="hidden" :name="`variants[${index}][id]`" x-model="variant.id">
                                                <div class="col-span-4">
                                                    <input class="input" :name="`variants[${index}][name]`" x-model="variant.name" placeholder="Nama">
                                                </div>
                                                <div class="col-span-3">
                                                    <input class="input" :name="`variants[${index}][sku]`" x-model="variant.sku" placeholder="SKU">
                                                </div>
                                                <div class="col-span-3">
                                                    <input class="input" type="number" step="0.01" :name="`variants[${index}][price_adjustment]`" x-model="variant.price_adjustment" placeholder="Adj. harga">
                                                </div>
                                                <button type="button" class="btn-ghost col-span-2 !px-2" @click="form.variants.splice(index, 1)">×</button>
                                            </div>
                                        </template>
                                        <p class="text-[12px] text-muted" x-show="!form.variants.length">Opsional. Produk tanpa varian tetap bisa dijual.</p>
                                    </div>
                                </div>

                                <div class="sm:col-span-2 mt-2 border-t border-line pt-4">
                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Opsi / Add-ons</p>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="applyDrinkPreset()">Preset minuman</button>
                                            <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="applyFoodPreset()">Preset makanan</button>
                                            <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="form.option_groups.push({ id: '', name: '', is_required: false, min_select: 0, max_select: 1, options: [{ id: '', name: '', price_adjustment: 0, is_active: true }] })">Tambah grup</button>
                                        </div>
                                    </div>
                                    <div class="space-y-3">
                                        <template x-for="(group, gIndex) in form.option_groups" :key="gIndex">
                                            <div class="rounded-xl border border-line bg-[#fafafa] p-3">
                                                <input type="hidden" :name="`option_groups[${gIndex}][id]`" x-model="group.id">
                                                <div class="grid gap-2 sm:grid-cols-12">
                                                    <div class="sm:col-span-4">
                                                        <input class="input" :name="`option_groups[${gIndex}][name]`" x-model="group.name" placeholder="Nama grup (Sugar / Level Pedas)">
                                                    </div>
                                                    <div class="sm:col-span-3">
                                                        <label class="flex h-full items-center gap-2 rounded-lg border border-line bg-white px-3 text-xs">
                                                            <input type="hidden" :name="`option_groups[${gIndex}][is_required]`" :value="group.is_required ? 1 : 0">
                                                            <input type="checkbox" x-model="group.is_required" @change="if (group.is_required && Number(group.min_select) < 1) group.min_select = 1">
                                                            Wajib pilih
                                                        </label>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <input class="input" type="number" min="0" max="20" :name="`option_groups[${gIndex}][min_select]`" x-model="group.min_select" placeholder="Min">
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <input class="input" type="number" min="1" max="20" :name="`option_groups[${gIndex}][max_select]`" x-model="group.max_select" placeholder="Max">
                                                    </div>
                                                    <button type="button" class="btn-ghost sm:col-span-1 !px-2" @click="form.option_groups.splice(gIndex, 1)">×</button>
                                                </div>
                                                <div class="mt-2 space-y-2">
                                                    <template x-for="(option, oIndex) in group.options" :key="oIndex">
                                                        <div class="grid grid-cols-12 gap-2">
                                                            <input type="hidden" :name="`option_groups[${gIndex}][options][${oIndex}][id]`" x-model="option.id">
                                                            <div class="col-span-5">
                                                                <input class="input" :name="`option_groups[${gIndex}][options][${oIndex}][name]`" x-model="option.name" placeholder="Nama opsi">
                                                            </div>
                                                            <div class="col-span-4">
                                                                <input class="input" type="number" step="0.01" :name="`option_groups[${gIndex}][options][${oIndex}][price_adjustment]`" x-model="option.price_adjustment" placeholder="+ harga">
                                                            </div>
                                                            <div class="col-span-2">
                                                                <label class="flex h-full items-center gap-1.5 rounded-lg border border-line bg-white px-2 text-[11px]">
                                                                    <input type="hidden" :name="`option_groups[${gIndex}][options][${oIndex}][is_active]`" :value="option.is_active ? 1 : 0">
                                                                    <input type="checkbox" x-model="option.is_active">
                                                                    Aktif
                                                                </label>
                                                            </div>
                                                            <button type="button" class="btn-ghost col-span-1 !px-2" @click="group.options.splice(oIndex, 1)">×</button>
                                                        </div>
                                                    </template>
                                                    <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="group.options.push({ id: '', name: '', price_adjustment: 0, is_active: true })">Tambah opsi</button>
                                                </div>
                                            </div>
                                        </template>
                                        <p class="text-[12px] text-muted" x-show="!form.option_groups.length">Pakai preset minuman/makanan, atau buat grup sendiri. Bisa diedit setelah ditambahkan.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="crud-modal-footer">
                            <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                            <button class="btn-add" type="submit" x-text="formMode === 'edit' ? 'Simpan perubahan' : 'Simpan'"></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 p-4" x-show="deleteOpen" x-cloak @click.self="deleteOpen = false">
                <div class="crud-modal-sm">
                    <h3 class="text-lg font-semibold text-heading">Hapus Produk</h3>
                    <p class="mt-2 text-sm text-muted">
                        Hapus <span class="font-medium text-heading" x-text="pendingDelete?.name"></span>? Data akan diarsipkan.
                    </p>
                    <form method="POST" class="mt-6 flex justify-end gap-2" :action="pendingDelete?.delete_url">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn-ghost" @click="deleteOpen = false">Batal</button>
                        <button class="btn-danger" type="submit">Hapus</button>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        function productPage() {
            const emptyForm = () => ({
                id: null,
                sku: '',
                name: '',
                category_id: '',
                unit_id: '',
                type: 'finished',
                bom_level: 0,
                description: '',
                image: '',
                image_url: '',
                price: 0,
                cost: 0,
                consignment_commission: 0,
                is_sellable: true,
                is_stockable: true,
                is_active: true,
                is_recommended: false,
                minimum_stock: 0,
                reorder_level: 0,
                maximum_stock: 0,
                station: '',
                prep_minutes: 0,
                variants: [],
                option_groups: [],
                recipe: [],
                update_url: '',
                options_url: '',
                delete_url: '',
            });

            const focus = @json($focusPayload);
            const formError = @json($formError);
            const requestedModal = @json($requestedModal);
            const formOld = @json($formOld);

            let form = emptyForm();
            if (focus) {
                form = { ...emptyForm(), ...focus, variants: focus.variants || [], option_groups: focus.option_groups || [] };
            }
            if (formError) {
                form = { ...form, ...formOld, variants: formOld.variants || form.variants || [], option_groups: formOld.option_groups || form.option_groups || [] };
            }

            return {
                storeUrl: @json($storeUrl),
                formOpen: requestedModal !== 'options' && (formError || requestedModal === 'create' || (requestedModal === 'edit' && !!focus)),
                optionsOpen: requestedModal === 'options' && (!!focus || formError),
                formMode: formError && requestedModal !== 'options' ? formOld.mode : (requestedModal === 'edit' ? 'edit' : 'create'),
                form,
                viewOpen: !formError && requestedModal === 'view' && !!focus,
                viewing: focus,
                deleteOpen: false,
                pendingDelete: null,
                serverFormError: formError,
                openCreate() {
                    this.formMode = 'create';
                    this.form = emptyForm();
                    this.viewOpen = false;
                    this.optionsOpen = false;
                    this.serverFormError = false;
                    this.formOpen = true;
                },
                openEdit(row) {
                    if (! row) return;
                    this.formMode = 'edit';
                    this.form = { ...emptyForm(), ...row, variants: row.variants ? [...row.variants] : [], option_groups: row.option_groups ? JSON.parse(JSON.stringify(row.option_groups)) : [] };
                    this.viewOpen = false;
                    this.deleteOpen = false;
                    this.optionsOpen = false;
                    this.serverFormError = false;
                    this.formOpen = true;
                },
                openOptions(row) {
                    if (! row) return;
                    this.form = { ...emptyForm(), ...row, option_groups: row.option_groups ? JSON.parse(JSON.stringify(row.option_groups)) : [] };
                    this.formOpen = false;
                    this.viewOpen = false;
                    this.deleteOpen = false;
                    this.serverFormError = false;
                    this.optionsOpen = true;
                },
                openView(row) {
                    if (! row) return;
                    this.viewing = row;
                    this.formOpen = false;
                    this.optionsOpen = false;
                    this.viewOpen = true;
                },
                confirmDelete(row) {
                    if (! row) return;
                    this.pendingDelete = row;
                    this.deleteOpen = true;
                },
                closeTop() {
                    if (this.deleteOpen) this.deleteOpen = false;
                    else if (this.optionsOpen) this.optionsOpen = false;
                    else if (this.formOpen) this.formOpen = false;
                    else if (this.viewOpen) this.viewOpen = false;
                },
                onImage(event) {
                    const file = event.target.files[0];
                    if (! file) return;
                    this.form.image_url = URL.createObjectURL(file);
                },
                optionRow(name) {
                    return { id: '', name, price_adjustment: 0, is_active: true };
                },
                optionGroup(name, options, required = true) {
                    return {
                        id: '',
                        name,
                        is_required: required,
                        min_select: required ? 1 : 0,
                        max_select: 1,
                        options: options.map((n) => this.optionRow(n)),
                    };
                },
                applyDrinkPreset() {
                    this.form.option_groups.push(
                        this.optionGroup('Serving', ['Ice', 'Hot']),
                        this.optionGroup('Level Ice', ['Normal Ice', 'Less Ice'], false),
                        this.optionGroup('Sugar', ['Normal Sugar', 'Less Sugar']),
                    );
                },
                applyFoodPreset() {
                    this.form.option_groups.push(
                        this.optionGroup('Level Pedas', ['Pedas', 'Tidak Pedas']),
                    );
                },
            };
        }
    </script>
@endpush
