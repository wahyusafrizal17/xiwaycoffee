@extends('layouts.app')
@section('title', 'Gaji')
@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Gaji</h1>
            <p class="mt-2 text-[13px] text-muted">Prorata 26 hari kerja/bulan dari hari hadir. {{ $label }}.</p>
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
                        <th>Gaji pokok</th>
                        <th>Hari masuk</th>
                        <th>Diterima</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-medium text-heading">{{ $row['employee']->user?->name }}</td>
                            <td>{{ $row['employee']->position }}</td>
                            <td class="tabular-nums">{{ money($row['salary']) }}</td>
                            <td>
                                <form method="POST" action="{{ route('employees.payroll.update', $row['employee']) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="month" value="{{ $month }}">
                                    <input class="input !h-9 !w-20" type="number" name="days" min="0" max="31" value="{{ $row['worked'] }}" required>
                                    <button class="text-[13px] font-medium text-brand" type="submit">Simpan</button>
                                </form>
                            </td>
                            <td class="tabular-nums font-medium text-heading">{{ money($row['net']) }}</td>
                            <td class="text-right">
                                <a class="text-[13px] font-medium text-brand" href="{{ route('employees.slip', [$row['employee'], 'month' => $month]) }}" target="_blank">Slip</a>
                            </td>
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
