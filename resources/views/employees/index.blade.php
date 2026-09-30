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
                        <th>Posisi utama</th>
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
                            <td>{{ $employee->primary_position }}</td>
                            <td class="tabular-nums">{{ money($employee->salary) }}</td>
                            <td class="text-muted">{{ $employee->user?->email }}</td>
                            <td class="text-right">
                                <details>
                                    <summary class="cursor-pointer text-[13px] font-medium text-brand">Ubah</summary>
                                    @php
                                        $package = (int) $employee->base_salary + (int) $employee->job_allowance + (int) $employee->transport_allowance + (int) $employee->cleanliness_allowance;
                                        $base = $package === 0 ? (int) $employee->salary : (int) $employee->base_salary;
                                    @endphp
                                    <form method="POST" action="{{ route('employees.update', $employee) }}" class="mt-3 grid gap-2 text-left">
                                        @csrf
                                        @method('PUT')
                                        <label class="text-[12px] text-muted">Jabatan
                                            <input class="input" name="position" value="{{ $employee->position }}" required maxlength="80">
                                        </label>
                                        <label class="text-[12px] text-muted">Posisi utama
                                            <input class="input" name="primary_position" value="{{ $employee->primary_position }}" maxlength="80">
                                        </label>
                                        <label class="text-[12px] text-muted">Gaji pokok
                                            <input class="input" name="base_salary" type="number" min="0" step="1" value="{{ $base }}" required>
                                        </label>
                                        <label class="text-[12px] text-muted">Tunjangan job
                                            <input class="input" name="job_allowance" type="number" min="0" step="1" value="{{ (int) $employee->job_allowance }}" required>
                                        </label>
                                        <label class="text-[12px] text-muted">Tunjangan transportasi
                                            <input class="input" name="transport_allowance" type="number" min="0" step="1" value="{{ (int) $employee->transport_allowance }}" required>
                                        </label>
                                        <label class="text-[12px] text-muted">Tunjangan kebersihan
                                            <input class="input" name="cleanliness_allowance" type="number" min="0" step="1" value="{{ (int) $employee->cleanliness_allowance }}" required>
                                        </label>
                                        <label class="text-[12px] text-muted">Bonus penjualan
                                            <input class="input" name="sales_bonus" type="number" min="0" step="1" value="{{ (int) $employee->sales_bonus }}" required>
                                        </label>
                                        <label class="text-[12px] text-muted">Potongan
                                            <input class="input" name="deduction" type="number" min="0" step="1" value="{{ (int) $employee->deduction }}" required>
                                        </label>
                                        <button class="btn-add" type="submit">Simpan</button>
                                    </form>
                                </details>
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
