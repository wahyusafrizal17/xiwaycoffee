<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ strtoupper($station) }} · {{ $order->order_number }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        @font-face { font-family: 'Juana'; src: url('/fonts/juana/Juana-Medium.otf') format('opentype'); font-weight: 500; font-style: normal; font-display: swap; }
        @font-face { font-family: 'Juana'; src: url('/fonts/juana/Juana-Bold.otf') format('opentype'); font-weight: 700; font-style: normal; font-display: swap; }
    </style>
    <style>
        :root {
            --brand: #6f1715;
            --brand-soft: #f3e6e4;
            --ink: #171717;
            --heading: #111111;
            --muted: #737373;
            --line: #e5e5e5;
            --canvas: #efe7de;
        }

        * { box-sizing: border-box; }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Juana', ui-serif, Georgia, serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--canvas);
            color: var(--ink);
            font-family: 'Fira Sans', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 16px 20px 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 36px;
            padding: 0 14px;
            border: 0;
            border-radius: 10px;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-brand { background: var(--brand); color: #fff; }
        .btn-ghost { background: #fff; color: var(--heading); box-shadow: 0 1px 0 rgba(17,17,17,.06); }

        .sheet {
            width: min(640px, calc(100% - 32px));
            margin: 16px auto 40px;
            overflow: hidden;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(17, 17, 17, 0.10);
        }

        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 28px;
            background:
                radial-gradient(520px 140px at 100% 0%, rgba(111, 23, 21, 0.42), transparent 58%),
                linear-gradient(180deg, #171717 0%, #0a0a0a 100%);
            color: #fff;
        }

        .hero img {
            height: 72px;
            width: auto;
            object-fit: contain;
        }

        .hero-copy {
            text-align: right;
        }

        .hero-kicker {
            margin: 0;
            color: rgba(255,255,255,.62);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .hero-title {
            margin: 4px 0 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .body {
            padding: 28px;
        }

        .intro {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .order-no {
            margin: 0;
            color: var(--heading);
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -.03em;
        }

        .outlet {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 6px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            height: 26px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f4f4f4;
            color: var(--heading);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .chip-brand {
            background: var(--brand-soft);
            color: var(--brand);
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 20px;
            margin-top: 22px;
            padding: 18px 0;
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .meta dt {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .meta dd {
            margin: 4px 0 0;
            color: var(--heading);
            font-size: 14px;
            font-weight: 600;
        }

        .table-callout {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 20px;
            padding: 16px 18px;
            border-radius: 16px;
            background: #111;
            color: #fff;
        }

        .table-callout span {
            color: rgba(255,255,255,.62);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .table-callout strong {
            display: block;
            margin-top: 4px;
            font-size: 28px;
            letter-spacing: -.03em;
        }

        .table-callout em {
            font-style: normal;
            color: #f7b4b4;
            font-size: 13px;
            font-weight: 600;
        }

        table {
            width: 100%;
            margin-top: 22px;
            border-collapse: collapse;
        }

        th {
            padding: 0 0 10px;
            border-bottom: 1px solid var(--line);
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            text-align: left;
            text-transform: uppercase;
        }

        th.qty, td.qty { text-align: right; width: 72px; }
        th.idx, td.idx { width: 42px; color: var(--muted); }

        td {
            padding: 14px 0;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: top;
        }

        tr:last-child td { border-bottom: 0; }

        .item-name {
            color: var(--heading);
            font-size: 15px;
            font-weight: 600;
        }

        .item-sub {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .item-note {
            display: inline-block;
            margin-top: 8px;
            padding: 5px 8px;
            border-left: 3px solid var(--brand);
            background: var(--brand-soft);
            color: #9a1212;
            font-size: 12px;
            font-weight: 600;
        }

        .qty-value {
            color: var(--heading);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -.03em;
            line-height: 1;
        }

        .empty {
            margin: 28px 0 8px;
            color: var(--muted);
            font-size: 14px;
            text-align: center;
        }

        .foot {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 12px;
        }

        .foot strong {
            display: block;
            color: var(--heading);
            font-size: 13px;
        }

        @media (max-width: 640px) {
            .sheet { width: calc(100% - 20px); border-radius: 18px; }
            .body, .hero { padding: 20px; }
            .meta { grid-template-columns: 1fr; }
            .intro { flex-direction: column; }
            .chips { justify-content: flex-start; }
            .hero-copy { text-align: left; }
        }

        @media print {
            @page { size: 80mm auto; margin: 0; }
            html, body { width: 80mm; background: #fff; }
            .toolbar { display: none !important; }
            .sheet {
                width: 80mm;
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .hero { padding: 8px 10px; }
            .hero img { display: none; }
            .body { padding: 8px 10px; }
        }
    </style>
</head>
<body>
    @php
        $stationLabel = match ($station) {
            'kitchen' => 'Kitchen Ticket',
            'bar' => 'Bar Ticket',
            'prep' => 'Tiket Dapur / Bar',
            default => 'Cashier Ticket',
        };
        $stationKicker = match ($station) {
            'kitchen' => 'Tiket dapur',
            'bar' => 'Tiket bar',
            'prep' => 'Pesanan dapur & bar',
            default => 'Tiket kasir',
        };
        $itemCount = $items->count();
    @endphp

    <div class="toolbar">
        <button type="button" class="btn btn-ghost" onclick="window.close()">Tutup</button>
        <button type="button" class="btn btn-brand" onclick="window.print()">Cetak</button>
    </div>

    <article class="sheet">
        <header class="hero">
            <img src="{{ asset('images/logo/logo.png') }}" alt="Xiway Pos">
            <div class="hero-copy">
                <p class="hero-kicker">{{ $stationKicker }}</p>
                <h1 class="hero-title">{{ $stationLabel }}</h1>
            </div>
        </header>

        <div class="body">
            <div class="intro">
                <div>
                    <p class="order-no">{{ $order->order_number }}</p>
                    <p class="outlet">{{ $order->outlet?->name ?? config('app.name') }}</p>
                </div>
                <div class="chips">
                    <span class="chip chip-brand">{{ $order->order_type?->label() ?? 'Order' }}</span>
                    @if ($order->status)
                        <span class="chip">{{ $order->status->label() }}</span>
                    @endif
                    @if ($reprint)
                        <span class="chip chip-brand">Reprint</span>
                    @endif
                </div>
            </div>

            <dl class="meta">
                <div>
                    <dt>Tanggal</dt>
                    <dd>{{ $order->created_at?->format('d M Y · H:i') }}</dd>
                </div>
                <div>
                    <dt>Server</dt>
                    <dd>{{ $order->user?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Tamu</dt>
                    <dd>{{ $order->customer?->name ?? 'Walk-in' }}</dd>
                </div>
                <div>
                    <dt>Estimasi</dt>
                    <dd>{{ $order->estimated_ready_at?->format('H:i') ?? '—' }}</dd>
                </div>
            </dl>

            @if ($order->table)
                <div class="table-callout">
                    <div>
                        <span>Meja</span>
                        <strong>{{ $order->table->code }}</strong>
                    </div>
                    <em>
                        {{ $order->table->capacity }} pax
                        @if ($order->guest_count)
                            · {{ $order->guest_count }} tamu
                        @endif
                    </em>
                </div>
            @endif

            @forelse ($items as $item)
                @if ($loop->first)
                    <table>
                        <thead>
                            <tr>
                                <th class="idx">#</th>
                                <th>Item</th>
                                <th class="qty">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                @endif
                            <tr>
                                <td class="idx">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>
                                <td>
                                    <div class="item-name">@if ($station === 'prep'){{ match ($item->station ?? $item->product?->station) { 'kitchen' => '[DAPUR] ', 'bar' => '[BAR] ', default => '' } }}@endif{{ $item->name }}</div>
                                    @if ($item->variant?->name)
                                        <div class="item-sub">{{ $item->variant->name }}</div>
                                    @endif
                                    @if ($item->notes)
                                        <div class="item-note">{{ $item->notes }}</div>
                                    @endif
                                </td>
                                <td class="qty">
                                    <span class="qty-value">{{ fmod((float) $item->quantity, 1.0) === 0.0 ? (int) $item->quantity : rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</span>
                                </td>
                            </tr>
                @if ($loop->last)
                        </tbody>
                    </table>
                @endif
            @empty
                <p class="empty">Tidak ada item untuk station {{ $station }}.</p>
            @endforelse

            <footer class="foot">
                <div>
                    <strong>{{ $itemCount }} item {{ $station === 'prep' ? 'dapur/bar' : $station }}</strong>
                    Dikirim ke station untuk persiapan.
                </div>
                <div style="text-align:right">
                    <strong>{{ now()->format('d M Y · H:i') }}</strong>
                    {{ config('app.name') }}
                </div>
            </footer>
        </div>
    </article>
    <script>
        window.addEventListener('load', () => {
            if (new URLSearchParams(window.location.search).has('preview')) return;
            window.print();
        });
    </script>
</body>
</html>
