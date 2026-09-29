@extends('layouts.app')
@section('title', 'Jadwal')
@section('breadcrumb', 'Karyawan')
@section('content')
    <div class="mb-5">
        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Shift karyawan</p>
        <h1 class="mt-1 font-serif text-[1.75rem] font-semibold leading-none text-heading">Jadwal</h1>
        <p class="mt-2 text-[13px] text-muted">30 September – 31 Oktober 2026. Terhubung ke akun karyawan.</p>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Hari</th>
                        @foreach ($employees as $employee)
                            <th class="{{ (int) $meId === (int) $employee->id ? 'text-brand' : '' }}">
                                {{ $employee->user?->name }}
                                <span class="block text-[11px] font-normal text-muted">{{ $employee->position }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($byDate as $date => $dayShifts)
                        @php
                            $isToday = $date === now()->toDateString();
                            $day = \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('l');
                        @endphp
                        <tr class="{{ $isToday ? 'bg-[#fff8e6]' : '' }}">
                            <td class="whitespace-nowrap text-[13px] {{ $isToday ? 'font-semibold text-heading' : 'text-muted' }}">
                                {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}
                            </td>
                            <td class="text-[13px]">{{ $day }}</td>
                            @foreach ($employees as $employee)
                                @php
                                    $shift = $dayShifts->firstWhere('employee_id', $employee->id);
                                    $off = $shift && ! $shift->starts_at;
                                @endphp
                                <td class="whitespace-nowrap text-[13px] {{ $off ? 'bg-[#fde8e8] font-medium text-[#c2410c]' : '' }} {{ (int) $meId === (int) $employee->id && ! $off ? 'font-medium text-heading' : '' }}">
                                    {{ $shift?->label($employee->position) ?? '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 2 + $employees->count() }}" class="py-16 text-center text-sm text-slate-400">Belum ada jadwal.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
