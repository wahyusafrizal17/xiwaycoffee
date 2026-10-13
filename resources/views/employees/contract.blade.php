@extends('layouts.app')
@section('title', 'Kontrak')
@section('content')
    @php
        $made = ($employee->created_at ?? now())->timezone('Asia/Jakarta')->locale('id');
        $romans = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];
        $number = sprintf('%03d/KT/CBN/%s/%s', $employee->id, $romans[(int) $made->format('n')], $made->format('Y'));
        $when = 'Pada hari '.$made->translatedFormat('l').' tanggal '.$made->translatedFormat('j').' bulan '.$made->translatedFormat('F').' tahun '.$made->translatedFormat('Y');
        $jabatan = $employee->position;
        if (filled($employee->primary_position)) {
            $jabatan .= ' / '.$employee->primary_position;
        }
        $wage = number_format((float) $employee->salary, 0, ',', '.');
        $signed = $employee->contract_signed_at?->timezone('Asia/Jakarta')->locale('id');
    @endphp
    <style>
        .sheet { max-width: 820px; margin: 0 auto; background: #fff; color: #111; padding: 36px 48px 48px; font-family: "Times New Roman", Times, serif; font-size: 14px; line-height: 1.45; }
        .sheet h1 { margin: 18px 0 4px; text-align: center; font-size: 18px; letter-spacing: 0.02em; }
        .sheet .no { margin: 0 0 16px; text-align: center; }
        .party { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .party td { padding: 1px 0; vertical-align: top; }
        .party .k { width: 168px; }
        .party .c { width: 16px; }
        .terms { margin: 8px 0 0; padding-left: 18px; }
        .terms li { margin: 2px 0; }
        .signs { display: flex; justify-content: space-between; text-align: center; margin-top: 28px; }
        .signs > div { width: 46%; }
        .sign-space { height: 132px; display: flex; align-items: center; justify-content: center; }
        .sign-space img { width: 120px; height: 120px; }
        @media print {
            aside, header, nav, .no-print { display: none !important; }
            main { padding: 0 !important; }
            .sheet { max-width: none; }
        }
    </style>

    <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">Surat perjanjian kerja waktu tertentu.</p>
        <div class="flex gap-2">
            @unless ($signed)
                <form method="POST" action="{{ route('employees.contract.sign') }}" onsubmit="return confirm('Tanda tangani kontrak ini? Tanda tangan tersimpan sebagai QR code.')">
                    @csrf
                    <button class="btn-add" type="submit">Tanda tangan</button>
                </form>
            @else
                <p class="text-sm text-muted">Ditandatangani {{ $signed->translatedFormat('j F Y H:i') }} WIB</p>
            @endunless
            <button class="btn-ghost" type="button" onclick="window.print()">Cetak</button>
        </div>
    </div>

    <article class="sheet">
        <h1>SURAT PERJANJIAN KERJA<br>UNTUK WAKTU TERTENTU (KONTRAK)</h1>
        <p class="no">No. {{ $number }}</p>
        <p>{{ $when }} telah dibuat dan disepakati perjanjian kerja antara :</p>

        <table class="party">
            <tr><td class="k">Nama</td><td class="c">:</td><td>Wahyu Safrizal</td></tr>
            <tr><td class="k">Jabatan</td><td class="c">:</td><td>Owner Xiway Coffee</td></tr>
            <tr><td class="k">Alamat</td><td class="c">:</td><td>Jl. Nusa Sari Raya No.6A, Citeureup, Kec. Cimahi Utara, Kota Cimahi, Jawa Barat 40525</td></tr>
        </table>
        <p>Dalam hal ini, selanjutnya disebut sebagai <strong>PIHAK PERTAMA</strong></p>

        <table class="party">
            <tr><td class="k">Nama</td><td class="c">:</td><td>{{ $employee->user?->name }}</td></tr>
            <tr><td class="k">Tempat, Tanggal Lahir</td><td class="c">:</td><td>—</td></tr>
            <tr><td class="k">No. Ktp</td><td class="c">:</td><td>—</td></tr>
            <tr><td class="k">Alamat</td><td class="c">:</td><td>{{ $employee->outlet?->address ?: '—' }}</td></tr>
        </table>
        <p>Dalam hal ini bertindak untuk dan atas nama diri sendiri, yang selanjutnya disebut sebagai <strong>PIHAK KEDUA</strong>.</p>
        <p>Kedua belah pihak sepakat untuk mengikatkan diri dalam Perjanjian Kerja Untuk Waktu Tertentu dengan ketentuan-ketentuan sebagai berikut :</p>
        <p>PIHAK PERTAMA menerima dan mempekerjakan PIHAK KEDUA sebagai :</p>
        <ol class="terms">
            <li>Status: Karyawan</li>
            <li>Jabatan / Unit kerja: {{ $jabatan }}</li>
            <li>Upah / Gaji : {{ $wage }} / Bulan</li>
        </ol>

        <div class="signs">
            <div>
                <p>PIHAK PERTAMA</p>
                <div class="sign-space"></div>
                <p>Wahyu Safrizal</p>
                <p>Owner Xiway Coffee</p>
            </div>
            <div>
                <p>PIHAK KEDUA</p>
                <div class="sign-space">
                    @if ($qr)
                        <img src="{{ $qr }}" alt="QR tanda tangan {{ $employee->user?->name }}">
                    @endif
                </div>
                <p>{{ $employee->user?->name }}</p>
            </div>
        </div>
    </article>
@endsection
