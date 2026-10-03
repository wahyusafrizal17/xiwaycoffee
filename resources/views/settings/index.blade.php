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
        </div>

        <div class="crud-modal-footer !rounded-none !border-t !border-line">
            <button class="btn-add" type="submit">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
                Simpan pengaturan
            </button>
        </div>
    </form>
@endsection
