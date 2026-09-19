<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            padding: 28px 32px;
        }
        .brand {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .meta {
            color: #666;
            font-size: 11px;
            margin-bottom: 22px;
        }
        .row {
            width: 100%;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .row .label { float: left; color: #222; }
        .row .value { float: right; text-align: right; color: #111; }
        .status { color: #d97706; font-weight: bold; }
        .status-done { color: #15803d; }
        .status-cancel { color: #dc2626; }
        .items { margin: 18px 0 14px; }
        .item {
            width: 100%;
            margin-bottom: 6px;
            overflow: hidden;
            font-size: 12px;
        }
        .item .name { float: left; max-width: 70%; }
        .item .price { float: right; }
        .item .note { clear: both; color: #888; font-size: 10px; padding-left: 2px; }
        .totals { margin-top: 6px; }
        .divider {
            border: none;
            border-top: 1px dashed #bbb;
            margin: 12px 0;
        }
        .total-row .label,
        .total-row .value {
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            margin-top: 28px;
            font-size: 11px;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
        $itemCount = $order->items->count();
        $status = $order->status?->value;
        $statusLabel = match ($status) {
            'completed' => 'Lunas',
            'cancelled' => 'Dibatalkan',
            'new' => 'Menunggu Konfirmasi',
            'processing', 'preparing' => 'Sedang Diproses',
            'ready' => 'Siap Diambil',
            'held' => 'Ditahan',
            default => $order->status?->label() ?? '-',
        };
        $statusClass = match ($status) {
            'completed' => 'status-done',
            'cancelled' => 'status-cancel',
            default => 'status',
        };
        $taxPct = rtrim(rtrim(number_format((float) ($order->tax_rate ?? 0), 2, '.', ''), '0'), '.');
    @endphp

    <div class="brand">{{ $order->outlet?->name ?: config('app.name') }}</div>
    <div class="meta">{{ $order->order_number }}</div>

    <div class="row">
        <span class="label">Nama</span>
        <span class="value">{{ $order->customer?->name ?: ($order->user?->name ?: '-') }}</span>
    </div>
    <div class="row">
        <span class="label">Meja</span>
        <span class="value">{{ $order->table?->code ? 'Meja '.$order->table->code : ($order->order_type?->label() ?: '-') }}</span>
    </div>
    <div class="row">
        <span class="label">Waktu Pesan</span>
        <span class="value">{{ $order->created_at?->translatedFormat('d M Y H:i') }}</span>
    </div>
    <div class="row">
        <span class="label">Status</span>
        <span class="value {{ $statusClass }}">{{ $statusLabel }}</span>
    </div>

    <div class="items">
        @foreach ($order->items as $item)
            <div class="item">
                <span class="name">{{ qty($item->quantity) }}× {{ $item->name }}</span>
                <span class="price">{{ $fmt($item->total) }}</span>
                @if ($item->notes)
                    <div class="note">{{ $item->notes }}</div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="totals">
        <div class="row">
            <span class="label">Subtotal ({{ $itemCount }} Item)</span>
            <span class="value">{{ $fmt($order->subtotal) }}</span>
        </div>
        @if ((float) $order->discount_amount > 0)
            <div class="row">
                <span class="label">Diskon</span>
                <span class="value">-{{ $fmt($order->discount_amount) }}</span>
            </div>
        @endif
        <div class="row">
            <span class="label">Ppn ({{ $taxPct ?: '0' }}%)</span>
            <span class="value">{{ $fmt($order->tax_amount) }}</span>
        </div>
        <hr class="divider">
        <div class="row total-row">
            <span class="label">Total</span>
            <span class="value">{{ $fmt($order->grand_total) }}</span>
        </div>
    </div>

    <div class="footer">{{ setting('receipt_footer', 'Terima kasih') }}</div>
</body>
</html>
