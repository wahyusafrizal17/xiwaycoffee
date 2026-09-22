@extends('layouts.site')

@section('title', 'XIWAY COFFEE | Specialty Coffee & Cafe di Cimahi')
@section('canonical', url('/'))

@php
    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($site['maps_query']);
@endphp

@push('head')
@php
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'CafeOrCoffeeShop',
        'name' => 'XIWAY COFFEE',
        'description' => 'Coffee shop di Cimahi Utara yang menyajikan Specialty Arabika Gayo, coffee, food, dan ruang nyaman untuk WFH, meeting, dan hangout.',
        'url' => url('/'),
        'image' => url($site['og_image']),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Jl. Nusa Sari Raya No.6A',
            'addressLocality' => 'Cimahi',
            'addressRegion' => 'Jawa Barat',
            'postalCode' => '40525',
            'addressCountry' => 'ID',
        ],
        'sameAs' => array_values(array_filter([$site['instagram'], $site['tiktok'] ?? null])),
        'servesCuisine' => 'Coffee',
        'priceRange' => '$$',
    ];
    if (! empty($site['phone'])) {
        $jsonLd['telephone'] = $site['phone'];
    }
    if (! empty($site['hours'])) {
        $jsonLd['openingHours'] = $site['hours'];
    }
@endphp
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) !!}</script>
@endpush

@section('content')
<header class="lp-nav" :class="scrolled && 'is-on'">
    <div class="lp-shell lp-nav-row">
        <a href="{{ route('home') }}" class="lp-wordmark" aria-label="XIWAY COFFEE">XIWAY</a>
        <nav class="lp-links" aria-label="Primary">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('site.menu') }}">Menu</a>
            <a href="#about">About</a>
            <a href="#visit">Visit</a>
        </nav>
        <a href="{{ route('site.menu') }}" class="lp-chip lp-nav-cta">Menu</a>
        <button type="button" class="lp-burger" @click="navOpen = !navOpen" :aria-expanded="navOpen.toString()" aria-label="Open menu">
            <span></span><span></span>
        </button>
    </div>
    <div class="lp-drawer" x-show="navOpen" x-cloak @click.outside="navOpen = false">
        <a href="{{ route('home') }}" @click="navOpen = false">Home</a>
        <a href="{{ route('site.menu') }}" @click="navOpen = false">Menu</a>
        <a href="#about" @click="navOpen = false">About</a>
        <a href="#visit" @click="navOpen = false">Visit</a>
    </div>
</header>

<main class="lp-home">
    <section class="lp-hero">
        <div class="lp-hero-glow" aria-hidden="true"></div>
        <div class="lp-shell lp-hero-grid">
            <div class="lp-hero-copy">
                <p class="lp-eyebrow">Specialty Arabika Gayo · Cimahi Utara</p>
                <h1>
                    <span class="lp-hero-brand">XIWAY</span>
                    <span class="lp-hero-brand">COFFEE</span>
                </h1>
                <p class="lp-quote">Good Coffee. Better People.</p>
                <p class="lp-intro">Coffee shop modern di Citeureup untuk ngopi, WFH, meeting, study, dan hangout — dengan racikan specialty yang hangat dan ruang yang nyaman.</p>
                <div class="lp-cta-row">
                    <a href="{{ route('site.menu') }}" class="lp-btn lp-btn-primary">Explore Menu</a>
                    <a href="{{ $mapsUrl }}" class="lp-btn lp-btn-ghost" target="_blank" rel="noopener noreferrer">Get Directions</a>
                </div>
            </div>
            <aside class="lp-hero-card" aria-label="Quick facts">
                <p class="lp-eyebrow">At a glance</p>
                <ul>
                    <li><span>Origin</span><strong>Arabika Gayo</strong></li>
                    <li><span>Place</span><strong>Citeureup, Cimahi</strong></li>
                    <li><span>Vibe</span><strong>WFH · Meet · Hangout</strong></li>
                    <li><span>Social</span><strong><a href="{{ $site['instagram'] }}" target="_blank" rel="noopener noreferrer">@xiwaycoffee</a></strong></li>
                </ul>
            </aside>
        </div>
        <div class="lp-marquee" aria-hidden="true">
            <div class="lp-marquee-track">
                @foreach (range(1, 2) as $loop)
                    <span>Coffee</span><span>·</span>
                    <span>WFH</span><span>·</span>
                    <span>Meeting</span><span>·</span>
                    <span>Study</span><span>·</span>
                    <span>Hangout</span><span>·</span>
                    <span>Food</span><span>·</span>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lp-block" id="about">
        <div class="lp-shell lp-about-grid">
            <div class="lp-about-left">
                <p class="lp-eyebrow lp-eyebrow-dark">The brand</p>
                <h2>Lebih dari<br>sekadar kopi.</h2>
            </div>
            <div class="lp-about-right">
                <p>XIWAY COFFEE adalah coffee shop di Cimahi Utara yang menyajikan Specialty Arabika Gayo, espresso drinks, non-coffee, food, dan snack.</p>
                <p>Kami membangun tempat yang nyaman untuk produktivitas dan percakapan — dari WFH pagi sampai hangout malam.</p>
                <p class="lp-about-tag">Good Coffee. Better People.</p>
            </div>
        </div>

        <div class="lp-shell lp-features">
            @foreach ([
                ['01', 'Specialty Coffee', 'Arabika Gayo & signature brew: Butterscotch Noir, Golden Cream Latte, Xiway Sea Salt.'],
                ['02', 'Work Ready', 'Wi‑Fi, stop kontak, dan suasana tenang untuk fokus kerja atau belajar.'],
                ['03', 'Meet Softly', 'Ruang santai untuk meeting kasual, diskusi ide, atau catch-up.'],
                ['04', 'Stay Fed', 'Makanan, mie, dan snack agar kamu tidak perlu pindah tempat.'],
            ] as [$n, $t, $c])
                <article class="lp-feature">
                    <span class="lp-feature-n">{{ $n }}</span>
                    <h3>{{ $t }}</h3>
                    <p>{{ $c }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="lp-panel">
        <div class="lp-shell lp-panel-inner">
            <div>
                <p class="lp-eyebrow">Menu</p>
                <h2>Lihat apa yang sedang diseduh.</h2>
                <p>Coffee, non-coffee, makanan, mie, dan snack — daftar lengkap siap dibuka.</p>
            </div>
            <a href="{{ route('site.menu') }}" class="lp-btn lp-btn-primary">Open Full Menu</a>
        </div>
    </section>

    <section class="lp-block" id="visit">
        <div class="lp-shell lp-visit-grid">
            <div class="lp-visit-copy">
                <p class="lp-eyebrow lp-eyebrow-dark">Visit</p>
                <h2>Temukan XIWAY<br>di Cimahi.</h2>
                <p class="lp-visit-lead">Jl. Nusa Sari Raya No.6A, Citeureup, Cimahi Utara — coffee shop yang mudah dijangkau untuk kerja maupun hangout.</p>
                <address>
                    @foreach ($site['address_lines'] as $line)
                        <span>{{ $line }}</span>
                    @endforeach
                </address>
                @if (! empty($site['hours']))
                    <p class="lp-meta"><span>Hours</span> {{ $site['hours'] }}</p>
                @endif
                @if (! empty($site['phone']))
                    <p class="lp-meta"><span>Phone</span> <a href="tel:{{ $site['phone'] }}">{{ $site['phone'] }}</a></p>
                @endif
                <div class="lp-cta-row">
                    <a href="{{ $mapsUrl }}" class="lp-btn lp-btn-dark" target="_blank" rel="noopener noreferrer">Open in Maps</a>
                    <a href="{{ $site['instagram'] }}" class="lp-btn lp-btn-soft" target="_blank" rel="noopener noreferrer">Instagram</a>
                </div>
            </div>
            <div class="lp-map-frame">
                <iframe
                    title="Peta XIWAY COFFEE Cimahi"
                    src="https://maps.google.com/maps?q={{ urlencode($site['maps_query']) }}&z=17&output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </div>
        </div>
    </section>

    <section class="lp-close">
        <div class="lp-shell lp-close-inner">
            <h2>See you at XIWAY.</h2>
            <p>Citeureup, Cimahi Utara</p>
            <div class="lp-cta-row lp-cta-center">
                <a href="{{ route('site.menu') }}" class="lp-btn lp-btn-primary">View Menu</a>
                <a href="{{ $mapsUrl }}" class="lp-btn lp-btn-ghost" target="_blank" rel="noopener noreferrer">Get Directions</a>
            </div>
        </div>
    </section>
</main>

<footer class="lp-foot">
    <div class="lp-shell lp-foot-row">
        <p>© {{ date('Y') }} XIWAY COFFEE</p>
        <nav>
            <a href="{{ route('site.menu') }}">Menu</a>
            <a href="#visit">Visit</a>
            <a href="{{ $site['instagram'] }}" target="_blank" rel="noopener noreferrer">Instagram</a>
        </nav>
    </div>
</footer>
@endsection
