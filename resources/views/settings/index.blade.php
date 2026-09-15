@extends('layouts.app')
@section('title', 'Settings')
@section('breadcrumb', 'Pengaturan')
@section('content')
    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Tarif pajak</p>
                <p class="stat-value">{{ number_format($stats['tax_rate'], 2) }}%</p>
                <p class="stat-hint">PPN / pajak penjualan</p>
            </div>
            <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-12V5m0 14v-2"/></svg>
            </span>
        </div>
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
        <div class="stat-card">
            <div>
                <p class="stat-kicker">Poin per transaksi</p>
                <p class="stat-value">{{ money($stats['points_earn_per_amount']) }}</p>
                <p class="stat-hint">Nominal belanja = 1 poin</p>
            </div>
            <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 2l2.9 6.9L22 9.8l-5.2 4.5 1.6 6.9L12 17.8 5.6 21.2 7.2 14.3 2 9.8l7.1-.9L12 2z"/></svg>
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
                <h6 class="mb-4 text-sm font-semibold text-heading">Pajak & biaya layanan</h6>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Tarif pajak (%)</label>
                        <input class="input" type="number" step="0.01" min="0" max="100" name="tax_rate" value="{{ $settings['tax_rate'] }}" required>
                        @error('tax_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label">Service charge (%)</label>
                        <input class="input" type="number" step="0.01" min="0" name="service_charge" value="{{ $settings['service_charge'] }}">
                        @error('service_charge')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="border-t border-line pt-8">
                <h6 class="mb-4 text-sm font-semibold text-heading">Program loyalitas</h6>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Nominal belanja per 1 poin</label>
                        <input class="input" type="number" min="1" name="points_earn_per_amount" value="{{ $settings['points_earn_per_amount'] }}" required>
                        <p class="mt-1 text-[12px] text-muted">Contoh: 10.000 berarti setiap Rp 10.000 transaksi = 1 poin.</p>
                        @error('points_earn_per_amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label">Nilai tukar 1 poin (Rp)</label>
                        <input class="input" type="number" min="1" name="points_redeem_value" value="{{ $settings['points_redeem_value'] }}" required>
                        <p class="mt-1 text-[12px] text-muted">Contoh: 100 berarti 1 poin = potongan Rp 100.</p>
                        @error('points_redeem_value')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="border-t border-line pt-8">
                <h6 class="mb-4 text-sm font-semibold text-heading">Identitas struk</h6>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Nama perusahaan</label>
                        <input class="input" type="text" name="company_name" maxlength="150" value="{{ $settings['company_name'] }}">
                        @error('company_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label">Footer struk</label>
                        <input class="input" type="text" name="receipt_footer" maxlength="255" value="{{ $settings['receipt_footer'] }}">
                        @error('receipt_footer')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Nama printer QZ Tray</label>
                        <input class="input" type="text" name="qz_printer" maxlength="120" value="{{ $settings['qz_printer'] }}" placeholder="GEZHI micro-printer">
                        <p class="mt-1 text-[12px] text-muted">Harus sama persis dengan nama di Windows, contoh GEZHI micro-printer. Di driver printer set Paper Size ke 80mm / Roll Paper, jangan Letter/A4.</p>
                        @error('qz_printer')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
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
