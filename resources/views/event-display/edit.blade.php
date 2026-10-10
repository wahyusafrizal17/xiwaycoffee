@extends('layouts.app')
@section('title', 'Event Display')
@section('breadcrumb', 'Sistem')
@section('content')
    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Gambar event</h5>
                    <p class="card-header-subtitle">Diputar bergantian di layar event</p>
                </div>
                <a href="{{ $displayUrl }}" target="_blank" class="btn-ghost text-[13px]">Buka /display-event</a>
            </div>
            <div class="grid grid-cols-2 gap-3 bg-[#0b0b0b] px-4 pb-4">
                @forelse ($images as $image)
                    <div class="overflow-hidden rounded-lg bg-white">
                        <img src="{{ $image['url'] }}" alt="Gambar event {{ $image['index'] + 1 }}" class="h-48 w-full bg-[#0b0b0b] object-contain">
                        <form method="POST" action="{{ route('event-display.destroy', $image['index']) }}" class="p-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger w-full !py-1.5">Hapus</button>
                        </form>
                    </div>
                @empty
                    <p class="col-span-2 py-16 text-center text-sm text-white/50">Belum ada gambar. Layar event memakai gambar bawaan.</p>
                @endforelse
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h5 class="card-header-title">Upload gambar event</h5>
                    <p class="card-header-subtitle">Bisa beberapa sekaligus (JPG/PNG, maks 5 MB tiap gambar, {{ \App\Http\Controllers\EventDisplayController::MAX_IMAGES }} gambar)</p>
                </div>
            </div>
            <form method="POST" action="{{ route('event-display.update') }}" enctype="multipart/form-data" class="space-y-4 px-6 pb-6">
                @csrf
                <div>
                    <label class="label">Gambar</label>
                    <input class="input" type="file" name="images[]" accept="image/*" multiple required>
                    @error('images') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                    @foreach ($errors->get('images.*') as $messages)
                        @foreach ($messages as $message)
                            <p class="mt-1 text-xs text-brand">{{ $message }}</p>
                        @endforeach
                    @endforeach
                </div>
                <button type="submit" class="btn-primary">Simpan gambar</button>
            </form>
        </div>
    </div>
@endsection
