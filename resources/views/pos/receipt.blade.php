<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=80mm">
    <title>Receipt {{ $order->order_number }}</title>
    <style>
        /* EPPOS Plus 80MM: paper 80mm, printable ~72mm */
        @page { size: 80mm auto; margin: 0; }

        * { box-sizing: border-box; }

        html, body {
            width: 80mm;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            font-family: ui-monospace, "Courier New", Courier, monospace;
            font-size: 12px;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body { padding: 3mm 4mm 6mm; }

        h1 { font-size: 15px; margin: 0 0 4px; text-align: center; }
        p { margin: 0 0 2px; }
        .center { text-align: center; }
        .muted { color: #000; }
        .row { display: flex; justify-content: space-between; gap: 8px; margin: 3px 0; }
        .row span:first-child { flex: 1; word-break: break-word; }
        .row span:last-child { white-space: nowrap; }
        hr { border: none; border-top: 1px dashed #000; margin: 8px 0; }
        .total { font-size: 14px; font-weight: 700; }

        @media print {
            html, body { width: 80mm; }
        }
    </style>
    <script>window.RasaQz = { printer: @json($qzPrinter ?? '') };</script>
    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.5/qz-tray.js"></script>
    @include('layouts.partials.qz-print')
</head>
<body>
    <p id="qz-status" class="center muted">Mencetak via QZ Tray…</p>
    <script>
        (async function () {
            var status = document.getElementById('qz-status');
            try {
                await window.RasaQz.printRaw(@json($qzPrinter ?? ''), @json($receiptEscpos ?? ''));
                status.textContent = 'Struk terkirim ke printer.';
                setTimeout(function () { window.close(); }, 800);
            } catch (e) {
                status.textContent = (e && e.message ? e.message : 'QZ Tray gagal.')
                    + ' Jangan pakai dialog Chrome Print.';
            }
        })();
    </script>
    <h1>{{ $order->outlet?->name ?? config('app.name') }}</h1>
    <p class="center muted">{{ $order->order_number }}</p>
    <p class="center muted">{{ $order->created_at->format('d/m/Y H:i') }}</p>
    <p class="center muted">{{ $order->order_type->label() }}@if($order->table) · {{ $order->table->code }}@endif</p>
    <p class="center muted">Kasir: {{ $order->user?->name }}</p>
    <hr>
    @foreach ($order->items as $item)
        @php
            $qty = (float) $item->quantity;
            $qtyLabel = fmod($qty, 1.0) === 0.0
                ? (string) (int) $qty
                : rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');
        @endphp
        <div class="row">
            <span>{{ $qtyLabel }} × {{ $item->name }}</span>
            <span>{{ money($item->total) }}</span>
        </div>
        @if ($item->notes)<p class="muted">  {{ $item->notes }}</p>@endif
    @endforeach
    <hr>
    <div class="row"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
    <div class="row"><span>Diskon</span><span>{{ money($order->discount_amount) }}</span></div>
    <div class="row"><span>Pajak</span><span>{{ money($order->tax_amount) }}</span></div>
    <div class="row total"><span>Total</span><span>{{ money($order->grand_total) }}</span></div>
    @foreach ($order->payments as $payment)
        <div class="row"><span>{{ $payment->method->label() }}</span><span>{{ money($payment->amount) }}</span></div>
        @if ($payment->change_amount > 0)
            <div class="row"><span>Kembalian</span><span>{{ money($payment->change_amount) }}</span></div>
        @endif
    @endforeach
    <hr>
    <p class="center">{{ setting('receipt_footer', 'Terima kasih telah berkunjung') }}</p>
</body>
</html>
