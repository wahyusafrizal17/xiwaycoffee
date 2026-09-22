@extends('layouts.site')

@section('title', 'Menu | XIWAY COFFEE — Cafe di Cimahi')
@section('meta_description', 'Lihat menu coffee, non-coffee, dan food di XIWAY COFFEE, coffee shop Specialty Arabika Gayo di Citeureup, Cimahi Utara.')
@section('canonical', route('site.menu'))

@section('content')
@php
    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($site['maps_query']);
@endphp

<header class="lp-nav is-on lp-nav-solid">
    <div class="lp-shell lp-nav-row">
        <a href="{{ route('home') }}" class="lp-wordmark" aria-label="XIWAY COFFEE">XIWAY</a>
        <nav class="lp-links" aria-label="Primary">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('site.menu') }}">Menu</a>
            <a href="{{ route('home') }}#visit">Visit</a>
        </nav>
        <a href="{{ $mapsUrl }}" class="lp-chip" target="_blank" rel="noopener noreferrer">Maps</a>
        <button type="button" class="lp-burger" @click="navOpen = !navOpen" :aria-expanded="navOpen.toString()" aria-label="Open menu">
            <span></span><span></span>
        </button>
    </div>
    <div class="lp-drawer" x-show="navOpen" x-cloak @click.outside="navOpen = false">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('site.menu') }}">Menu</a>
        <a href="{{ route('home') }}#visit">Visit</a>
        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer">Maps</a>
    </div>
</header>

<main class="lp-menu">
    <div class="lp-shell">
        <p class="lp-eyebrow">Menu</p>
        <h1>What’s brewing</h1>
        <p class="lp-menu-lead">Daftar coffee, non-coffee, food, dan snack di XIWAY COFFEE Cimahi.</p>

        @forelse ($groups as $category => $items)
            <section class="lp-menu-group">
                <h2>{{ $category }}</h2>
                <ul class="lp-menu-list">
                    @foreach ($items as $item)
                        <li>
                            <div class="lp-menu-item">
                                <div>
                                    <h3>
                                        {{ $item->name }}
                                        @if ($item->star)
                                            <span class="lp-star" aria-label="Recommended">★</span>
                                        @endif
                                    </h3>
                                    @if ($item->description)
                                        <p>{{ $item->description }}</p>
                                    @endif
                                </div>
                                <p class="lp-price">{{ money($item->price) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="lp-menu-lead">Menu segera tersedia. Kunjungi kami di Citeureup, Cimahi Utara.</p>
        @endforelse

        <div class="lp-cta-row">
            <a href="{{ route('home') }}" class="lp-btn lp-btn-soft">Back to Home</a>
            <a href="{{ $mapsUrl }}" class="lp-btn lp-btn-dark" target="_blank" rel="noopener noreferrer">Get Directions</a>
        </div>
    </div>
</main>

<footer class="lp-foot" style="background:#0c0b0a">
    <div class="lp-shell lp-foot-row">
        <p>© {{ date('Y') }} XIWAY COFFEE</p>
        <p>Specialty Arabika Gayo · Citeureup, Cimahi Utara</p>
    </div>
</footer>
@endsection
