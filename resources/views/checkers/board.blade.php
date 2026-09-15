@extends('layouts.app')
@section('title', $title)
@section('breadcrumb', 'Operations')
@section('content')
    @php
        $stationLabel = $station === 'kitchen' ? 'dapur' : 'bar';
        $lanes = [
            'new' => ['label' => 'Masuk', 'hint' => 'Order baru, mulai proses', 'tone' => 'in'],
            'preparing' => ['label' => 'Proses', 'hint' => 'Sedang disiapkan', 'tone' => 'cook'],
            'ready' => ['label' => 'Siap', 'hint' => 'Siap disajikan', 'tone' => 'done'],
        ];
        $totalTickets = $counts['new'] + $counts['preparing'] + $counts['ready'];
    @endphp

    <div class="kds" x-data x-init="setTimeout(() => location.reload(), 12000)">
        @if ($totalTickets === 0)
            <div class="kds-empty">
                <span class="kds-empty-icon">✓</span>
                <p>Tidak ada order {{ $stationLabel }} yang menunggu.</p>
                <span>Tiket baru akan muncul di kolom Masuk.</span>
            </div>
        @else
            <div class="kds-lanes">
                @foreach ($lanes as $key => $lane)
                    <section class="kds-lane kds-lane-{{ $lane['tone'] }}">
                        <header class="kds-lane-head">
                            <div>
                                <p>{{ $lane['label'] }}</p>
                                <span>{{ $lane['hint'] }}</span>
                            </div>
                            <strong>{{ $counts[$key] }}</strong>
                        </header>

                        <div class="kds-lane-body">
                            @forelse ($columns[$key] as $items)
                                @php
                                    $order = $items->first()?->order;
                                    $wait = $order?->created_at ? (int) $order->created_at->diffInMinutes(now()) : 0;
                                    $waitLabel = $wait < 1 ? 'Baru' : ($wait < 60 ? $wait.' mnt' : ($wait < 1440 ? intdiv($wait, 60).' jam' : intdiv($wait, 1440).' hr'));
                                    $urgent = $key === 'new' && $wait >= 10;
                                    $place = $order?->table?->code
                                        ?: ($order?->order_type?->label() ?? 'Order');
                                @endphp
                                <article class="kds-ticket {{ $urgent ? 'is-urgent' : '' }}">
                                    <header class="kds-ticket-head">
                                        <div>
                                            <p class="kds-place">{{ $place }}</p>
                                            <p class="kds-number">{{ $order?->order_number }}</p>
                                        </div>
                                        <div class="kds-wait" x-data="kdsWait(@js($order?->created_at?->toIso8601String()))" x-init="tick()">
                                            <span x-text="label">{{ $waitLabel }}</span>
                                            @if ($urgent)
                                                <em>Terlambat</em>
                                            @endif
                                        </div>
                                    </header>

                                    <p class="kds-meta">
                                        {{ $order?->order_type?->label() }}
                                        · {{ $order?->customer?->name ?? 'Walk-in' }}
                                        @if ($order?->estimated_ready_at)
                                            · ETA {{ $order->estimated_ready_at->format('H:i') }}
                                        @endif
                                    </p>

                                    <div class="kds-items">
                                        @foreach ($items as $item)
                                            @php
                                                $qty = (float) $item->quantity;
                                                $qtyLabel = fmod($qty, 1.0) === 0.0
                                                    ? (string) (int) $qty
                                                    : rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',');
                                                $itemStation = $item->station ?? $item->product?->station ?? $item->product?->category?->station;
                                                $kitchenItem = $itemStation === 'kitchen';
                                                $barView = $station === 'bar';
                                                $kitchenView = $station === 'kitchen';
                                            @endphp
                                            <div class="kds-item kds-item-{{ $item->status }}">
                                                <div class="kds-item-body">
                                                    <p class="kds-item-name">
                                                        <span class="kds-qty">{{ $qtyLabel }}×</span>
                                                        {{ $item->name }}
                                                    </p>
                                                    @if ($item->variant?->name)
                                                        <p class="kds-item-sub">{{ $item->variant->name }}</p>
                                                    @endif
                                                    @if ($barView && $kitchenItem)
                                                        <p class="kds-item-sub">Dapur{{ $item->status === 'ready' ? ' · siap diambil' : ' memasak' }}</p>
                                                    @endif
                                                    @if ($item->notes)
                                                        <p class="kds-note">{{ $item->notes }}</p>
                                                    @endif
                                                </div>
                                                <div class="kds-actions">
                                                    @if ($kitchenView)
                                                        @if ($item->status === 'new')
                                                            <form method="POST" action="{{ route('order-items.status', $item) }}">
                                                                @csrf
                                                                <input type="hidden" name="status" value="preparing">
                                                                <button class="kds-btn kds-btn-dark" type="submit">Proses</button>
                                                            </form>
                                                        @elseif ($item->status === 'preparing')
                                                            <form method="POST" action="{{ route('order-items.status', $item) }}">
                                                                @csrf
                                                                <input type="hidden" name="status" value="ready">
                                                                <button class="kds-btn kds-btn-brand" type="submit">Siap</button>
                                                            </form>
                                                        @endif
                                                    @elseif ($barView && $kitchenItem)
                                                        @if ($item->status === 'ready')
                                                            <form method="POST" action="{{ route('order-items.status', $item) }}">
                                                                @csrf
                                                                <input type="hidden" name="status" value="served">
                                                                <button class="kds-btn kds-btn-done" type="submit">Antar</button>
                                                            </form>
                                                        @endif
                                                    @elseif ($item->status === 'new')
                                                        <form method="POST" action="{{ route('order-items.status', $item) }}">
                                                            @csrf
                                                            <input type="hidden" name="status" value="preparing">
                                                            <button class="kds-btn kds-btn-dark" type="submit">Proses</button>
                                                        </form>
                                                    @elseif ($item->status === 'preparing')
                                                        <form method="POST" action="{{ route('order-items.status', $item) }}">
                                                            @csrf
                                                            <input type="hidden" name="status" value="ready">
                                                            <button class="kds-btn kds-btn-brand" type="submit">Siap</button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('order-items.status', $item) }}">
                                                            @csrf
                                                            <input type="hidden" name="status" value="served">
                                                            <button class="kds-btn kds-btn-done" type="submit">Sajikan</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </article>
                            @empty
                                <div class="kds-lane-empty">Kosong</div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    function kdsWait(iso) {
        return {
            label: 'Baru',
            tick() {
                if (!iso) return;
                const minutes = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));
                this.label = minutes < 1
                    ? 'Baru'
                    : (minutes < 60
                        ? minutes + ' mnt'
                        : (minutes < 1440 ? Math.floor(minutes / 60) + ' jam' : Math.floor(minutes / 1440) + ' hr'));
                setTimeout(() => this.tick(), 15000);
            },
        };
    }
</script>
@endpush
