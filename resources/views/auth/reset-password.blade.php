@extends('layouts.guest')
@section('title', 'Password Baru')
@section('content')
<div class="login-form">
    <img src="{{ asset('images/logo/logo.png') }}" alt="Rasa POS" class="login-form-logo">
    <h2 class="mt-6 text-xl font-semibold text-heading">Buat password baru</h2>
    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="login-field">
            <span class="login-field-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4zM4 6l8 7 8-7"/></svg>
            </span>
            <input type="email" name="email" value="{{ old('email', $email) }}" required placeholder="nama@email.com">
        </div>
        <div class="login-field">
            <span class="login-field-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 11V8a5 5 0 0110 0v3M6 11h12v10H6V11z"/></svg>
            </span>
            <input type="password" name="password" required placeholder="Password baru">
        </div>
        @error('password') <p class="-mt-2 text-xs text-brand">{{ $message }}</p> @enderror
        <div class="login-field">
            <span class="login-field-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 11V8a5 5 0 0110 0v3M6 11h12v10H6V11z"/></svg>
            </span>
            <input type="password" name="password_confirmation" required placeholder="Konfirmasi password">
        </div>
        <button type="submit" class="login-submit">Simpan password</button>
    </form>
    <p class="login-copy">© {{ date('Y') }} Rasa POS. All rights reserved.</p>
</div>
@endsection
