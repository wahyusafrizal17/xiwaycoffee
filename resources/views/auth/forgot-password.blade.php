@extends('layouts.guest')
@section('title', 'Lupa Password')
@section('content')
<div class="login-form">
    <img src="{{ asset('images/logo/logo.png') }}" alt="Rasa POS" class="login-form-logo">
    <h2 class="mt-6 text-xl font-semibold text-heading">Reset password</h2>
    <p class="mt-2 text-sm text-muted">Masukkan email akun Anda. Link reset akan dikirim jika email terdaftar.</p>
    @if (session('status'))
        <div class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-4">
        @csrf
        <div class="login-field">
            <span class="login-field-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM4 6l8 7 8-7"/></svg>
            </span>
            <input type="email" name="email" value="{{ old('email') }}" required placeholder="nama@email.com">
        </div>
        @error('email') <p class="-mt-2 text-xs text-brand">{{ $message }}</p> @enderror
        <button type="submit" class="login-submit">Kirim link reset</button>
        <a href="{{ route('login') }}" class="block text-center text-sm text-muted">Kembali ke login</a>
    </form>
    <p class="login-copy">© {{ date('Y') }} Rasa POS. All rights reserved.</p>
</div>
@endsection
