@extends('layouts.app')
@section('title', 'Slip gaji')
@section('content')
    <div class="mb-5">
        <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Slip gaji</h1>
        <p class="mt-2 text-[13px] text-muted">Pilih bulan untuk membuka slip.</p>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th>Hari masuk</th>
                        <th>Diterima</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-medium text-heading">{{ $row['label'] }}</td>
                            <td>{{ $row['worked'] }} hari</td>
                            <td class="tabular-nums font-medium text-heading">{{ money($row['net']) }}</td>
                            <td class="text-right">
                                <a class="text-[13px] font-medium text-brand" href="{{ route('employees.mine', ['month' => $row['month']]) }}">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-16 text-center text-sm text-slate-400">Belum ada slip.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
