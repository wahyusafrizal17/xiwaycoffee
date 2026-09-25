@extends('layouts.app')
@section('title', 'Event Display')
@section('breadcrumb', 'Sistem')
@section('content')
    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Preview</h5>
                    <p class="card-header-subtitle">Gambar yang tampil di layar event</p>
                </div>
                <a href="{{ $displayUrl }}" target="_blank" class="btn-ghost text-[13px]">Buka /display-event</a>
            </div>
            <div class="bg-[#0b0b0b] px-4 pb-4">
                <img src="{{ $imageUrl }}" alt="Preview event" class="mx-auto max-h-[28rem] w-full object-contain">
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Upload gambar event</h5>
                    <p class="card-header-subtitle">Ganti poster untuk event baru (JPG/PNG, maks 5 MB)</p>
                </div>
            </div>
            <form method="POST" action="{{ route('event-display.update') }}" enctype="multipart/form-data" class="space-y-4 px-6 pb-6">
                @csrf
                <div>
                    <label class="label">Gambar</label>
                    <input class="input" type="file" name="image" accept="image/*" required>
                    @error('image') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary">Simpan gambar</button>
            </form>
        </div>
    </div>
@endsection
