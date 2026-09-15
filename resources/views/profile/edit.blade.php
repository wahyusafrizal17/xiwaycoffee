@extends('layouts.app')
@section('title', 'Profil')
@section('breadcrumb', 'Akun')
@section('content')
    @php
        $roleLabel = $stats['role'];
        $outletLabel = $user->outlets->pluck('name')->filter()->join(', ') ?: (current_outlet()?->name ?? 'Tanpa outlet');
    @endphp

    <div x-data="profilePhoto(@js($user->avatarUrl()))">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Peran</p>
                    <p class="stat-value text-xl">{{ $stats['role'] }}</p>
                    <p class="stat-hint">Hak akses akun</p>
                </div>
                <span class="stat-icon bg-[#e8f1ff] text-[#3b82f6]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 14a4 4 0 10-8 0m8 0a6 6 0 10-12 0m12 0v1a3 3 0 01-3 3H9a3 3 0 01-3-3v-1m12 0a9 9 0 10-18 0"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Outlet</p>
                    <p class="stat-value">{{ number_format($stats['outlets']) }}</p>
                    <p class="stat-hint truncate" title="{{ $outletLabel }}">{{ $outletLabel }}</p>
                </div>
                <span class="stat-icon bg-[#e8f8ee] text-[#1f9d57]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
                </span>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-kicker">Login terakhir</p>
                    <p class="stat-value text-xl">{{ $stats['last_login'] }}</p>
                    <p class="stat-hint">{{ $user->is_active ? 'Akun aktif' : 'Akun nonaktif' }}</p>
                </div>
                <span class="stat-icon bg-[#fff3e8] text-[#ff9f43]">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card mb-4 overflow-hidden">
            @csrf
            @method('PUT')
            <input type="hidden" name="remove_avatar" :value="removeAvatar ? 1 : 0">
            <input class="sr-only" type="file" name="avatar" x-ref="file" accept="image/jpeg,image/png,image/webp" @change="onChange">

            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Profil akun</h5>
                    <p class="card-header-subtitle">Profil saya — kelola nama, kontak, dan foto tampilan.</p>
                </div>
            </div>

            <div class="space-y-8 px-5 py-6">
                <section>
                    <h6 class="mb-4 text-sm font-semibold text-heading">Foto profil</h6>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <button type="button" class="profile-photo shrink-0 self-start" @click="pick()">
                            <img x-show="preview" x-cloak :src="preview" alt="{{ $user->name }}" class="h-full w-full object-cover">
                            <span class="profile-photo-fallback" x-show="!preview">{{ $user->initials() }}</span>
                            <span class="profile-photo-overlay">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M8 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Ubah foto</span>
                            </span>
                        </button>

                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-heading">{{ $user->name }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="badge-soft">{{ $roleLabel }}</span>
                                <span class="text-sm text-muted">{{ $outletLabel }}</span>
                            </div>
                            <p class="mt-3 text-[12px] text-muted">JPG, PNG, atau WEBP. Maksimal 2 MB. Foto ini tampil di header aplikasi.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="btn-ghost !rounded-xl !px-3.5 !py-2 text-xs" @click="pick()">Unggah foto</button>
                                <button type="button" class="btn-ghost !rounded-xl !px-3.5 !py-2 text-xs text-muted" x-show="preview" x-cloak @click="clear()">Hapus foto</button>
                            </div>
                        </div>
                    </div>
                    @error('avatar')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </section>

                <section class="border-t border-line pt-8">
                    <h6 class="mb-4 text-sm font-semibold text-heading">Informasi kontak</h6>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="label">Nama</label>
                            <input class="input" name="name" required maxlength="255" value="{{ old('name', $user->name) }}">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label">Email</label>
                            <input class="input" type="email" name="email" required value="{{ old('email', $user->email) }}">
                            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label">Telepon</label>
                            <input class="input" name="phone" maxlength="30" value="{{ old('phone', $user->phone) }}" placeholder="08…">
                            @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>
            </div>

            <div class="crud-modal-footer !rounded-none !border-t !border-line">
                <button class="btn-add" type="submit">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
                    Simpan profil
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('profile.password') }}" class="card mb-4 overflow-hidden">
            @csrf
            @method('PUT')

            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Keamanan</h5>
                    <p class="card-header-subtitle">Perbarui password untuk menjaga akun tetap aman.</p>
                </div>
            </div>

            <div class="space-y-4 px-5 py-6">
                <div>
                    <label class="label">Password saat ini</label>
                    <input class="input" type="password" name="current_password" required autocomplete="current-password">
                    @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Password baru</label>
                        <input class="input" type="password" name="password" required autocomplete="new-password">
                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label">Konfirmasi password</label>
                        <input class="input" type="password" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="crud-modal-footer !rounded-none !border-t !border-line">
                <button class="btn-add" type="submit">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Ubah password
                </button>
            </div>
        </form>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Sesi perangkat</h5>
                    <p class="card-header-subtitle">Akhiri sesi di perangkat ini. Order yang sedang berjalan tidak terpengaruh.</p>
                </div>
            </div>

            <div class="crud-modal-footer !rounded-none !border-t !border-line !justify-between">
                <p class="text-sm text-muted">Masuk sebagai <span class="font-semibold text-heading">{{ $user->email }}</span></p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-ghost !rounded-xl text-brand hover:bg-brand-soft" type="submit">Logout</button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function profilePhoto(initial) {
        return {
            preview: initial,
            removeAvatar: false,
            pick() {
                this.$refs.file?.click();
            },
            onChange(event) {
                const file = event.target.files?.[0];
                if (! file) return;
                this.removeAvatar = false;
                if (this.preview && String(this.preview).startsWith('blob:')) {
                    URL.revokeObjectURL(this.preview);
                }
                this.preview = URL.createObjectURL(file);
            },
            clear() {
                this.removeAvatar = true;
                if (this.preview && String(this.preview).startsWith('blob:')) {
                    URL.revokeObjectURL(this.preview);
                }
                this.preview = null;
                if (this.$refs.file) this.$refs.file.value = '';
            },
        };
    }
</script>
@endpush
