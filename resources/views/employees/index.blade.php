@extends('layouts.app')
@section('title', 'Karyawan')
@section('content')
    <div class="mb-5">
        <h1 class="font-serif text-[1.75rem] font-semibold leading-none text-heading">Karyawan</h1>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Jabatan</th>
                        <th>Gaji pokok</th>
                        <th>Akun</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td class="font-medium text-heading">{{ $employee->user?->name }}</td>
                            <td>{{ $employee->position }}</td>
                            <td class="tabular-nums">{{ money($employee->salary) }}</td>
                            <td class="text-muted">{{ $employee->user?->email }}</td>
                            <td class="text-right">
                                <details>
                                    <summary class="cursor-pointer text-[13px] font-medium text-brand">Ubah</summary>
                                    <form method="POST" action="{{ route('employees.update', $employee) }}" class="mt-3 grid gap-2 text-left">
                                        @csrf
                                        @method('PUT')
                                        <input class="input" name="position" value="{{ $employee->position }}" required maxlength="80">
                                        <input class="input" name="salary" type="number" min="0" step="1" value="{{ (int) $employee->salary }}" required>
                                        <button class="btn-add" type="submit">Simpan</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center text-sm text-slate-400">Belum ada karyawan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
