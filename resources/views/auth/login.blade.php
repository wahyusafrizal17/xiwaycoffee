@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<div class="login-form" x-data="{ showPassword: false }">
    <img src="{{ asset('images/logo/logo.png') }}" alt="Rasa POS" class="login-form-logo">

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
        @csrf
        <div class="login-field">
            <span class="login-field-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
            </span>
            <input type="email" name="email" value="{{ old('email', 'admin@example.com') }}" required autofocus placeholder="nama@email.com">
        </div>
        @error('email') <p class="-mt-2 text-xs text-brand">{{ $message }}</p> @enderror

        <div class="login-field">
            <span class="login-field-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 11V8a5 5 0 0110 0v3M6 11h12v10H6V11z"/></svg>
            </span>
            <input :type="showPassword ? 'text' : 'password'" name="password" required placeholder="Minimal 6 karakter">
            <button type="button" class="login-field-action" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'">
                <svg x-show="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.6 10.6A3 3 0 0012 15a3 3 0 002.4-4.4M9.9 5.1A10.8 10.8 0 0112 5c6.5 0 10 7 10 7a16.6 16.6 0 01-3.2 4.3M6.1 6.1A16.7 16.7 0 002 12s3.5 7 10 7a10.8 10.8 0 003.1-.5"/></svg>
            </button>
        </div>
        @error('password') <p class="-mt-2 text-xs text-brand">{{ $message }}</p> @enderror

        <label class="login-remember">
            <input type="checkbox" name="remember">
            <span>Ingat saya</span>
        </label>

        <button type="submit" class="login-submit">Masuk</button>
    </form>

    <p class="login-copy">© {{ date('Y') }} Rasa POS. All rights reserved.</p>
</div>
@endsection
