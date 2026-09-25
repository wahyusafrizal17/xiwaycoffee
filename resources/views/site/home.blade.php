@extends('layouts.site')

@section('title', 'XIWAY COFFEE | Specialty Coffee & Cafe di Cimahi')
@section('canonical', url('/'))

@php
    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($site['maps_query']);
    $heroImage = $site['hero_image'] ?? '/images/menu/xiway-menu-board.jpg';
    $categories = $groups->keys()->values()->all();
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
<div
    x-data="xiwayLanding({
        wa: @js($whatsapp),
        categories: @js($categories),
    })"
>
<header class="lp-nav is-on">
    <div class="lp-shell lp-nav-row">
        <a href="#top" class="lp-wordmark" aria-label="XIWAY COFFEE">XIWAY</a>
        <nav class="lp-links" aria-label="Primary">
            <a href="#menu">Menu</a>
            <a href="#lokasi">Lokasi</a>
            <a href="#vip">VIP</a>
            <a href="#kontak">Kontak</a>
        </nav>
        <a href="#vip" class="lp-chip lp-nav-cta">Reservasi VIP</a>
        <button type="button" class="lp-burger" @click="navOpen = !navOpen" :aria-expanded="navOpen.toString()" aria-label="Open menu">
            <span></span><span></span>
        </button>
    </div>
    <div class="lp-drawer" x-show="navOpen" x-cloak @click.outside="navOpen = false">
        <a href="#menu" @click="navOpen = false">Menu</a>
        <a href="#lokasi" @click="navOpen = false">Lokasi</a>
        <a href="#vip" @click="navOpen = false">VIP</a>
        <a href="#kontak" @click="navOpen = false">Kontak</a>
    </div>
</header>

<main class="lp-home" id="top">
    <section class="lp-hero lp-hero-banner">
        <div class="lp-hero-media">
            <img
                src="{{ asset($heroImage) }}"
                alt="XIWAY COFFEE — Good Coffee. Better People. Specialty Arabika Gayo"
                loading="eager"
            >
        </div>
        <h1 class="sr-only">XIWAY COFFEE</h1>
        <div class="lp-hero-actions">
            <div class="lp-shell lp-cta-row">
                <a href="#menu" class="lp-btn lp-btn-primary">Lihat Menu</a>
                <a href="#vip" class="lp-btn lp-btn-ghost">Reservasi VIP</a>
            </div>
        </div>
    </section>

    <section class="lp-block lp-menu-section" id="menu">
        <div class="lp-shell">
            <p class="lp-eyebrow lp-eyebrow-dark">Menu</p>
            <h2>Apa yang sedang diseduh</h2>
            <p class="lp-section-lead">Coffee, non-coffee, makanan, dan snack dari dapur XIWAY.</p>

            @if ($groups->isNotEmpty())
                <div class="lp-menu-tabs" role="tablist" aria-label="Kategori menu">
                    <button type="button" class="lp-menu-tab" :class="menuTab === 'all' && 'is-on'" @click="menuTab = 'all'">Semua</button>
                    @foreach ($categories as $category)
                        <button type="button" class="lp-menu-tab" :class="menuTab === @js($category) && 'is-on'" @click="menuTab = @js($category)">{{ $category }}</button>
                    @endforeach
                </div>

                @foreach ($groups as $category => $items)
                    <section class="lp-menu-group" x-show="menuTab === 'all' || menuTab === @js($category)" x-cloak>
                        <h3>{{ $category }}</h3>
                        <ul class="lp-menu-list">
                            @foreach ($items as $item)
                                <li>
                                    <div class="lp-menu-item">
                                        <div>
                                            <h4>
                                                {{ $item->name }}
                                                @if ($item->star)
                                                    <span class="lp-star" aria-label="Recommended">★</span>
                                                @endif
                                            </h4>
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
                @endforeach
            @else
                <p class="lp-section-lead">Menu segera tersedia. Singgah ke Citeureup, Cimahi Utara.</p>
            @endif
        </div>
    </section>

    <section class="lp-block lp-visit-section" id="lokasi">
        <div class="lp-shell lp-visit-grid">
            <div class="lp-visit-copy">
                <p class="lp-eyebrow lp-eyebrow-dark">Lokasi</p>
                <h2>Temukan XIWAY<br>di Cimahi</h2>
                <p class="lp-visit-lead">Jl. Nusa Sari Raya No.6A, Citeureup — mudah dijangkau untuk kerja maupun hangout.</p>
                <address>
                    @foreach ($site['address_lines'] as $line)
                        <span>{{ $line }}</span>
                    @endforeach
                </address>
                @if (! empty($site['hours']))
                    <p class="lp-meta"><span>Jam</span> {{ $site['hours'] }}</p>
                @endif
                @if (! empty($site['phone']))
                    <p class="lp-meta"><span>Telepon</span> <a href="tel:{{ $site['phone'] }}">{{ $site['phone'] }}</a></p>
                @endif
                <div class="lp-cta-row">
                    <a href="{{ $mapsUrl }}" class="lp-btn lp-btn-dark" target="_blank" rel="noopener noreferrer">Buka Maps</a>
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

    <section class="lp-panel lp-form-section" id="vip">
        <div class="lp-shell lp-form-grid">
            <div>
                <p class="lp-eyebrow">Ruang VIP</p>
                <h2>Reservasi ruang privat</h2>
                <p>Untuk meeting, gathering, atau hangout lebih tenang. Isi form — kami lanjutkan lewat WhatsApp.</p>
            </div>
            <form class="lp-form" @submit.prevent="sendVip()">
                <div class="lp-form-row">
                    <label>
                        <span>Nama</span>
                        <input type="text" x-model="vip.name" required maxlength="80" autocomplete="name" placeholder="Nama lengkap">
                    </label>
                    <label>
                        <span>WhatsApp</span>
                        <input type="tel" x-model="vip.phone" required maxlength="20" autocomplete="tel" placeholder="08xxxxxxxxxx">
                    </label>
                </div>
                <div class="lp-form-row">
                    <label>
                        <span>Tanggal</span>
                        <input type="date" x-model="vip.date" required>
                    </label>
                    <label>
                        <span>Jam</span>
                        <input type="time" x-model="vip.time" required>
                    </label>
                </div>
                <label>
                    <span>Catatan</span>
                    <textarea x-model="vip.notes" rows="3" maxlength="400" placeholder="Jumlah orang, keperluan, permintaan khusus…"></textarea>
                </label>
                <button type="submit" class="lp-btn lp-btn-primary" :disabled="!wa">Kirim via WhatsApp</button>
                <p class="lp-form-note" x-show="!wa" x-cloak>Nomor WhatsApp admin belum diatur. Hubungi kami lewat Instagram sementara.</p>
                <p class="lp-form-note" x-show="notice" x-text="notice" x-cloak></p>
            </form>
        </div>
    </section>

    <section class="lp-block lp-contact-section" id="kontak">
        <div class="lp-shell lp-form-grid">
            <div>
                <p class="lp-eyebrow lp-eyebrow-dark">Kontak</p>
                <h2>Chat admin XIWAY</h2>
                <p class="lp-section-lead">Tanya menu, jam buka, atau kerja sama. Pesan langsung masuk ke WhatsApp kami.</p>
                <div class="lp-contact-links">
                    @if ($whatsapp)
                        <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">WhatsApp langsung</a>
                    @endif
                    <a href="{{ $site['instagram'] }}" target="_blank" rel="noopener noreferrer">{{ $site['instagram_handle'] }}</a>
                    @if (! empty($site['phone']))
                        <a href="tel:{{ $site['phone'] }}">{{ $site['phone'] }}</a>
                    @endif
                </div>
            </div>
            <form class="lp-form lp-form-light" @submit.prevent="sendContact()">
                <label>
                    <span>Nama</span>
                    <input type="text" x-model="contact.name" required maxlength="80" autocomplete="name" placeholder="Nama kamu">
                </label>
                <label>
                    <span>Pesan</span>
                    <textarea x-model="contact.message" rows="4" required maxlength="500" placeholder="Tulis pertanyaan singkat…"></textarea>
                </label>
                <button type="submit" class="lp-btn lp-btn-dark" :disabled="!wa">Kirim via WhatsApp</button>
            </form>
        </div>
    </section>
</main>

<footer class="lp-foot">
    <div class="lp-shell lp-foot-row">
        <p>© {{ date('Y') }} XIWAY COFFEE</p>
        <nav>
            <a href="#menu">Menu</a>
            <a href="#lokasi">Lokasi</a>
            <a href="#vip">VIP</a>
            <a href="{{ $site['instagram'] }}" target="_blank" rel="noopener noreferrer">Instagram</a>
        </nav>
    </div>
</footer>
</div>

@push('scripts')
<script>
function xiwayLanding({ wa, categories }) {
    return {
        navOpen: false,
        menuTab: 'all',
        notice: '',
        wa: wa || '',
        categories: categories || [],
        vip: { name: '', phone: '', date: '', time: '', notes: '' },
        contact: { name: '', message: '' },
        openWa(text) {
            if (!this.wa) {
                this.notice = 'WhatsApp admin belum tersedia.';
                return;
            }
            window.open('https://wa.me/' + this.wa + '?text=' + encodeURIComponent(text), '_blank', 'noopener');
        },
        sendVip() {
            const v = this.vip;
            const lines = [
                'Halo XIWAY Coffee, saya ingin reservasi ruang VIP.',
                '',
                'Nama: ' + v.name.trim(),
                'WhatsApp: ' + v.phone.trim(),
                'Tanggal: ' + v.date,
                'Jam: ' + v.time,
            ];
            if (v.notes.trim()) lines.push('Catatan: ' + v.notes.trim());
            this.openWa(lines.join('\n'));
        },
        sendContact() {
            const c = this.contact;
            this.openWa([
                'Halo admin XIWAY Coffee,',
                '',
                'Nama: ' + c.name.trim(),
                'Pesan: ' + c.message.trim(),
            ].join('\n'));
        },
    };
}
</script>
@endpush
@endsection
