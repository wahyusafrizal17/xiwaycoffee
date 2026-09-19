@extends('layouts.app')
@section('title', 'Undangan Tamu')
@section('breadcrumb', 'System')
@section('content')
    <div class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h5 class="card-header-title">List tamu undangan</h5>
                <p class="card-header-subtitle">Grand Opening XIWAY COFFEE · Share via WhatsApp</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th class="w-12">#</th>
                        <th>Nama tamu</th>
                        <th>Link undangan</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($guests as $guest)
                        <tr>
                            <td>{{ $guest->sort_order }}</td>
                            <td class="font-semibold">{{ $guest->name }}</td>
                            <td>
                                <a class="text-sm text-brand hover:underline" href="{{ $guest->inviteUrl() }}" target="_blank" rel="noopener">
                                    /invite/{{ $guest->slug }}
                                </a>
                            </td>
                            <td class="col-actions">
                                <a
                                    class="btn-primary !px-3 !py-1.5 text-sm"
                                    href="{{ $guest->whatsappShareUrl() }}"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    Share WA
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-16 text-center text-sm text-slate-400">Belum ada tamu. Jalankan seeder InviteGuestSeeder.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
