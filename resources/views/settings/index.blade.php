@extends('layouts.app')
@section('title', 'Settings')
@section('breadcrumb', 'Pengaturan')
@section('content')
    <div class="mb-5 grid gap-4 md:grid-cols-2">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Service charge</p>
                <p class="stat-value">{{ number_format($stats['service_charge'], 2) }}%</p>
                <p class="stat-hint">Biaya layanan kasir</p>
            </div>
            <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5h6M9 9h6M5 5h.01M5 9h.01M5 13h14M5 17h14M5 21h14"/></svg>
            </span>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.update') }}" class="card overflow-hidden">
        @csrf
        @method('PUT')

        <div class="card-header">
            <div>
                <h5 class="card-header-title">Pengaturan umum</h5>
                <p class="card-header-subtitle">Konfigurasi global yang berlaku untuk semua outlet.</p>
            </div>
        </div>

        <div class="space-y-8 px-5 py-6">
            <section>
                <h6 class="mb-4 text-sm font-semibold text-heading">Service charge</h6>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Service charge (%)</label>
                        <input class="input" type="number" step="0.01" min="0" name="service_charge" value="{{ $settings['service_charge'] }}">
                        @error('service_charge')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section>
                <h6 class="text-sm font-semibold text-heading">Sewa ruko</h6>
                <p class="mb-4 mt-1 text-[13px] text-muted">Masuk operasional dashboard per bulan, yaitu nilai per tahun dibagi 12.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Sewa per tahun (Rp)</label>
                        <input class="input" type="number" step="1" min="0" name="rent_yearly" value="{{ $settings['rent_yearly'] }}">
                        @error('rent_yearly')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section x-data="{ phones: @js(array_values($kitchenPhones)) }">
                <h6 class="text-sm font-semibold text-heading">WhatsApp dapur</h6>
                <p class="mb-4 mt-1 text-[13px] text-muted">Pesanan dapur dikirim ke semua nomor ini.</p>
                <div class="max-w-xl space-y-3">
                    <template x-for="(phone, index) in phones" :key="index">
                        <div class="flex items-center gap-2">
                            <input class="input" type="tel" inputmode="tel" name="kitchen_whatsapp[]" placeholder="08xxxxxxxxxx" x-model="phones[index]">
                            <button type="button" class="shrink-0 text-[13px] font-medium text-brand" @click="phones.length > 1 ? phones.splice(index, 1) : phones[0] = ''">Hapus</button>
                        </div>
                    </template>
                    @error('kitchen_whatsapp')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button type="button" class="text-[13px] font-medium text-brand" @click="phones.push('')">+ Tambah nomor</button>
                </div>
            </section>
        </div>

        <div class="crud-modal-footer !rounded-none !border-t !border-line">
            <button class="btn-add" type="submit">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
                Simpan pengaturan
            </button>
        </div>
    </form>
@endsection
