@extends('layouts.app')
@section('title', 'Laba Rugi')
@section('breadcrumb', 'Keuangan')
@section('content')
    @php
        $s = $summary;
        $p = $s['previous'];
        $pct = fn ($v) => number_format($v, 1, ',', '.').'%';
        $share = fn ($v) => $s['omzet'] > 0 ? $v / $s['omzet'] * 100 : 0;
        $change = function ($now, $before) {
            if (abs($before) < 1) {
                return null;
            }

            return ($now - $before) / abs($before) * 100;
        };
        $netChange = $p ? $change($s['net'], $p['net']) : null;
        // Bar memakai skala omzet, atau total biaya bila biaya melebihi omzet (rugi).
        $barBase = max($s['omzet'], $s['hpp'] + $s['operational'], 0.01);
        $structure = [
            ['label' => 'HPP', 'amount' => $s['hpp'], 'color' => 'bg-[#c2410c]'],
            ['label' => 'BOP', 'amount' => $s['bop'], 'color' => 'bg-[#a16207]'],
            ['label' => 'Gaji karyawan', 'amount' => $s['salary'], 'color' => 'bg-heading'],
            ['label' => 'Sewa ruko', 'amount' => $s['rent'], 'color' => 'bg-[#6b635c]'],
        ];
    @endphp

    <form method="GET" action="{{ route('reports.laba-rugi') }}" class="mb-5 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Bulan</label>
            <input class="input !h-10 !rounded-xl" type="month" name="month" value="{{ $filters['month'] }}" required>
        </div>
        @if ($canSwitchOutlet)
            <div>
                <label class="label">Outlet</label>
                <select class="input !h-10 !min-w-[180px] !rounded-xl" name="outlet_id">
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected((int) $outletId === $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <input type="hidden" name="outlet_id" value="{{ $outletId }}">
        @endif
        <button type="submit" class="btn-primary !h-10 !rounded-xl !px-5 text-[13px]">Terapkan</button>
    </form>

    {{-- Ringkasan utama --}}
    <section class="mb-5 overflow-hidden rounded-2xl bg-[#161414] text-white shadow-[0_18px_40px_-24px_rgba(0,0,0,.55)]">
        <div class="grid lg:grid-cols-[1.2fr_1fr]">
            <div class="p-6 sm:p-8">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">Laba bersih · {{ $s['label'] }}</p>
                <p class="mt-3 text-[2.6rem] font-semibold leading-none tracking-tight tabular-nums sm:text-[3.25rem] {{ $s['net'] < 0 ? 'text-[#ff8a8a]' : 'text-[#7ee2a8]' }}">{{ money($s['net']) }}</p>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-[12px]">
                    <span class="rounded-full px-2.5 py-1 font-semibold {{ $s['net'] < 0 ? 'bg-[#ff8a8a]/15 text-[#ff8a8a]' : 'bg-[#7ee2a8]/15 text-[#7ee2a8]' }}">{{ $s['status'] }} · margin {{ $pct($s['net_margin']) }}</span>
                    @if ($netChange !== null)
                        <span class="rounded-full bg-white/10 px-2.5 py-1 font-medium text-white/70">{{ $netChange >= 0 ? '▲' : '▼' }} {{ $pct(abs($netChange)) }} dari {{ $p['label'] }}</span>
                    @endif
                </div>
                <p class="mt-5 text-[12px] text-white/40">Omzet cafe − HPP − BOP − gaji karyawan − sewa ruko.</p>
            </div>
            <div class="grid grid-cols-2 border-t border-white/10 lg:border-l lg:border-t-0">
                <div class="border-b border-r border-white/10 p-6">
                    <p class="text-[12px] text-white/60">Omzet cafe</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">{{ money($s['omzet']) }}</p>
                </div>
                <div class="border-b border-white/10 p-6">
                    <p class="text-[12px] text-white/60">Laba kotor</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">{{ money($s['gross_profit']) }}</p>
                    <p class="mt-0.5 text-[11px] text-white/40">Margin {{ $pct($s['gross_margin']) }}</p>
                </div>
                <div class="border-r border-white/10 p-6">
                    <p class="text-[12px] text-white/60">Total biaya operasional</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">{{ money($s['operational']) }}</p>
                </div>
                <div class="p-6">
                    <p class="text-[12px] text-white/60">Order lunas</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">{{ qty($s['orders']) }}</p>
                    <p class="mt-0.5 text-[11px] text-white/40">Rata-rata {{ money($s['aov']) }} · {{ qty($s['cups']) }} cup</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mb-5 grid items-start gap-5 xl:grid-cols-[1.5fr_1fr]">
        {{-- Laporan laba rugi --}}
        <div class="rounded-2xl border border-[#ebe4dc] bg-white p-6">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <h2 class="font-serif text-xl font-semibold text-heading">Laporan laba rugi</h2>
                    <p class="mt-1 text-[12px] text-muted">{{ $s['label'] }}. Order lunas yang tidak dibatalkan.</p>
                </div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">% omzet</p>
            </div>

            @php
                $line = 'flex items-baseline justify-between gap-4 py-2 text-[13px]';
                $total = 'flex items-baseline justify-between gap-4 border-t border-[#ebe4dc] py-3 text-[14px] font-semibold text-heading';
                $section = 'mt-5 text-[11px] font-semibold uppercase tracking-[0.1em] text-muted';
            @endphp

            <p class="{{ $section }}">Pendapatan</p>
            <div class="{{ $line }}"><span>Penjualan minuman</span><span class="tabular-nums">{{ money($s['drinks']) }}</span></div>
            <div class="{{ $line }}"><span>Penjualan makanan titipan</span><span class="tabular-nums">{{ money($s['food_sales']) }}</span></div>
            @if ($s['service'] != 0)
                <div class="{{ $line }}"><span>Charge layanan</span><span class="tabular-nums">{{ money($s['service']) }}</span></div>
            @endif
            @if ($s['other'] != 0)
                <div class="{{ $line }}"><span>Penyesuaian (poin, pembulatan)</span><span class="tabular-nums">{{ money($s['other']) }}</span></div>
            @endif
            <div class="{{ $total }}"><span>Penjualan kotor</span><span class="tabular-nums">{{ money($s['gross']) }}</span></div>
            <div class="{{ $line }} text-muted"><span>Dikurangi setoran makanan ke vendor ({{ 100 - (int) config('pos.food_cafe_percent', 10) }}%)</span><span class="tabular-nums">−{{ money($s['setoran']) }}</span></div>
            <div class="{{ $total }} !border-heading"><span>Omzet cafe</span><span class="tabular-nums">{{ money($s['omzet']) }}</span></div>
            <p class="-mt-1 text-[11px] text-muted">Termasuk komisi makanan {{ money($s['commission']) }}. Diskon yang sudah diberikan {{ money($s['discount']) }}.</p>

            <p class="{{ $section }}">HPP (harga pokok penjualan)</p>
            @forelse (collect($s['categories'])->where('hpp', '>', 0) as $row)
                <div class="{{ $line }}"><span>{{ $row['name'] }}</span><span class="tabular-nums">{{ money($row['hpp']) }}</span></div>
            @empty
                <p class="py-2 text-[13px] text-muted">Belum ada HPP. Isi cost produk agar terhitung.</p>
            @endforelse
            <div class="{{ $total }}"><span>Total HPP</span><span class="flex gap-4 tabular-nums"><span class="text-[12px] font-normal text-muted">{{ $pct($share($s['hpp'])) }}</span>{{ money($s['hpp']) }}</span></div>

            <div class="mt-1 flex items-baseline justify-between gap-4 rounded-xl bg-[#f7f3ee] px-4 py-3 text-[15px] font-semibold text-heading">
                <span>Laba kotor</span><span class="flex gap-4 tabular-nums"><span class="text-[12px] font-normal text-muted">{{ $pct($s['gross_margin']) }}</span>{{ money($s['gross_profit']) }}</span>
            </div>

            <p class="{{ $section }}">Biaya operasional</p>
            @foreach ($s['bop_rows'] as $row)
                <div class="{{ $line }}"><span>{{ $row['label'] }} <span class="text-[11px] text-muted">· {{ $row['count'] }} catatan BOP</span></span><span class="tabular-nums">{{ money($row['total']) }}</span></div>
            @endforeach
            <div class="{{ $line }}"><span>Gaji karyawan <span class="text-[11px] text-muted">· gaji tetap karyawan aktif</span></span><span class="tabular-nums">{{ money($s['salary']) }}</span></div>
            <div class="{{ $line }}"><span>Sewa ruko <span class="text-[11px] text-muted">· sewa per tahun ÷ 12</span></span><span class="tabular-nums">{{ money($s['rent']) }}</span></div>
            <div class="{{ $total }}"><span>Total biaya operasional</span><span class="flex gap-4 tabular-nums"><span class="text-[12px] font-normal text-muted">{{ $pct($share($s['operational'])) }}</span>{{ money($s['operational']) }}</span></div>
            @if ($s['skipped_fixed'] > 0)
                <p class="-mt-1 text-[11px] text-muted">BOP kategori Gaji dan Sewa {{ money($s['skipped_fixed']) }} tidak dihitung lagi, karena sudah memakai nilai tetap di atas.</p>
            @endif

            <div class="mt-4 flex items-baseline justify-between gap-4 rounded-xl px-4 py-4 text-[16px] font-bold {{ $s['net'] < 0 ? 'bg-brand-soft text-brand' : 'bg-[#eaf6ee] text-[#166534]' }}">
                <span>Laba bersih <span class="ml-1 text-[12px] font-semibold">{{ $s['status'] }}</span></span>
                <span class="flex gap-4 tabular-nums"><span class="text-[12px] font-medium">{{ $pct($s['net_margin']) }}</span>{{ money($s['net']) }}</span>
            </div>
        </div>

        <div class="space-y-5">
            {{-- Struktur omzet --}}
            <div class="rounded-2xl border border-[#ebe4dc] bg-white p-6">
                <h2 class="font-serif text-xl font-semibold text-heading">Ke mana omzet pergi</h2>
                <p class="mt-1 text-[12px] text-muted">Porsi tiap biaya dari omzet cafe {{ money($s['omzet']) }}.@if ($s['net'] < 0) Total biaya melebihi omzet {{ money(-$s['net']) }}.@endif</p>
                <div class="mt-5 flex h-3 gap-0.5 overflow-hidden rounded-full bg-[#f3eee8]">
                    @foreach ($structure as $part)
                        <div class="h-full {{ $part['color'] }}" style="width: {{ max(0, $part['amount'] / $barBase * 100) }}%"></div>
                    @endforeach
                    @if ($s['net'] > 0)
                        <div class="h-full bg-[#16a34a]" style="width: {{ $s['net'] / $barBase * 100 }}%"></div>
                    @endif
                </div>
                <div class="mt-5 space-y-3 text-[13px]">
                    @foreach ($structure as $part)
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $part['color'] }}"></span>{{ $part['label'] }}</span>
                            <span class="tabular-nums"><span class="text-muted">{{ $pct($share($part['amount'])) }}</span> · {{ money($part['amount']) }}</span>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between gap-3 border-t border-[#f0ebe4] pt-3 font-semibold text-heading">
                        <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $s['net'] < 0 ? 'bg-brand' : 'bg-[#16a34a]' }}"></span>Laba bersih</span>
                        <span class="tabular-nums {{ $s['net'] < 0 ? 'text-brand' : 'text-[#166534]' }}">{{ $pct($s['net_margin']) }} · {{ money($s['net']) }}</span>
                    </div>
                </div>
            </div>

            {{-- Banding bulan lalu --}}
            @if ($p)
                <div class="rounded-2xl border border-[#ebe4dc] bg-white p-6">
                    <h2 class="font-serif text-xl font-semibold text-heading">Dibanding bulan lalu</h2>
                    <p class="mt-1 text-[12px] text-muted">{{ $s['label'] }} vs {{ $p['label'] }}.</p>
                    <table class="mt-4 w-full text-[13px]">
                        <thead class="text-[11px] uppercase tracking-[0.08em] text-muted">
                            <tr><th class="pb-2 text-left font-semibold"></th><th class="pb-2 text-right font-semibold">{{ $p['label'] }}</th><th class="pb-2 text-right font-semibold">Ini</th><th class="pb-2 text-right font-semibold">Ubah</th></tr>
                        </thead>
                        <tbody>
                            @foreach ([['Omzet cafe', 'omzet', false], ['HPP', 'hpp', true], ['Biaya operasional', 'operational', true], ['Laba bersih', 'net', false]] as [$name, $key, $cost])
                                @php $diff = $change($s[$key], $p[$key]); $good = $diff === null ? null : ($cost ? $diff <= 0 : $diff >= 0); @endphp
                                <tr class="border-t border-[#f3eee8]">
                                    <td class="py-2.5 font-medium text-heading">{{ $name }}</td>
                                    <td class="py-2.5 text-right tabular-nums text-muted">{{ money($p[$key]) }}</td>
                                    <td class="py-2.5 text-right tabular-nums text-heading">{{ money($s[$key]) }}</td>
                                    <td class="py-2.5 text-right tabular-nums font-semibold {{ $good === null ? 'text-muted' : ($good ? 'text-[#166534]' : 'text-brand') }}">{{ $diff === null ? '—' : ($diff >= 0 ? '+' : '−').$pct(abs($diff)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Per kategori --}}
    <div class="mb-5 overflow-hidden rounded-2xl border border-[#ebe4dc] bg-white">
        <div class="px-6 pt-6">
            <h2 class="font-serif text-xl font-semibold text-heading">Laba per kategori</h2>
            <p class="mt-1 text-[12px] text-muted">Penjualan setelah diskon. Untuk makanan titipan, laba cafe hanya komisinya.</p>
        </div>
        <div class="table-wrap mt-4">
            <table class="w-full text-[13px]">
                <thead class="text-[11px] uppercase tracking-[0.08em] text-muted">
                    <tr class="border-b border-[#f0ebe4]">
                        <th class="px-6 py-3 text-left font-semibold">Kategori</th>
                        <th class="py-3 pl-3 text-right font-semibold">Qty</th>
                        <th class="py-3 pl-3 text-right font-semibold">Penjualan</th>
                        <th class="py-3 pl-3 text-right font-semibold">HPP</th>
                        <th class="py-3 pl-3 text-right font-semibold">Laba cafe</th>
                        <th class="py-3 pl-3 pr-6 text-right font-semibold">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($s['categories'] as $row)
                        <tr class="border-b border-[#f6f2ee] last:border-0">
                            <td class="px-6 py-3 font-medium text-heading">
                                {{ $row['name'] }}
                                @if ($row['consignment'])
                                    <span class="ml-1 rounded-full bg-[#fff3e8] px-2 py-0.5 text-[10px] font-semibold text-[#c2410c]">titipan</span>
                                @endif
                            </td>
                            <td class="py-3 pl-3 text-right tabular-nums">{{ qty($row['qty']) }}</td>
                            <td class="py-3 pl-3 text-right tabular-nums">{{ money($row['sales']) }}</td>
                            <td class="py-3 pl-3 text-right tabular-nums text-muted">{{ money($row['hpp']) }}</td>
                            <td class="py-3 pl-3 text-right font-semibold tabular-nums {{ $row['profit'] < 0 ? 'text-brand' : 'text-heading' }}">{{ money($row['profit']) }}</td>
                            <td class="py-3 pl-3 pr-6 text-right tabular-nums text-muted">{{ $pct($row['margin']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-14 text-center text-sm text-muted">Belum ada penjualan di bulan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
