@extends('layouts.app')
@section('title', 'Rekap Absensi')
@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Rekap absensi</h1>
        </div>
        <form method="GET">
            <input class="input" type="month" name="month" value="{{ $month }}" onchange="this.form.submit()">
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Jabatan</th>
                        <th>Jadwal masuk</th>
                        <th>OFF</th>
                        <th>Hadir</th>
                        <th>Terlambat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-medium text-heading">{{ $row['employee']->user?->name }}</td>
                            <td>{{ $row['employee']->position }}</td>
                            <td>{{ $row['scheduled'] }} hari</td>
                            <td>{{ $row['off'] }} hari</td>
                            <td>{{ $row['attended'] }} hari</td>
                            <td>{{ $row['late'] }} hari</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-sm text-slate-400">Belum ada karyawan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
