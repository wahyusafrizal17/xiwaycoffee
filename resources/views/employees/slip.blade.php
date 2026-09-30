<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Slip gaji {{ $row['employee']->user?->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e8e8e8; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 13px; }
        .bar { display: flex; justify-content: flex-end; gap: 8px; max-width: 820px; margin: 16px auto 0; }
        .bar a, .bar button { font: 13px Arial, Helvetica, sans-serif; border: 1px solid #ccc; background: #fff; border-radius: 6px; padding: 8px 12px; cursor: pointer; color: #111; text-decoration: none; }
        .sheet { max-width: 820px; margin: 16px auto 40px; background: #fff; padding: 36px 48px 48px; }
        h1 { margin: 0 0 10px; text-align: center; font-size: 18px; letter-spacing: 0.02em; }
        hr { border: 0; border-top: 2px solid #111; margin: 12px 0; }
        .head { display: flex; justify-content: space-between; gap: 32px; align-items: flex-start; }
        .company { font-weight: 700; margin: 0 0 2px; }
        .company-address { margin: 0; line-height: 1.45; }
        .meta { border-collapse: collapse; }
        .meta td { padding: 1px 0; vertical-align: top; }
        .meta .k { padding-right: 28px; }
        .meta .c { padding-right: 10px; }
        h2 { margin: 18px 0 8px; font-size: 13px; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines td { padding: 2px 0; }
        table.lines .num { text-align: right; width: 1%; white-space: nowrap; padding-left: 24px; }
        table.lines .num span { display: inline-block; min-width: 120px; text-align: right; }
        table.lines tr.total td { font-weight: 700; padding-top: 8px; }
        table.lines tr.total .num span { border-top: 1.5px solid #111; padding-top: 3px; }
        table.lines tr.grand td { font-weight: 700; padding-top: 16px; }
        table.lines tr.grand .num span { border-bottom: 3px double #111; padding-bottom: 2px; }
        .words { margin: 14px 0 0; font-style: italic; }
        .place { margin: 28px 0 8px; text-align: center; }
        .signs { display: flex; justify-content: space-between; text-align: center; margin-top: 8px; }
        .signs > div { width: 46%; }
        .sign-space { height: 88px; display: flex; align-items: center; justify-content: center; }
        .sign-space img { height: 78px; width: auto; }
        .who { margin: 0; }
        .note { margin: 0 0 6px; }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { margin: 0; max-width: none; box-shadow: none; }
        }
    </style>
</head>
<body>
    @php
        $employee = $row['employee'];
        $address = $outlet?->address ?: 'Jl. Nusa Sari Raya No. 6A, Cimahi Utara, Kota Cimahi';
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|,/', $address))));
        $city = 'Cimahi';
        foreach ($lines as $line) {
            if (preg_match('/^(jl\.?|rt\b|rw\b)/i', $line)) {
                continue;
            }
            $city = preg_replace('/^(kota|kec\.?|kel\.?)\s+/i', '', $line) ?: $city;
            break;
        }
        $issued = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        if ($issued->isFuture()) {
            $issued = now();
        }
        $money = fn (int $amount) => number_format($amount, 0, '.', ',');
        $status = $employee->is_active ? 'Karyawan' : 'Nonaktif';
    @endphp
    <div class="bar">
        <a href="{{ route('employees.payroll', ['month' => $month]) }}">Kembali</a>
        <button type="button" onclick="window.print()">Cetak</button>
    </div>
    <article class="sheet">
        <h1>SLIP GAJI</h1>
        <hr>
        <div class="head">
            <div>
                <p class="company">XIWAY COFFEE</p>
                @foreach ($lines as $line)
                    <p class="company-address">{{ $line }}</p>
                @endforeach
            </div>
            <table class="meta">
                <tr><td class="k">Periode</td><td class="c">:</td><td>{{ $label }}</td></tr>
                <tr><td class="k">Karyawan</td><td class="c">:</td><td>{{ $employee->user?->name }}</td></tr>
                <tr><td class="k">Jabatan</td><td class="c">:</td><td>{{ $employee->position }}</td></tr>
                @if (filled($employee->primary_position))
                    <tr><td class="k">Posisi</td><td class="c">:</td><td>{{ $employee->primary_position }}</td></tr>
                @endif
                <tr><td class="k">Status</td><td class="c">:</td><td>{{ $status }}</td></tr>
            </table>
        </div>
        <hr>

        <h2>PENERIMAAN</h2>
        <p class="note">Gaji prorata ({{ $row['worked'] }}/26 hari)</p>
        <table class="lines">
            @foreach ($row['lines'] as $label => $amount)
                <tr>
                    <td>- {{ $label }}</td>
                    <td class="num"><span>{{ $money($amount) }}</span></td>
                </tr>
            @endforeach
            <tr>
                <td>- Bonus Penjualan</td>
                <td class="num"><span>{{ $money($row['bonus']) }}</span></td>
            </tr>
            <tr class="total">
                <td>Total Penerimaan</td>
                <td class="num"><span>{{ $money($row['earnings']) }}</span></td>
            </tr>
        </table>

        <h2>POTONGAN</h2>
        <table class="lines">
            <tr>
                <td>- Potongan</td>
                <td class="num"><span>{{ $money($row['deduction']) }}</span></td>
            </tr>
            <tr class="total">
                <td>Total Potongan</td>
                <td class="num"><span>{{ $money($row['deduction']) }}</span></td>
            </tr>
            <tr class="grand">
                <td>TOTAL DITERIMA KARYAWAN</td>
                <td class="num"><span>{{ $money($row['net']) }}</span></td>
            </tr>
        </table>

        <p class="words"># Tertulis : {{ ucwords(terbilang($row['net'])) }} Rupiah</p>
        <hr>
        <p class="place">{{ $city }}, {{ $issued->locale('id')->translatedFormat('j F Y') }}</p>
        <div class="signs">
            <div>
                <p class="who">Penerima</p>
                <div class="sign-space"></div>
                <p class="who">{{ $employee->user?->name }}</p>
            </div>
            <div>
                <p class="who">XIWAY COFFEE</p>
                <div class="sign-space">
                    <img src="{{ asset('images/logo/xiway-logo.png') }}" alt="Xiway Coffee">
                </div>
                <p class="who">Owner / Management</p>
            </div>
        </div>
    </article>
</body>
</html>
