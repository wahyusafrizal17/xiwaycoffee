@extends('layouts.app')
@section('title', 'Setoran makanan')
@section('breadcrumb', 'Reports')
@section('content')
    @php
        $period = $period ?? ($filters['period'] ?? 'day');
        $range = $range ?? ['label' => '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''];
        $columnFilters = collect($filters)->only(['outlet_id'])->filter(fn ($value) => filled($value));
        $formError = $errors->any();
        $formOpen = $formError || request()->boolean('open');
        $previewOutlet = filled($filters['outlet_id'] ?? null) ? '&outlet_id='.urlencode((string) $filters['outlet_id']) : '';
    @endphp

    <div x-data="{ formOpen: {{ $formOpen ? 'true' : 'false' }} }" @keydown.escape.window="formOpen = false">
        <div class="no-print">
            @include('reports._period_bar', [
                'action' => route('reports.setoran'),
                'columnFilters' => $columnFilters,
            ])
        </div>

        <div class="no-print mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Penjualan makanan</p>
                    <p class="stat-value">{{ money($sales) }}</p>
                    <p class="stat-hint">{{ $range['label'] }}</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Bagian cafe</p>
                    <p class="stat-value">{{ money($commission) }}</p>
                    <p class="stat-hint">{{ (int) config('pos.food_cafe_percent', 10) }}% dari penjualan</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Setoran periode</p>
                    <p class="stat-value">{{ money($setoran) }}</p>
                    <p class="stat-hint">Penjualan − komisi</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Belum disetor</p>
                    <p class="stat-value {{ $outstanding > 0 ? 'text-[#c2410c]' : '' }}">{{ money($outstanding) }}</p>
                    <p class="stat-hint">Akumulasi − sudah disetor</p>
                </div>
            </div>
        </div>

        <div class="mb-5 card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Rincian setoran</h5>
                    <p class="card-header-subtitle">Makanan milik mitra, {{ $range['label'] }}.</p>
                </div>
                <div class="card-header-actions no-print">
                    <button type="button" class="btn-pdf" onclick="window.print()">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg>
                        Cetak setoran
                    </button>
                    @if (auth()->user()->hasPermission('reports.view'))
                        <button type="button" class="btn-add" @click="formOpen = true" @if ($setoran <= 0) disabled @endif>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                            Catat setoran
                        </button>
                    @endif
                </div>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Qty</th>
                            <th>Penjualan</th>
                            <th>Bagian cafe</th>
                            <th>Setoran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td>{{ rtrim(rtrim(number_format((float) $row->qty, 3, ',', '.'), '0'), ',') }}</td>
                                <td>{{ money($row->sales) }}</td>
                                <td>{{ money($row->commission) }}</td>
                                <td>{{ money($row->setoran) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-16 text-center text-sm text-slate-400">Belum ada penjualan makanan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="setor-print-foot">
                        <tr>
                            <td class="font-semibold">Total</td>
                            <td class="font-semibold">{{ rtrim(rtrim(number_format((float) $rows->sum('qty'), 3, ',', '.'), '0'), ',') }}</td>
                            <td class="font-semibold">{{ money($sales) }}</td>
                            <td class="font-semibold">{{ money($commission) }}</td>
                            <td class="font-semibold">{{ money($setoran) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="no-print card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Riwayat setoran</h5>
                    <p class="card-header-subtitle">Pencatatan saat cafe menyetor ke mitra. Bukan BOP.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Tanggal setor</th>
                            <th>Periode</th>
                            <th class="text-right">Nominal</th>
                            <th>Keterangan</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settlements as $settlement)
                            <tr>
                                <td class="whitespace-nowrap text-[13px] text-muted">{{ $settlement->settled_on?->format('d M Y') }}</td>
                                <td class="whitespace-nowrap text-[13px] text-muted">
                                    @if ($settlement->period_from && $settlement->period_to)
                                        {{ $settlement->period_from->format('d M Y') }} – {{ $settlement->period_to->format('d M Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right font-medium tabular-nums">{{ money($settlement->amount) }}</td>
                                <td class="max-w-[280px] truncate text-[13px] text-muted">{{ $settlement->notes ?: '—' }}</td>
                                <td class="text-[13px]">{{ $settlement->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-16 text-center text-sm text-slate-400">Belum ada setoran tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (auth()->user()->hasPermission('reports.view'))
        <div class="no-print fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="formOpen" x-cloak @click.self="formOpen = false">
            <div class="crud-modal" @click.stop>
                <form class="flex min-h-0 flex-1 flex-col" method="POST" action="{{ route('reports.setoran.store') }}">
                    @csrf
                    <div class="crud-modal-body">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-serif text-lg font-semibold text-heading">Catat setoran</h3>
                                <p class="mt-1 text-[13px] text-muted">Nominal mengikuti penjualan makanan pada periode yang dipilih.</p>
                            </div>
                            <button type="button" class="modal-close" @click="formOpen = false">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>

                        <div class="mt-5 grid gap-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label">Dari tanggal</label>
                                    <input class="input" type="date" name="from" required value="{{ old('from', $filters['from'] ?? '') }}" onchange="if (this.value && this.form.to.value) location.href='{{ route('reports.setoran') }}?open=1&from='+encodeURIComponent(this.value)+'&to='+encodeURIComponent(this.form.to.value)+'{{ $previewOutlet }}'">
                                    @error('from')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="label">Sampai tanggal</label>
                                    <input class="input" type="date" name="to" required value="{{ old('to', $filters['to'] ?? '') }}" onchange="if (this.form.from.value && this.value) location.href='{{ route('reports.setoran') }}?open=1&from='+encodeURIComponent(this.form.from.value)+'&to='+encodeURIComponent(this.value)+'{{ $previewOutlet }}'">
                                    @error('to')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div>
                                <label class="label">Tanggal setor</label>
                                <input class="input" type="date" name="settled_on" required value="{{ old('settled_on', now()->toDateString()) }}">
                                @error('settled_on')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Nominal</label>
                                <input class="input bg-[#fafafa]" type="text" value="{{ money($setoran) }}" readonly>
                                <p class="mt-1 text-[12px] text-muted">{{ 100 - (int) config('pos.food_cafe_percent', 10) }}% penjualan makanan pada periode di atas. Sisa belum disetor {{ money($outstanding) }}.</p>
                            </div>
                            <div>
                                <label class="label">Keterangan</label>
                                <input class="input" type="text" name="notes" value="{{ old('notes') }}" placeholder="Opsional" maxlength="255">
                                @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="crud-modal-footer">
                        <button type="button" class="btn-ghost" @click="formOpen = false">Batal</button>
                        <button class="btn-add" type="submit" @if ($setoran <= 0) disabled @endif>Simpan setoran</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>

    <style>
        .setor-print-foot { display: none; }
        @media print {
            aside, header, nav, .no-print { display: none !important; }
            main { padding: 0 !important; }
            .setor-print-foot { display: table-footer-group; }
        }
    </style>
@endsection
